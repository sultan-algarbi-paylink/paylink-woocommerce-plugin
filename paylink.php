<?php

/** @noinspection ALL */

/** @noinspection DuplicatedCode */

/**
 * Plugin Name: Paylink Payment Gateway
 * Plugin URI:  https://paylink.sa
 * Author:      Paylink Co
 * Author URI:  https://paylink.sa
 * Description: Use this woocommerce payment gateway plugin to enable clients of your store to pay using Paylink gateway.
 * Version:     3.0.2
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: paylink
 * Domain Path: /languages
 * 
 * Class WC_Gateway_Paylink file.
 *
 * @package WooCommerce\Paylink
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Check if WooCommerce is installed
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	notice_error('WooCommerce is not installed.');
	return;
}

// Add a custom payment gateway
add_action('plugins_loaded', 'init_paylink_gateway', 11);
add_filter('woocommerce_payment_gateways', 'add_paylink_gateway');

// Initialize the gateway
function init_paylink_gateway()
{
	// If the class WC_Payment_Gateway doesn't exist, it means WooCommerce is not installed.
	if (!class_exists('WC_Payment_Gateway')) {
		notice_error('WC Payment Gateway class does not exist. WooCommerce is not installed.');
		return;
	}

	// Include the Paylink gateway class
	require_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-paylink.php';

	add_action('woocommerce_thankyou', 'paylink_add_order_meta');
	add_action('woocommerce_order_details_after_order_table', 'paylink_display_order_reference', 10, 1);

	// Add the Paylink order meta.
	function paylink_add_order_meta($order_id)
	{
		if (isset($_GET['transactionNo'])) {
			update_post_meta($order_id, '_paylink_transaction_no', $_GET['transactionNo']);
		} else {
			update_post_meta($order_id, '_paylink_transaction_no', 'N/A');
		}
	}

	// Display the Paylink order reference in the order details.
	function paylink_display_order_reference($order)
	{
		$paylink_transaction_no = $order->get_meta('_paylink_transaction_no');
		if ($paylink_transaction_no) {
			echo '<p><strong>Paylink Transaction Number:</strong> ' . $paylink_transaction_no . '</p>';
		}
	}
}

// Add the gateway to WooCommerce
function add_paylink_gateway($methods)
{
	$methods[] = 'WC_Gateway_Paylink';
	return $methods;
}

// Display an error notice
function notice_error(string $error)
{
	echo '<div class="notice notice-error">';
	echo "<p>$error</p>";
	echo '</div>';
}
