<?php
/**
 * Plugin Name: Auto post name
 * Description: Automatically renames post name by title
 * Version: 0.1
 * Author: Luuptek
 * Author URI: https://www.luuptek.fi
 * License: GPLv2
 */

/**
 * Security Note:
 * Consider blocking direct access to your plugin PHP files by adding the following line at the top of each of them,
 * or be sure to refrain from executing sensitive standalone PHP code before calling any WordPress functions.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


class Auto_post_name {

	protected static $instance = null;
	private $plugin_text_domain = 'auto-post-name';

	function __construct() {
		add_action( 'init', [ $this, 'initializeHooks' ] );
	}

	public static function getInstance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}
	}

	/**
	 * Init hooks here
	 */
	public function initializeHooks() {
		add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		add_action( 'admin_init', [ $this, 'auto_post_name_settings_init' ] );
		add_action( 'save_post', [ $this, 'update_post_name' ] );
		add_action( 'wp_ajax_rebuild_post_names', [ $this, 'rebuild_post_names' ] );
		add_action( 'wp_ajax_nopriv_rebuild_post_names', [ $this, 'rebuild_post_names' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );
	}

	/**
	 * Add options page
	 */
	public function add_options_page() {
		add_options_page(
			__( 'Auto post name', $this->plugin_text_domain ),
			__( 'Auto post name', $this->plugin_text_domain ),
			'manage_options',
			'auto-post-name',
			[ $this, 'create_settings_page' ]
		);
	}

	/**
	 * Options page callback
	 */
	public function create_settings_page() {
		?>
        <div class="wrap">
            <form action='options.php' method='post'>
				<?php
				settings_fields( 'autoPostNamePluginPage' );
				do_settings_sections( 'autoPostNamePluginPage' );
				submit_button( __( 'Save post types', $this->plugin_text_domain ) );
				?>

            </form>
            <h2><?php _e( 'Modify post names automatically', $this->plugin_text_domain ) ?></h2>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label
                                for="auto_post_name_post_type"><?php _e( 'Post type', $this->plugin_text_domain ) ?></label>
                    </th>
                    <td>
                        <select name="auto_post_name_post_type" id="auto_post_name_post_type">
                            <optgroup label="<?php _e( 'Post types', $this->plugin_text_domain ) ?>">
                                <option value="any"><?php _e( 'All', $this->plugin_text_domain ) ?></option>
								<?php
								$post_types = get_post_types( [ 'public' => true ], 'objects' );

								foreach ( $post_types as $post_type ) {
									?>
                                    <option value="<?php echo $post_type->name ?>"><?php echo $post_type->labels->singular_name ?></option>
									<?php
								}
								?>
                            </optgroup>
                        </select>

                        <p class="description">
							<?php _e( 'Choose post type', $this->plugin_text_domain ) ?>
                        </p>
                    </td>

                </tr>
                </tbody>
            </table>

            <p class="submit">
                <button class="button button-primary" id="modify_post_names_btn">
					<?php _e( 'Modify post names', $this->plugin_text_domain ) ?>
                </button>
            </p>
        </div>
		<?php
	}

	/**
	 * Updates the modified post
	 * post name = sanitized post title
	 *
	 * @param $post_id
	 */
	public function update_post_name( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) ) {

			/**
			 * Only do magic if this is selected post type
			 */
			if ( $this->to_modify_post_name( $post_id ) ) {
				// unhook this function so it doesn't loop infinitely
				remove_action( 'save_post', [ $this, 'update_post_name' ] );

				// update the post, which calls save_post again
				wp_update_post(
					[
						'ID'        => $post_id,
						'post_name' => sanitize_title( get_the_title( $post_id ) ),
					]
				);

				// re-hook this function
				add_action( 'save_post', [ $this, 'update_post_name' ] );
			}
		}
	}

	/**
	 * Register auto post name settings
	 */
	public function auto_post_name_settings_init() {

		register_setting( 'autoPostNamePluginPage', 'auto_post_name_settings' );

		add_settings_section(
			'auto_post_name_section',
			__( 'Auto post name settings', '$this->plugin_text_domain' ),
			[ $this, 'auto_post_name_settings_section_callback' ],
			'autoPostNamePluginPage'
		);

		add_settings_field(
			'auto_post_name_selected_post_types_field',
			__( 'Affected post types', $this->plugin_text_domain ),
			[ $this, 'auto_post_name_selected_post_types_render' ],
			'autoPostNamePluginPage',
			'auto_post_name_section'
		);


	}

	/**
	 * Selected post types rendering
	 */
	public function auto_post_name_selected_post_types_render() {
		?>
        <select name="auto_post_name_settings[post_types][]" multiple>
			<?php
			$post_types = get_post_types( [ 'public' => true ], 'objects' );

			foreach ( $post_types as $post_type ) {
				?>
                <option value="<?php echo $post_type->name ?>"<?php echo $this->is_post_type_selected( $post_type->name ) ?>><?php echo $post_type->labels->singular_name ?></option>
				<?php
			}
			?>
        </select>

		<?php

	}

	/**
	 * Settings section callback
	 */
	public function auto_post_name_settings_section_callback() {
		_e( 'Select post types you wanna have effect by this plugin.', $this->plugin_text_domain );
	}

	/**
	 * Used in select options
	 *
	 * returns 'SELECTED' if current select item is found from options
	 *
	 * @param $post_type_name
	 *
	 * @return string
	 */
	private function is_post_type_selected( $post_type_name ) {
		$selected_post_types = $this->get_affected_post_types();
		$found               = false;

		if ( in_array( $post_type_name, $selected_post_types ) ) {
			$found = true;
		}

		if ( $found === true ) {
			return ' SELECTED';
		} else {
			return '';
		}
	}

	/**
	 * Get affected post types from options
	 *
	 * @return bool / post types array
	 */
	private function get_affected_post_types() {
		$options = get_option( 'auto_post_name_settings' ) ? get_option( 'auto_post_name_settings' ) : null;

		if ( $options ) {
			$selected_post_types = $options['post_types'];

			return $selected_post_types;
		} else {
			return false;
		}
	}

	/**
	 * Checks post type of the modified post and returns true, if ok to modify
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	private function to_modify_post_name( $post_id ) {
		$selected_post_types = $this->get_affected_post_types();

		$post_type = get_post_type_object( get_post_type( $post_id ) );

		if ( in_array( $post_type->name, $selected_post_types ) || ! is_array( $selected_post_types ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Function to call via ajax
	 */
	public function rebuild_post_names() {
		$nonce     = $_POST['nonce'];
		$post_type = $_POST['post_type'];

		if ( ! wp_verify_nonce( $nonce, 'auto_post_name_nonce' ) ) {
			die( 'Try harder ;)' );
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => - 1
			)
		);

		foreach ( $posts as $post ) {
			wp_update_post(
				[
					'ID'        => $post->ID,
					'post_name' => sanitize_title( get_the_title( $post->ID ) ),
				]
			);
		}

		die();
	}

	/**
	 * Register styles/scripts
	 */
	public function admin_styles() {
		wp_enqueue_script( 'admin-scripts', plugins_url( '/js/admin.js', __FILE__ ), [ 'jquery' ] );
		wp_localize_script( 'admin-scripts', 'auto_post_name',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'apn_nonce'   => wp_create_nonce( 'auto_post_name_nonce' ),
				'string_done' => __( 'DONE', $this->plugin_text_domain ),
			)
		);
	}


}

Auto_post_name::getInstance();
