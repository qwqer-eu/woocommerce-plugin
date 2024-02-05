<?php
/*
 * QWQER: OmnivaParcelTerminal
 */
defined('ABSPATH') || exit;

require_once 'settings.php';

function terminal_settings(): array
{
    $zone_ids = array_keys(array('') + WC_Shipping_Zones::get_zones());
    foreach ($zone_ids as $zone_id) {
        $shipping_zone = new WC_Shipping_Zone($zone_id);

        $shipping_methods = $shipping_zone->get_shipping_methods(true, 'values');
        foreach ($shipping_methods as $instance_id => $shipping_method) {
            if ($shipping_method->id == 'qwqer_shipping_method') {
                $token = $shipping_method->qwqer_key;
            }
        }
    }

    $response = new WC_Qwqer_Shipping_Method();

    $get_terminals = $response->getTerminals($token);

    $select_terminals = array(
        '0' => __('Please select a terminal', 'qwqer'),
    );

    foreach ($get_terminals as $terminals) {
        foreach ($terminals as $terminal) {
            foreach ($terminal as $key => $value) {
                $select_terminals[$value['id']] = $value['name'];
            }
        }
    }

    return array(
        'targeted_methods' => array('qwqer_shipping_method_omnivaParcelTerminal'),
        'field_id' => 'terminal_name',
        'field_type' => 'select',
        'field_label' => '',
        'label_name' => __('Terminal name', 'qwqer'),
        'field_options' => $select_terminals,
    );
}


function unparse_url($parsed_url)
{

    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

    return $path;
}

/*
 * Display a terminal field
 */
add_action( 'wp_footer', 'terminal_select_field', 10, 1 );
function terminal_select_field(): void
{
    global $woocommerce;
    $return_url = wc_get_checkout_url();
    $actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $checkout_page = '';
    if( strpos( $actual_link, unparse_url(parse_url($return_url)) ) )
    {
        $checkout_page = 'yes';
    }

    extract( terminal_settings() );

    $chosen  = WC()->session->get('chosen_shipping_methods');
    $value   = WC()->session->get($field_id);
    $value   = WC()->session->__isset($field_id) ? $value : WC()->checkout->get_value('_'.$field_id);
    $options = array(); // Initializing

    $zone_ids = array_keys( array('') + WC_Shipping_Zones::get_zones() );

    $select_terminal = array();
    foreach ( $zone_ids as $zone_id )
    {
        $shipping_zone = new WC_Shipping_Zone($zone_id);

        $shipping_methods = $shipping_zone->get_shipping_methods( true, 'values' );
        foreach ( $shipping_methods as $instance_id => $shipping_method )
        {
            if($shipping_method->id == 'qwqer_shipping_method') {
                $select_terminal[] = $shipping_method->qwqer_omnivaParcelTerminal;
            }
        }
    }

    if( $checkout_page == 'yes' && $select_terminal[0] == 'yes' ) {
        echo '<div class="qwqer_terminals" style="display: none">';

        foreach( $field_options as $key => $option_value ) {
            $option_key = $key == 0 ? '' : $key;
            $options[$option_key] = $option_value;
        }

        woocommerce_form_field( $field_id, array(
            'type'     => $field_type,
            'label'    => '',
            'class'    => array( 'form-row-wide ' . $field_id . '-' . $field_type ),
            'required' => true,
            'options'  => $options,
        ), $value );

        echo '<div id="terminal_hidden_checkout_field">
                <input type="hidden" class="" name="billing_terminal" id="billing_terminal" value="">
        </div>';
        echo '<div id="terminal_id_hidden_checkout_field">
                <input type="hidden" class="" name="billing_terminal_id" id="billing_terminal_id" value="">
        </div>';
        echo '</div>';
        echo "
        <script>
            jQuery(document).ready(function () {
                setTimeout(function () {
                   jQuery('#shipping-option label, #shipping_method label').each(function () {
                        var terminal = jQuery(this).attr('for');
                        if( terminal == 'radio-control-0-qwqer_shipping_method_omnivaParcelTerminal' || terminal == 'shipping_method_0_qwqer_shipping_method_omnivaparcelterminal') {
                            jQuery('.qwqer_terminals').appendTo(jQuery(this));
                            jQuery('.qwqer_terminals').show();
                        }
                    }); 
                }, 2500);
            });
        </script>
        <style>
            #terminal_name {
                background-color: #fff;
                border: 1px solid hsla(0,0%,7%,.8);
                border-radius: 4px;
                box-sizing: border-box;
                color: #2b2d2f;
                font-family: inherit;
                font-size: 16px;
                line-height: 0;
                margin: 0;
                min-height: 0;
                padding: 10px;
                width: 100%;
            }
        </style>
        ";
    }

}

