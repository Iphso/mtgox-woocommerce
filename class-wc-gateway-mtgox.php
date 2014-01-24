<?php
/*
Plugin Name: MtGox WooCommerce Payment Gateway
Plugin URI: https://github.com/nils--/mtgox-woocommerce
Description: Accept Bitcoin payments on your WooCommerce store. Payments are securely processed by MtGox, the leading Bitcoin exchange.
Version: 1.0
Author: Tibanne Co. Ltd.
Author URI: http://www.tibanne.com/
License: Proprietary
License URI: https://raw.github.com/nils--/mtgox-woocommerce/master/LICENSE
GitHub Plugin URI: https://github.com/nils--/mtgox-woocommerce
*/

if (!defined('ABSPATH'))
{
	exit; // Exit if accessed directly
}

// Create and initialize the WC_Gateway_Mtgox class
add_action('plugins_loaded', 'woocommerce_mtgox_init', 0);

// Register MtGox payment gateway
add_filter('woocommerce_payment_gateways', 'woocommerce_add_mtgox_gateway');

function woocommerce_add_mtgox_gateway($methods)
{
	$methods[] = 'WC_Gateway_Mtgox';
	return $methods;
}

// Add new Bitcoin currency
add_filter('woocommerce_currencies', 'mtgox_add_btc_currency');
if (!function_exists('mtgox_add_btc_currency'))
{
	function mtgox_add_btc_currency($currencies)
	{
		$currencies['BTC'] = 'Bitcoin';
		return $currencies;
	}
}

// Add a currency symbol for Bitcoin
add_filter('woocommerce_currency_symbol', 'mtgox_add_btc_currency_symbol', 10, 2);
if (!function_exists('mtgox_add_btc_currency_symbol'))
{
	function mtgox_add_btc_currency_symbol($currency_symbol, $currency)
	{
		// NOTE: This must be a switch statement coded exactly as below, or
		// other currencies will not get a symbol.

		switch ($currency)
		{
			case 'BTC':
				$currency_symbol = 'BTC';
				break;
		}
		
		return $currency_symbol;
	}
}

