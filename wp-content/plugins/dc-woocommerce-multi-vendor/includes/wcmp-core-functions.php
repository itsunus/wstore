<?php
if(!function_exists('get_wcmp_vendor_settings')) {
	/**
		* get plugin settings
		* @return array
	*/
	function get_wcmp_vendor_settings($name = '', $tab = '') {
		if(empty($tab) && empty($name)) return '';
		if(empty($tab)) return get_option($name);
		if(empty($name)) return get_option("wcmp_{$tab}_settings_name");
		$settings = get_option("wcmp_{$tab}_settings_name");
		if(!isset($settings[$name])) return '';
		return $settings[$name];
	}
}

if( ! function_exists( 'is_user_wcmp_pending_vendor' ) ) {
	/**
	 * check if user is vendor
	 * @param userid or WP_User object
	 * @return array
	 */
	function is_user_wcmp_pending_vendor( $user ) {
		
		if( ! is_object( $user ) ) {
			$user = new WP_User( absint( $user ) );
		}
		return ( is_array( $user->roles ) && in_array( 'dc_pending_vendor', $user->roles ) );
	}
}


if( ! function_exists( 'is_user_wcmp_rejected_vendor' ) ) {
	/**
	 * check if user is vendor
	 * @param userid or WP_User object
	 * @return array
	 */
	function is_user_wcmp_rejected_vendor( $user ) {
		
		if( ! is_object( $user ) ) {
			$user = new WP_User( absint( $user ) );
		}
		return ( is_array( $user->roles ) && in_array( 'dc_rejected_vendor', $user->roles ) );
	}
}

if( ! function_exists( 'is_user_wcmp_vendor' ) ) {
	/**
	 * check if user is vendor
	 * @param userid or WP_User object
	 * @return boolean
	 */
	function is_user_wcmp_vendor( $user ) {
		
		if( ! is_object( $user ) ) {
			$user = new WP_User( absint( $user ) );
		}
		return ( is_array( $user->roles ) && in_array( 'dc_vendor', $user->roles ) );
	}
}

if( ! function_exists( 'get_wcmp_vendors' ) ) {
	/**
	 * Get all vendors
	 * @return arr Array of vendors
	 */
	function get_wcmp_vendors( $args = array() ) {
		global $WCMp;
		
		$vendors_array = false;
		
		$args = wp_parse_args( $args, array( 'role' => 'dc_vendor', 'fields' => 'ids', 'orderby' => 'registered', 'order' => 'ASC' ) );
		$user_query = new WP_User_Query( $args );
		
		if ( ! empty( $user_query->results ) ) {
			
			foreach( $user_query->results as $vendor_id ) {
				$vendors_array[] = get_wcmp_vendor( $vendor_id );
			}
		}
	
		return $vendors_array;
	}
}

if( ! function_exists( 'get_wcmp_vendor' ) ) {
	/**
	* Get individual vendor info by ID
	* @param  int $vendor_id ID of vendor
	* @return obj            Vendor object
	*/
	function get_wcmp_vendor( $vendor_id = 0 ) {
		global $WCMp;
	
		$vendor = false;
		
		if( is_user_wcmp_vendor( $vendor_id ) ) {
			$vendor = new WCMp_Vendor( absint( $vendor_id ) );
		}
	
		return $vendor;
	}
}

if( ! function_exists( 'get_wcmp_vendor_by_term' ) ) {
	/**
	 * Get individual vendor info by term id
	 * @param $term_id ID of term
	 */
	function get_wcmp_vendor_by_term( $term_id ) {
		$vendor = false;
		if ( $user_id = get_woocommerce_term_meta( $term_id, '_vendor_user_id' ) ) {
			if ( is_user_wcmp_vendor( $user_id ) ) {
				$vendor = get_wcmp_vendor( $user_id );
			}
		}
		return $vendor;
	}
}

