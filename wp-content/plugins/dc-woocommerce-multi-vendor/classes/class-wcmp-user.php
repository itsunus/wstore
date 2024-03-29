<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @class 		WCMp User Class
 *
 * @version		2.2.0
 * @package		WCMp
 * @author 		DualCube
 */
class WCMp_User {
  
  private $post_type;
  
  public function __construct() {
  	
  	// Add dc_pending_vendor, dc_vendor, dc_rejected_vendor role
    $this->register_user_role();
    
    // Set vendor role
    add_action( 'user_register',  array( &$this, 'vendor_registration' ), 10, 1 );
    
    // Add column product in users dashboard
    add_filter( 'manage_users_columns', array( &$this,'column_register_product' ));
    add_filter( 'manage_users_custom_column', array( &$this, 'column_display_product' ), 10, 3 );
    
    // Set vendor_action links in user dashboard
    add_filter(	'user_row_actions', array( &$this, 'vendor_action_links' ), 10, 2 );
    
    // Add addistional user fields
    add_action( 'show_user_profile', array( &$this, 'additional_user_fields' ) );
    add_action( 'edit_user_profile', array( &$this, 'additional_user_fields') );
    
    // Validate addistional user fields
		add_action( 'user_profile_update_errors', array( &$this, 'validate_user_fields' ), 10, 3 );
		
		// Save addistional user fields
    add_action( 'personal_options_update', array( &$this,'save_vendor_data') );
    add_action( 'edit_user_profile_update', array( &$this, 'save_vendor_data') );
    
    // Delete vendor
    add_action( 'delete_user', array( &$this, 'delete_vendor') );
    
    add_action( 'admin_head', array($this , 'profile_admin_buffer_start') );
    add_action( 'admin_footer', array($this , 'profile_admin_buffer_end') );
    
    // Add vednor registration checkbox in front-end
    add_action( 'woocommerce_register_form', array($this, 'wcmp_woocommerce_register_form'));
    
    // Created customer notification
    add_action( 'woocommerce_created_customer_notification', array($this, 'wcmp_woocommerce_created_customer_notification'), 9, 3);
    
    add_action( 'set_user_role', array(&$this, 'set_user_role'), 30, 3 );
    
    // Add message in my account page after vendore registrtaion
    add_action( 'woocommerce_before_my_account', array(&$this, 'woocommerce_before_my_account'));
    
    add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'wcmp_order_emails_available' ) );
    
    add_filter('woocommerce_registration_redirect', array( $this, 'vendor_login_redirect'), 30, 1);
    
    $this->register_vendor_from_vendor_dashboard();
    
    add_filter( 'woocommerce_login_redirect', array( $this, 'wcmp_vendor_login' ), 10, 2 );
	}
	
	function wcmp_vendor_login($redirect, $user) {
		global $WCMp;
		
		if( $user->roles[0] == 'dc_vendor' ) {
			$pages = get_wcmp_vendor_settings('wcmp_pages_settings_name');
			$redirect = get_permalink($pages['vendor_dashboard']);
		}
		
		return $redirect;
	}
	
	function register_vendor_from_vendor_dashboard() {
		global $WCMp;
		$user = wp_get_current_user();
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if(isset($_POST['vendor_apply']) && $user ) {	
				if( isset( $_POST['pending_vendor'] ) && ( $_POST['pending_vendor'] == 'true' ) ) {
					$this->vendor_registration($user->ID);
					$this->wcmp_woocommerce_created_customer_notification();
					wp_redirect( get_permalink( get_option('woocommerce_myaccount_page_id') ) );
					exit;
				}
			}
		} 
	}
	
	/**
	 * Vendor login template redirect
	 */
	function vendor_login_redirect( $redirect_to ) {
		$user = get_user_by( 'email', $_POST['email'] );
		if(is_user_wcmp_vendor($user->ID)) {
			$pages = get_option("wcmp_pages_settings_name");
			$redirect_to = get_permalink($pages['vendor_dashboard']);
			return $redirect_to;
		}
		return apply_filters('wcmp_vendor_login_redirect', $redirect_to, $user);
	}

  
  /**
	 * WCMp Vendor message at WC myAccount
	 * @access public
	 * @return void
	 */
  public function woocommerce_before_my_account() {
  	global $WCMp;
  	$current_user = wp_get_current_user();
		if(is_user_wcmp_pending_vendor($current_user)) {
			_e('Congratulations! You have successfully applied as a Vendor. Please wait for further notifications from the admin.', $WCMp->text_domain);
			do_action('add_vendor_extra_information_my_account');
		}  
		if(is_user_wcmp_vendor($current_user)) {
			$admin_url = admin_url();
			echo apply_filters( 'wcmp_vendor_goto_dashboard', '<a href="'.$admin_url.'">'.__('Dashboard - manage your account here', $WCMp->text_domain).'</a>' );
		}
  }
  
  
  /**
	 * Set vendor user role and associate capabilities
	 *
	 * @access public
	 * @param user_id, new role, old role
	 * @return void
	 */
  public function set_user_role($user_id, $new_role, $old_role) {
  	global $WCMp;
  	
  	$user = new WP_User( $user_id );
  	if($user_id && $new_role == 'dc_rejected_vendor') {
  		$user_dtl = get_userdata( absint( $user_id ) );
  		$email = WC()->mailer()->emails['WC_Email_Rejected_New_Vendor_Account'];
  		$email->trigger( $user_id, $user_dtl->user_pass );
  		if(in_array('dc_vendor', $old_role)) {
  			$vendor = get_wcmp_vendor($user_id);
  			if($vendor) wp_delete_term( $vendor->term_id, 'dc_vendor_shop' );
  		}
  		wp_delete_user($user_id); 
  	}
  	if($user_id && $new_role == 'dc_pending_vendor') {
  		if(in_array('dc_vendor', $old_role)) {
  			$caps = $this->get_vendor_caps( $user_id );
				foreach( $caps as $cap ) {
					$user->remove_cap( $cap );
				}
  		}
  		$user->remove_cap('manage_woocommerce');
  	}
  	if($user_id && $new_role == 'dc_vendor') {
  		
  		$this->update_vendor_meta($user_id);
  		
  		$user->add_cap('assign_product_terms');
  		$user->add_cap('read_product');
  		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_upload_files') ) {
  			$user->add_cap('upload_files');
  		}
  		$user->add_cap('read_product');
  		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_product') ) {
  			$vendor_submit_products = get_user_meta($user_id, '_vendor_submit_product', true);
				if( $vendor_submit_products ) {
					$caps = array();
					$caps[] = "edit_product";
					$caps[] = "delete_product";
					$caps[] = "edit_products";
					$caps[] = "edit_others_products";
					$caps[] = "delete_published_products";
					$caps[] = "delete_products";
					$caps[] = "delete_others_products";
					$caps[] = "edit_published_products";
					foreach( $caps as $cap ) {
						$user->add_cap( $cap );
					} 
				}
			}
			
			if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_coupon') ) {
  			$vendor_submit_coupon = get_user_meta($user_id, '_vendor_submit_coupon', true);
				if( $vendor_submit_coupon ) {
					$caps = array();
					$caps[] = 'edit_shop_coupons';
					$caps[] = 'read_shop_coupons';
					$caps[] = 'delete_shop_coupons';
					$caps[] = 'edit_published_shop_coupons';
					$caps[] = 'delete_published_shop_coupons';
					$caps[] = 'edit_others_shop_coupons';
					$caps[] = 'delete_others_shop_coupons';
					foreach( $caps as $cap ) {
						$user->add_cap( $cap );
					} 
				}
			}
  	}
  	do_action('wcmp_set_user_role', $user_id, $new_role, $old_role);
  }
  
	/**
	 * Register vendor user role
	 *
	 * @access public
	 * @return void
	 */
  public function register_user_role() {
  	global $wp_roles, $WCMp;
    if ( class_exists('WP_Roles') ) if ( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();
    if ( is_object($wp_roles) ) {
    	
    	$wordpress_default_role = get_option('default_role');
    	
    	remove_role( 'dc_vendor' );    	remove_role( 'dc_pending_vendor' );    	remove_role( 'dc_rejected_vendor' );
    	
      // Vendor role
      add_role( 'dc_vendor', apply_filters('dc_vendor_role', __('Vendor', $WCMp->text_domain )), array(
        'read' 					=> true,
        'edit_posts' 		=> true,
        'delete_posts' 	=> false,
        'manage_product' => true,
        'view_woocommerce_reports' => true,
      ) );
      // Pending Vendor role
      add_role( 'dc_pending_vendor', apply_filters('dc_pending_vendor_role', __('Pending Vendor', $WCMp->text_domain )), array(
        'read' 					=> true,
        'edit_posts' 		=> false,
        'delete_posts' 	=> false,
      ) );
      // Pending Vendor role
      add_role( 'dc_rejected_vendor', apply_filters('dc_rejected_vendor_role', __('Rejected Vendor', $WCMp->text_domain )), array(
        'read' 					=> true,
        'edit_posts' 		=> false,
        'delete_posts' 	=> false,
      ) );
      if(isset($wordpress_default_role)) update_option('default_role', $wordpress_default_role);
    }
  }
  
	/**
	 * Set up array of vendor admin capabilities
	 *
	 * @access private
	 * @param int $user_id
	 * @return arr Vendor capabilities
	 */
	private function get_vendor_caps( $user_id ) {
		global $WCMp;
		$caps = array();
		$caps[] = "assign_product_terms";
		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_upload_files') ) {
			$caps[] = "upload_files" ;
		}
		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_product') ) {
			$vendor_submit_products = get_user_meta($user_id, '_vendor_submit_product', true);
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
		}
		$caps[] = "read_product";
		$caps[] = "read_shop_coupon";
		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_coupon') ) {
			$vendor_submit_coupon = get_user_meta($user_id, '_vendor_submit_coupon', true);
			if( $vendor_submit_coupon ) {
				$caps[] = 'edit_shop_coupons';
				$caps[] = 'read_shop_coupons';
				$caps[] = 'delete_shop_coupons';
				$caps[] = 'edit_published_shop_coupons';
				$caps[] = 'delete_published_shop_coupons';
				$caps[] = 'edit_others_shop_coupons';
				$caps[] = 'delete_others_shop_coupons';
			}
		}
		return apply_filters('vednor_capabilities', $caps, $user_id);
	}
	
	/**
   * Add capabilities to vendor admins
   *
   * @param int $user_id User ID of vendor admin
   */
  public function add_vendor_caps( $user_id = 0 ) {
    if( $user_id > 0 ) {
      $caps = $this->get_vendor_caps( $user_id );
      $user = new WP_User( $user_id );
      foreach( $caps as $cap ) {
      	//echo $cap;
        $user->add_cap( $cap );
      }
    }
    //die;
  }

  /**
	 * Get vendor details
	 *
	 * @param $user_id
	 * @access public
	 * @return array
	 */
  public function get_vendor_fields($user_id) {
  	global $WCMp;
		
		$vendor = new WCMp_Vendor($user_id);
		$settings_product = get_option('wcmp_product_settings_name');
		$settings_capabilities = get_option('wcmp_capabilities_settings_name');
		
		$fields = apply_filters('wcmp_vendor_fields', array(
			"vendor_page_title" => array(
				'label' => __('Vendor Page Title', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->page_title,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_page_slug" => array(
				'label' => __('Vendor Page Slug', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->page_slug,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_description" => array(
				'label' => __('Description', $WCMp->text_domain),
				'type' => 'wpeditor',
				'value' => $vendor->description,
				'class'	=> "user-profile-fields"
			), //Wp Eeditor
			
			"vendor_hide_address" => array(
				'label' => __('Hide address in frontend', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->hide_address,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			),
			
			"vendor_hide_phone" => array(
				'label' => __('Hide phone in frontend', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->hide_phone,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			),
			
			"vendor_hide_email" => array(
				'label' => __('Hide email in frontend', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->hide_email,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			),
			
			"vendor_hide_description" => array(
				'label' => __('Hide description in frontend', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->hide_description,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			),
			
			"vendor_company" => array(
				'label' => __('Company Name', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->company,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_address_1" => array(
				'label' => __('Address 1', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->address_1,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_address_2" => array(
				'label' => __('Address 2', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->address_2,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_city" => array(
				'label' => __('City', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->city,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_postcode" => array(
				'label' => __('Postcode', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->postcode,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_external_store_url" => array(
				'label' => __('External store URL', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->external_store_url,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_external_store_label" => array(
				'label' => __('External store URL label', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->external_store_label,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_state" => array(
				'label' => __('State', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->state,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_country" => array(
				'label' => __('Country', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->country,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_phone" => array(
				'label' => __('Phone', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->phone,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_fb_profile" => array(
				'label' => __('Facebook Profile', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->fb_profile,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_twitter_profile" => array(
				'label' => __('Twitter Profile', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->twitter_profile,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_google_plus_profile" => array(
				'label' => __('Google+ Profile', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->google_plus_profile,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_linkdin_profile" => array(
				'label' => __('LinkedIn Profile', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->linkdin_profile,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_youtube" => array(
				'label' => __('YouTube Channel', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->youtube,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_image" => array(
				'label' => __('Logo', $WCMp->text_domain),
				'type' => 'upload',
				'prwidth' => 125,
				'value' => $vendor->image,
				'class'	=> "user-profile-fields"
			),// Upload
			"vendor_banner" => array(
				'label' => __('Banner', $WCMp->text_domain),
				'type' => 'upload',
				'prwidth' => 600,
				'value' => $vendor->banner,
				'class'	=> "user-profile-fields"
			),// Upload			
			"vendor_csd_return_address1" => array(
				'label' => __('Customer address1', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_address1,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_csd_return_address2" => array(
				'label' => __('Customer address2', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_address2,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_csd_return_country" => array(
				'label' => __('Customer Country', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_country,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_csd_return_state" => array(
				'label' => __('Customer Return State', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_state,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_csd_return_city" => array(
				'label' => __('Customer Return City', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_city,
				'class'	=> "user-profile-fields"
			), // Text 
			"vendor_csd_return_zip" => array(
				'label' => __('Customer Return Zip', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->csd_return_zip,
				'class'	=> "user-profile-fields"
			), // Text  
			"vendor_customer_phone" => array(
				'label' => __('Customer Phone', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->customer_phone,
				'class'	=> "user-profile-fields"
			), // Text
			"vendor_customer_email" => array(
				'label' => __('Customer Email', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->customer_email,
				'class'	=> "user-profile-fields"
			), // Text
		), $user_id);
		
		if ( !$WCMp->vendor_caps->vendor_capabilities_settings('is_vendor_add_external_url') ) {
			unset($fields['vendor_external_store_url']);
			unset($fields['vendor_external_store_label']);
		}
		
		$fields["vendor_payment_mode"] =  array(
				'label' => __('Payment Mode', $WCMp->text_domain),
				'type' => 'select',
				'options' => apply_filters( 'wcmp_vendor_payment_mode', array('paypal_masspay' => __('PayPal Masspay', $WCMp->text_domain), 'direct_bank' => __('Direct Bank', $WCMp->text_domain)) ),
				'value' => $vendor->payment_mode,
				'class'	=> "user-profile-fields"
			); // Text
		
		$fields["vendor_bank_account_type" ] = array(
			'label' => __('Bank Account Type', $WCMp->text_domain),
			'type' => 'select',
			'options' => array('current' => __('Current', $WCMp->text_domain), 'savings' => __('Savings', $WCMp->text_domain)),
			'value' => $vendor->bank_account_type,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_bank_account_number" ] = array(
			'label' => __('Bank Account Name', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->bank_account_number,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_bank_name"] = array(
			'label' => __('Bank Name', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->bank_name,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_aba_routing_number" ] = array(
			'label' => __('ABA Routing Number', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->aba_routing_number,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_bank_address"] = array(
			'label' => __('Bank Address', $WCMp->text_domain),
			'type' => 'textarea',
			'value' => $vendor->bank_address,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_destination_currency"] = array(
			'label' => __('Destination Currency', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->destination_currency,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_iban" ] = array(
			'label' => __('IBAN', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->iban,
			'class'	=> "user-profile-fields"
		); // Text
		
		$fields["vendor_account_holder_name"] = array(
			'label' => __('Account Holder Name', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->account_holder_name,
			'class'	=> "user-profile-fields"
		); // Text
		$fields["vendor_paypal_email"] = array(
			'label' => __('PayPal Email', $WCMp->text_domain),
			'type' => 'text',
			'value' => $vendor->paypal_email,
			'class'	=> "user-profile-fields"
		); // Text
		
		if( isset( $settings_product['is_policy_on'] ) && isset($settings_product['policies_can_override_by_vendor'] ) ) {			
			$fields['vendor_is_policy_off'] = array(
				'label' => __('Is Policy Tab Hide From Product Page', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->is_policy_off,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_policy_tab_title'] = array(
				'label' => __('Enter the title of Policies Tab', $WCMp->text_domain), 
				'type' => 'text',				
				'value' => $vendor->policy_tab_title,
				'class' => 'user-profile-fields'
			);
			$fields['vendor_cancellation_policy'] = array(
				'label' => __('Cancellation/Return/Exchange Policy', $WCMp->text_domain), 
				'type' => 'textarea',				
				'value' => $vendor->cancellation_policy,
				'class' => 'user-profile-fields'
			);
			$fields['vendor_refund_policy'] = array(
				'label' => __('Refund Policy', $WCMp->text_domain), 
				'type' => 'textarea',				
				'value' => $vendor->refund_policy,
				'class' => 'user-profile-fields'
			);
			$fields['vendor_shipping_policy'] = array(
				'label' => __('Shipping Policy', $WCMp->text_domain), 
				'type' => 'textarea',				
				'value' => $vendor->shipping_policy,
				'class' => 'user-profile-fields'
			);			
		}
		if( isset( $settings_capabilities['can_vendor_add_message_on_email_and_thankyou_page'] ) ) {
			$fields['vendor_message_to_buyers'] = array(
				'label' => __('Message to Buyers', $WCMp->text_domain), 
				'type' => 'textarea',				
				'value' => $vendor->message_to_buyers,
				'class' => 'user-profile-fields'
			);		
			
			$fields['vendor_hide_message_to_buyers'] = array(
				'label' => __('Is Message to buyer Hide From Users', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->hide_message_to_buyers,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			
		}	
		$user = wp_get_current_user();
		if( is_array( $user->roles ) && in_array( 'administrator', $user->roles )) {
			$fields['vendor_commission'] = array(
				'label' => __('Commission Amount', $WCMp->text_domain),
				'type' => 'text',
				'value' => $vendor->commission,
				'class'	=> "user-profile-fields"
			); // Text   
			$fields['vendor_submit_product'] = array(
				'label' => __('Submit products', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->submit_product,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_publish_product'] = array(
				'label' => __('Disallow direct publishing of products', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->publish_product,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_submit_coupon'] = array(
				'label' => __('Submit Coupons', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->submit_coupon,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_publish_coupon'] = array(
				'label' => __('Disallow direct publishing of coupons', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->publish_coupon,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_give_tax'] = array(
				'label' => __('Withhold Tax', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->give_tax,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_give_shipping'] = array(
				'label' => __('Withhold Shipping', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->give_shipping,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			$fields['vendor_turn_off'] = array(
				'label' => __('Block this vendor with all items', $WCMp->text_domain), 
				'type' => 'checkbox',
				'dfvalue' => $vendor->turn_off,
				'value' => 'Enable',
				'class' => 'user-profile-fields'
			);
			
			
			
			if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage') {
				unset($fields['vendor_commission']);
				$fields['vendor_commission_percentage'] = array(
					'label' => __('Commission Percentage(%)', $WCMp->text_domain), 
					'type' => 'text',
					'value' => $vendor->commission_percentage,
					'class' => 'user-profile-fields'
				);
				$fields['vendor_commission_fixed_with_percentage'] = array(
					'label' => __('Commission(fixed), Per Transaction', $WCMp->text_domain), 
					'type' => 'text',
					'value' => $vendor->commission_fixed_with_percentage,
					'class' => 'user-profile-fields'
				);
			}
			
			if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage_qty') {
				unset($fields['vendor_commission']);
				$fields['vendor_commission_percentage'] = array(
					'label' => __('Commission Percentage(%)', $WCMp->text_domain), 
					'type' => 'text',
					'value' => $vendor->commission_percentage,
					'class' => 'user-profile-fields'
				);
				$fields['vendor_commission_fixed_with_percentage_qty'] = array(
					'label' => __('Commission Fixed Per Unit', $WCMp->text_domain), 
					'type' => 'text',
					'value' => $vendor->commission_fixed_with_percentage_qty,
					'class' => 'user-profile-fields'
				);
			}
			
		}
		
		if(! $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_product')  ) {
			unset($fields['vendor_submit_product']);
		}
		if(! $WCMp->vendor_caps->vendor_capabilities_settings('is_published_product')  ) {
			unset($fields['vendor_publish_product']);
		}
		
		if(! $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_coupon')  ) {
			unset($fields['vendor_submit_coupon']);
		}
		if(! $WCMp->vendor_caps->vendor_capabilities_settings('is_published_coupon')  ) {
			unset($fields['vendor_publish_coupon']);
		}
				
  	return $fields;
  }
  
	/**
	 * Actions at Vendor Registration
	 *
	 * @access public
	 * @param $user_id
	 */
  public function vendor_registration( $user_id ) {
  	global $WCMp;
  	$is_approve_manually = $WCMp->vendor_caps->vendor_general_settings('approve_vendor_manually');
  	if(isset($_POST['pending_vendor']) &&  ($_POST['pending_vendor'] == 'true')  &&  !is_user_wcmp_vendor( $user_id ) && $is_approve_manually ) {
  		$user = new WP_User( absint( $user_id ) );
  		$user->remove_role( 'customer' );           
  		$user->remove_role( 'Subscriber' );
  		$user->add_role( 'dc_pending_vendor' );
  	}
  	
  	if(isset($_POST['pending_vendor']) &&  ($_POST['pending_vendor'] == 'true')  &&  !is_user_wcmp_vendor( $user_id ) && ! $is_approve_manually ) {
  		$user = new WP_User( absint( $user_id ) );
  		$user->remove_role( 'customer' );           
  		$user->remove_role( 'Subscriber' );
  		$user->add_role( 'dc_vendor' );
  		$this->update_vendor_meta($user_id);
  	}
  	
  	if ( is_user_wcmp_vendor( $user_id ) ) {
  		$this->update_vendor_meta($user_id);
  		$this->add_vendor_caps( $user_id );
  		$vendor = get_wcmp_vendor( $user_id );
			$vendor->generate_term();
  	}
  }
  
  /**
	 * ADD commission column on user dashboard
	 *
	 * @access public
	 * @return array
	*/	
  function column_register_product( $columns ) {
  	global $WCMp;
		$columns['product'] = __('Products', $WCMp->text_domain);
		return $columns;
	}
	
	/**
	 * Display commission column on user dashboard
	 *
	 * @access public
	 * @return string
	*/		
	function column_display_product( $empty, $column_name, $user_id ) {
		if ( 'product' != $column_name && ! is_user_wcmp_vendor( $user_id ) )                                                                     
			return $empty;
		$vendor = get_wcmp_vendor( $user_id );
		if ( $vendor )  {
			$product_count = count($vendor->get_products());
			return "<a href='edit.php?post_type=product&dc_vendor_shop=".$vendor->user_data->user_login."'><strong>{$product_count}</strong></a>" ;
		}
		else return "<strong></strong>";
	}
	
	/**
	 * Add vendor action link in user dashboard
	 *
	 * @access public
	 * @return array
	*/	
	function vendor_action_links( $actions, $user_object ) {
		global $WCMp;
		
		if ( is_user_wcmp_vendor( $user_object ) ) {
			$vendor = get_wcmp_vendor( $user_object->ID );
			if ($vendor) {
				$actions['view_vendor'] = "<a target=_blank class='view_vendor' href='" . $vendor->permalink . "'>" . __( 'View', $WCMp->text_domain ) . "</a>";
			}
		}
		
		if ( is_user_wcmp_pending_vendor( $user_object ) ) {
			$vendor = get_wcmp_vendor( $user_object->ID );
			$actions['activate'] = "<a class='activate_vendor' data-id='".$user_object->ID."'href=#>" . __( 'Approve', $WCMp->text_domain ) . "</a>";
			$actions['reject'] = "<a class='reject_vendor' data-id='".$user_object->ID."'href=#>" . __( 'Reject', $WCMp->text_domain ) . "</a>";
		}
		
		if ( is_user_wcmp_rejected_vendor( $user_object ) ) {
			$vendor = get_wcmp_vendor( $user_object->ID );
			$actions['activate'] = "<a class='activate_vendor' data-id='".$user_object->ID."'href=#>" . __( 'Approve', $WCMp->text_domain ) . "</a>";
		}
		
		
		return $actions;
	}
	
	/**
	 * Additional user  fileds at Profile page
	 *
	 * @access private
	 * @param $user obj
	 * @return void
	 */
	function additional_user_fields( $user ) {
		global $WCMp;
		$vendor = get_wcmp_vendor( $user->ID );
		if ( $vendor ) { ?>
			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label for="View Vendor" > <?php _e('View Vendor', $WCMp->text_domain); ?></label>
						</th>
						<td>
							<a class="button-primary" target="_blank" href=<?php echo $vendor->permalink; ?>>View</a>
						</td>
					</tr>
					<?php $WCMp->wcmp_wp_fields->dc_generate_form_field( $this->get_vendor_fields( $user->ID ), array('in_table' => 1) ); ?>
				</tbody>
			</table>
			<?php
		}
	}
	
	/**
	 * Validate user additional fields
	 */
	function validate_user_fields( &$errors, $update, &$user ) {
		global $WCMp;
		if(isset($_POST['vendor_page_slug'])) {
			if(!$update) {
				if ( term_exists( sanitize_title( $_POST['vendor_page_slug'] ), 'dc_vendor_shop' ) ) {
					$errors->add( 'vendor_slug_exists', __( 'Slug Already Exists', $WCMp->text_domain ) );
				}
			} else {
				if(is_user_wcmp_vendor($user->ID)) {
					$vendor = get_wcmp_vendor( $user->ID );
					if(isset($vendor->term_id)) $vendor_term = get_term($vendor->term_id, 'dc_vendor_shop');
					if(isset($_POST['vendor_page_slug']) && isset($vendor_term->slug) && $vendor_term->slug != $_POST['vendor_page_slug']) {
						if ( term_exists( sanitize_title( $_POST['vendor_page_slug'] ), 'dc_vendor_shop' ) ) {
							$errors->add( 'vendor_slug_exists', __( 'Slug already exists', $WCMp->text_domain ) );
						}
					}
				}
			}
		}
	}
	
	/**
		* Saves additional user fields to the database
		* function save_vendor_data
		* @access private
		* @param int $user_id
		* @return void
		*/
	function save_vendor_data( $user_id ) {
		global $WCMp;
		$user = new WP_User($user_id);
		// only saves if the current user can edit user profiles
		if ( ! current_user_can( 'edit_user', $user_id ) )	return false;
		$errors = new WP_Error();
		
		if(!is_user_wcmp_vendor($user_id) && $_POST['role'] == 'dc_vendor') {
			$user->add_role( 'dc_vendor' );
			$this->update_vendor_meta($user_id);
			$this->add_vendor_caps( $user_id );
			$vendor = get_wcmp_vendor( $user_id );
			$vendor->generate_term();
			$user_dtl = get_userdata( absint( $user_id ) );
			$email = WC()->mailer()->emails['WC_Email_Approved_New_Vendor_Account'];
			$email->trigger( $user_id, $user_dtl->user_pass );
		}
		
		$fields = $this->get_vendor_fields( $user_id );
		
		$vendor = get_wcmp_vendor( $user_id );
		foreach( $fields as $fieldkey => $value ) {
			if ( isset( $_POST[ $fieldkey ] ) ) {
				if ( $fieldkey == 'vendor_page_title' ) {
					if( $vendor && ! $vendor->update_page_title( wc_clean( $_POST[$fieldkey] ) ) ) {
						$errors->add( 'vendor_title_exists', __( 'Title Update Error', $WCMp->text_domain ) );
					} else {
						wp_update_user( array( 'ID' => $user_id, 'display_name' => $_POST[ $fieldkey ]  ) );
					}
				} elseif ( $fieldkey == 'vendor_page_slug' ) {
					if ( $vendor && ! $vendor->update_page_slug( wc_clean( $_POST[$fieldkey] ) ) ) {
						$errors->add( 'vendor_slug_exists', __( 'Slug already exists', $WCMp->text_domain ) );
					}
				} elseif ( $fieldkey == 'vendor_publish_product' ) {
					$user->remove_cap('publish_products');
					update_user_meta( $user_id, '_' . $fieldkey, wc_clean( $_POST[ $fieldkey ] ) );
				} elseif ( $fieldkey == 'vendor_publish_coupon' ) {
					$user->remove_cap('publish_shop_coupons');
					update_user_meta( $user_id, '_' . $fieldkey, wc_clean( $_POST[ $fieldkey ] ) );
			  } else {
					update_user_meta( $user_id, '_' . $fieldkey, wc_clean( $_POST[ $fieldkey ] ) );
				}
			}	else if( !isset( $_POST['vendor_submit_product'] ) && $fieldkey == 'vendor_submit_product' )  {
				delete_user_meta($user_id, '_vendor_submit_product');
			} else if(!isset( $_POST['vendor_submit_coupon'] ) && $fieldkey == 'vendor_submit_coupon') {
				delete_user_meta($user_id, '_vendor_submit_coupon');
			} else if(!isset( $_POST['vendor_hide_description'] ) && $fieldkey == 'vendor_hide_description') {
				delete_user_meta($user_id, '_vendor_hide_description');
			} else if(!isset( $_POST['vendor_hide_address'] ) && $fieldkey == 'vendor_hide_address') {
				delete_user_meta($user_id, '_vendor_hide_address');
			}	else if(!isset( $_POST['vendor_hide_message_to_buyers'] ) && $fieldkey == 'vendor_hide_message_to_buyers') {
				delete_user_meta($user_id, '_vendor_hide_message_to_buyers');
			}else if(!isset( $_POST['vendor_hide_phone'] ) && $fieldkey == 'vendor_hide_phone') {
				delete_user_meta($user_id, '_vendor_hide_phone');
			} else if(!isset( $_POST['vendor_hide_email'] ) && $fieldkey == 'vendor_hide_email') {
				delete_user_meta($user_id, '_vendor_hide_email');
			} else if(!isset( $_POST['vendor_give_tax'] ) && $fieldkey == 'vendor_give_tax') {
				delete_user_meta($user_id, '_vendor_give_tax');
			} else if(!isset( $_POST['vendor_give_shipping'] ) && $fieldkey == 'vendor_give_shipping') {
				delete_user_meta($user_id, '_vendor_give_shipping');
			} else if(!isset( $_POST['vendor_turn_off'] ) && $fieldkey == 'vendor_turn_off') {
				delete_user_meta($user_id, '_vendor_turn_off');
			} else if(!isset( $_POST['vendor_publish_product'] ) && $fieldkey == 'vendor_publish_product') {
				delete_user_meta($user_id, '_vendor_publish_product');
				if($WCMp->vendor_caps->vendor_capabilities_settings('is_published_product')  ) {
					$user->add_cap('publish_products');
				}
			} else if(!isset( $_POST['vendor_publish_coupon'] ) && $fieldkey == 'vendor_publish_coupon') {
				if($WCMp->vendor_caps->vendor_capabilities_settings('is_published_coupon')  ) {
					$user->add_cap('publish_shop_coupons');
				}
				delete_user_meta($user_id, '_vendor_publish_coupon');
			}
			else if(!isset( $_POST['vendor_is_policy_off'] ) && $fieldkey == 'vendor_is_policy_off') {
				delete_user_meta($user_id, '_vendor_is_policy_off');
			}
		}
		$this->user_change_cap( $user_id );
		
		if( is_user_wcmp_vendor($user_id) && isset($_POST['role']) && $_POST['role'] != 'dc_vendor' ) {
			$vendor = get_wcmp_vendor( $user_id );
			$user->remove_role( 'dc_vendor' );
			if( $_POST['role'] != 'dc_pending_vendor' ) {
				$user->remove_role( 'dc_pending_vendor' );
			}
			wp_delete_term( $vendor->term_id, 'dc_vendor_shop' );
		}		
	}
	
	/**
		* Delete vendor data on user delete
		* function delete_vendor
		* @access private
		* @param int $user_id
		* @return void
		*/
	function delete_vendor( $user_id ) {
		global $WCMp;
		
  	if( is_user_wcmp_vendor( $user_id ) ) {
  		
  		$vendor = get_wcmp_vendor( $user_id );
			
			do_action( 'delete_dc_vendor', $vendor );
			
			if( isset( $_POST['reassign_user'] ) && ! empty( $_POST['reassign_user'] ) && ( $_POST['delete_option'] == 'reassign' ) ) {
				if( is_user_wcmp_vendor( absint( $_POST['reassign_user'] ) ) ) {
					if( $products = $vendor->get_products( array( 'fields' => 'ids' ) ) ) {
						foreach( $products as $product_id ) {
							$new_vendor = get_wcmp_vendor( absint( $_POST['reassign_user'] ) );
							wp_set_object_terms( $product_id, absint( $new_vendor->term_id ), $WCMp->taxonomy->taxonomy_name );
						}
					}
				} else {
					wp_die( __( 'Select a Vendor.', $WCMp->text_domain ) );
				}
			}
			
			wp_delete_term( $vendor->term_id, $WCMp->taxonomy->taxonomy_name );
		}
	}
	
	/**
	 * Change user capability
	 *
	 * @access public
	 * @return void
	*/	
	function user_change_cap( $user_id ) {
		global $WCMp;
		
		$user = new WP_User( $user_id );
		
		$product_caps = array("edit_product","delete_product","edit_products","edit_others_products","delete_published_products","delete_products","delete_others_products","edit_published_products");
		$is_submit_product = get_user_meta( $user_id, '_vendor_submit_product', true );
		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_product') ) {
			if($is_submit_product) {
				foreach( $product_caps as $product_cap_add ) {
					$user->add_cap( $product_cap_add );
				}
			} 
		}
		if(empty($is_submit_product)) {
			foreach( $product_caps as $product_cap_remove ) {
				$user->remove_cap( $product_cap_remove );
			}
		}
		
		$coupon_caps = array("edit_shop_coupons", "delete_shop_coupons", "edit_shop_coupons", "edit_others_shop_coupons" , "delete_published_shop_coupons", "delete_shop_coupons", "delete_others_shop_coupons"	, "edit_published_shop_coupons");
		$is_submit_coupon = get_user_meta( $user_id, '_vendor_submit_coupon', true );
		if( $WCMp->vendor_caps->vendor_capabilities_settings('is_submit_coupon') ) {
			if($is_submit_coupon) {
				foreach( $coupon_caps as $coupon_cap_add ) {
					$user->add_cap( $coupon_cap_add );
				}
			} 
		}
		if(empty($is_submit_coupon)) {
			foreach( $coupon_caps as $coupon_cap_remove ) {
				$user->remove_cap( $coupon_cap_remove );
			}
		}
	}
	
	function profile_admin_buffer_start() {
		ob_start( array( $this , 'remove_plain_bio' ) );
	}
	
	function profile_admin_buffer_end() {
		$screen = get_current_screen();
		if(in_array( $screen->id, array( 'users' ))) {
			ob_end_flush();
		}
	}
	
	/**
	 * remove_plain_bio
	 *
	 * @access public
	 * @return $buffer
	*/	
	function remove_plain_bio($buffer) {
		$titles = array('#<h3>About Yourself</h3>#','#<h3>About the user</h3>#');
		$buffer=preg_replace($titles,'<h3>Password</h3>',$buffer,1);
		$biotable='#<h3>Password</h3>.+?<table.+?/tr>#s';
		$buffer=preg_replace($biotable,'<h3>Password</h3> <table class="form-table">',$buffer,1);
		return $buffer;
	}
	
	/**
	 * Add vendor form in woocommece registration form
	 *
	 * @access public
	 * @return void
	*/	
	public function wcmp_woocommerce_register_form() {
		global $WCMp;
		$customer_can = $WCMp->vendor_caps->vendor_general_settings('enable_registration');
		if($customer_can) {
			?>
			<tr>
				<p class="form-row form-row-wide">
					<input type="checkbox" name="pending_vendor" value="true"> <?php echo apply_filters('wcmp_vendor_registration_checkbox', __( 'Apply to become a vendor? ', $WCMp->text_domain )); ?>
				</p>
			</tr>
		<?php }
	}
	
	/**
	 * Add vendor form in woocommece registration form
	 *
	 * @access public
	 * @return void
	*/	
	public function wcmp_woocommerce_add_vendor_form() {
		global $WCMp;
		$customer_can = $WCMp->vendor_caps->vendor_general_settings('enable_registration');
		if($customer_can) {
			?>
			<tr>
				<p class="form-row form-row-wide">
					<input type="checkbox" name="pending_vendor" value="true"> <?php echo apply_filters('wcmp_vendor_registration_checkbox', __( 'Apply to become a vendor? ', $WCMp->text_domain ));?>
				</p>
			</tr>
				<tr><input type="submit" name="vendor_apply" value="<?php _e( 'Save', $WCMp->text_domain ) ?>"></tr>
		<?php }
	}
	
	/**
	 * created customer notification
	 *
	 * @access public
	 * @return void
	*/	
	function wcmp_woocommerce_created_customer_notification() {
		if(isset($_POST['pending_vendor']) && !empty($_POST['pending_vendor'])) {
			remove_action('woocommerce_created_customer_notification', array(WC()->mailer(), 'customer_new_account'), 10, 3);
			add_action( 'woocommerce_created_customer_notification', array($this, 'wcmp_customer_new_account'), 10, 3);
		}
	}
	
	/**
	 * Send mail on new vendor creation
	 *
	 * @access public
	 * @return void
	*/	
	function wcmp_customer_new_account( $customer_id, $new_customer_data = array(), $password_generated = false ) {
		if ( ! $customer_id )
			return;
		$user_pass = ! empty( $new_customer_data['user_pass'] ) ? $new_customer_data['user_pass'] : '';
		$email = WC()->mailer()->emails['WC_Email_Vendor_New_Account'];
		$email->trigger( $customer_id, $user_pass, $password_generated );
		$email_admin = WC()->mailer()->emails['WC_Email_Admin_New_Vendor_Account'];
		$email_admin->trigger( $customer_id, $user_pass, $password_generated );
	}
	
	
	
	/**
	 * WCMp Order available emails
	 *
	 * @param array $available_emails
	 * @return available_emails
	 */
	public function wcmp_order_emails_available( $available_emails )	{
		$available_emails[ ] = 'vendor_new_order';

		return $available_emails;
	}
	
	/**
	 * update_vendor_meta
	 *
	 * @param  $user_id
	 */
	public function update_vendor_meta($user_id) {
		update_user_meta($user_id, '_vendor_submit_product', 'Enable');
		update_user_meta($user_id, '_vendor_submit_coupon', 'Enable');
		
		update_user_meta($user_id, '_vendor_image', '');
		update_user_meta($user_id, '_vendor_banner', '');
		update_user_meta($user_id, '_vendor_address_1', '');
		update_user_meta($user_id, '_vendor_city', '');
		update_user_meta($user_id, '_vendor_state', '');
		update_user_meta($user_id, '_vendor_country', '');
		update_user_meta($user_id, '_vendor_phone', '');
		update_user_meta($user_id, '_vendor_postcode', '');
	}
}
?>