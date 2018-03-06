(function ($) {

    $(document).ready(function () {
        $('#modify_post_names_btn').on('click', function () {
            var post_type = $('#auto_post_name_post_type').val();

            $.ajax({
                url: auto_post_name.ajaxurl,
                type: 'POST',
                data: ( {
                        action: 'rebuild_post_names',
                        post_type: post_type,
                        nonce: auto_post_name.apn_nonce
                    }
                ),
                success: function () {
                    $('#modify_post_names_btn').text(auto_post_name.string_done);
                }
            });


        });
    });

})(jQuery);