if( ! function_exists( 'get_wcmp_product_vendors' ) ) {

	/**
	 * Get vendors for product
	 * @param  int $product_id Product ID
	 * @return arr             Array of product vendors
	 */
	function get_wcmp_product_vendors( $product_id = 0 ) {
		global $WCMp;
		$vendor_data = false;
	
		if( $product_id > 0 ) {
			$vendors_data = wp_get_post_terms( $product_id, $WCMp->taxonomy->taxonomy_name );
			foreach( $vendors_data as $vendor ) {
				$vendor_obj = get_wcmp_vendor_by_term( $vendor->term_id );
				if( $vendor_obj ) {
					$vendor_data = $vendor_obj;
				}
			}
			if(!$vendor_data) {
				$product_obj = get_post($product_id);
				$author_id = $product_obj->post_author;
				if($author_id) {
					$vendor_data = get_wcmp_vendor($author_id);
				}
			}
		}
	
		return $vendor_data;
	}
}

if( ! function_exists( 'doProductVendorLOG' ) ) {
	/**
	* Write to log file
	*/
	function doProductVendorLOG($str) {
		global $WCMp;
		$file = $WCMp->plugin_path.'log/product_vendor.log';
		if(file_exists($file)) {
			// Open the file to get existing content
			$current = file_get_contents($file);
			if($current) {
				// Append a new content to the file
				$current .= "$str" . "\r\n";
				$current .= "-------------------------------------\r\n";
			} else {
				$current = "$str" . "\r\n";
				$current .= "-------------------------------------\r\n";
			}
			// Write the contents back to the file
			file_put_contents($file, $current);
		}
		
	}
}

if( ! function_exists( 'is_vendor_dashboard' ) ) {

	/**
		* check if vendor dashboard page
		* @return boolean
	*/
	function is_vendor_dashboard() {
		$pages = get_option("wcmp_pages_settings_name");
		if(isset($pages['vendor_dashboard'])) {
			return is_page( $pages['vendor_dashboard'] ) ? true : false;
		}
		return false;
	}
}

if( ! function_exists( 'is_shop_settings' ) ) {

	/**
		* check if shop settings page
		* @return boolean
	*/
	function is_shop_settings() {
		$pages = get_option("wcmp_pages_settings_name");
		if(isset($pages['shop_settings'])) {
			return is_page( $pages['shop_settings'] ) ? true : false;
		}
		return false;
	}
}

if( ! function_exists( 'change_cap_existing_users' ) ) {
	
	/**
		* Change Capability in existing users
		* @return void
	*/
	function change_cap_existing_users( $user_cap ) {
		$product_caps = array("edit_product","delete_product","edit_products","edit_others_products","delete_published_products","delete_products","delete_others_products","edit_published_products");
		$coupon_caps = array("edit_shop_coupons", "delete_shop_coupons", "edit_shop_coupons", "edit_others_shop_coupons" , "delete_published_shop_coupons", "delete_shop_coupons", "delete_others_shop_coupons"	, "edit_published_shop_coupons");
		$get_dc_vendors = get_wcmp_vendors();
		if($get_dc_vendors) {
			foreach($get_dc_vendors as $get_dc_vendor) {
				$user =  new WP_User( $get_dc_vendor->id );
				if($user) {
					if( $user_cap == 'is_upload_files' ) $user->remove_cap('upload_files');
					if( $user_cap == 'is_submit_product' ) {
						foreach( $product_caps as $product_cap ) {
							 $user->remove_cap($product_cap);
						}
					}
					if($user_cap == 'is_submit_coupon') {
						foreach( $coupon_caps as $coupon_cap ) {
							 $user->remove_cap($coupon_cap);
						}
					}
					if( $user_cap == 'is_published_product' ) $user->remove_cap('publish_products');
					if( $user_cap == 'is_published_coupon' ) $user->remove_cap('publish_shop_coupons');
				}
			}
		}
	}
}

