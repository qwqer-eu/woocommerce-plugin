<?php
/*
 * Plugin Name: QWQER Shipping for Woocommerce
 * Plugin URI: https://qwqer.lv/
 * Description: Express Delivery for Woocommerce
 * Version: 1.2
 * Author: QWQER
 * Author URI: https://qwqer.lv/
 * License: GPLv3
 */

defined('ABSPATH') || exit;

define( 'WOOCOMMERCE_CHECKOUT', true );


/*
 * Languages
 */
add_action( 'plugins_loaded', 'qwqer_textdomain' );

function qwqer_textdomain() {
    load_plugin_textdomain( 'qwqer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/*
 * Check if the Woocommerce plugin is activated
 */
function qwqerActivation()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        /*
         * If the Woocommerce plugin is not active, then deactivate this plugin
         */
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

add_action('admin_init', 'qwqerActivation');

/*
 * Check when you need to show an error message
 */
function qwqerAdminNoticesMsg()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'qwqerAdminNotices');
    }
}

add_action('admin_init', 'qwqerAdminNoticesMsg');

/*
 * Error message
 */
function qwqerAdminNotices()
{
    echo '<div class="error"><p>' . __('Important! Install and activate the plugin "Woocommerce"', 'qwqer') . '</p></div>'; //Message
}

/*
 * Error message store address and city fields
 */
function qwqerSettingsAdminNotices()
{
    echo '<div class="error"><p>' . __('Important! Address and city fields in woocommerce settings are required', 'qwqer') . '</p></div>'; //Message
}

/*
 * If the logic is not broken, then run all the functions of the plugin
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
    add_action('woocommerce_shipping_init', 'qwqer_shipping_method_init');
    function qwqer_shipping_method_init()
    {
        $store_address = get_option('woocommerce_store_address');
        $store_city = get_option('woocommerce_store_city');
        if($store_address && $store_city) {
            require_once 'inc' . DIRECTORY_SEPARATOR . 'settings.php';
            require_once 'inc' . DIRECTORY_SEPARATOR . 'createOrder.php';
            require_once 'inc' . DIRECTORY_SEPARATOR . 'OmnivaParcelTerminal.php';
        } else {
            add_action('admin_notices', 'qwqerSettingsAdminNotices');
        }

    }

    add_filter('woocommerce_shipping_methods', 'qwqer_shipping_method');
    function qwqer_shipping_method($methods)
    {
        $methods['qwqer_shipping_method'] = 'WC_Qwqer_Shipping_Method';
        return $methods;
    }
}

/*
 * Clear form checkout
 */
function ClearCheckoutForm()
{
    if (is_checkout()) { ?>
        <script type="text/javascript">
            ( function( $ ) {
                $('form.checkout').each(function() {
                    $('form.checkout').find('input').val('');
                });
            }( jQuery ) );
        </script>
    <?php }
}
add_action( 'wp_footer', 'ClearCheckoutForm' );

/*
 * Select terminals
 */
function qwqer_app_scripts()
{

    $localize = array(
        'ajaxurl' => admin_url( 'admin-ajax.php' )
    );
    wp_enqueue_script( 'qwqer-app-script', plugin_dir_url( __FILE__ ) . '/inc/js/qwqer_app.js', array( 'jquery' ) );
    wp_localize_script( 'qwqer-app-script', 'qwqer_app_script', $localize);

}

add_action( 'wp_enqueue_scripts', 'qwqer_app_scripts', 999 );
add_action( 'wp_ajax_terminal_request', 'terminal_request' );
add_action( 'wp_ajax_nopriv_terminal_request', 'terminal_request' );

/*
 * Get terminal
 */
function terminal_request()
{
    $select_terminal = $_REQUEST['terminal'];

    $zone_ids = array_keys( array('') + WC_Shipping_Zones::get_zones() );

    foreach ( $zone_ids as $zone_id )
    {
        $shipping_zone = new WC_Shipping_Zone($zone_id);

        $shipping_methods = $shipping_zone->get_shipping_methods( true, 'values' );
        foreach ( $shipping_methods as $instance_id => $shipping_method )
        {
            if($shipping_method->id == 'qwqer_shipping_method') {
                $token = $shipping_method->qwqer_key;
            }
        }
    }

    $response = new WC_Qwqer_Shipping_Method();
    $get_terminals = $response->getTerminals($token);

    foreach($get_terminals['data']['omniva'] as $terminal) {
        if($select_terminal == $terminal['id']) {
            $name = $terminal['name'];
            //$coordinates = $terminal['coordinates'];
        }
    }

    if ( isset($_POST['refresh_shipping']) && $_POST['refresh_shipping'] === 'yes' ) {
        WC()->session->set('refresh_shipping', '1' );
    } else {
        WC()->session->set('refresh_shipping', '0' );
    }

    WC()->session->set('billing_terminal', $name );
    WC()->session->set('billing_terminal_id', $select_terminal );

    echo $name . '+' . $select_terminal;
    die();
}



add_filter( 'woocommerce_checkout_fields', 'bbloomer_checkout_fields_trigger_refresh', 9999 );

function bbloomer_checkout_fields_trigger_refresh( $fields ) {
    $fields['billing']['billing_city']['class'][] = 'update_totals_on_change';

    return $fields;
}


/*
 * Refresh after select terminals
 */
add_action( 'woocommerce_checkout_update_order_review', 'refresh_terminals', 10, 1 );
function refresh_terminals( $post_data )
{
    if ( WC()->session->get('refresh_shipping' ) === '1' ) {
        foreach ( WC()->cart->get_shipping_packages() as $package_key => $package ) {
            WC()->session->set( 'shipping_for_package_' . $package_key, false );
        }
        WC()->cart->calculate_shipping();
    }
}

/*
 * Displaying terminal in customer order
 */
add_action( 'woocommerce_order_details_after_customer_details', 'display_terminal_in_customer_order', 10 );
function display_terminal_in_customer_order( $order )
{

    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
    $billing_terminal = get_post_meta( $order_id, '_billing_terminal', true );
    if ( !empty($billing_terminal) ) {
        echo '<p class="billing-terminal"><strong>'.__('Omniva Parcel locker', 'qwqer') . ':</strong> ' . get_post_meta( $order_id, '_billing_terminal', true ) .'</p>';
    }

}

/*
 * Displaying terminal on Admin order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_terminal_in_admin_order_meta', 10, 1 );
function display_terminal_in_admin_order_meta( $order )
{

    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
    $billing_terminal = get_post_meta( $order_id, '_billing_terminal', true );
    if ( !empty($billing_terminal) ) {
        echo '<p><strong>' . __('Omniva Parcel locker', 'qwqer') . ':</strong> ' . get_post_meta($order_id, '_billing_terminal', true) . '</p>';
        echo '<p><strong>' . __('Omniva Parcel locker ID', 'qwqer') . ':</strong> ' . get_post_meta($order_id, '_billing_terminal_id', true) . '</p>';
    }

}


/*
 * Displaying terminal on email notifications
 */
add_action('woocommerce_email_customer_details','add_terminal_to_emails_notifications', 15, 4 );
function add_terminal_to_emails_notifications( $order, $sent_to_admin, $plain_text, $email )
{

    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

    $output = '';
    $billing_terminal = get_post_meta( $order_id, '_billing_terminal', true );

    if ( !empty($billing_terminal) ) {
        $output .= '<div><strong>' . __('Omniva Parcel locker', 'qwqer') . '</strong> <span class="text">' . $billing_terminal . '</span></div>';
    }

    echo $output;

}