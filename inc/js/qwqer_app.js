jQuery(document).on( 'change', 'select#terminal_name', function(){
    var value = jQuery(this).val();
    var refresh = 'yes';
    var data = {
        action: 'terminal_request',
        RequestType: 'terminal',
        terminal:  value,
        refresh_shipping: refresh,
    };

    jQuery('.wc-block-components-checkout-place-order-button').attr('disabled', true);

    jQuery.post(
        qwqer_app_script.ajaxurl,
        data,
        function(terminal){
            jQuery(document.body).trigger('update_checkout');
            var billing_terminal = terminal.split('+')[0];
            var billing_terminal_id = terminal.split('+')[1];
            jQuery('#billing_terminal').val(billing_terminal);
            jQuery('#billing_terminal_id').val(billing_terminal_id);
        }
    );
    setTimeout(function () {
        location.reload();
    }, 2000);
});