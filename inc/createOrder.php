<?php
/*
 * Create order after payment
 */
defined('ABSPATH') || exit;

require_once 'settings.php';
function createOrder($order_id)
{
    $response = new WC_Qwqer_Shipping_Method();
    /*
     * static data
     */
    $api_url = 'https://qwqer.hostcream.eu/api/';
    $api_coordinates_url = 'v1/places/geocode';
    $api_order_url = "v1/clients/auth/trading-points/{trading-point-id}/delivery-orders";

    $test = $response->arrayFields();

    $order = new WC_Order($order_id);

    $phone = $order->get_billing_phone();
    $name = $order->get_billing_first_name();
    $billing_address_1 = $order->get_shipping_address_1();
    $billing_city = $order->get_shipping_city();

    $params = [];

    foreach ($order->get_items('shipping') as $item_id => $item) {
        $shipping_method_id = $item->get_method_id();
        $shipping_method_instance_id = $item->get_instance_id();
    }

    $shipping_methods = WC()->shipping->get_shipping_method_class_names();
    $method_instance = new $shipping_methods['qwqer_shipping_method']( $shipping_method_instance_id );

    $token = $method_instance->get_option( 'qwqer_key' );
    $trading_point_id = $method_instance->get_option( 'qwqer_id' );
    $shop_phone = $method_instance->get_option( 'qwqer_phone' );
    $shop_phone = str_replace('+', '', $shop_phone);
    $category = $method_instance->get_option( 'qwqer_category' );

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
        $data_order = array(
            'type' => 'Regular',
            'category' => $category,
            'real_type' => 'ScheduledDelivery',
            'origin' => $storeOwnerAddress,
            'destinations' => [$clientOwnerAddress],
        );
        $order_url = str_replace('{trading-point-id}', $trading_point_id, $api_order_url);
        $create = $response->getResponse($data_order, $api_url.$order_url, $token);

        $data_log = json_encode($create);
        $register_log = fopen($_SERVER['DOCUMENT_ROOT'].'/register_log.txt', 'a');
        fwrite($register_log, $data_log . PHP_EOL);
        fclose($register_log);

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
            $errors->add('validation', __('Delivery is not possible!', 'woocommerce'));
        }
    }
}