if( ! function_exists( 'add_cap_existing_users' ) ) {
	/**
	* Add Capability in existing users
	* @return void
	*/
	function add_cap_existing_users( $user_cap ) {
		$get_dc_vendors = get_wcmp_vendors();
		if($get_dc_vendors) {
			foreach($get_dc_vendors as $get_dc_vendor) {
				$caps = array();
				$user =  new WP_User( $get_dc_vendor->id );
				if($user) {
					if( $user_cap == 'is_submit_product')  {
						$vendor_submit_products = get_user_meta($user->ID, '_vendor_submit_product', true);
						if( $vendor_submit_products ) {
							$caps[] = "edit_product";
							$caps[] = "delete_product";
							$caps[] = "edit_products";
							$caps[] = "edit_others_products";
							$caps[] = "delete_published_products";
							$caps[] = "delete_products";
							$caps[] = "delete_others_products";
							$caps[] = "edit_published_products";
						}
						$caps[] = "read_product";
						foreach( $caps as $cap ) {
							$user->add_cap( $cap );
						}
					} else if( $user_cap == 'is_submit_coupon'){
						$vendor_submit_products = get_user_meta($user->ID, '_vendor_submit_coupon', true);
						if( $vendor_submit_products ) {
							$caps[] = 'edit_shop_coupon';
							$caps[] = 'delete_shop_coupon';
							$caps[] = 'edit_shop_coupons';
							$caps[] = 'read_shop_coupons';
							$caps[] = 'delete_shop_coupons';
							$caps[] = 'edit_published_shop_coupons';
							$caps[] = 'delete_published_shop_coupons';
							$caps[] = 'edit_others_shop_coupons';
							$caps[] = 'delete_others_shop_coupons';
						}
						$caps[] = "edit_posts";
						$caps[] = "read_shop_coupon";
						foreach( $caps as $cap ) {
							$user->add_cap( $cap );
						}
					} else {
						$user->add_cap($user_cap);
					}
				}
			}
		}
	}
}


if( ! function_exists( 'get_vendor_from_an_order' ) ) {
	/**
		* Get vendor from a order
		* @return array
	*/
	function get_vendor_from_an_order($order_id) {
		$vendors = array();
		$order = new WC_Order( $order_id );
		$items = $order->get_items( 'line_item' );
		foreach( $items as $item_id => $item ) {
			$vendor_id = $order->get_item_meta( $item_id, '_vendor_id', true );
			if( $vendor_id ) {
				$vendors[] = get_user_meta( $vendor_id, '_vendor_term_id', true );
			} else {
				$product_id = $order->get_item_meta( $item_id, '_product_id', true );
				if( $product_id ) {
					$product_vendors = get_wcmp_product_vendors($product_id); 
					if( $product_vendors ) {
						$vendors[] = $product_vendors->term_id;
					}
				}
			}
		}
		return $vendors;
	}
}

