<?php
/**
 * Plugin Name: Nimbbl WooCommerce
 * Plugin URI: https://nimbbl.biz/
 * Description: Get fast checkout with UPI, BNPL and accept all payment modes: Debit/Credit Cards, Netbanking, Wallets and more.
 * Author: Nimbbl
 * Author URI: https://nimbbl.biz/
 * Version: 4.0.8
 * Text Domain: nimbbl-woocommerce
 * Domain Path: /languages
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loading text domain
 */
function load_wc_nimbbl_textdomain() {
	load_plugin_textdomain( 'nimbbl-woocommerce', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'load_wc_nimbbl_textdomain' );

function wc_nimbbl_payment_gateway_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Nimbbl requires WooCommerce to be installed and active. You can download %s here.', 'nimbbl-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
		return;
	}
	
	if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

	class WC_Nimbbl_Payment_Gateway extends WC_Payment_Gateway {

		public function __construct() {

			$this->id                   = 'nimbbl_payment_gateway';
			$this->icon = apply_filters('woocommerce_nimbbl_icon', plugins_url('assets/images/nimbbl.png', __FILE__));
			$this->method_title         = __( 'Nimbbl', 'nimbbl-woocommerce' );
			$this->method_description   = __( 'Get fast checkout with UPI, BNPL and accept all payment modes: Debit/Credit Cards, Netbanking, Wallets and more.', 'nimbbl-woocommerce' );
			$this->has_fields           = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title                = $this->get_option( 'title' );
			$this->description          = $this->get_option( 'description' );
			$this->payment_mode         = $this->get_option( 'payment_mode' );
			$this->debug_mode           = $this->get_option( 'debug_mode' );
			$this->access_key           = ($this->payment_mode) ? $this->get_option( 'live_access_key' ) : $this->get_option( 'test_access_key' );
			$this->secret_key           =  ($this->payment_mode) ? $this->get_option( 'live_secret_key' ) : $this->get_option( 'test_secret_key' );
			$this->api_url 				= 'https://api.nimbbl.tech/api/v3'; //use qa4api for testing
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_receipt_nimbbl_payment_gateway', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_api_nimbblcallback', array( $this, 'process_webhook' ) );
			add_action( 'woocommerce_api_nimbbl_process_response', array( $this, 'process_response' ) );
			add_action('woocommerce_order_details_after_order_table', array($this, 'order_details_after_order_table'), 9);
			add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'admin_order_data_after_order_details'));
		}

		
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title'         => __( 'Enable/Disable', 'nimbbl-woocommerce' ),
					'label'         => __( 'Nimbbl', 'nimbbl-woocommerce' ),
					'type'          => 'checkbox',
					'default'       => 'yes',
				),
				'title' => array(
					'title'         => __( 'Title', 'nimbbl-woocommerce' ),
					'type'          => 'safe_text',
					'description'   => __( 'Payment method title that the customer will see on your checkout.', 'nimbbl-woocommerce' ),
					'default'       => __( 'One-click checkout', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'description' => array(
					'title'         => __( 'Description', 'nimbbl-woocommerce' ),
					'type'          => 'textarea',
					'description'   => __( 'Payment method description that the customer will see on your checkout.', 'nimbbl-woocommerce' ),
					'default'       => __( 'Pay with your fastest payment mode in seconds. All payment modes are supported.', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'payment_mode' => array(
                    'title' => __('Payment Mode', 'nimbbl-woocommerce'),
                    'description' => __('Choose payment environment.', 'nimbbl-woocommerce'),
                    'type' => 'select',
                    'default' => '0',
                    'options' => array(
                        '1' => __('Production', 'nimbbl-woocommerce'),
                        '0' => __('Sandbox', 'nimbbl-woocommerce'),
                    )
                ),
				'test_access_key' => array(
					'title'         => __( 'Test Access Key', 'nimbbl-woocommerce' ),
					'type'          => 'text',
					'description'   => __( 'Enter the Sandbox access key from the Nimbbl dashboard.', 'nimbbl-woocommerce' ),
					'placeholder'   => __( 'Test Access Key', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'test_secret_key' => array(
					'title'         => __( 'Test Secret Key', 'nimbbl-woocommerce' ),
					'type'          => 'password',
					'description'   => __( 'Enter the Sandbox secret key from the Nimbbl dashboard.', 'nimbbl-woocommerce' ),
					'placeholder'   => __( 'Test Secret Key', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'live_access_key' => array(
					'title'         => __( 'Live Access Key', 'nimbbl-woocommerce' ),
					'type'          => 'text',
					'description'   => __( 'Enter the Live access key from the Nimbbl dashboard.', 'nimbbl-woocommerce' ),
					'placeholder'   => __( 'Live Access Key', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'live_secret_key' => array(
					'title'         => __( 'Live Secret Key', 'nimbbl-woocommerce' ),
					'type'          => 'password',
					'description'   => __( 'Enter the Live access key from the Nimbbl dashboard.', 'nimbbl-woocommerce' ),
					'placeholder'   => __( 'Live Secret Key', 'nimbbl-woocommerce' ),
					'desc_tip'      => false
				),
				'debug_mode' => array(
                    'title' => __('Debug Mode', 'nimbbl-woocommerce'),
                    'description' => __('Choose debug mode.', 'nimbbl-woocommerce'),
                    'type' => 'select',
                    'default' => '0',
                    'options' => array(
                        '1' => __('Yes', 'nimbbl-woocommerce'),
                        '0' => __('No', 'nimbbl-woocommerce'),
                    )
                ),
				'' => array(
					'title'         => __( 'Webhook Url', 'nimbbl-woocommerce' ),
					'type'          => 'hidden',
					'description'   => __( 'Connect with Nimbbl to set this up on your Nimbbl dashboard.', 'nimbbl-woocommerce' ) .'<br/><br/>'. WC()->api_request_url('nimbblcallback'),
					'desc_tip'      => false
				),
			);
		}

		
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			$nimbbl_order_id = $order->get_meta('wc_nimbbl_order_id');
			if(!empty($nimbbl_order_id)) {
				return array(
					'result'	=> 'success',
					'redirect'	=> add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)),
				);
			}
			
			//check API Authorization here
			$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$order_id.' Token request submitted : ' .print_r(array('access_key' => $this->access_key, 'access_secret' => $this->secret_key ), true) );
			$response_auth = $this->is_nimbbl_api_authorization();
			if(is_array($response_auth) && $response_auth['result'] == 'fail') {
				$this->nimbb_plugin_log(WC_Log_Levels::ERROR, 'Order '.$order_id.' Token request failed : ' . $response_auth['message']);
				$this->add_notice( $response_auth['message'], 'error' );
				return array(
					'result'	=> 'fail',
					'redirect'	=> '',
				);
			} else {
				$auth_token = $response_auth['token'];
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$order_id.' Token received : ' . $auth_token);
				$order_data = $this->create_nimbbl_order_data($order);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$order_id.' request submitted : ' . print_r( $order_data, true ));
				$response = $this->send_nimbbl_order_data_to_api($order_data, $auth_token);
				if(is_array($response) && $response['result'] == 'fail') {
					$this->nimbb_plugin_log(WC_Log_Levels::ERROR, 'Order '.$order_id.' creation failed : ' . $response['message']);
					$this->add_notice( $response['message'], 'error' );
					return array(
						'result'	=> 'fail',
						'redirect'	=> '',
					);
				} else {
					$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$order_id.' created : ' . print_r( $response['data'], true ));
					$order->update_meta_data( 'wc_nimbbl_order_id', $response['data']['order_id']);
					$order->update_meta_data('wc_nimbbl_refresh_token', $response['data']['refresh_token']);
					$order->save();
					return array(
						'result'	=> 'success',
						'redirect'	=> add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)),
					);
				}
			}
		}
		
		protected function add_notice($message, $type = 'notice')
		{
			global $woocommerce;
			$type = in_array($type, array('notice', 'error', 'success'), true) ? $type : 'notice';
			if (function_exists('wc_add_notice')) {
				wc_add_notice($message, $type);
			} else {
				switch ($type) {
					case "error":
						$woocommerce->add_error($message);
						break;
					default:
						$woocommerce->add_message($message);
						break;
				}
			}
		}
		
		public function send_nimbbl_refund_order_data_to_api($auth_token, $nimbbl_order_id, $nimbbl_transaction_id, $amount) {
			$refund_data = array(
				'transaction_id' => $nimbbl_transaction_id,
				'refund_amount' => $amount,
			);
			$response_json = wp_remote_post( $this->api_url.'/refund', array(
				'body'	  => json_encode ( $refund_data ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth_token,
				),
			) );
			$response = json_decode( $response_json['body'], true );
			if(isset($response['error'])) {
				return array(
					'result'	=> 'fail',
					'message'	=> $response['error']['message']
				);
			} else {
				return array(
					'result'	=> 'success',
					'data'	=> $response
				);
			}
		}

		public function get_create_order_token($refresh_token) {
			$response = wp_remote_post($this->api_url.'/refresh-token', array(
				'body' => json_encode(array('refresh_token' => $refresh_token))
			));

			if (is_wp_error($response)){
				return array(
					'result' => 'fail'
				);
			}
			$formattedResponse = json_decode($response['body'], true);
			if(isset($formattedResponse['token'])){
				return array(
					'result' => 'success',
					'token' => $formattedResponse['token']
				);
			} else {
				return array(
					'result' => 'fail'
				);
			}
		}
		
		public function is_nimbbl_api_authorization() {
			
			$response_json = wp_remote_post( $this->api_url.'/generate-token', array(
				'body'	  => json_encode ( array('access_key' => $this->access_key, 'access_secret' => $this->secret_key ) ),
			));
			
			if ( is_wp_error( $response_json ) ) {
				return array(
					'result'	=> 'fail',
					'message'	=> __( 'Payment error: Failed to communicate with nimbbl server.', 'nimbbl-woocommerce' )
				);
			}
			$response = json_decode( $response_json['body'], true );
			if(isset($response['token'])) {
				return array(
					'result'	=> 'success',
					'token'	=> $response['token']
				);
			} else {
				return array(
					'result'	=> 'fail',
					'message'	=> __( 'Invalid access key or access secret', 'nimbbl-woocommerce' )
				);
			}
		}
		
		public function send_nimbbl_order_data_to_api($order_data, $auth_token) {
			$response_json = wp_remote_post( $this->api_url.'/create-order', array(
				'body'	  => json_encode ( $order_data ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth_token,
				),
			) );
			if ( is_wp_error( $response_json ) ) {
				return array(
					'result'	=> 'fail',
					'message'	=> __( 'Payment error: Failed to communicate with nimbbl server.', 'nimbbl-woocommerce' )
				);
			}
			$response = json_decode( $response_json['body'], true );
			if(isset($response['error'])) {
				if(isset($response['error']['nimbbl_merchant_message'])) {
					return array(
						'result'	=> 'fail',
						'message'	=> $response['error']['nimbbl_merchant_message']
					);
				} else {
					return array(
						'result'	=> 'fail',
						'message'	=> $response['error']
					);
				}
			} else {
				return array(
					'result'	=> 'success',
					'data'	=> $response
				);
			}
		}
		
		public function get_nimbbl_order_data_to_api($nimbbl_order_id, $auth_token) {
			
			$response_json = wp_remote_post( $this->api_url.'/order?order_id='.$nimbbl_order_id, array(
				'method'	  => 'GET',
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth_token,
				),
			) );
			if ( is_wp_error( $response_json ) ) {
				return array(
					'result'	=> 'fail',
					'message'	=> __( 'Payment error: Failed to communicate with nimbbl server.', 'nimbbl-woocommerce' )
				);
			}
			$response = json_decode( $response_json['body'], true );
			if(isset($response['error'])) {
				return array(
					'result'	=> 'fail',
					'message'	=> $response['error']['message']
				);
			} else {
				return array(
					'result'	=> 'success',
					'data'	=> $response
				);
			}
		}
		
		public function create_nimbbl_order_data($order) {
			
			$nimbbl_order_id = $order->get_meta('wc_nimbbl_order_id');
			
			$data = array(
				// 'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
				'order_id' => $nimbbl_order_id,
				'amount_before_tax' => doubleval($order->get_total() - $order->get_total_tax()),
				'tax' => doubleval($order->get_total_tax()),
				'total_amount' => doubleval($order->get_total()),
				'referrer_platform' => 'woocommerce',
				'referrer_platform_version' => '4.0.8',
				'invoice_id' => ''.$order->get_id(),
				'user' => $this->create_nimbbl_user_order_data($order),
				'shipping_address' => $this->create_nimbbl_shipping_order_data($order),
				'currency' => $order->get_currency(),
				'order_line_items' => $this->create_nimbbl_line_items_order_data($order),
				'order_from_ip' => $order->get_customer_ip_address(),
				'device_user_agent' => $order->get_customer_user_agent(),
				'callback_url' => WC()->api_request_url('nimbbl_process_response'),
				'callback_mode' => 'callback_url_redirect'
			);
			
			return $data;
		}
		
		public function create_nimbbl_user_order_data($order) {
			$data = array(
				'mobile_number' => $order->get_billing_phone(),
				'email' => $order->get_billing_email(),
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
			);
			
			return $data;
		}
		
		public function create_nimbbl_shipping_order_data($order) {
			$data = array(
				'street' => $order->get_billing_address_1(),
				'area' => empty($order->get_billing_address_2()) ? $order->get_billing_address_1() : $order->get_billing_address_2(),
				'city' => $order->get_billing_city(),
				'state' => $order->get_billing_state(),
				'pincode' => $order->get_billing_postcode(),
				'address_type' => 'residential',
			);
			
			return $data;
		}
		
		public function create_nimbbl_line_items_order_data($order) {
			$line_items = array();
			foreach ($order->get_items() as $item_id => $item) {
				$item_product = $item->get_product();
				$product = array(
					'sku_id' => $item_product->get_sku(),
					'title' => $item->get_name(),
					'description' => strip_tags($item_product->get_description()),
					'quantity' => ''.$item->get_quantity(),
					'rate' => doubleval($item->get_subtotal()),
					'amount_before_tax' => $item->get_total() - $item->get_total_tax(),
					'tax' => $item->get_total_tax(),
					'total_amount' => $item->get_total(),
					'image_url' => wp_get_attachment_url($item_product->get_image_id()),
				);
				
				array_push($line_items, $product);
			}
			
			return $line_items;
		}
		
		public function receipt_page( $order_id ) {
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$order_id.' Checkout initialised.');
				$order = wc_get_order( $order_id );
				$refreshToken = $order->get_meta('wc_nimbbl_refresh_token');
				$tokenResponse = $this->get_create_order_token($refreshToken);

				if ($tokenResponse['result'] === 'fail'){
					// add order note and customer message
					$order->add_order_note('Checkout cannot be launched as the order is older than 24 hours. Please update your cart.');
					$this->add_notice( __('Seems like your cart is stale, please update your cart items and retry payment.', 'nimbbl-woocommerce'), 'error' );
					exit;
				}

				$callback_url =  WC()->api_request_url('nimbbl_process_response');
				$data = array(
					'callback_url' => $callback_url,
					'token' => $tokenResponse['token'],
					'order_id' => $order->get_meta('wc_nimbbl_order_id')
				);
				$this->add_nimbbl_checkout_script($data);
				
				?>
				<div class="loading-payment-text" style="position: fixed;top: 0px;left: 0px;transform: none;text-align: center;border-radius: 10px;padding: 20px;right: 0px;margin: auto;max-width: 100%;color: #000;border: 2px solid #000;height: 100%;width: 100%;border: none;background-color: rgba(0, 0, 0, 0.8);bottom: 0px;right: 0px;margin: auto;"><p style="position: fixed;top: 50%;left: 50%;transform: translate(-50%, -50%);text-align: center;color: #F00;background: #fff;background-color: rgb(255, 255, 255);border-radius: 10px;padding: 20px;right: 0px;margin: auto;max-width: 785px;background-color: #f6f3f1;color: #000;border: 2px solid #000;"><?php echo __( 'Do not refresh, you will be redirected to the Nimbbl checkout.', 'nimbbl-woocommerce' ); ?></p></div>
				<?php
		}
		
		public function add_nimbbl_checkout_script ($data) {
			wp_enqueue_script('nimbbl_wc_script', plugins_url('assets/js/nimbbl.js', __FILE__), array(), null, array('strategy' => 'defer'));
			wp_localize_script('nimbbl_wc_script', 'nimbbl_wc_checkout_vars', $data);
		}

		
		
		public function process_webhook() {
			$response = file_get_contents('php://input');
			$webhook_data = json_decode($response, true);
			list($id_order, ) = explode('-', $webhook_data['order']['invoice_id']);
			$order_id = $webhook_data['nimbbl_order_id'];
			if($id_order) {
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$id_order.' Webhook received : ' . print_r( $webhook_data, true ) );
				$order = wc_get_order($id_order);
				$nimbbl_transaction_id = $webhook_data['nimbbl_transaction_id'];
				if(!empty($nimbbl_transaction_id) && isset($nimbbl_transaction_id)) {
					$verified = $this->verify_nimbbl_payment_signature($webhook_data, $order);
					$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Payload status : '.$webhook_data['status'].' for Order Id : '.$order_id );
					$orderStatus = $order->get_status();
					$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order Status for Webhook : '.$orderStatus);
					if($verified) {
						$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Signature check success, now updating order on woocommerce');
						$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Webhook Status '.$webhook_data['transaction']['status']);
						if ($webhook_data['transaction']['status'] === 'succeeded') {
							$order->payment_complete($nimbbl_transaction_id);
							$order->add_order_note('Your payment is processed as we have received \'succeeded\' from webhook.');
							$order->update_meta_data( 'wc_nimbbl_transaction_id', $nimbbl_transaction_id );
							$order->update_meta_data( 'wc_nimbbl_message', $webhook_data['message'] );
							$order->save();
						} elseif($webhook_data['transaction']['status'] === 'failed') {
							if($orderStatus === 'processing'){
								$order->add_order_note('Your payment is already processed from webhook.');
							}
							else {
								$order->update_status( 'failed' );
								$order->add_order_note('Your payment is cancelled as we have received \'failed\' from webhook.');
							}
							$order->update_meta_data( 'wc_nimbbl_transaction_id', $nimbbl_transaction_id );
							$order->update_meta_data( 'wc_nimbbl_message', $webhook_data['message'] );
							$order->save();
						}
					}
					else{
						$order->add_order_note('Signature mismatch occcurred in webhook.');
					}
				}
			}
		}

		public function format_amount($amount){
			$totalAmount = "";
			$inp = (string)$amount;
			$inp = str_replace(',','', $inp);
			$array = explode('.', $inp);
			$totalAmount = $totalAmount.$array[0];
			if(sizeof($array) == 1){
				$totalAmount = $totalAmount.".00";
			}
			else{
				$secondHalf = $array[1];
				$counter = 0;
				$totalAmount .=".";
				foreach(str_split($secondHalf) as $char){
					$counter++;
					$totalAmount .= $char;
					if($counter == 2){
						break;
					}
				}
				if(strlen($secondHalf) == 1){
				    $totalAmount .="0";
				}
			}
			$this->nimbb_plugin_log(WC_Log_Levels::DEBUG, 'Amount after format '.$totalAmount );
			return $totalAmount;
		}
		
		public function verify_nimbbl_payment_signature($data, $order) {
			$nimbbl_transaction_id = $data['nimbbl_transaction_id'];
			$nimbbl_signature = $data['transaction']['signature'];
			$signature_version = $data['transaction']['signature_version'];
			$signature_string = "";
			$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Payload Data : '.json_encode($data));
			if(empty($signature_version)){
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Signature Version is empty or null');
				return false;
			}
			if($signature_version == "v3") {
				$amount = $this->format_amount($data['transaction']['transaction_amount']);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'V3 Signature Generation');
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Invoice Id V3 : '.$data['order']['invoice_id']);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Id V3 : '.$nimbbl_transaction_id);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Amount V3 : '.$amount);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transation Currency V3 : '.$data['transaction']['transaction_currency']);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Status V3 : '.$data['transaction']['status']);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Type V3 : '.$data['transaction']['transaction_type']);
				$signature_string = $data['order']['invoice_id'].'|'.$nimbbl_transaction_id.'|'.$amount.'|'.$data['transaction']['transaction_currency'].'|'.$data['transaction']['status'].'|'.$data['transaction']['transaction_type'];
			}
			else {
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'V2 Signature Generation');
				$amount = sprintf("%.2f", $order->get_total());
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order Id V2 : '.$order->get_id());
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Id V2 : '.$nimbbl_transaction_id);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Amount V2 : '.$amount);
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transation Currency V2 : '.$order->get_currency());
				$signature_string = $order->get_id(). '|'. $nimbbl_transaction_id. '|'. $amount. '|'. $order->get_currency();
			}
			$generated_signature = hash_hmac('sha256', $signature_string, $this->secret_key);
			$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Generated Signature : '.$generated_signature);
			$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Nimbbl Signature : '.$nimbbl_signature);
			if($generated_signature == $nimbbl_signature) {
				$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Generated Signature matched with Payload Signature');
				return true;
			}
			$this->nimbb_plugin_log(WC_Log_Levels::ERROR, 'Generated Signature did not match with Payload Signature');
			return false;
		}
		
		public function process_response () {
			global $woocommerce;
			$responses = sanitize_text_field($_REQUEST['response']);
			$response_json = base64_decode($responses);
			$data = json_decode($response_json, true);
			if(isset($data['payload']['nimbbl_transaction_id']) && !empty($data['payload']['nimbbl_transaction_id'])) {
				$nimbbl_order_id = $data['payload']['nimbbl_order_id'];
				$response_auth = $this->is_nimbbl_api_authorization();
				if(is_array($response_auth) && $response_auth['result'] == 'fail') {
					$this->add_notice( __('Payment error: Invalid transaction.', 'nimbbl-woocommerce'), 'error' );
					wp_safe_redirect( wc_get_checkout_url() );
					exit;
				} else {
					$auth_token = $response_auth['token'];
					$response = $this->get_nimbbl_order_data_to_api($nimbbl_order_id, $auth_token);
					$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order Response from Nimbbl '.json_encode($response));
					if(is_array($response) && $response['result'] == 'failed') {
						$this->add_notice( __('Payment error: Invalid transaction.', 'nimbbl-woocommerce'), 'error' );
						wp_safe_redirect( wc_get_checkout_url() );
						exit;
					} else {
						list($id_order, ) = explode('-', $response['data']['invoice_id']);
						$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order '.$id_order.' Callback received : ' . print_r( $data['payload'], true ));
						$order = wc_get_order($id_order);
						$nimbbl_transaction_id = $data['payload']['nimbbl_transaction_id'];
						$nimbbl_signature = $data['payload']['nimbbl_signature'];
						$verified = $this->verify_nimbbl_payment_signature($data['payload'], $order);
						$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Payload status : '.$data['payload']['status'].' for Order Id : '.$nimbbl_order_id );
					    if($verified){
							$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Signature check success, now updating order on woocommerce');
							$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Transaction Callback Status '.$data['payload']['nimbbl_transaction_id']);
							$order->update_meta_data( 'wc_nimbbl_transaction_id', $data['payload']['nimbbl_transaction_id'] );
							$orderStatus = $order->get_status();
							$this->nimbb_plugin_log(WC_Log_Levels::INFO, 'Order Status for Callback : '.$orderStatus);
							if ($data['payload']['transaction']['status'] === 'succeeded') {
								$woocommerce->cart->empty_cart();
								$order->payment_complete($data['payload']['nimbbl_transaction_id']);
								$order->add_order_note('Your payment is processed as we have received \'succeeded\' from callback.');
								wp_safe_redirect( $this->get_return_url( $order ) );
								exit;
							}
							else if($data['payload']['transaction']['status'] === 'pending'){
								if($orderStatus === 'processing'){
									$order->add_order_note('Your payment is already processed from callback.');
									wp_safe_redirect( $this->get_return_url( $order ) );
									exit;
								}
								$order->update_status( 'failed' );
								$order->add_order_note('Your payment is cancelled as we have received \'pending\' from callback.');
								$this->add_notice(__('Your payment is cancelled as we have received \'pending\' from callback.', 'nimbbl-woocommerce'), 'error');
								wp_safe_redirect( wc_get_checkout_url() );
								exit;
							} 
							else {
								if($orderStatus === 'processing'){
									$order->add_order_note('Your payment is already processed from callback.');
									wp_safe_redirect( $this->get_return_url( $order ) );
									exit;
								}
								$order->update_status( 'failed' );
								$order->add_order_note('Your payment is cancelled as we have received \'failed\' from callback.');
								$this->add_notice( __(' Your payment is cancelled.', 'nimbbl-woocommerce'), 'error' );
								wp_safe_redirect( wc_get_checkout_url() );
								exit;
							}
						}
						else {
							$order->add_order_note('Signature mismatch occcurred in callback.');
							wp_safe_redirect( wc_get_checkout_url() );
							exit;
						}
					}
				}
			} else {
				$this->add_notice( __(' Your payment is cancelled.', 'nimbbl-woocommerce'), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
		}
		
		public function order_details_after_order_table($order)
		{
			$payment_method = $order->get_payment_method();
			if ('nimbbl_payment_gateway' == $payment_method) {
			?>
            <h2 style="margin-top:20px;" class="woocommerce-order-details__title"><?php _e('Nimbbl Payment info', 'nimbbl-woocommerce'); ?></h2>
            <table class="<?php echo esc_attr($this->id); ?>_table" cellpadding="0" cellspacing="0">
                <tr>
                    <td><?php _e('Nimbbl Order Id', 'nimbbl-woocommerce'); ?>:</td>
                    <td><?php echo esc_attr($order->get_meta('wc_nimbbl_order_id')); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Nimbbl Transaction Id', 'nimbbl-woocommerce'); ?>:</td>
                    <td><?php echo esc_attr($order->get_meta('wc_nimbbl_transaction_id')); ?></td>
                </tr>
            </table>
            <?php
			}
		}
		
		public function admin_order_data_after_order_details($order)
        {
            $payment_method = $order->get_payment_method();
            if ('nimbbl_payment_gateway' == $payment_method) {
                ob_start();
                echo "<h3>" . __('Nimbbl Payment info', 'nimbbl-woocommerce') . "</h3>";
                echo "<p>" . __('Nimbbl Order Id', 'nimbbl-woocommerce') . " : " . esc_attr($order->get_meta('wc_nimbbl_order_id')) . "</p>";
                echo "<p>" . __('Nimbbl Transaction Id', 'nimbbl-woocommerce') . " : " . esc_attr($order->get_meta('wc_nimbbl_transaction_id')) . "</p>";
            }
		}
		
		public function nimbb_plugin_log( $level, $message ) {
			if($this->debug_mode) {
				$log = wc_get_logger();
				$log->log( $level, $message, array( 'source' => 'nimbbl-woocommerce-log') );
			}
		}
	}
	
	function wc_nimbbl_gateway_plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimbbl_payment_gateway' ) . '">' . __( 'Configure', 'nimbbl-woocommerce' ) . '</a>'
		);
		return array_merge( $plugin_links, $links );
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_nimbbl_gateway_plugin_links' );
	
	function wc_nimbbl_payment_gateway_class( $gateways ) {
		$gateways[] = 'WC_Nimbbl_Payment_Gateway';
		return $gateways;
	}
	add_filter( 'woocommerce_payment_gateways', 'wc_nimbbl_payment_gateway_class' );

	function add_type_attributes($tag, $handle, $src) {
		if ('nimbbl_wc_script' !== $handle) {
			return $tag;
		}
		$tag = '<script defer type="module" src="'.esc_url($src).'"></script>';
		return $tag;
	}
	add_filter('script_loader_tag', 'add_type_attributes', 10, 3);
}
add_action('plugins_loaded', 'wc_nimbbl_payment_gateway_init');
