<?php
/*
 * QWQER: Create order after payment
 */
defined('ABSPATH') || exit;

require_once 'settings.php';

add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
function createOrder($order_id)
{
    $response = new WC_Qwqer_Shipping_Method();
    /*
     * static data
     */
    $api_url = 'https://qwqer.lv/api/';
    $api_coordinates_url = 'v1/places/geocode';
    $api_order_url = "v1/clients/auth/trading-points/{trading-point-id}/delivery-orders";

    $order = new WC_Order($order_id);

    $phone = $order->get_billing_phone();
    $name = $order->get_billing_first_name();
    $billing_address_1 = $order->get_shipping_address_1();
    $billing_city = $order->get_shipping_city();

    $params = [];

    foreach ($order->get_items('shipping') as $item_id => $item) {
        $shipping_method_id = $item->get_method_id();
        $shipping_method_instance_id = $item->get_instance_id();
        $shipping_method_title = $item->get_method_title();
    }

    $shipping_methods = WC()->shipping->get_shipping_method_class_names();
    $method_instance = new $shipping_methods['qwqer_shipping_method']( $shipping_method_instance_id );

    $token = $method_instance->get_option( 'qwqer_key' );
    $shipping_title_scheduledDelivery = $method_instance->get_option( 'qwqer_title' );
    $shipping_title_expressDelivery = $method_instance->get_option( 'qwqer_title_expressDelivery' );
    $shipping_title_omnivaParcelTerminal = $method_instance->get_option( 'qwqer_title_omnivaParcelTerminal' );
    $trading_point_id = $method_instance->get_option( 'qwqer_id' );
    $shop_phone = $method_instance->get_option( 'qwqer_phone' );
    $shop_phone = str_replace('+', '', $shop_phone);
    $category = $method_instance->get_option( 'qwqer_category' );

    $billing_terminal = WC()->session->get('billing_terminal');
    $billing_terminal_id = WC()->session->get('billing_terminal_id');

    /*
     * Get terminal info
     */
    $get_terminals = $response->getTerminals($token);
    foreach ($get_terminals['data']['omniva'] as $terminal) {
        if ($billing_terminal_id == $terminal['id']) {
            $name_terminal = $terminal['name'];
            $coordinates_terminal = $terminal['coordinates'];
        }
    }
    $name_terminal = isset($name_terminal) ? $name_terminal : null;
    $coordinates_terminal = isset($coordinates_terminal) ? $coordinates_terminal : null;

    if($shipping_method_title == $shipping_title_scheduledDelivery) {
        $real_type = 'ScheduledDelivery';
    }

    if($shipping_method_title == $shipping_title_expressDelivery) {
        $real_type = 'ExpressDelivery';
    }

    if($shipping_method_id == 'qwqer_shipping_method') {
        /*
         * Get store coordinates and address
         */
        $data_info_strore = [
            "address" => get_option('woocommerce_store_address') . ' ' . get_option('woocommerce_store_city'),
        ];
        $info_store = $response->getResponse($data_info_strore, $api_url.$api_coordinates_url, $token);

        $storeOwnerAddress = $params;
        $storeOwnerAddress["address"] = $info_store['data']['address'];
        $storeOwnerAddress["coordinates"] = $info_store['data']['coordinates'];

        /*
         * Get client coordinates and address
         */
        $data_info_client = [
            "address" => $billing_address_1 . ' ' . $billing_city,
        ];
        $info_client = $response->getResponse($data_info_client, $api_url.$api_coordinates_url, $token);

        $clientOwnerAddress = $params;
        $clientOwnerAddress["address"] = $info_client['data']['address'];
        $clientOwnerAddress["coordinates"] = $info_client['data']['coordinates'];

        /*
         * Create order
         */
        $storeOwnerAddress["name"] = get_bloginfo('name');
        $storeOwnerAddress["phone"] = '+'.$shop_phone;

        $clientOwnerAddress["name"] = $name;
        $clientOwnerAddress["phone"] = $phone;
        if($shipping_method_title == $shipping_title_scheduledDelivery) {
            $data_order = array(
                'type' => 'Regular',
                'category' => $category,
                'real_type' => $real_type,
                'origin' => $storeOwnerAddress,
                'destinations' => [$clientOwnerAddress],
            );
        }
        if($shipping_method_title == $shipping_title_expressDelivery) {
            $data_order = array(
                'type' => 'Regular',
                'category' => $category,
                'real_type' => $real_type,
                'origin' => $storeOwnerAddress,
                'destinations' => [$clientOwnerAddress],
            );
        }
        $terminal_name = $shipping_title_omnivaParcelTerminal . ' : ' . $name_terminal;
        if($shipping_method_title == $terminal_name) {
            $data_info_terminal = [
                "name" => $name,
                "phone" => $phone,
                "address" => $name_terminal,
                "coordinates" => $coordinates_terminal,
                "country" => 'LV',
                "city" => 'Rīga',
            ];

            $data_order = array(
                'type' => 'Regular',
                'category' => $category,
                'real_type' => 'OmnivaParcelTerminal',
                'origin' => $storeOwnerAddress,
                'destinations' => [$data_info_terminal],
                'parcel_size' => 'L'
            );

            /*
             * Save terminal for order
             */
            update_post_meta( $order_id, '_billing_terminal', sanitize_text_field($billing_terminal) );
            update_post_meta( $order_id, '_billing_terminal_id', sanitize_text_field($billing_terminal_id) );
        }
        $order_url = str_replace('{trading-point-id}', $trading_point_id, $api_order_url);
        $create = $response->getResponse($data_order, $api_url.$order_url, $token);

    }

}
add_action('woocommerce_order_status_processing', 'createOrder');

/*
 * Disable checkout if shipping not available
 */
add_action( 'woocommerce_after_checkout_validation', 'qwqerValidate', 10, 2 );
function qwqerValidate($fields, $errors) {
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    $cart = WC()->cart;
    if(in_array( 'qwqer_shipping_method', $chosen_shipping_methods )) {
        if($cart->get_shipping_total() == 0) {
            $errors->add('validation', __('Delivery is not possible!', 'qwqer'));
        }
    }
}

/*
 * Required phone number
 */
add_filter( 'woocommerce_checkout_fields', 'required_billing_phone', 25 );

function required_billing_phone( $fields )
{

    $fields[ 'billing' ][ 'billing_phone' ][ 'required' ] = true;
    $fields[ 'shipping' ][ 'shipping_phone' ][ 'required' ] = true;

    return $fields;

}