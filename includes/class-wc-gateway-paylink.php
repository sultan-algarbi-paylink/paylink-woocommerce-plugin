<?php

/** @noinspection ALL */

/**
 * @class       WC_Gateway_Paylink
 * @extends     WC_Payment_Gateway
 * @version     3.0.3
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_Paylink extends WC_Payment_Gateway
{
    // Form fields properties
    private string $app_id;             // The APP ID provided by Paylink.
    private string $secret_key;         // The secret key provided by Paylink.
    private string $callback_url;       // The URL where Paylink will send the payment status callback.
    private string $card_brands;         // The card brands to use for the payment.
    private bool   $display_thank_you;  // Display a Thank you page before redirecting to the Paylink payment page.
    private string $instructions;       // Gateway instructions that will be added to the thank you page and emails.
    private string $fail_msg;           // The error message that appears to the user if the payment fails.
    private bool   $test_mode;          // Enable Test Mode.

    // General properties
    private string|null $token;         // The token used to authenticate the request.
    private string $base_url;           // The base URL for the API requests.

    // Constants
    private const VALID_CARD_BRANDS = ['mada', 'visaMastercard', 'amex', 'tabby', 'tamara', 'stcpay', 'urpay'];

    public function __construct()
    {
        // Load the plugin text domain.
        load_plugin_textdomain('paylink', false, 'paylink/languages');

        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->title = $this->get_option('title', __('Paylink Payment Gateway', 'paylink'));
        $this->description = $this->get_option('description', __('Seamless transactions with popular payment methods in the Kingdom of Saudi Arabia, including:', 'paylink'));
        $this->callback_url = $this->get_option('callback_url');
        $this->display_thank_you = $this->get_option('display_thank_you', 'yes') === 'yes';
        $this->instructions = $this->get_option('instructions', __('Thank you for your order, you will be redirected to Paylink payment page.', 'paylink'));
        $this->fail_msg = $this->get_option('fail_msg', __('Your payment has failed, please try again.', 'paylink'));
        $this->test_mode = $this->get_option('test_mode', 'yes') === 'yes';

        // Set the APP ID and secret key based on the test mode.
        if ($this->test_mode) {
            $this->app_id = 'APP_ID_1123453311';
            $this->secret_key = '0662abb5-13c7-38ab-cd12-236e58f43766';
            $this->base_url = 'https://restpilot.paylink.sa';
        } else {
            $this->app_id = $this->get_option('app_id');
            $this->secret_key = $this->get_option('secret_key');
            $this->base_url = 'https://restapi.paylink.sa';
        }

        // Get the card brands from the settings.
        $card_brands_option = $this->get_option('card_brands', '');

        // Filter the array to keep only valid card brands
        $filtered_array = array_filter(explode(',', $card_brands_option), function ($brand) {
            return in_array($brand, self::VALID_CARD_BRANDS);
        });

        // Set the card brands
        $this->card_brands = count($filtered_array) > 0 ? implode(',', $filtered_array) : implode(',', self::VALID_CARD_BRANDS);
        $this->update_option('card_brands', $this->card_brands);

        // Check if the APP ID and Secret Key are empty
        if (empty($this->app_id) || empty($this->secret_key)) {
            $this->enabled = false;
            $this->update_option('enabled', 'no');
        }

        $this->token = null;

        // Payment listener/API hook to get the invoice.
        add_action('init', array($this, 'get_invoice'));
        add_action('woocommerce_api_paylink', array(&$this, 'get_invoice'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array(&$this, 'get_invoice'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook to add the invoice.
        add_action('woocommerce_receipt_paylink', array(&$this, 'add_invoice'));
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id                 = 'paylink';
        $this->icon               = apply_filters('woocommerce_paylink_icon', plugins_url('../assets/icon.min.png', __FILE__));
        $this->method_title       = __('Paylink Payment Gateway', 'paylink');
        $this->method_description = __("Paylink payment gateway for WooCommerce, It provides your customers with the popular payment methods in the Kingdom of Saudi Arabia, such as", 'paylink') . ' ' . self::_get_valid_card_brands_string();
        $this->has_fields         = false;
    }

    // ---------------------------------- inheritdoc Methods ----------------------------------
    /**
     * {@inheritdoc}
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'paylink'),
                'type' => 'checkbox',
                'label' => __('Enable Paylink Payment Gateway', 'paylink'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'paylink'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'paylink'),
                'default' => __('Paylink Payment Gateway', 'paylink'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'paylink'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'paylink'),
                'default' => __('Seamless transactions with popular payment methods in the Kingdom of Saudi Arabia, including:', 'paylink'),
                'desc_tip' => true,
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'paylink'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'paylink'),
                'default' => 'yes',
                'description' => __('Place the payment gateway in test mode.', 'paylink'),
            ),
            'app_id' => array(
                'title' => __('Live APP ID', 'paylink'),
                'type' => 'text',
                'description' => __('This is the APP ID provided by Paylink.', 'paylink'),
                'default' => '',
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => __('Live Secret Key', 'paylink'),
                'type' => 'text',
                'description' => __('This is the secret key provided by Paylink.', 'paylink'),
                'default' => '',
                'desc_tip' => true,
            ),
            // 'callback_url' => array(
            //     'title' => __('Callback URL', 'paylink'),
            //     'type' => 'text',
            //     'description' => __('This is the URL where Paylink will send the payment status callback.', 'paylink'),
            //     'default' => get_site_url() . '/wc-api/' . strtolower(get_class($this)),
            //     'desc_tip' => true,
            //     'custom_attributes' => [
            //         'readonly' => 'readonly'
            //     ]
            // ),
            // 'card_brands' => array(
            //     'title' => __('Card Brands', 'paylink'),
            //     'type' => 'text',
            //     'description' => __('The card brands to use for the payment. Separate the brands with a comma. Valid brands are:', 'paylink') . ' ' . implode(',', self::VALID_CARD_BRANDS),
            //     'default' => implode(',', self::VALID_CARD_BRANDS),
            //     'desc_tip' => true,
            // ),
            'display_thank_you' => array(
                'title' => __('Display Thank you page', 'paylink'),
                'type' => 'checkbox',
                'label' => __('Display a Thank you page before redirecting to the Paylink payment page.', 'paylink'),
                'default' => 'yes',
            ),
            'instructions' => array(
                'title' => __('Instructions', 'paylink'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'paylink'),
                'default' => __('Thank you for your order, you will be redirected to Paylink payment page.', 'paylink'),
                'desc_tip' => true,
            ),
            'fail_msg' => array(
                'title' => __('Failed Payment Message', 'paylink'),
                'description' => __('This controls the error message that appears to the user if the payment fails.', 'paylink'),
                'type' => 'textarea',
                'default' => __('Your payment has failed, please try again.', 'paylink'),
                'desc_tip' => true
            ),
        );
    }

    /**
     * {@inheritdoc}
     * Process the payment and return the result.
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        try {

            // Clear the WooCommerce notices.
            if (function_exists('wc_clear_notices')) {
                wc_clear_notices();
            }

            // Get the order object
            $order = wc_get_order($order_id);

            // Check if the order exists
            if (!$order) {
                throw new Exception(__('Order not found!', 'paylink'));
            }

            // Add custom order notes
            $order->add_order_note(__('Thank you for your order. Please complete your payment using the payment button below.', 'paylink'));

            // Get the checkout URL
            $checkout_payment_url = $order->get_checkout_payment_url(true);

            // Return thankyou redirect.
            return array(
                'result'   => 'success',
                'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), $checkout_payment_url)),
            );
        } catch (Exception $e) {
            $this->_postError($e->getMessage(), '', __FUNCTION__);
            return array(
                'result' => 'fail',
                'redirect' => wc_get_checkout_url(),
            );
        }
    }

    /**
     * {@inheritdoc}
     * This content will be shown on the checkout page. under the payment method.
     */
    public function payment_fields()
    {
        // flex container
        echo '<div style="display: flex; justify-content: center; align-items: center;">';
        echo '<img src="' . plugins_url('../assets/description_icon.png', __FILE__) . '" alt="' . $this->title . '" height="75" style="margin-right: 20px; margin-left: 20px;">';
        if (!empty($this->description)) {
            echo '<div>';
            echo wpautop(wptexturize($this->description));
            echo '</div>';
        }
        echo '</div>';

        echo '<div style="margin-top: 20px; text-align: center;">';
        echo '<img src="' . plugins_url('../assets/payment_methods_min.png', __FILE__) . '" alt="' . self::_get_valid_card_brands_string() . '" style="max-height: 130px;">';
        if ($this->test_mode) {
            echo '<br/><br/><hr/><p style="color: red; font-weight: bold; background-color: white;">' . __('Test Mode is enabled.', 'paylink') . '</p>';
        }
        echo '</div>';
    }

    // ---------------------------------- Helper Methods ----------------------------------

    /**
     * Get a string of the valid card brands.
     *
     * @return string
     */
    private static function _get_valid_card_brands_string()
    {
        $translated_brands = [];
        foreach (self::VALID_CARD_BRANDS as $brand) {
            if ($brand === 'visaMastercard') {
                $translated_brands[] = __('Visa/Mastercard', 'paylink');
                continue;
            }
            $translated_brands[] = __($brand, 'paylink');
        }
        return implode(', ', $translated_brands);
    }

    // ---------------------------------- API Methods ----------------------------------

    /**
     * Authenticate with the Paylink API.
     * @return bool
     * @throws Exception
     */
    private function _paylink_auth()
    {
        try {
            // Endpoint URL
            $endpoint = $this->base_url . '/api/auth';

            // Check if the APP ID and Secret Key are set
            if (empty($this->app_id) || empty($this->secret_key)) {
                throw new Exception(__('Unable to send authentication request: API ID or secret key is missing. Please provide the required credentials to proceed.', 'paylink'));
            }

            // Send the authentication request to the Paylink API
            $response = wp_safe_remote_post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'apiId' => $this->app_id,
                    'secretKey' => $this->secret_key,
                    'persistToken' => true
                ]),
            ]);

            // Check for errors in the HTTP request
            if (is_wp_error($response)) {
                throw new Exception(__('Failed to connect to the Paylink API. Please try again later.', 'paylink'));
            }

            // Get the response status
            $status_code = wp_remote_retrieve_response_code($response);

            // Check if the response status is successful
            if (!$this->_check_response_status($status_code)) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Exception(__('Failed to authenticate with Paylink API. Status Code:', 'paylink') . ' ' . ($status_code ?? 'N/A') . ', ' . __('Error:', 'paylink') . ' ' . ($error_message ?? 'N/A'));
            }

            // Decode the response body
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check if the response body is empty
            if (empty($response_body)) {
                throw new Exception(__('Empty response received from Paylink API while authenticating.', 'paylink'));
            }

            // Check if the authentication token is found in the response
            if (empty($response_body['id_token'])) {
                throw new Exception(__('No authentication token found in the response from Paylink API.', 'paylink'));
            }

            // Set the token
            $this->token = $response_body['id_token'];
            return true;
        } catch (Exception $e) {
            $this->_postError(__('Error:') . ' ' . $e->getMessage(), $endpoint, __FUNCTION__);
            $this->token = null;
            return false;
        }
    }

    public function add_invoice($order_id)
    {
        try {
            // Clear the WooCommerce notices.
            if (function_exists('wc_clear_notices')) {
                wc_clear_notices();
            }

            // Endpoint URL
            $endpoint = $this->base_url . '/api/addInvoice';

            // Check if the order ID is empty
            if (empty($order_id)) {
                throw new Exception(__('Order ID is missing!', 'paylink'));
            }

            // Check if the token is set
            if (!$this->token && !$this->_paylink_auth()) {
                throw new Exception(__('Failed to authenticate with the Paylink API!', 'paylink'));
            }

            // Get the order
            $order = wc_get_order($order_id);

            // Check if the order exists
            if (!$order) {
                throw new Exception(__('Order not found!', 'paylink'));
            }

            // Redirect URL
            $redirect_url = add_query_arg('wc-api', strtolower(get_class($this)), $order->get_checkout_order_received_url());

            // Get the products
            $products = [];
            $items = $order->get_items();
            foreach ($items as $item) {
                $product = $item->get_product();
                $products[] = [
                    'title' => $product->get_name(),
                    'price' => $product->get_price(),
                    'qty' => $item->get_quantity(),
                    'description' => $product->get_description(),
                    'isDigital' => $product->is_virtual(),
                ];
            }

            // Add the invoice to the Paylink API
            $response = wp_safe_remote_post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode([
                    'orderNumber' => $order_id,
                    'clientName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'clientMobile' => $order->get_billing_phone(),
                    'clientEmail' => $order->get_billing_email(),
                    'amount' => $order->get_total(),
                    'callBackUrl' => $redirect_url,
                    'note' => '',
                    'lang' => 'ar',
                    'products' => $products,
                    'currency' => $order->get_currency(),
                    // 'supportedCardBrands' => explode(',', $this->card_brands),
                    'displayPending' => true,
                ]),
                'timeout' => 60,
                'httpversion' => '1.1',
                'user-agent' => '1.0',
            ]);

            // Check for errors in the HTTP request
            if (is_wp_error($response)) {
                throw new Exception(__('Failed to connect to the Paylink API. Please try again later.', 'paylink'));
            }

            // Get the response status
            $status_code = wp_remote_retrieve_response_code($response);

            // Check if the response status is successful
            if (!$this->_check_response_status($status_code)) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Exception(__('Failed to add invoice to Paylink API. Status Code:', 'paylink') . ' ' . ($status_code ?? 'N/A') . ', ' . __('Error:', 'paylink') . ' ' . ($error_message ?? 'N/A'));
            }

            // Decode the response body
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check if the response body is empty
            if (empty($response_body)) {
                throw new Exception(__('Empty response received from Paylink API while adding invoice.', 'paylink'));
            }

            // Check if the URL is found in the response
            if (!$response_body['url']) {
                throw new Exception(__('No URL found in the Paylink API response', 'paylink'));
            }

            // Get the URL
            $url = $response_body['url'];

            // Redirect to the Paylink payment page
            if ($this->display_thank_you) {
                echo '<p>' . $this->instructions . '</p>';
                echo "<script>setTimeout(function() {window.location.href = '" . $url . "';}, 2000);</script>";
            } else {
                wp_redirect($url);
            }
        } catch (Exception $e) {
            $this->_postError(__('Error:') . ' ' . $e->getMessage(), $endpoint, __FUNCTION__);
        }
    }

    public function get_invoice()
    {
        try {
            // Clear the WooCommerce notices.
            if (function_exists('wc_clear_notices')) {
                wc_clear_notices();
            }

            // Checkout URL for redirection in case of errors
            $checkout_url = wc_get_checkout_url();

            // Check if the order ID is set
            if (empty($_REQUEST['orderNumber'])) {
                throw new Exception(__('No order ID found in the request!', 'paylink'));
            }

            // Check if the transaction number is set
            if (empty($_REQUEST['transactionNo'])) {
                throw new Exception(__('No transaction number found in the request!', 'paylink'));
            }

            // Check if the token is set
            if (!$this->token && !$this->_paylink_auth()) {
                throw new Exception(__('Failed to authenticate with the Paylink API!', 'paylink'));
            }

            // Set Order ID and Transaction Number
            $order_id = sanitize_text_field($_REQUEST['orderNumber']);
            $transaction_no = sanitize_text_field($_REQUEST['transactionNo']);

            // Check if the order ID is empty
            if (empty($order_id)) {
                throw new Exception(__('Order ID is missing!', 'paylink'));
            }

            // Check if the transaction number is empty
            if (empty($transaction_no)) {
                throw new Exception(__('Transaction number is missing!', 'paylink'));
            }

            // Endpoint URL
            $endpoint = $this->base_url . '/api/getInvoice/' . $transaction_no;

            // Get the order
            $order = wc_get_order($order_id);

            // Check if the order exists
            if (!$order) {
                throw new Exception(__('Order not found!', 'paylink'));
            }

            // Get the invoice from the Paylink API
            $response = wp_safe_remote_get($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 60,
                'httpversion' => '1.1',
                'user-agent' => '1.0',
            ]);

            // Check for errors in the HTTP request
            if (is_wp_error($response)) {
                throw new Exception(__('Failed to connect to the Paylink API. Please try again later.', 'paylink'));
            }

            // Get the response status
            $status_code = wp_remote_retrieve_response_code($response);

            // Check if the response status is successful
            if (!$this->_check_response_status($status_code)) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Exception(__('Failed to get invoice from Paylink API. Status Code:', 'paylink') . ' ' . ($status_code ?? 'N/A') . ', ' . __('Error:', 'paylink') . ' ' . ($error_message ?? 'N/A'));
            }

            // Decode the response body
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check if the response body is empty
            if (empty($response_body)) {
                throw new Exception(__('Empty response received from Paylink API while getting invoice.', 'paylink'));
            }

            // Check if the order status is found in the response
            if (!$response_body['orderStatus']) {
                throw new Exception(__('No order status found in the Paylink API response', 'paylink'));
            }

            // Get the order status
            $order_status = mb_convert_case(sanitize_text_field($response_body['orderStatus']), MB_CASE_LOWER, 'UTF-8');

            // Check if the order status is paid or completed
            if ($order_status === 'paid') {
                $checkout_url = add_query_arg('transactionNo', $transaction_no, $order->get_checkout_order_received_url()); // Get the checkout URL for the Thank you page
                $order->payment_complete($transaction_no);
                WC()->cart->empty_cart();
            } else if ($order_status === 'canceled') {
                $order->update_status('cancelled');
                throw new Exception(__('Payment was cancelled!', 'paylink'));
            } else if ($order_status === 'pending' && empty($response_body['paymentErrors'])) {
                $order->update_status('pending');
                throw new Exception(__('Payment is pending', 'paylink'));
            } else {
                $order->update_status('failed');
                throw new Exception(__('Payment failed, Try again!', 'paylink'));
            }
        } catch (Exception $e) {
            $this->_postError(esc_html($this->fail_msg) . ', ' . $e->getMessage(), $endpoint, __FUNCTION__);
        } finally {
            wp_redirect($checkout_url);
        }
    }

    // ---------------------------------- Helper Methods ----------------------------------

    /**
     * Check if the response status is successful.
     * @param int|string $status_code The response status code.
     * @return bool
     */
    private function _check_response_status(int|string $status_code)
    {
        return !empty($status_code) && $status_code >= 200 && $status_code < 300;
    }

    /**
     * Log an error to the Paylink API.
     * @param string $error The error message.
     * @param string $calledUrl The URL where the error occurred.
     * @param string $method The method where the error occurred.
     * @return void
     * @throws Exception
     */
    private function _postError($error, $calledUrl, $method)
    {
        // Log the error to the server
        error_log('Paylink Error: ' . $error . ' in ' . $method . ' at ' . $calledUrl);

        // Display the error message to the user
        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, 'error');
            wc_print_notices();
        } else {
            $this->add_error($error);
            $this->display_errors();
        }

        // Log the error to the Paylink API
        try {
            wp_safe_remote_post('https://paylinkapp.paylink.sa/careapi/wp_log_error', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'apiId' => $this->app_id,
                    'apiKey' => $this->secret_key,
                    'error' => $error,
                    'calledUrl' => $calledUrl,
                    'method' => $method
                ]),
            ]);
        } catch (Exception $e) {
            error_log('Failed to log the error to the Paylink API: ' . $e->getMessage());
        }
    }
}
