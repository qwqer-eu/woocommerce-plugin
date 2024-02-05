<?php
/*
 * QWQER: Settings plugin
 */
defined('ABSPATH') || exit;

require_once 'OmnivaParcelTerminal.php';
class WC_Qwqer_Shipping_Method extends WC_Shipping_Method
{
    public const QWQER_API_URL = 'https://qwqer.lv/api/';
    public const QWQER_GET_COORDINATES_URL = 'v1/plugins/woocommerce/places/geocode';
    public const QWQER_GET_PRICE_URL = 'v1/plugins/woocommerce/clients/auth/trading-points/{trading-point-id}/delivery-orders/get-price';

    public const QWQER_GET_TERMINALS = 'v1/plugins/woocommerce/parcel-machines';

    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);

        $this->id = 'qwqer_shipping_method';
        $this->id_expressDelivery = 'qwqer_shipping_method_expressDelivery';
        $this->id_omnivaParcelTerminal = 'qwqer_shipping_method_omnivaParcelTerminal';
        $this->instance_id = absint($instance_id);
        $this->title = __('QWQER Shipping Method', 'qwqer');
        $this->method_title = __('QWQER Shipping Method', 'qwqer');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        $this->init();
    }

    public function init(): void
    {
        $this->init_form_fields();
        $this->init_instance_settings();
        $this->init_settings();

        $this->qwqer_title = $this->get_option('qwqer_title');
        $this->qwqer_title_expressDelivery = $this->get_option('qwqer_title_expressDelivery');
        $this->qwqer_title_omnivaParcelTerminal = $this->get_option('qwqer_title_omnivaParcelTerminal');
        $this->qwqer_key = $this->get_option('qwqer_key');
        $this->qwqer_id = $this->get_option('qwqer_id');
        $this->qwqer_phone = $this->get_option('qwqer_phone');
        $this->qwqer_category = $this->get_option('qwqer_category');
        $this->qwqer_expressDelivery = $this->get_option('qwqer_expressDelivery');
        $this->qwqer_omnivaParcelTerminal = $this->get_option('qwqer_omnivaParcelTerminal');

        $this->store_address = get_option('woocommerce_store_address');
        $this->store_city = get_option('woocommerce_store_city');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields(): void
    {
        $this->instance_form_fields = array(
            'qwqer_title' => array(
                'title' => __('Method Title', 'qwqer'),
                'type' => 'text',
                'default' => __('QWQER Scheduled Delivery', 'qwqer'),
                'description' => __('Scheduled Delivery', 'qwqer'),
            ),
            'qwqer_title_expressDelivery' => array(
                'title' => __('Method Title', 'qwqer'),
                'type' => 'text',
                'default' => __('QWQER Express Delivery', 'qwqer'),
                'description' => __('Express Delivery', 'qwqer'),
            ),
            'qwqer_title_omnivaParcelTerminal' => array(
                'title' => __('Method Title', 'qwqer'),
                'type' => 'text',
                'default' => __('Omniva Parcel locker', 'qwqer'),
                'description' => __('Delivery to the parce locker (Omniva)', 'qwqer'),
            ),
            'qwqer_id' => array(
                'title' => __('Trading-Point ID', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_key' => array(
                'title' => __('API token', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_phone' => array(
                'title' => __('Shop phone number', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_category' => array(
                'title' => __('Select category', 'qwqer'),
                'description' => __('Category to which your shop`s products belong', 'qwqer'),
                'type' => 'select',
                'default' => 'Other',
                'options' => array(
                    'Other' => __('Other', 'qwqer'),
                    'Flowers' => __('Flowers', 'qwqer'),
                    'Food' => __('Food', 'qwqer'),
                    'Electronics' => __('Electronics', 'qwqer'),
                    'Cake' => __('Cake', 'qwqer'),
                    'Present' => __('Present', 'qwqer'),
                    'Clothes' => __('Clothes', 'qwqer'),
                    'Document' => __('Document', 'qwqer'),
                    'Jewelry' => __('Jewelry', 'qwqer'),
                ),
            ),
            'qwqer_expressDelivery' => array(
                'title' => __('Express Delivery', 'qwqer'),
                'type' => 'checkbox',
            ),
            'qwqer_omnivaParcelTerminal' => array(
                'title' => __('Omniva Parcel Locker', 'qwqer'),
                'type' => 'checkbox',
            ),
        );
    }

    public function get_instance_form_fields(): array
    {
        return parent::get_instance_form_fields();
    }

    public function getResponse($params, $url, $token)
    {

        $curl = curl_init($url);
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer " . $token,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

        $result = curl_exec($curl);
        curl_close($curl);

        return $result = json_decode($result, true);

    }

    public function getTerminals($token)
    {

        $curl = curl_init(self::QWQER_API_URL . self::QWQER_GET_TERMINALS);
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer " . $token,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        curl_close($curl);

        return json_decode($result, true);

    }

    public function calculate_shipping($package = array())
    {
        if(is_checkout())
        {
            /*
             * Check Trading-Point ID
             */
            if (!$this->qwqer_id) {
                return false;
            }

            /*
             * Check API token
             */
            if (!$this->qwqer_key) {
                return false;
            }

            $params = [];

            $billing_address_1 = WC()->session->get('shipping_address_1', WC()->checkout->get_value('shipping_address_1'));
            $billing_city = WC()->session->get('shipping_city', WC()->checkout->get_value('shipping_city'));
            $billing_terminal = WC()->session->get('billing_terminal');
            $billing_terminal_id = WC()->session->get('billing_terminal_id');

            /*
             * Get store coordinates and address
             */
            $data_info_strore = [
                "address" => get_option('woocommerce_store_address') . ' ' . get_option('woocommerce_store_city'),
            ];
            $info_store = $this->getResponse($data_info_strore, self::QWQER_API_URL . self::QWQER_GET_COORDINATES_URL, $this->qwqer_key);

            $storeOwnerAddress = $params;
            $storeOwnerAddress["address"] = $info_store['data']['address'];
            $storeOwnerAddress["coordinates"] = $info_store['data']['coordinates'];

            /*
             * Get client coordinates and address
             */
            $data_info_client = [
                "address" => $billing_address_1 . ' ' . $billing_city,
            ];
            $info_client = $this->getResponse($data_info_client, self::QWQER_API_URL . self::QWQER_GET_COORDINATES_URL, $this->qwqer_key);

            $clientOwnerAddress = [];
            if (is_checkout()) {
                if (isset($info_client['data']['address']) && isset($info_client['data']['coordinates'])) {
                    $clientOwnerAddress["address"] = $info_client['data']['address'];
                    $clientOwnerAddress["coordinates"] = $info_client['data']['coordinates'];
                }
            }

            /*
             * Get terminal info
             */
            $get_terminals = $this->getTerminals($this->qwqer_key);
            foreach ($get_terminals['data']['omniva'] as $terminal) {
                if ($billing_terminal_id == $terminal['id']) {
                    $name = $terminal['name'];
                    $coordinates = $terminal['coordinates'];
                }
            }
            $name = isset($name) ? $name : null;
            $coordinates = isset($coordinates) ? $coordinates : null;

            /*
             * Get delivery price
             */
            $data_price_scheduledDelivery = array(
                'type' => 'Regular',
                'category' => $this->qwqer_category,
                'real_type' => 'ScheduledDelivery',
                'origin' => $storeOwnerAddress,
                'destinations' => [$clientOwnerAddress],
            );

            $data_price_expressDelivery = array(
                'type' => 'Regular',
                'category' => $this->qwqer_category,
                'real_type' => 'ExpressDelivery',
                'origin' => $storeOwnerAddress,
                'destinations' => [$clientOwnerAddress],
            );

            $data_info_terminal = [
                "address" => $name,
                "coordinates" => $coordinates,
                "country" => 'LV',
                "city" => 'Rīga',
            ];

            $data_price_omnivaParcelTerminal = array(
                'type' => 'Regular',
                'category' => $this->qwqer_category,
                'real_type' => 'OmnivaParcelTerminal',
                'origin' => $storeOwnerAddress,
                'destinations' => [$data_info_terminal],
                'parcel_size' => 'L'
            );

            $url_api_price = str_replace('{trading-point-id}', $this->qwqer_id, self::QWQER_GET_PRICE_URL);
            $price_scheduledDelivery = $this->getResponse($data_price_scheduledDelivery, self::QWQER_API_URL . $url_api_price, $this->qwqer_key);
            if ($this->qwqer_expressDelivery == 'yes') {
                $price_expressDelivery = $this->getResponse($data_price_expressDelivery, self::QWQER_API_URL . $url_api_price, $this->qwqer_key);
            }

            if ($this->qwqer_omnivaParcelTerminal == 'yes') {
                $price_omnivaParcelTerminal = $this->getResponse($data_price_omnivaParcelTerminal, self::QWQER_API_URL . $url_api_price, $this->qwqer_key);
            }

            /*
             * Check field address and city in woocommerce checkout
             */
            if ($billing_address_1 && $billing_city)
            {
                /*
                 * Check error delivery
                 */
                $message_scheduledDelivery = isset($price_scheduledDelivery['message']) ? $price_scheduledDelivery['message'] : null;
                $message_expressDelivery = isset($price_expressDelivery['message']) ? $price_expressDelivery['message'] : null;
                if ($message_scheduledDelivery || $message_expressDelivery) {
                    $this->add_rate(array(
                        'id' => $this->id,
                        //'label' => $this->qwqer_title . ' ' . $price['message'],
                        'label' => $this->qwqer_title . ' ' . __('One or more of the destinations is out of orgin`s delivery area!', 'qwqer'),
                    ));

                    $this->add_rate(array(
                        'id' => $this->id_expressDelivery,
                        //'label' => $this->qwqer_title . ' ' . $price['message'],
                        'label' => $this->qwqer_title_expressDelivery . ' ' . __('One or more of the destinations is out of orgin`s delivery area!', 'qwqer'),
                    ));

                    return false;
                }

                /*
                 * Add price
                 */
                $total_price = $price_scheduledDelivery['data']['client_price'];
                $this->add_rate(array(
                    'id' => $this->id,
                    'label' => $this->qwqer_title,
                    'cost' => $total_price / 100,
                ));

                if ($this->qwqer_expressDelivery == 'yes') {
                    $total_price = $price_expressDelivery['data']['client_price'];
                    $this->add_rate(array(
                        'id' => $this->id_expressDelivery,
                        'label' => $this->qwqer_title_expressDelivery,
                        'cost' => $total_price / 100,
                    ));
                }

                if($billing_terminal) {
                    $terminal_name = ' : ' . $billing_terminal;
                } else {
                    $terminal_name = '';
                }

                if ($this->qwqer_omnivaParcelTerminal == 'yes' || $billing_terminal) {
                    $price_omnivaParcelTerminal = isset($price_omnivaParcelTerminal['data']['client_price']) ? $price_omnivaParcelTerminal['data']['client_price'] : null;
                    $total_price = $price_omnivaParcelTerminal;
                    $this->add_rate(array(
                        'id' => $this->id_omnivaParcelTerminal,
                        'label' => $this->qwqer_title_omnivaParcelTerminal . $terminal_name,
                        'cost' => $total_price / 100,
                    ));
                }
            } else {
                $this->add_rate(array(
                    'id' => $this->id,
                    'label' => sprintf(__('%s. Please fill in the address and city field', 'qwqer'), $this->qwqer_title),
                ));
                if ($this->qwqer_expressDelivery == 'yes') {
                    $this->add_rate(array(
                        'id' => $this->id_expressDelivery,
                        'label' => sprintf(__('%s. Please fill in the address and city field', 'qwqer'), $this->qwqer_title_expressDelivery),
                    ));
                }
                if ($this->qwqer_omnivaParcelTerminal == 'yes') {
                    $this->add_rate(array(
                        'id' => $this->id_omnivaParcelTerminal,
                        'label' => sprintf(__('%s. Please select a parcel locker from the list', 'qwqer'), $this->qwqer_title_omnivaParcelTerminal),
                    ));
                }

            }
        }
    }
}