function woocommerce_mtgox_init()
{
	if (!class_exists('WC_Payment_Gateway')) return;
	/**
	 * MtGox Payment Gateway
	 *
	 * Provides a MtGox Payment Gateway.
	 *
	 * @class 		WC_Gateway_Mtgox
	 * @extends		WC_Payment_Gateway
	 * @author 		Tibanne Co. Ltd.
	 */
	class WC_Gateway_Mtgox extends WC_Payment_Gateway
	{
		/**
		 *
		 * @var WC_Logger
		 */
		private $log;
		
		/**
		 * Description text to show on MtGox checkout page.
		 * @var string
		 */
		private $payment_page_description;
		
		/**
		 * Whether to send an email on successful payment or not.
		 * @var bool
		 */
		private $email;
		
		/**
		 * Whether to automatically sell BTC at market rate or not.
		 * @var bool
		 */
		private $auto_sell;
		
		/**
		 * Whether to only allow existing MtGox customers to pay transactions or not.
		 * @var bool
		 */
		private $instant_only;
		
		/**
		 * MtGox API key.
		 * @var string
		 */
		private $api_key;
		
		/**
		 * MtGox API secret.
		 * @var string
		 */
		private $api_secret;
		
		/**
		 * Whether debug log is enabled or not.
		 * @var type bool
		 */
		private $debug;
		
		/**
		 * Constructor for the payment gateway.
		 *
		 * @return void
		 */
		public function __construct()
		{
			global $woocommerce;
			$this->id = 'mtgox';
			$this->icon = apply_filters('woocommerce_mtgox_icon', plugins_url('assets/mtgox.png', __FILE__));
			$this->has_fields = false;
			$this->liveurl = 'https://mtgox.com/?p=woocommerce-plugin';
			$this->method_title = 'MtGox';
			$this->method_description = 'Accept Bitcoin payments on your WooCommerce store. Payments are securely processed by MtGox, the leading Bitcoin exchange.';
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->payment_page_description = $this->get_option('payment_page_description');
			$this->email = $this->get_option('email') === 'yes';
			$this->auto_sell = $this->get_option('auto_sell') === 'yes';
			$this->instant_only = $this->get_option('instant_only') === 'yes';
			$this->api_key = $this->get_option('api_key');
			$this->api_secret = $this->get_option('api_secret');
			$this->debug = $this->get_option('debug') === 'yes';

			// Do we log?

			if ($this->debug) $this->log = $woocommerce->logger();

			// Actions

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			));
			add_action('woocommerce_api_wc_gateway_' . $this->id, array(
				$this,
				'check_mtgox_response'
			));
		}

		/**
		 * Check if this payment gateway is enabled and actually available in
		 * the user's country.
		 *
		 * @return bool
		 */
		public function is_valid_for_use()
		{
			$currencies = apply_filters('woocommerce_paypal_supported_currencies', array(
				'BTC',
				'USD',
				'AUD',
				'CAD',
				'CHF',
				'CNY',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'JPY',
				'NZD',
				'PLN',
				'RUB',
				'SEK',
				'SGD',
				'THB',
				'NOK',
				'CZK'
			));
			return in_array(get_woocommerce_currency() , $currencies);
		}

		/**
		 * Render payment gateway options for the administration panel.
		 *
		 * @return void
		 */
		public function admin_options()
		{
?>
			<h3>MtGox</h3>
			<p>Easily process Bitcoin payments using the MtGox Merchant Tools.</p>

			<?php
			if ($this->is_valid_for_use()): ?>

				<table class="form-table">
					<?php
				$this->generate_settings_html(); ?>
				</table><!--/.form-table-->

			<?php
			else: ?>
				<div class="inline error"><p><strong>MtGox Gateway Disabled</strong>:
				Unfortunately MtGox does not currently support your shop's currency.</p></div>
			<?php
			endif;
		}

		/**
		 * Initialize form fields for the MtGox payment gateway.
		 *
		 * @return void
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => 'Enable/Disable',
					'type' => 'checkbox',
					'label' => 'Enable MtGox Payment Gateway',
					'default' => 'yes'
				) ,
				'title' => array(
					'title' => 'Title',
					'type' => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default' => 'Bitcoin via MtGox',
					'desc_tip' => true,
				) ,
				'description' => array(
					'title' => 'Description',
					'type' => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default' => 'Pay in Bitcoin using MtGox Instant Checkout. If you do not have a MtGox account, you can still make a regular Bitcoin transfer.',
				) ,
				'payment_page_description' => array(
					'title' => 'Payment Page Description',
					'type' => 'textarea',
					'description' => 'This controls the description which the user sees on MtGox. If this field is empty, it will say "Payment to [Username] on MtGox.',
					'default' => 'Thank you for shopping with us.'
				) ,
				'email' => array(
					'title' => 'Send Email on Payment',
					'type' => 'checkbox',
					'description' => 'Send an email when you receive an email for each successful payment.',
					'default' => 'no',
					'desc_tip' => true
				) ,
				'auto_sell' => array(
					'title' => 'Automatically Sell',
					'type' => 'checkbox',
					'description' => 'Automatically sell received Bitcoin at the current market rate.',
					'default' => '',
					'desc_tip' => true,
					'placeholder' => 'you@youremail.com'
				) ,
				'instant_only' => array(
					'title' => 'MtGox Customers Only',
					'type' => 'checkbox',
					'description' => 'Only allow existing customers of MtGox to pay transactions.',
					'default' => '',
					'desc_tip' => true,
					'placeholder' => 'you@youremail.com'
				) ,
				'api_key' => array(
					'title' => 'API Key',
					'type' => 'text',
					'description' => 'You can get an API key free of charge in the Security Center (scroll down to Advanced API Key Creation).',
				) ,
				'api_secret' => array(
					'title' => 'API Secret',
					'type' => 'text',
					'description' => 'Your API secret was generated when you created your MtGox API key.',
					'desc_tip' => true,
				) ,
				'debug' => array(
					'title' => 'Debug Log',
					'type' => 'checkbox',
					'label' => 'Enable logging',
					'default' => 'no',
					'description' => sprintf('Write debugging information to <code>woocommerce/logs/mtgox-%s.txt</code>', sanitize_file_name(wp_hash('mtgox'))) ,
				)
			);
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @return array Either an array with result and redirect information,
		 * or nothing in case of an error.
		 *
		 */
		public function process_payment($order_id)
		{
			global $woocommerce;
			$order = new WC_Order($order_id);
			$ret = $this->api_create_order($order);
			if ($ret->result !== 'success')
			{

				// If the API returns any sort of error, the debug log will
				// cover it, but we shouldn't show any details to the user.

				$woocommerce->add_error('Failed to initiate payment. Please contact us if you need assistance.');
				return;
			}

			update_post_meta($order_id, 'Transaction ID', $ret->return->transaction);
			return array
			(
				'result' => 'success',
				'redirect' => $ret->return->payment_url
			);
		}

		/**
		 * Call the MtGox order creation API to create a transaction that the
		 * user can then pay using Bitcoin.
		 *
		 * @param WC_Order $order The WooCommerce order containing the order
		 * details.
		 * @return array An object with status information, a transaction ID,
		 * and a link to the MtGox payment gateway that the user can be sent
		 * to in order to complete payment.
		 */
		private function api_create_order(WC_Order $order)
		{
			$time = explode(' ', microtime());
			$params = array(
				'nonce' => $time[1] . substr($time[0], 2, 6) ,
				'currency' => get_woocommerce_currency() ,
				'amount' => $order->get_total() ,
				'ipn' => str_replace('https:', 'http:', add_query_arg('wc-api', 'wc_gateway_' . $this->id, home_url('/'))) ,
				'description' => $this->payment_page_description,
				'data' => serialize(array(
					$order->id,
					$order->order_key
				)) ,
				'email' => $this->email ? 1 : 0,
				'autosell' => $this->autosell ? 1 : 0,
				'multipay' => 0,
				'instant_only' => $this->instant_only ? 1 : 0,
				'return_success' => $this->get_return_url($order) ,
				'return_failure' => $order->get_cancel_order_url() ,
			);
			$post_data = http_build_query($params, '', '&');
			$headers = array(
				'Rest-Key' => $this->api_key,
				'Rest-Sign' => base64_encode(hash_hmac('sha512', $post_data, base64_decode($this->api_secret) , true)) ,
				'Content-Type' => 'application/x-www-form-urlencoded'
			);
			if ($this->debug)
			{
				$this->log->add('mtgox', 'MtGox API request headers: ' . var_export($headers, true));
				$this->log->add('mtgox', 'MtGox API request parameters: ' . var_export($params, true));
			}

			$response = wp_remote_post('https://data.mtgox.com/api/1/generic/merchant/order/create', array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 0,
				'httpversion' => '1.1',
				'blocking' => true,
				'headers' => $headers,
				'body' => $params,
				'cookies' => array()
			));
			if (is_wp_error($response))
			{
				if ($this->debug)
				{
					$error_message = $response->get_error_message();
					$this->log->add('mtgox', 'Error connecting to MtGox API:' . $error_message);
				}

				return false;
			}
			else
			{
				if ($this->debug)
				{
					$this->log->add('mtgox', 'MtGox API response: ' . var_export($response, true));
				}

				return json_decode($response['body']);
			}
		}

		public function check_mtgox_response()
		{
			global $woocommerce;
			
			@ob_clean();
			
			$raw_data = file_get_contents("php://input");
			$posted = stripslashes_deep($_POST);
			$expected_signature = hash_hmac('sha512', $raw_data, base64_decode($this->api_secret) , true);
			$actual_signature = base64_decode($_SERVER['HTTP_REST_SIGN']);
			if ($actual_signature != $expected_signature)
			{
				if ($this->debug)
				{
					$this->log->add('mtgox', 'MtGox IPN error. Signature mismatch (Expected: ' . base64_encode($expected_signature) . ', Received: ' . base64_encode($actual_signature));
				}

				wp_die('MtGox IPN response validation failed.');
			}

			if ($this->debug)
			{
				$this->log->add('mtgox', 'MtGox IPN response: ' . var_export($posted, true));
			}

			$status = esc_html($posted['status']);
			$order_data = maybe_unserialize($posted['data']);
			$payment_id = esc_html($posted['payment_id']);
			
			if (!is_array($order_data))
			{
				if ($this->debug == 'yes')
				{
					$this->log->add('mtgox', 'Error: Order data invalid.');
				}

				wp_die('MtGox IPN response validation failed.');
			}

			list($order_id, $order_key) = $order_data;
			$order = new WC_Order($order_id);
			
			// Validate key
			if ($order->order_key !== $order_key)
			{
				if ($this->debug == 'yes')
				{
					$this->log->add('mtgox', 'Error: Order Key does not match.');
				}

				wp_die('MtGox IPN response validation failed.');
			}
			
			// Add the payment ID to the order
			update_post_meta($order_id, 'Payment ID', $payment_id);

			switch ($status)
			{
				case 'paid':
					$order->add_order_note(sprintf('MtGox payment completed (Payment ID: %s)', $payment_id));
					$order->payment_complete();
					break;

				case 'partial':

					// Put the order on hold for now

					$order->update_status('on-hold');

					// Note for the customer

					$message = <<< EOF
Your payment was received, but is still pending confirmation by the network. Therefore,
your order is currently on-hold. Please contact us for more information regarding your
order and payment status. (Payment ID: %s)
EOF;
					$order->add_order_note(sprintf($message, $payment_id) , 1);

					// Note for shop owner

					$message = <<< EOF
This order is currently on hold. Payment was still pending confirmation by the network and
your MtGox merchant account was not credited immediately. You should confirm whether your
account was credited for the full amount due. (Payment ID: %s);
EOF;
					$order->add_order_note(sprintf($message, $payment_id));
					$order->reduce_order_stock();
					break;

				case 'cancelled':
					$order->cancel_order();

					// Clean out the cart

					$woocommerce->cart->empty_cart();
					break;

				default:
					$order->update_status('failed');

					// Note for the customer

					$message = <<< EOF
Your payment could not be processed, so your order was cancelled. You were NOT charged
for this order. Please contact us for assistance. (Payment ID: %s);
EOF;
					$order->add_order_note(sprintf($message, $payment_id) , 1);

					// Note for shop owner

					$message = "Payment failed to process (Status: %s, Payment ID: %s)";
					$order->add_order_note(sprintf($message, $status, $payment_id));
					break;
			}

			if ($this->debug == 'yes')
			{
				$this->log->add('mtgox', 'Processed IPN response.');
			}

			echo '[OK]';
			exit;
		}
	}
}