<?php 
/**
 *  WCMPp Vendor Admin Dashboard - Vendor WP-Admin Dashboard Pages
 * 
 * @version	2.2.0
 * @package WCMp
 * @author  Dualcube
 */

Class WCMp_Admin_Dashboard { 
	
	private $wcmp_vendor_order_page;
	
	function __construct() { 
		
		// Add Shop Settings page 
		add_action( 'admin_menu', array( $this, 'vendor_dashboard_pages') ); 
		
		add_action( 'woocommerce_product_options_shipping', array( $this, 'wcmp_product_options_shipping'));
		
		add_action(	'save_post', array( &$this, 'process_vendor_data' ) );
		
		// Init export functions
    $this->export_csv();
    
    // Init submit comment
    $this->submit_comment();
    
    $this->vendor_withdrawl();
    
    $this->export_vendor_orders_csv();
	}
	
	/**
	 * Vendor Commission withdrawl
	 */
	function vendor_withdrawl() {
		global $WCMp;
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			if( isset( $_POST[ 'vendor_get_paid' ] ) ) {
				
				$vendor = get_wcmp_vendor(get_current_user_id());
				
				//check unpaid commission threshold
				$total_vendor_due = $vendor->wcmp_vendor_get_total_amount_due();
				$get_vendor_thresold = 0;
				if(isset($WCMp->vendor_caps->payment_cap['commission_threshold']))$get_vendor_thresold = (float)$WCMp->vendor_caps->payment_cap['commission_threshold'];
				if($get_vendor_thresold > $total_vendor_due) return;
				$transaction_data = array();
				
				
				if(!empty($_POST['check_order_number'])) {
					foreach($_POST['check_order_number'] as $commission) {
						$commisssion_status = get_post_meta( $commission, '_paid_status', true );
						if($commisssion_status == 'paid') continue;
						$WCMp_Commission = new WCMp_Commission();
						$commission_data = $WCMp_Commission->get_commission( $commission );
						$commission_order_id = get_post_meta( $commission, '_commission_order_id', true );
						$vendor_shipping = get_post_meta($commission, '_shipping', true);
						$vendor_tax = get_post_meta($commission, '_tax', true);
						
						$order = new WC_Order ( $commission_order_id );
						$due_vendor = $vendor->wcmp_get_vendor_part_from_order($order, $vendor->term_id);
						
						if($vendor_shipping) $vendor_shipping = $due_vendor['shipping'];
						if($vendor_tax) $vendor_tax = $due_vendor['tax'];
						
						$vendor_due = 0;
						$vendor_due = (float)$commission_data->amount  + (float)$vendor_shipping + (float)$vendor_tax;
						
						if($commission_data->vendor->term_id != $vendor->term_id ) continue;
						
						if(array_key_exists($commission_data->vendor->term_id, $transaction_data)) {
							$commission_totals[ $commission_data->vendor->term_id ]['amount'] += apply_filters( 'paypal_masspay_amount', $vendor_due, $commission_order_id, $commission_data->vendor->term_id);
						} else {							
							$commission_totals[ $commission_data->vendor->term_id ]['amount'] = apply_filters( 'paypal_masspay_amount', $vendor_due, $commission_order_id, $commission_data->vendor->term_id);
						}
						$transaction_data[$commission_data->vendor->term_id]['commission_detail'][$commission] = $commission_order_id;
						$transaction_data[$commission_data->vendor->term_id]['amount'] = $commission_totals[ $commission_data->vendor->term_id ]['amount'];
					}
						
					$transfer_charge  = $WCMp->vendor_caps->payment_cap['commission_transfer'];
					if(isset($transfer_charge)) {
						$no_of_thresold_orders =  $WCMp->vendor_caps->payment_cap['no_of_orders'];
						if(!$no_of_thresold_orders) $no_of_thresold_orders = 0;
						$no_of_paid_transaction = $no_of_paid_transaction = count($WCMp->transaction->get_transactions($vendor->term_id));//20;//count($vendor->wcmp_vendor_transaction());
						if((int)$no_of_paid_transaction > (int)$no_of_thresold_orders) { 
							$commission_totals[ $vendor->term_id ]['transfer_charge'] = $transfer_charge;
							$transaction_data[ $vendor->term_id ]['transfer_charge'] = $transfer_charge;
						}
					}
					$commission_payment_mode = get_user_meta($vendor->id, '_vendor_payment_mode', true);
					if($commission_payment_mode == 'paypal_masspay') {
				
						// Set info for all payouts
						$currency = get_woocommerce_currency();
						$payout_note = sprintf( __( 'Total commissions earned from %1$s as at %2$s on %3$s', $WCMp->text_domain ), get_bloginfo( 'name' ), date( 'H:i:s' ), date( 'd-m-Y' ) );
						
						$commissions_data = array();
						if(!empty($commission_totals)) {
							foreach( $commission_totals as $vendor_id => $total ) {
								
								if(!isset($total['amount'])) $total['amount'] = 0;
								if(isset($total['transfer_charge'])) $total_payable = $total['amount'] + $total['transfer_charge'];
								else $total_payable = $total['amount'];
								
								// Get vendor data
								$vendor_paypal_email = get_user_meta($vendor->id, '_vendor_paypal_email', true);
								// Set vendor recipient field
								if( isset( $vendor_paypal_email ) && strlen( $vendor_paypal_email ) > 0 ) {
									$recipient = $vendor_paypal_email;
									$commissions_data[] = array( 
											'recipient' => $recipient,
											'total' => $total_payable,
											'currency' => $currency,
											'vendor_id' =>$vendor_id,
											'payout_note' =>$payout_note
									);
								}
								
							}
							
							$result = $WCMp->paypal_masspay->call_masspay_api($commissions_data);
							
							if($result) {
								// create a new transaction by vendor
								if(!empty($transaction_data))	$transaction_id = $WCMp->transaction->insert_new_transaction($transaction_data, 'wcmp_completed', 'paypal_masspay', $result);
								$email_admin = WC()->mailer()->emails['WC_Email_Admin_Widthdrawal_Request'];
								$email_admin->trigger( $transaction_id, $vendor->term_id);
							}
						}
					} else if($commission_payment_mode == 'direct_bank') {
						if(!empty($commission_totals)) {
							// create a new transaction by vendor
							if(!empty($transaction_data)) {
								$transaction_id = $WCMp->transaction->insert_new_transaction($transaction_data, 'wcmp_processing', 'direct_bank');
								foreach($commission_totals as $vendor_id => $total) {
									$email_vendor = WC()->mailer()->emails['WC_Email_Vendor_Direct_Bank'];
									$email_vendor->trigger( $transaction_id, $vendor_id );      
									$email_admin = WC()->mailer()->emails['WC_Email_Admin_Widthdrawal_Request'];
									$email_admin->trigger( $transaction_id, $vendor_id );
								}
							}
						}
					}
					
					do_action('wcmp_vendor_commission_payment_mode');
					
					$wcmp_pages = get_option('wcmp_pages_settings_name');
					$get_transaction_thankyou_page = $wcmp_pages['vendor_transaction_thankyou'];
					if(isset($get_transaction_thankyou_page)) {
						if(isset($transaction_id)) $location = add_query_arg('transaction_id', $transaction_id, get_permalink( $get_transaction_thankyou_page ));
						else  $location = get_permalink( $get_transaction_thankyou_page );
						wp_safe_redirect( $location );
						exit;
					}
				}
			}
		}		
	}
	
	
	/**
	 * Export CSV from vendor dasboard page
	 *
	 * @access public
	 * @return void
	*/	
	function export_csv() {
		global $WCMp;
		
		$user_id = get_current_user_id();
		
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			
			if(isset($_POST['export_transaction'])) {
				$transaction_details = array();
				if(!empty($_POST['transaction_ids'])) {
					$date = date( 'd-m-Y' );
					$filename = 'TransactionReport-' . $date . '.csv';
					header("Pragma: public");
					header("Expires: 0");
					header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
					header("Content-Type: application/force-download");
					header("Content-Type: application/octet-stream");
					header("Content-Type: application/download");
					header("Content-Disposition: attachment;filename={$filename}");
					header("Content-Transfer-Encoding: binary");
					header("Content-Type: charset=UTF-8");
					
					$headers = array(
						'date'   => __( 'Date', $WCMp->text_domain),
						'trans_id' => __( 'Transaction ID', $WCMp->text_domain ),
						'order_ids'    => __( 'Order IDs', $WCMp->text_domain ),
						'mode'    => __( 'Mode', $WCMp->text_domain ),
						'commission' => __( 'Commission', $WCMp->text_domain ),
						'fee'    => __( 'Fee', $WCMp->text_domain ),
						'credit'   => __( 'Credit', $WCMp->text_domain ),
					);
					$order_ids = '';
					if(!empty($_POST['transaction_ids'])) {
						foreach($_POST['transaction_ids'] as $transaction_id) {
							$commission_details = get_post_meta($transaction_id, 'commission_detail', true);
							if(!empty($commission_details)) {
								$is_first = false;
								foreach($commission_details as $commission_id => $order_id) {
									if($is_first) $order_ids .= ', ';
									$order_ids .= '#'.$order_id;
									$is_first = true;
								}
							} 
							
							$transfer_charge =  get_post_meta($transaction_id, 'transfer_charge', true);	
							$transaction_amt = get_post_meta($transaction_id, 'amount', true);
							$transaction_commission =  $transfer_charge + $transaction_amt;
							
							$mode = get_post_meta($transaction_id, 'transaction_mode', true);							
							if($mode == 'paypal_masspay') $transaction_mode = __('PayPal',  $WCMp->text_domain);
							else if($mode == 'direct_bank') $transaction_mode = __('Direct Bank Transfer',  $WCMp->text_domain);
							
							$order_datas[] = array(
								'date' => get_the_date( 'd-m-Y', $transaction_id ),
								'trans_id' => '#'.$transaction_id,
								'order_ids' => $order_ids,
								'mode' => $transaction_mode,
								'commission' => $transaction_commission,
								'fee' => $transfer_charge,
								'credit' => $transaction_amt,
							); 
						}
					}
						
					
					// Initiate output buffer and open file
					ob_start();
					$file = fopen( "php://output", 'w' );
					
					// Add headers to file
					fputcsv( $file, $headers );
					// Add data to file
					if(!empty($order_datas)) {
						foreach ( $order_datas as $order_data ) {
							fputcsv( $file, $order_data );
						}
					} else {
						fputcsv( $file, array( __( 'Sorry. no transaction data is available upon your selection', $WCMp->text_domain ) ) );
					}
				
					// Close file and get data from output buffer
					fclose( $file );
					$csv = ob_get_clean();
				
					// Send CSV to browser for download
					echo $csv;
					die();
				}
			}
			if( $WCMp->vendor_caps->vendor_capabilities_settings('is_order_csv_export') && !empty( $_POST[ 'wcmp_stat_export_submit' ] ) ) {
				$user = wp_get_current_user();
				$vendor = get_wcmp_vendor($user->ID);
				$vendor_orders = array_unique($vendor->get_orders());
				if(!empty($vendor_orders)) $this->generate_csv($vendor_orders, $vendor);	
			}
		}
	}
	
	public function generate_csv($customer_orders, $vendor) {
		global $WCMp;
		$order_datas = array();
		$index = 0;
		$date = date( 'd-m-Y' );
		$filename = 'SalesReport-' . $date . '.csv';
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");
		
		$headers = array(
			'order'   => __( 'Order', $WCMp->text_domain),
			'date_of_purchase' => __( 'Date of Purchase', $WCMp->text_domain ),
			'time_of_purchase' => __( 'Time Of Purchase', $WCMp->text_domain ),
			'vendor_name' => __( 'Vendor Name', $WCMp->text_domain ),
			'product' => __( 'Items bought', $WCMp->text_domain ),
			'qty' => __( 'Quantity', $WCMp->text_domain ),
			'discount_used' => __( 'Discount Used', $WCMp->text_domain ),
			'payment_system' => __( 'Payment System', $WCMp->text_domain ),
			'buyer_name'    => __( 'Customer Name', $WCMp->text_domain ),
			'buyer_email'    => __( 'Customer Email', $WCMp->text_domain ),
			'buyer_contact'    => __( 'Customer Contact', $WCMp->text_domain ),
			'billing_address' => __( 'Billing Address Details', $WCMp->text_domain ),
			'shipping_address' => __( 'Shipping Address Details', $WCMp->text_domain ),
			'order_status'    => __( 'Order Status', $WCMp->text_domain ),
			'tax'   => __( 'Tax', $WCMp->text_domain ),
			'shipping'     => __( 'Shipping', $WCMp->text_domain ),
			'commission_share'   => __( 'Commission Share', $WCMp->text_domain ),
		);
		
		if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('is_show_email') ) {
			unset( $headers[ 'buyer_name' ] );
		}
		if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_dtl') ) {
			unset( $headers[ 'buyer_email' ] );
			unset( $headers[ 'buyer_contact' ] );
		}
		if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_billing') ) {
			unset( $headers[ 'billing_address' ] );
		}
		if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_shipping') ) {
			unset( $headers[ 'shipping_address' ] );
		}
		
		if($vendor) {
			if(!empty($customer_orders)) {
				foreach ( $customer_orders as $commission_id => $customer_order ) {
					$order = new WC_Order($customer_order);
					$vendor_items = $vendor->get_vendor_items_from_order($customer_order, $vendor->term_id);
					$item_names = '';
					$item_qty = 0;
					if ( sizeof( $vendor_items ) > 0 ) {
						foreach( $vendor_items as $item ) {
							$item_names .= $item['name']. ', ';
							$item_qty++; 
						}
						
						//coupons count
						$coupon_used = '';
						$coupons = $order->get_items( 'coupon' );
						foreach ( $coupons as $coupon_item_id => $item ) {
							$coupon = new WC_Coupon( trim( $item['name'] ));
							$coupon_post = get_post($coupon->id);
							$author_id = $coupon_post->post_author;
							if($vendor->id == $author_id) {
								$coupon_used .= $item['name'] .', ';
							} 
						}
						
						// Formatted Addresses
						$formatted_billing_address = apply_filters( 'woocommerce_order_formatted_billing_address', array(
							'address_1'     => $order->billing_address_1,
							'address_2'     => $order->billing_address_2,
							'city'          => $order->billing_city,
							'state'         => $order->billing_state,
							'postcode'      => $order->billing_postcode,
							'country'       => $order->billing_country
						), $order );
						$formatted_billing_address = WC()->countries->get_formatted_address( $formatted_billing_address );
						
						$formatted_shipping_address = apply_filters( 'woocommerce_order_formatted_shipping_address', array(
							'address_1'     => $order->shipping_address_1,
							'address_2'     => $order->shipping_address_2,
							'city'          => $order->shipping_city,
							'state'         => $order->shipping_state,
							'postcode'      => $order->shipping_postcode,
							'country'       => $order->shipping_country
						), $order );
						$formatted_shipping_address = WC()->countries->get_formatted_address( $formatted_shipping_address );
						
						$customer_name = $order->billing_first_name . ' ' . $order->billing_last_name;
						$customer_email = $order->billing_email;
						$customer_phone = $order->billing_phone;
						
						$order_datas[$index] = array(
							'order' => '#'. $customer_order,
							'date_of_purchase' => date_i18n( 'd-m-Y', strtotime($order->order_date)),
							'time_of_purchase' => date_i18n( 'H', strtotime($order->order_date)) . ' : ' . date_i18n( 'i', strtotime($order->order_date)),
							'vendor_name' => $vendor->user_data->display_name,
							'product' => $item_names,
							'qty' => $item_qty,
							'discount_used' => apply_filters('wcmp_export_discount_used_in_order', $coupon_used),
							'payment_system' => $order->payment_method_title,
							'buyer_name' => $customer_name,
							'buyer_email' => $customer_email,
							'buyer_contact' => $customer_phone,
							'billing_address' => str_replace('<br/>', ', ', $formatted_billing_address),
							'shipping_address' => str_replace('<br/>', ', ', $formatted_shipping_address),
							'order_status' => $order->get_status(),
							'tax' => get_post_meta($commission_id, '_tax', true),
							'shipping' => get_post_meta($commission_id, '_shipping', true),
							'commission_share' => get_post_meta($commission_id, '_commission_amount', true),
						);
						if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('is_show_email') ) {
							unset( $order_datas[$index][ 'buyer_name' ] );
						}
						if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_dtl') ) {
							unset( $order_datas[$index][ 'buyer_email' ] );
							unset( $order_datas[$index][ 'buyer_contact' ] );
						}
						if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_billing') ) {
							unset( $order_datas[$index][ 'billing_address' ] );
						}
						if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('show_customer_shipping') ) {
							unset( $order_datas[$index][ 'shipping_address' ] );
						}
						$index++;
					}
				}
			}
		}
		// Initiate output buffer and open file
		ob_start();
		$file = fopen( "php://output", 'w' );

		// Add headers to file
		fputcsv( $file, $headers );
		// Add data to file
		foreach ( $order_datas as $order_data ) {
			if ( ! $WCMp->vendor_caps->vendor_capabilities_settings('is_order_show_email') ) {
				unset( $order_data[ 'buyer' ] );
			}
			fputcsv( $file, $order_data );
		}
	
		// Close file and get data from output buffer
		fclose( $file );
		$csv = ob_get_clean();
	
		// Send CSV to browser for download
		echo $csv;
		die();
	}
	
	
	/**
	 * Submit Comment 
	 *
	 * @access public
	 * @return void
	*/	
	function submit_comment() {
		global $WCMp;
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			if ( !empty( $_POST[ 'wcmp_submit_comment' ] ) ) {
				
				$user = wp_get_current_user();
				$user = $user->ID;
				
				// Don't submit empty comments
				if ( empty( $_POST[ 'comment_text' ] ) ) {
					return false;
				}

				// Only submit if the order has the product belonging to this vendor
				$order = new WC_Order ( $_POST['order_id'] );
				$comment = esc_textarea( $_POST[ 'comment_text' ] );
				$order->add_order_note( $comment, 1 );
			}
		}
	}
	
	function vendor_dashboard_pages() {
		global $WCMp;
		$user = wp_get_current_user();
		$vendor = get_wcmp_vendor($user->ID);
		if($vendor) {
			$hook = add_menu_page( __( 'Orders', $WCMp->text_domain ), __( 'Orders', $WCMp->text_domain ), 'read', 'dc-vendor-orders', array( $this, 'wcmp_vendor_orders_page' ) );
			add_action( "load-$hook", array( $this, 'add_order_page_options' ) );
			if ($WCMp->vendor_caps->vendor_payment_settings('give_shipping') ) {
				$give_shipping_override = get_user_meta( $user->ID, '_vendor_give_shipping', true ); 
				if(!$give_shipping_override) {
					add_menu_page( __( 'Shipping', $WCMp->text_domain ), __( 'Shipping', $WCMp->text_domain ), 'read', 'dc-vendor-shipping', array( $this, 'shipping_page' ) );
				}
			}
		}
	}
	
	/**
	 * HTML setup for the Orders Page 
	 */
	public static function shipping_page() {
		global $WCMp;
		
		$vendor_user_id = get_current_user_id();
		$vendor_data = get_wcmp_vendor($vendor_user_id);
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			if(isset( $_POST['vendor_shipping_data'] )) {
				$fee = 0;
				$vendor_shipping_data = get_user_meta($vendor_user_id, 'vendor_shipping_data', true);
				$cost = isset($_POST['vendor_shipping_data']['shipping_amount']) ? stripslashes($_POST['vendor_shipping_data']['shipping_amount']) : 0;
				$international_cost = isset($_POST['vendor_shipping_data']['international_shipping_amount']) ? stripslashes($_POST['vendor_shipping_data']['international_shipping_amount']) : 0;
				$fee = isset($_POST['vendor_shipping_data']['handling_amount']) ? $_POST['vendor_shipping_data']['handling_amount'] : 0;
				if(!empty($cost)) {
					$shipping_updt = true; 
					$dc_flat_rates = array();
					
					if(!term_exists($vendor_data->user_data->user_login, 'product_shipping_class')) {
						$shipping_term = wp_insert_term( $vendor_data->user_data->user_login, 'product_shipping_class' );
						if(!is_wp_error($shipping_term)) {
							update_user_meta($vendor_user_id, 'shipping_class_id', $shipping_term['term_id']);
							add_woocommerce_term_meta($shipping_term['term_id'], 'vendor_id', $vendor_user_id); 
							add_woocommerce_term_meta($shipping_term['term_id'], 'vendor_shipping_origin',  $_POST['vendor_shipping_data']['ship_from']);
						} else {
							$shipping_updt = false;
						}
					} else {
						$shipping_class_id = get_user_meta($vendor_user_id, 'shipping_class_id', true);
						update_woocommerce_term_meta($shipping_class_id, 'vendor_shipping_origin',  $_POST['vendor_shipping_data']['ship_from']);
					}
					
					if ( version_compare( WC_VERSION, '2.4.0', '>' ) ) {
						$woocommerce_flat_rate_settings = get_option('woocommerce_flat_rate_settings');
						$woocommerce_flat_rate_settings['class_cost_' . sanitize_title($vendor_data->user_data->user_login)] = $cost;
						update_option('woocommerce_flat_rate_settings', $woocommerce_flat_rate_settings);
						
						$woocommerce_international_delivery_settings = get_option('woocommerce_international_delivery_settings');
						$woocommerce_international_delivery_settings['class_cost_' . sanitize_title($vendor_data->user_data->user_login)] = $international_cost;
						update_option('woocommerce_international_delivery_settings', $woocommerce_international_delivery_settings);
						
					} else {
						$woocommerce_flat_rates = get_option('woocommerce_flat_rates');
						$woocommerce_flat_rates[sanitize_title($vendor_data->user_data->user_login)] = array('cost' => $cost, 'fee' => $fee);
						update_option('woocommerce_flat_rates', $woocommerce_flat_rates);
					}
					update_user_meta($vendor_user_id, 'vendor_shipping_data', $_POST['vendor_shipping_data']);
					if($shipping_updt) {
						echo '<div class="updated">'.__( "Shipping Data Updated.", $WCMp->text_domain ).'</div>';
					} else {
						echo '<div class="error">'.__( "Shipping Data Not Updated.", $WCMp->text_domain ).'</div>';
						delete_user_meta($vendor_user_id, 'vendor_shipping_data');
					}
				} else {
					echo '<div class="error">'.__( "Specify Shipping Amount.", $WCMp->text_domain ).'</div>';
				}
			}
		} 
		
		$vendor_shipping_data = get_user_meta($vendor_user_id, 'vendor_shipping_data', true);		
		?>
		<div class="wrap">

			<div id="icon-woocommerce" class="icon32 icon32-woocommerce-reports"><br/></div>
			<h2><?php _e( 'Shipping', $WCMp->text_domain ); ?></h2>

			<form name="vendor_shipping_form" method="post">
				<table>
					<tbody>
						<?php
						if ( version_compare( WC_VERSION, '2.4.0', '>' ) ) { ?>
							<tr>
								<td><label><?php _e('Enter Shipping Amount for "Flat Rate" :', $WCMp->text_domain); ?></label></td>
								<td><input name="vendor_shipping_data[shipping_amount]" type="text" step="0.01" value='<?php echo isset($vendor_shipping_data['shipping_amount']) ?  $vendor_shipping_data['shipping_amount'] :  ''; ?>' /></td>
							</tr>
							<tr><td></td><td><?php _e( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>. Supports the following placeholders: <code>[qty]</code> = number of items, <code>[cost]</code> = cost of items,<br><code>[fee percent="10" min_fee="20"]</code> = Percentage based fee.', $WCMp->text_domain );?> <br><br></td></tr>
							<tr>
								<td><label><?php _e('Enter Shipping Amount for "International Flat Rate" :', $WCMp->text_domain); ?></label></td>
								<td><input name="vendor_shipping_data[international_shipping_amount]" type="text" step="0.01" value='<?php echo isset($vendor_shipping_data['international_shipping_amount']) ?  $vendor_shipping_data['international_shipping_amount'] :  ''; ?>' /></td>
							</tr>
							<tr><td></td><td><?php _e( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>. Supports the following placeholders: <code>[qty]</code> = number of items, <code>[cost]</code> = cost of items,<br><code>[fee percent="10" min_fee="20"]</code> = Percentage based fee.', $WCMp->text_domain );?> <br><br></td></tr>
						<?php } else { ?>
							<tr>
							<td><label><?php _e('Enter Shipping Amount :', $WCMp->text_domain); ?></label></td>
							<td><input name="vendor_shipping_data[shipping_amount]" type="text" step="0.01" value='<?php echo isset($vendor_shipping_data['shipping_amount']) ?  $vendor_shipping_data['shipping_amount'] :  ''; ?>' /></td>
						</tr>
							<tr>
								<td><label><?php _e('Enter Handling Fee :', $WCMp->text_domain); ?></label></td>
								<td><input name="vendor_shipping_data[handling_amount]" type="number" step="0.01" value='<?php echo isset($vendor_shipping_data['handling_amount']) ?  $vendor_shipping_data['handling_amount'] :  '';?>' /></td>
							</tr>
						<?php } ?>
						<tr>
							<td><label><?php _e('Ship from :', $WCMp->text_domain); ?></label></td>
							<td><input name="vendor_shipping_data[ship_from]" type="text" value="<?php echo isset($vendor_shipping_data['ship_from']) ? $vendor_shipping_data['ship_from'] :  ''; ?>" /></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
			<br class="clear"/>
		</div>
		<?php
	}
	
	function process_vendor_data($post_id) {
		$post = get_post( $post_id );
		if( $post->post_type == 'product' ) {
			if(isset($_POST['dc_product_shipping_class']))	wp_set_object_terms( $post_id, (int)wc_clean( $_POST['dc_product_shipping_class'] ), 'product_shipping_class', false );
		}
	}
	
	/**
	 *
	 *
	 * @param unknown $status
	 * @param unknown $option
	 * @param unknown $value
	 *
	 * @return unknown
	 */
	public static function set_table_option( $status, $option, $value )	{
		if ( $option == 'orders_per_page' ) {
			return $value;
		}
	}


	/**
	 * Add order page options
	 * Defined cores in Vendor Order Page class
	 */
	public function add_order_page_options() {
		global $WCMp;
		$args = array(
			'label'   => 'Rows',
			'default' => 10,
			'option'  => 'orders_per_page'
		);
		add_screen_option( 'per_page', $args );
		
		$WCMp->load_class( 'vendor-order-page' );
		$this->wcmp_vendor_order_page = new WCMp_Vendor_Order_Page();
	}


	/**
	 * Generate Orders Page view 
	 */
	public function wcmp_vendor_orders_page(){
		global $woocommerce, $WCMp;
		
		$this->wcmp_vendor_order_page->wcmp_prepare_order_page_items();

		?>
		<div class="wrap">

			<div id="icon-woocommerce" class="icon32 icon32-woocommerce-reports"><br/></div>
			<h2><?php _e( 'Orders', $WCMp->text_domain ); ?></h2>

			<form id="posts-filter" method="get">

				<input type="hidden" name="page" value="dc-vendor-orders"/>
				<?php $this->wcmp_vendor_order_page->display(); ?>

			</form>
			<div id="ajax-response"></div>
			<br class="clear"/>
		</div>
		<?php 
	}
	
	function wcmp_product_options_shipping() { 
		global $WCMp, $post;
		
		$classes = get_the_terms( $post->ID, 'product_shipping_class' );
		if ( $classes && ! is_wp_error( $classes ) ) {
			$current_shipping_class = current( $classes )->term_id;
		} else {
			$current_shipping_class = false;
		}
		$product_shipping_class = get_terms( 'product_shipping_class', array('hide_empty' => 0));
		$current_user_id = get_current_user_id();
		$option = '<option value="-1">' . __( "No shipping class", $WCMp->text_domain ) . '</option>';
		
		if(!empty($product_shipping_class)) {
			$shipping_option_array = array();
			$vednor_shipping_option_array = array();
			foreach($product_shipping_class as $product_shipping) {
				$vendor_shipping_data = get_user_meta($current_user_id, 'vendor_shipping_data', true);		
				if(is_user_wcmp_vendor($current_user_id) ) {
					
					$vendor_id = get_woocommerce_term_meta( $product_shipping->term_id, 'vendor_id', true );
					if(!$vendor_id)	{
						//$vednor_shipping_option_array[$product_shipping->term_id] = $product_shipping->name;						
					} 
					else {
						if($vendor_id == $current_user_id) {						
							$vednor_shipping_option_array[$product_shipping->term_id] = $product_shipping->name;
						}
					}
				} else {
					
					$shipping_option_array[$product_shipping->term_id] = $product_shipping->name;
				}
			}
			if(!empty($vednor_shipping_option_array)) {
				$shipping_option_array = array();
				$shipping_option_array = $vednor_shipping_option_array;
			}
			if(!empty($shipping_option_array)) {
				foreach($shipping_option_array as $shipping_option_array_key => $shipping_option_array_val) {
					if($current_shipping_class && $shipping_option_array_key == $current_shipping_class) {
						$option .= '<option selected value="'.$shipping_option_array_key.'">'.$shipping_option_array_val.'</option>';
					} else {
						$option .= '<option value="'.$shipping_option_array_key.'">'.$shipping_option_array_val.'</option>';
					}
				}
			}
		}
		?>
		<p class="form-field dimensions_field">
		
			<label for="product_shipping_class">Shipping class</label> 
			<select class="select short" id="dc_product_shipping_class" name="dc_product_shipping_class">
				<?php echo $option; ?>
			</select>
			<img class="help_tip" src="<?php echo $WCMp->plugin_url . 'assets/images/help.png'; ?>" height="16" width="16">
		</p>
		<?php
	}
	
	public function export_vendor_orders_csv() {
		global $WCMp, $wpdb;
		if($_SERVER['REQUEST_METHOD'] == 'POST') {			
			if(isset($_POST['wcmp_submit_order_total_hidden']) && !empty($_POST['wcmp_submit_order_total_hidden'])) {
				$user = wp_get_current_user();
				$vendor = get_wcmp_vendor($user->ID);
				$order_data = array();
				if(isset($_POST['select_all']) && !empty($_POST['select_all'])) {
					foreach($_POST['select_all'] as $order_id => $value) {
						$customer_orders = $wpdb->get_results( "SELECT DISTINCT commission_id from `{$wpdb->prefix}wcmp_vendor_orders` where vendor_id = ".$vendor->id." AND order_id = ".$order_id, ARRAY_A);
						$commission_id = $customer_orders[0]['commission_id'];
						$order_data[$commission_id] = $order_id ;
					}
				}
				if(isset($_POST['select_processing'])) {
					foreach($_POST['select_processing'] as $order_idd => $value) {
						$customer_orders = $wpdb->get_results( "SELECT DISTINCT commission_id from `{$wpdb->prefix}wcmp_vendor_orders` where vendor_id = ".$vendor->id." AND order_id = ".$order_idd, ARRAY_A);
						$commission_id = $customer_orders[0]['commission_id'];
						$order_data[$commission_id] = $order_idd ;
					}
				}
				if(isset($_POST['select_completed'])) {
					foreach($_POST['select_completed'] as $order_iddd => $value) {
						$customer_orders = $wpdb->get_results( "SELECT DISTINCT commission_id from `{$wpdb->prefix}wcmp_vendor_orders` where vendor_id = ".$vendor->id." AND order_id = ".$order_iddd, ARRAY_A);
						$commission_id = $customer_orders[0]['commission_id'];
						$order_data[$commission_id] = $order_iddd ;
					}
				}
				if(!empty($order_data)) $this->generate_csv($order_data, $vendor);
			}
		}
	}
	
	public function is_order_shipped($order_id, $vendor) {
		global $WCMp, $wpdb;		
		$shipping_status = $wpdb->get_results( "SELECT DISTINCT shipping_status from `{$wpdb->prefix}wcmp_vendor_orders` where vendor_id = ".$vendor->id." AND order_id = ".$order_id, ARRAY_A);
		$shipping_status = $shipping_status[0]['shipping_status'];
		if($shipping_status == 0) return false;
		if($shipping_status == 1) return true;
	}
	
	function save_store_settings($user_id, $post) {
		global $WCMp;
		$vendor = get_wcmp_vendor($user_id);
		$fields = $WCMp->user->get_vendor_fields($user_id);
		foreach( $fields as $fieldkey => $value ) {
			
			if ( isset( $post[ $fieldkey ] )  ) {
				if(	$fieldkey == "vendor_page_slug" &&  !empty($post[ $fieldkey ]) )	{
					if ( $vendor && ! $vendor->update_page_slug( wc_clean( $_POST[$fieldkey] ) ) ) {
						echo  _e( 'Slug already exists', $WCMp->text_domain );						
					}
					else {
						update_user_meta( $user_id, '_' . $fieldkey, wc_clean( $post[ $fieldkey ] ) );
					}
					continue;
				}
				if( $fieldkey == 'vendor_description' ) update_user_meta( $user_id, '_' . $fieldkey,  $post[ $fieldkey ]  );
				else update_user_meta( $user_id, '_' . $fieldkey, wc_clean( $post[ $fieldkey ] ) );
				if ( $fieldkey == 'vendor_page_title' ) {
					if( ! $vendor->update_page_title( wc_clean( $post[ $fieldkey ] ) ) ) {
						echo  _e( 'Shop Title Update Error', $WCMp->text_domain );
					} else {
						wp_update_user( array( 'ID' => $user_id, 'display_name' => $post[ $fieldkey ]  ) );
					}
				} 
			} else if(!isset( $post['vendor_hide_description'] ) && $fieldkey == 'vendor_hide_description') {
				delete_user_meta($user_id, '_vendor_hide_description');
			} else if(!isset( $post['vendor_hide_email'] ) && $fieldkey == 'vendor_hide_email') {
				delete_user_meta($user_id, '_vendor_hide_email');
			} else if(!isset( $post['vendor_hide_address'] ) && $fieldkey == 'vendor_hide_address') {
				delete_user_meta($user_id, '_vendor_hide_address');
			} else if(!isset( $post['vendor_hide_phone'] ) && $fieldkey == 'vendor_hide_phone') {
				delete_user_meta($user_id, '_vendor_hide_phone');
			} else if(!isset( $post['vendor_hide_message_to_buyers'] ) && $fieldkey == 'vendor_hide_message_to_buyers') {
				delete_user_meta($user_id, '_vendor_hide_message_to_buyers');
			}
		} 
	}
}

?>