if( ! function_exists( 'is_vendor_page' ) ) {
	/**
		* check if vendor pages
		* @return boolean
	*/
	function is_vendor_page() {
		
		$pages = get_option("wcmp_pages_settings_name");
		
		$return = false;
		
		if(is_page( absint ( $pages['shop_settings'] ) ) ) $return = true;
		
		if(is_page( absint( $pages['vendor_dashboard'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['view_order'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_transaction_thankyou'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_transaction_detail'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_order_detail'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_policies'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_billing'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_report'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_widthdrawals'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_university'] ) ) ) $return = true;
		
		if(is_page( absint ( $pages['vendor_messages'] ) ) ) $return = true;
			
		
		$return = apply_filters('wcmp_plugin_pages_redirect', $return);
		
		return $return;
	}    
}

if( ! function_exists( 'is_vendor_order_by_product_page' ) ) {
	/**
		* check if vendor order page
		* @return boolean
	*/
	function is_vendor_order_by_product_page() {
		$pages = get_option("wcmp_pages_settings_name");
		return ( is_page( absint ( $pages['view_order'] ) )  ); 
	}    
}


if( ! function_exists( 'get_vendor_coupon_amount' ) ) {
	/**
		* get vendor coupon from order.
		* @return boolean
	*/
	function get_vendor_coupon_amount($item_product_id, $order_id, $vendor) {
		$order = new WC_Order ($order_id);
		$coupons = $order->get_used_coupons();
		$coupon_used = array();
		if(!empty($coupons)) {
			foreach($coupons as $coupon_code) {
				$coupon = new WC_Coupon( $coupon_code );
				$coupon_post = get_post($coupon->id);
				$author_id = $coupon_post->post_author;
				if(get_current_user_id() != $author_id) {
					continue;
				} else {
					$coupon_product_ids = $coupon->product_ids;
					if(!in_array($item_product_id, $coupon_product_ids)) {
						continue;
					} else {
						$coupon_used[] = $coupon_code;
					}
				}
			}
			if(!empty($coupon_used)) {
				$return_coupon = ' ,   Copoun Used : ';
				$no_of_coupon_use = false;
				foreach($coupon_used as $coupon_use) {
					if(!$no_of_coupon_use)	$return_coupon .= '"'. $coupon_use . '"';
					else $return_coupon .= ', "' . $coupon_use .'"';
					$no_of_coupon_use = true;
				}
				return $return_coupon;
			} else {
				return null;
			}
		}
	}
}
if( ! function_exists( 'wcmp_action_links' ) ) {

	/**
	 * Product Vendor Action Links Function
	 *
	 * @access public
	 * @param plugin links
	 * @return plugin links
	*/	
  function wcmp_action_links($links) {
		global $WCMp;
		$plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wcmp-setting-admin' ) . '">' . __( 'Settings', $WCMp->text_domain ) . '</a>'  );
    return array_merge( $plugin_links, $links );
	}
}
if( ! function_exists( 'wcmp_get_all_blocked_vendors' ) ) {

	/**
	 * wcmp_get_all_blocked_vendors Function
	 *
	 * @access public
	 * @return plugin array
	*/	
  function wcmp_get_all_blocked_vendors() {
  	$vendors = get_wcmp_vendors();
  	$blocked_vendor = array();
  	if(!empty($vendors)) {
  		foreach($vendors as $vendor_key => $vendor) {
  			$is_block = get_user_meta($vendor->id, '_vendor_turn_off' , true);
  			if($is_block) {
  				$blocked_vendor[] = $vendor;
  			}
  		}
  	}
  	return $blocked_vendor;
  }
  
}

if( ! function_exists( 'wcmp_get_vendors_due_from_order' ) ) {  
  /**
	 * wcmp_get_vendors_due_from_order function to get vendor due from an order.
	 * @access public
	 * @param order , vendor term id 
	*/
	function wcmp_get_vendors_due_from_order($order_id) {
		$order = new WC_Order($order_id);
		$items = $order->get_items( 'line_item' );
		$vendors_array = array();
		if( $items ) {
			foreach( $items as $item_id => $item ) {
				$product_id = $order->get_item_meta( $item_id, '_product_id', true );
				if( $product_id ) {
					$vendor = get_wcmp_product_vendors($product_id);
					if(!empty($vendor) && isset($vendor->term_id)) {
						$vendors_array[$vendor->term_id] = $vendor->wcmp_get_vendor_part_from_order($order, $vendor->term_id);
					}
				}
			}
		}
		return $vendors_array;
	}
}
if( ! function_exists( 'activate_wcmp_plugin' ) ) {
	/**
   * On activation, include the installer and run it.
   *
   * @access public
   * @return void
   */
  function activate_wcmp_plugin() {
    require_once( 'class-wcmp-install.php' );
    new WCMp_Install();
    update_option( 'dc_product_vendor_plugin_installed', 1 );
  }
}

if( ! function_exists( 'wcmpArrayToObject' ) ) {  
 function wcmpArrayToObject($d) {
		if (is_array($d)) {
			/*
			* Return array converted to object
			* Using __FUNCTION__ (Magic constant)
			* for recursive call
			*/
			return (object) array_map(__FUNCTION__, $d);
		}	else {
			// Return object
			return $d;
		}
 }
}

if( ! function_exists( 'wcmp_paid_commission_status' ) ) {  
	function wcmp_paid_commission_status($commission_id) {
		update_post_meta( $commission_id, '_paid_status', 'paid', 'unpaid' );
		update_post_meta( $commission_id, '_paid_date', time());
	}
}
if(! function_exists( 'wcmp_rangeWeek' ) ) {
	function wcmp_rangeWeek($datestr) {
			date_default_timezone_set(date_default_timezone_get());
			$dt = strtotime($datestr);
			$res['start'] = date('N', $dt)==1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
			$res['end'] = date('N', $dt)==7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
			return $res;
	}
}
?>
