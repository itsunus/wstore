<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @class 		WCMp Product Class
 *
 * @version		2.2.0
 * @package		WCMp
 * @author 		DualCube
*/
class WCMp_Product {

	public function __construct() {
		
		add_action(	'woocommerce_product_write_panel_tabs', array( &$this, 'add_vendor_tab' ), 30);
		add_action(	'woocommerce_product_write_panels', array( &$this, 'output_vendor_tab'), 30);
		add_action(	'save_post', array( &$this, 'process_vendor_data' ) );		
		$settings_product = get_option('wcmp_product_settings_name');
		if(isset($settings_product['is_policy_on'])) {
			if(isset($settings_product['product_level_policies_on'])) {			
				add_action(	'woocommerce_product_write_panel_tabs', array( &$this, 'add_policies_tab' ), 30);
				add_action(	'woocommerce_product_write_panels', array( &$this, 'output_policies_tab'), 30);
				add_action(	'save_post', array( &$this, 'process_policies_data' ) );
			}
			add_filter( 'woocommerce_product_tabs', array( &$this, 'product_policy_tab' ) );
		}		
		add_action( 'woocommerce_ajax_save_product_variations', array($this, 'save_variation_commission') );		
		add_action( 'woocommerce_product_after_variable_attributes', array( &$this, 'add_variation_settings' ), 10, 3 );
		add_filter( 'pre_get_posts', array( &$this, 'convert_business_id_to_taxonomy_term_in_query'));
		if( is_admin() ) {
			add_action( 'transition_post_status',  array( &$this, 'on_all_status_transitions'), 10, 3 );
		}
		add_action( 'woocommerce_product_thumbnails', array( &$this, 'add_report_abuse_link' ), 30 );
		add_filter( 'woocommerce_product_tabs', array( &$this, 'product_vendor_tab' ) );		
		add_filter( 'wp_count_posts', array( &$this, 'vendor_count_products' ), 10, 3 );		
		/* Related Products */
    add_filter( 'woocommerce_related_products_args', array( $this, 'related_products_args' ), 15 );    
    // bulk edit vendor set
    add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'add_product_vendor' ) );
    add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_vendor_bulk_edit' ) );
    
    // Filters
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		add_filter( 'parse_query', array( $this, 'product_vendor_filters_query' ) );
		
		$this->vendor_product_restriction();
	}
	
	function vendor_product_restriction() {
		global $WCMp;
		if(is_ajax()) return;
		$current_user_id = get_current_user_id();		
		if( is_user_wcmp_vendor($current_user_id) ) {
			if( isset($_GET['post']) ) {
				$current_post_id = $_GET['post'];
				if( get_post_type($current_post_id) == 'product' ) {
					if(in_array(get_post_status( $current_post_id ), array('draft', 'publish', 'pending'))) {
						$product_vendor_obj = get_wcmp_product_vendors($current_post_id);
						if( $product_vendor_obj->id != $current_user_id ) {
							wp_redirect(admin_url() . 'edit.php?post_type=product');
							exit;
						}
					}
				} 
				else if( get_post_type($current_post_id) == 'shop_coupon' ) {
					$coupon_obj = get_post($current_post_id);
					if( $coupon_obj->post_author != $current_user_id ) {
						wp_redirect(admin_url() . 'edit.php?post_type=shop_coupon');
						exit;
					}
				}
			}
		}
	}
	
	function product_vendor_filters_query($query) {
		global $typenow, $wp_query;
		
		$taxonomy = 'dc_vendor_shop';
		$q_vars = &$query->query_vars;
		if ( 'product' == $typenow ) {
			if( isset($q_vars['post_type']) && $q_vars['post_type'] == 'product' ) {
				if( isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
					$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
					$q_vars[$taxonomy] = $term->slug;
				}
			}
		}
	}
	
	function restrict_manage_posts() {
		global $typenow, $wp_query;
		
		$post_type = 'product';
		$taxonomy = 'dc_vendor_shop';
		
		if( !is_user_wcmp_vendor(get_current_user_id()) ) {
			if ( 'product' == $typenow ) {
				if ($typenow == $post_type) {
				$selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
				$info_taxonomy = get_taxonomy($taxonomy);
				wp_dropdown_categories(array(
					'show_option_all' => __("Show All {$info_taxonomy->label}"),
					'taxonomy' => $taxonomy,
					'name' => $taxonomy,
					'orderby' => 'name',
					'selected' => $selected,
					'show_count' => true,
					'hide_empty' => true,
				));
			};
			}
		}
	}
	
	/**
	 * Save product vendor by bluk edit
	 *
	 * @param object $product
	 */
	function save_vendor_bulk_edit($product) {
		global $WCMp;
		
		$product_id = $product->id;
		
		$current_user_id = get_current_user_id();
		if( !is_user_wcmp_vendor($current_user_id) ) {
		
			if( isset($_REQUEST['choose_vendor_bulk']) && !empty($_REQUEST['choose_vendor_bulk']) ) {
				if( is_numeric($_REQUEST['choose_vendor_bulk']) ) {
					$vendor_term = $_REQUEST['choose_vendor_bulk'];
					
					$term = get_term( $vendor_term , 'dc_vendor_shop' );
					//wp_delete_object_term_relationships( $product_id, 'dc_vendor_shop' );
					wp_set_post_terms( $product_id, $term->name , 'dc_vendor_shop', false );
				
					$vendor = get_wcmp_vendor_by_term($vendor_term);
					if ( ! wp_is_post_revision( $product_id ) ) {
						// unhook this function so it doesn't loop infinitely
						remove_action('save_post', array($this, 'process_vendor_data'));
						// update the post, which calls save_post again
						wp_update_post( array('ID' => $post_id, 'post_author'  => $vendor->id) );
						// re-hook this function
						add_action('save_post', array($this, 'process_vendor_data'));
					}
				}
			}
		}
	}
	
	/**
	 * Add product vendor
	 */
	function add_product_vendor() {
		global $WCMp;
		
		$current_user_id = get_current_user_id();
		if( !is_user_wcmp_vendor($current_user_id) ) {
		
			?>
				<label>
					<span class="title"><?php esc_html_e( 'Vendor', $WCMp->text_domain ); ?></span>
						<span class="input-text-wrap vendor_bulk">
							<select name="choose_vendor_bulk" id="choose_vendor_ajax_bulk" class="ajax_chosen_select_vendor" data-placeholder="<?php _e('Search for vendor', $WCMp->text_domain ) ?>" style="width:300px;" >
								<option value="0"><?php _e( "Choose a vendor", $WCMp->text_domain ) ?></option>
							</select>
						</span>
					</span>
				</label>
				
			<?php
		}
	}
	
	/**
	 * Show related products or not
	 *
	 * @return arg
	 */
	function related_products_args($args ) {
		global $product, $WCMp;
		
		$vendor = get_wcmp_product_vendors( $product->id );
		

		if( ! $vendor ) {
			return $args;
		}

		$frontend_cap_arr = $WCMp->vendor_caps->frontend_cap;		
		if( array_key_exists('show_related_products', $frontend_cap_arr) ) {
			$related = $frontend_cap_arr['show_related_products'];
		}
		
		if(!$related) {
			return $args;
		} else if('disable' == $related) {
			return false;
		} elseif( 'all_related' == $related ) {
			return $args;
		} elseif( 'vendors_related' == $related ){
			$vendor_products = $vendor->get_products();
			$vendor_product_ids = array();
			if(!empty($vendor_products)) {
				foreach($vendor_products as $vendor_product) {
					$vendor_product_ids[] = $vendor_product->ID;
				}
			}
			$args['post__in'] = $vendor_product_ids;
			return $args;
		}
  }
	
  /**
   * Filter product list as per vendor
   */
	public function filter_products_list( $request ) {
		global $typenow;

		$current_user = wp_get_current_user();

		if ( is_admin() && is_user_wcmp_vendor($current_user) && 'product' == $typenow ) {
				$request[ 'author' ] = $current_user->ID;
				$term_id = get_user_meta($current_user->ID, '_vendor_term_id', true);
				$taxquery = array(
						array(
								'taxonomy' => 'dc_vendor_shop',
								'field' => 'id',
								'terms' => array( $term_id ),
								'operator'=> 'IN'
						)
				);
	
			$request['tax_query'] = $taxquery;
		}
		
		return $request;
	}

  /**
   * Count vendor products
   */
	public function vendor_count_products( $counts, $type, $perm ) {
		$current_user = wp_get_current_user();

		if ( is_user_wcmp_vendor($current_user) && 'product' == $type ) {
			$term_id = get_user_meta($current_user->ID, '_vendor_term_id', true);
			
			$args = array(
				'post_type' => $type,
				'tax_query' => array(
					array(
							'taxonomy' => 'dc_vendor_shop',
							'field' => 'id',
							'terms' => array( $term_id ),
							'operator'=> 'IN'
					),
				),
			);

			/**
			 * Get a list of post statuses.
			 */
			$stati = get_post_stati();

			// Update count object
			foreach ( $stati as $status ) {
					$args['post_status'] = $status;
					$query = new WP_Query( $args );
					$posts = $query->get_posts();
					$counts->$status     = count( $posts );
			}
		}

		return $counts;
	}
	
	/**
	* Notify admin on publish product by vendor
	*
	* @return void
	*/
	function on_all_status_transitions( $new_status, $old_status, $post ) {
		if ( $new_status != $old_status && $post->post_status == 'pending') {
			$current_user = get_current_user_id();
			if($current_user) $current_user_is_vendor = is_user_wcmp_vendor($current_user);  
			if($current_user_is_vendor) {
				//send mails to admin for new vendor product
				$vendor = get_wcmp_vendor_by_term(get_user_meta( $current_user, '_vendor_term_id', true ));
				$email_admin = WC()->mailer()->emails['WC_Email_Vendor_New_Product_Added'];
				$email_admin->trigger( $post->post_id, $post, $vendor );
			}
		} else if( $new_status != $old_status &&  $post->post_status == 'publish' ) {
			$current_user = get_current_user_id();
			if($current_user) $current_user_is_vendor = is_user_wcmp_vendor($current_user);  
			if($current_user_is_vendor) {
				//send mails to admin for new vendor product
				$vendor = get_wcmp_vendor_by_term(get_user_meta( $current_user, '_vendor_term_id', true ));
				$email_admin = WC()->mailer()->emails['WC_Email_Vendor_New_Product_Added'];
				$email_admin->trigger( $post->post_id, $post, $vendor );
			}
		} 
		if( current_user_can('administrator') && $new_status != $old_status &&  $post->post_status == 'publish') { 
			if( isset($_POST['choose_vendor'] ) && !empty($_POST['choose_vendor'])) {
				$term = get_term( $_POST['choose_vendor'] , 'dc_vendor_shop' );
				if($term) {
				  $vendor = get_wcmp_vendor_by_term( $term->term_id );
				  $email_admin = WC()->mailer()->emails['WC_Email_Admin_Added_New_Product_to_Vendor'];
				  $email_admin->trigger( $post->post_id, $post, $vendor );
				 }
			}
		}
	}

	/**
	* Add Vendor tab in single product page 
	*
	* @return void
	*/
	function add_vendor_tab() { 
		global $WCMp;
		?>
		<li class="vendor_icon vendor_icons"><a href="#choose_vendor"><?php _e( 'Vendor', $WCMp->text_domain ); ?></a></li>
	<?php }
		
	/**
	* Output of Vendor tab in single product page 
	*
	* @return void
	*/
	function output_vendor_tab() {
		global $post, $WCMp, $woocommerce;
		$html = '';
		$vendor = get_wcmp_product_vendors($post->ID); 
		$commission_per_poduct = get_post_meta($post->ID, '_commission_per_product', true);
		$current_user = get_current_user_id();
		if($current_user) $current_user_is_vendor = is_user_wcmp_vendor($current_user);  
		$html .= '<div class="options_group" > <table class="form-field form-table">' ;
		$html .= '<tbody>';
		if( $vendor ) {
			$option = '<option value="' . $vendor->term_id . '" selected="selected">' . $vendor->user_data->user_login . '</option>';
		} else if($current_user_is_vendor) {
			$vendor = get_wcmp_vendor_by_term(get_user_meta( $current_user, '_vendor_term_id', true ));
			$option = '<option value="' . $vendor->term_id . '" selected="selected">' . $vendor->user_data->user_login . '</option>';
		} else {
			$option = '<option>' . __( "Choose a vendor", $WCMp->text_domain ) . '</option>';
		}
		$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for="' . esc_attr( 'vendor' ) . '">' . __( "Vendor", $WCMp->text_domain ) . '</label></td><td>';
		if(!$current_user_is_vendor) {
			$html .= '<select name="' . esc_attr( 'choose_vendor' ) . '" id="' . esc_attr( 'choose_vendor_ajax' ) . '" class="ajax_chosen_select_vendor" data-placeholder="' . __( "Search for vendor", $WCMp->text_domain ) . '" style="width:300px;" >' . $option . '</select>' ;
			$html .= '<p class="description">' . 'choose vendor' . '</p>' ;
		} else {
			$html .= '<label id="vendor-label" for="' . esc_attr( 'vendor' ) . '">' . $vendor->user_data->user_login . '</label>';
			$html .= '<input type="hidden" name="' . esc_attr( 'choose_vendor' ) . '"   value="' . $vendor->term_id. '" />';
		}
		$html .= '</td><tr/>' ;
		
		$commission_percentage_per_poduct = get_post_meta($post->ID, '_commission_percentage_per_product', true);
		$commission_fixed_with_percentage = get_post_meta($post->ID, '_commission_fixed_with_percentage', true);
		$commission_fixed_with_percentage_qty = get_post_meta($post->ID, '_commission_fixed_with_percentage_qty', true);
		if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage') {
			
			if(!$current_user_is_vendor) {
				$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Percentage", $WCMp->text_domain ) . '</label></td><td>';
				$html .= '<input class="input-commision" type="text" name="commission_percentage" value="'.$commission_percentage_per_poduct.'"% />';
			} else {
				if(!empty($commission_percentage_per_poduct)) {
					$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Percentage", $WCMp->text_domain ) . '</label></td><td>';
					$html .= '<span>'.$commission_percentage_per_poduct.'%</span>';
				}
			}
			$html .= '</td></tr>';
			
			if(!$current_user_is_vendor) {
				$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Fixed per transaction", $WCMp->text_domain ) . '</label></td><td>';
				$html .= '<input class="input-commision" type="text" name="fixed_with_percentage" value="'.$commission_fixed_with_percentage.'" />';
			} else {
				if(!empty($commission_fixed_with_percentage)) {
					$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Fixed per transaction", $WCMp->text_domain ) . '</label></td><td>';
					$html .= '<span>'.$commission_fixed_with_percentage.'</span>';
				}
			}
			$html .= '</td></tr>';
			
			
		} else if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage_qty') {
			
			if(!$current_user_is_vendor) {
				$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Percentage", $WCMp->text_domain ) . '</label></td><td>';
				$html .= '<input class="input-commision" type="text" name="commission_percentage" value="'.$commission_percentage_per_poduct.'"% />';
			} else {
				if(!empty($commission_percentage_per_poduct)) {
					$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission Percentage", $WCMp->text_domain ) . '</label></td><td>';
					$html .= '<span>'.$commission_percentage_per_poduct.'%</span>';
				}
			}
			$html .= '</td></tr>';	
			
			if(!$current_user_is_vendor) {
				$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "fixed amount">' . __( "Commission Fixed per unit", $WCMp->text_domain ) . '</label></td><td>';
				$html .= '<input class="input-commision" type="text" name="fixed_with_percentage_qty" value="'.$commission_fixed_with_percentage_qty.'" />';
			} else {
				if(!empty($commission_fixed_with_percentage_qty)) {
					$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "fixed amount">' . __( "Commission Fixed per unit", $WCMp->text_domain ) . '</label></td><td>';
					$html .= '<span>'.$commission_fixed_with_percentage_qty.'</span>';
				}
			}
			$html .= '</td></tr>';
			
		} else {
			
			if(!$current_user_is_vendor) {
				$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission", $WCMp->text_domain ) . '</label></td><td>';
				$html .= '<input class="input-commision" type="text" name="commision" value="'.$commission_per_poduct.'" />';
			} else {
				if(!empty($commission_per_poduct)) {
					$html .= '<tr valign="top"><td scope="row"><label id="vendor-label" for= "Commission">' . __( "Commission", $WCMp->text_domain ) . '</label></td><td>';
					$html .= '<span>'.$commission_per_poduct.'</span>';
				}
			}
			$html .= '</td></tr>';	
		}
		
		if($vendor) {
			if ( current_user_can( 'manage_options' ) ) {
				$html .= '<tr valign="top"><td scope="row"><input type="button" class="delete_vendor_data button" value="' . __("Unassign vendor", $WCMp->text_domain) . '" /></td></tr>';
				
				wp_localize_script( 'commission_js', 'unassign_vendors_data', array('current_product_id' => $post->ID) );
				
			}
		}
		
		$html .= '</tbody>' ;
		$html .= '</table>';
		$html .= '</div>' ;
		?>
		<div id="choose_vendor" class="panel woocommerce_options_panel">
			<?php echo	$html; ?>
		</div>
	<?php }
	
	
	
	function add_policies_tab() {
		global $WCMp;
		?>
		<li class="policy_icon policy_icons"><a href="#set_policies"><?php _e( 'Policies', $WCMp->text_domain ); ?></a></li>
		<?php		
	}
	
	
	function output_policies_tab() {
		global $post, $WCMp, $woocommerce;
		$_wcmp_enable_policy_tab  = get_post_meta($post->ID,'_wcmp_enable_policy_tab',true) ? get_post_meta($post->ID,'_wcmp_enable_policy_tab',true) : '';
		$_wcmp_cancallation_policy  = get_post_meta($post->ID,'_wcmp_cancallation_policy',true) ? get_post_meta($post->ID,'_wcmp_cancallation_policy',true) : '';
		$_wcmp_refund_policy  = get_post_meta($post->ID,'_wcmp_refund_policy',true) ? get_post_meta($post->ID,'_wcmp_refund_policy',true) : '';
		$_wcmp_shipping_policy  = get_post_meta($post->ID,'_wcmp_shipping_policy',true) ? get_post_meta($post->ID,'_wcmp_shipping_policy',true) : '';
		?>
		<div id="set_policies" class="panel woocommerce_options_panel">
			<div class="options_group" >
				 <table class="form-field form-table">
				 	 <tbody>
				 	 		<tr>
				 	 	  	<td>
				 	 	  		<p><strong><?php echo __('Hide From Product Page'); ?> : </strong> &nbsp;&nbsp;<input type="checkbox" value="Enable" name="_wcmp_enable_policy_tab" <?php if($_wcmp_enable_policy_tab != ''){?> checked="Checked" <?php }?> ></p>				 	 	      		 	 	      
				 	 	    </td>				 	 	  
				 	 	  </tr>	
				 	 	  <tr>
				 	 	  	<td>
				 	 	  		<p><strong><?php echo __('Cancellation/Return/Exchange Policy'); ?> : </strong></p>
				 	 	      <textarea class="widefat" name="_wcmp_cancallation_policy"  ><?php echo $_wcmp_cancallation_policy; ?></textarea>		 	 	      
				 	 	    </td>				 	 	  
				 	 	  </tr>
				 	 	  <tr>
				 	 	  	<td>
				 	 	  		<p><strong><?php echo __('Refund Policy'); ?> : </strong></p>
				 	 	      <textarea class="widefat" name="_wcmp_refund_policy"  ><?php echo $_wcmp_refund_policy; ?></textarea>		 	 	      
				 	 	    </td>				 	 	  
				 	 	  </tr>
				 	 	  <tr>
				 	 	  	<td>
				 	 	  		<p><strong><?php echo __('Shipping Policy'); ?> : </strong></p>
				 	 	      <textarea class="widefat" name="_wcmp_shipping_policy"  ><?php echo $_wcmp_shipping_policy; ?></textarea>		 	 	      
				 	 	    </td>				 	 	  
				 	 	  </tr>
				 	 </tbody>
				 </table>			
			</div>
		</div>
		
		<?php	
	}
	
	function process_policies_data($post_id) {
		$post = get_post( $post_id );
		if( $post->post_type == 'product' ) {			
			if(isset($_POST['_wcmp_enable_policy_tab'])) {				
				update_post_meta( $post_id, '_wcmp_enable_policy_tab', $_POST['_wcmp_enable_policy_tab'] );
			}
			else {
				update_post_meta( $post_id, '_wcmp_enable_policy_tab', '' );
			}
			if(isset($_POST['_wcmp_cancallation_policy'])) {
				
				update_post_meta( $post_id, '_wcmp_cancallation_policy', $_POST['_wcmp_cancallation_policy'] );
			}
			if(isset($_POST['_wcmp_refund_policy'])) {
				update_post_meta( $post_id, '_wcmp_refund_policy', $_POST['_wcmp_refund_policy'] );
			}
			if(isset($_POST['_wcmp_shipping_policy'])) {
				update_post_meta( $post_id, '_wcmp_shipping_policy', $_POST['_wcmp_shipping_policy'] );
			}			
		}		
	}

	/**
	* Save vendor related data
	*
	* @return void
	*/
	function process_vendor_data( $post_id ) {
		$post = get_post( $post_id );
		
		if( $post->post_type == 'product' ) {
			if(isset($_POST['commision'])) {
				update_post_meta( $post_id, '_commission_per_product', $_POST['commision'] );
			} 
			
			if(isset($_POST['commission_percentage'])) {
				update_post_meta( $post_id, '_commission_percentage_per_product', $_POST['commission_percentage'] );
			}
			
			if(isset($_POST['fixed_with_percentage_qty'])) {
				update_post_meta( $post_id, '_commission_fixed_with_percentage_qty', $_POST['fixed_with_percentage_qty'] );
			}
			
			if(isset($_POST['fixed_with_percentage'])) {
				update_post_meta( $post_id, '_commission_fixed_with_percentage', $_POST['fixed_with_percentage'] );
			}
			
			if( isset($_POST['choose_vendor'] ) && !empty($_POST['choose_vendor'])) {
				$term = get_term( $_POST['choose_vendor'] , 'dc_vendor_shop' );
				wp_delete_object_term_relationships( $post_id, 'dc_vendor_shop' );
				wp_set_post_terms( $post_id, $term->name , 'dc_vendor_shop', true );
				
				$vendor = get_wcmp_vendor_by_term($_POST['choose_vendor']);
				if ( ! wp_is_post_revision( $post_id ) ){
					// unhook this function so it doesn't loop infinitely
					remove_action('save_post', array($this, 'process_vendor_data'));
					// update the post, which calls save_post again
					wp_update_post( array('ID' => $post_id, 'post_author'  => $vendor->id) );
					// re-hook this function
					add_action('save_post', array($this, 'process_vendor_data'));
				}
			}
			
			if(isset( $_POST['variable_post_id'] ) && !empty( $_POST['variable_post_id'] )) {
				foreach( $_POST['variable_post_id'] as $post_key => $value ) {
					if( isset($_POST['variable_product_vendors_commission'][$post_key]) ) {
						$commission = $_POST['variable_product_vendors_commission'][$post_key];
						update_post_meta( $value , '_product_vendors_commission' , $commission );
					}
					
					if( isset($_POST['variable_product_vendors_commission_percentage'][$post_key]) ) {
						$commission = $_POST['variable_product_vendors_commission_percentage'][$post_key];
						update_post_meta( $value , '_product_vendors_commission_percentage' , $commission );
					}
					
					if( isset($_POST['variable_product_vendors_commission_fixed_per_trans'][$post_key]) ) {
						$commission = $_POST['variable_product_vendors_commission_fixed_per_trans'][$post_key];
						update_post_meta( $value , '_product_vendors_commission_fixed_per_trans' , $commission );
					}
					
					if( isset($_POST['variable_product_vendors_commission_fixed_per_qty'][$post_key]) ) {
						$commission = $_POST['variable_product_vendors_commission_fixed_per_qty'][$post_key];
						update_post_meta( $value , '_product_vendors_commission_fixed_per_qty' , $commission );
					}
					
					if( isset($_POST['dc_variable_shipping_class'][$post_key]) ) {
						$_POST['dc_variable_shipping_class'][$post_key] = ! empty( $_POST['dc_variable_shipping_class'][$post_key] ) ? (int) $_POST['dc_variable_shipping_class'][$post_key] : '';
						$array = wp_set_object_terms( $value, $_POST['dc_variable_shipping_class'][$post_key], 'product_shipping_class');
						unset($_POST['dc_variable_shipping_class'][$post_key]);
					}
				}
			}
		}
	}
	
	/**
	 * Save variation product commission
	 *
	 * @return void
	 */
	function save_variation_commission() {
		if(isset( $_POST['variable_post_id'] ) && !empty( $_POST['variable_post_id'] )) {
			foreach( $_POST['variable_post_id'] as $post_key => $value ) {
				if( isset($_POST['variable_product_vendors_commission'][$post_key]) ) {
					$commission = $_POST['variable_product_vendors_commission'][$post_key];
					update_post_meta( $value , '_product_vendors_commission' , $commission );
					unset($_POST['variable_product_vendors_commission'][$post_key]);
				}
				
				if( isset($_POST['variable_product_vendors_commission_percentage'][$post_key]) ) {
					$commission = $_POST['variable_product_vendors_commission_percentage'][$post_key];
					update_post_meta( $value , '_product_vendors_commission_percentage' , $commission );
					unset($_POST['variable_product_vendors_commission_percentage'][$post_key]);
				}
				
				if( isset($_POST['variable_product_vendors_commission_fixed_per_trans'][$post_key]) ) {
					$commission = $_POST['variable_product_vendors_commission_fixed_per_trans'][$post_key];
					update_post_meta( $value , '_product_vendors_commission_fixed_per_trans' , $commission );
					unset($_POST['variable_product_vendors_commission_fixed_per_trans'][$post_key]);
				}
				
				if( isset($_POST['variable_product_vendors_commission_fixed_per_qty'][$post_key]) ) {
					$commission = $_POST['variable_product_vendors_commission_fixed_per_qty'][$post_key];
					update_post_meta( $value , '_product_vendors_commission_fixed_per_qty' , $commission );
					unset($_POST['variable_product_vendors_commission_fixed_per_qty'][$post_key]);
				}
				if( isset($_POST['dc_variable_shipping_class'][$post_key]) ) {
					$_POST['dc_variable_shipping_class'][$post_key] = ! empty( $_POST['dc_variable_shipping_class'][$post_key] ) ? (int) $_POST['dc_variable_shipping_class'][$post_key] : '';
					$array = wp_set_object_terms( $value, $_POST['dc_variable_shipping_class'][$post_key], 'product_shipping_class');
					unset($_POST['dc_variable_shipping_class'][$post_key]);
				}
			}
		}
	}
	
	/**
	* Save vendor related data for variation
	*
	* @return void
	*/
	public function add_variation_settings( $loop, $variation_data, $variation ) {
		global $WCMp;
		
		$html = '';
		
		$html .= '<p class="form-row hide_if_variation_virtual form-row-full"><label>'. __( 'Shipping class:', $WCMp->text_domain ).'</label>';
		$classes = get_the_terms( $variation->ID, 'product_shipping_class' );
		if ( $classes && ! is_wp_error( $classes ) ) {
			$current_shipping_class = current( $classes )->term_id;
		} else {
			$current_shipping_class = false;
		}
		$product_shipping_class = get_terms( 'product_shipping_class', array('hide_empty' => 0));
		$current_user_id = get_current_user_id();
		$option = '<option value="-1">Same as parent</option>';
		
		if(!empty($product_shipping_class)) {
			$shipping_option_array = array();
			$vednor_shipping_option_array = array();
			foreach($product_shipping_class as $product_shipping) {
				$vendor_shipping_data = get_user_meta($current_user_id, 'vendor_shipping_data', true);		
				if(is_user_wcmp_vendor($current_user_id) ) {
					$vendor_id = get_woocommerce_term_meta( $product_shipping->term_id, 'vendor_id', true );
					if(!$vendor_id)	{
						$vednor_shipping_option_array[$product_shipping->term_id] = $product_shipping->name;
					} else {
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
		$html .= '<select name="dc_variable_shipping_class['.$loop.']" id="dc_variable_shipping_class['.$loop.']" class="postform">';
		$html .= $option;
		$html .= '</select>';
		
		$commission = $commission_percentage = $commission_fixed_per_trans = $commission_fixed_per_qty = '';
		$commission = get_post_meta($variation->ID, '_product_vendors_commission', true );
		$commission_percentage = get_post_meta($variation->ID, '_product_vendors_commission_percentage', true );
		$commission_fixed_per_trans = get_post_meta($variation->ID, '_product_vendors_commission_fixed_per_trans', true );
		$commission_fixed_per_qty = get_post_meta($variation->ID, '_product_vendors_commission_fixed_per_qty', true );
		
		if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage') {
			
			if( is_user_wcmp_vendor(get_current_user_id()) ) {
				if( isset($commission_percentage) && !empty($commission_percentage) ) {
					$html .= '<tr>
											<td>
												<div class="_product_vendors_commission_percentage">
													<label for="_product_vendors_commission_percentage_' . $loop . '">' . __( 'Commission (percentage)', $WCMp->text_domain ) . ':</label>
													<span class="variable_commission_cls">' . $commission_percentage . '</span>
												</div>
											</td>
										</tr>';
				}
				if( isset($commission_percentage) && !empty($commission_percentage) ) {
					$html .= '<tr>
											<td>
												<div class="_product_vendors_commission_fixed_per_trans">
													<label for="_product_vendors_commission_fixed_per_trans_' . $loop . '">' . __( 'Commission (fixed) Per Transaction', $WCMp->text_domain ) . ':</label>
													<span class="variable_commission_cls">' . $commission_fixed_per_trans . '</span>
												</div>
											</td>
										</tr>';
				}
			} else {
				$html .= '<tr>
										<td>
											<div class="_product_vendors_commission_percentage">
												<label for="_product_vendors_commission_percentage_' . $loop . '">' . __( 'Commission (percentage)', $WCMp->text_domain ) . ':</label>
												<input size="4" type="text" name="variable_product_vendors_commission_percentage[' . $loop . ']" id="_product_vendors_commission_percentage_' . $loop . '" value="' . $commission_percentage . '" />
											</div>
										</td>
									</tr>';
				$html .= '<tr>
										<td>
											<div class="_product_vendors_commission_fixed_per_trans">
												<label for="_product_vendors_commission_fixed_per_trans_' . $loop . '">' . __( 'Commission (fixed) Per Transaction', $WCMp->text_domain ) . ':</label>
												<input size="4" type="text" name="variable_product_vendors_commission_fixed_per_trans[' . $loop . ']" id="_product_vendors_commission_fixed_per_trans__' . $loop . '" value="' . $commission_fixed_per_trans . '" />
											</div>
										</td>
									</tr>';
			}
								
		} else if($WCMp->vendor_caps->payment_cap['commission_type'] == 'fixed_with_percentage_qty') {
			
			if( is_user_wcmp_vendor(get_current_user_id()) ) {
				if( isset($commission_percentage) && !empty($commission_percentage) ) {
					$html .= '<tr>
											<td>
												<div class="_product_vendors_commission_percentage">
													<label for="_product_vendors_commission_percentage_' . $loop . '">' . __( 'Commission Percentage', $WCMp->text_domain ) . ':</label>
													<span class="variable_commission_cls">' . $commission_percentage . '</span>
												</div>
											</td>
										</tr>';
				}

				if( isset($commission_fixed_per_qty) && !empty($commission_fixed_per_qty) ) {
					$html .= '<tr>
										<td>
											<div class="_product_vendors_commission_fixed_per_qty">
												<label for="_product_vendors_commission_fixed_per_qty_' . $loop . '">' . __( 'Commission Fixed per unit', $WCMp->text_domain ) . ':</label>
												<span class="variable_commission_cls">' . $commission_fixed_per_qty . '</span>
											</div>
										</td>
									</tr';
				}
			} else {
				$html .= '<tr>
										<td>
											<div class="_product_vendors_commission_percentage">
												<label for="_product_vendors_commission_percentage_' . $loop . '">' . __( 'Commission Percentage', $WCMp->text_domain ) . ':</label>
												<input size="4" type="text" name="variable_product_vendors_commission_percentage[' . $loop . ']" id="_product_vendors_commission_percentage_' . $loop . '" value="' . $commission_percentage . '" />
											</div>
										</td>
									</tr>';

				$html .= '<tr>
										<td>
											<div class="_product_vendors_commission_fixed_per_qty">
												<label for="_product_vendors_commission_fixed_per_qty_' . $loop . '">' . __( 'Commission Fixed per unit', $WCMp->text_domain ) . ':</label>
												<input size="4" type="text" name="variable_product_vendors_commission_fixed_per_qty[' . $loop . ']" id="_product_vendors_commission_fixed_per_qty__' . $loop . '" value="' . $commission_fixed_per_qty . '" />
											</div>
										</td>
									</tr';
			}
			
		} else {
			if( is_user_wcmp_vendor(get_current_user_id()) ) {
				if( isset($commission) && !empty($commission) ) {
					$html .= '<tr>
											<td>
												<div class="_product_vendors_commission">
													<label for="_product_vendors_commission_' . $loop . '">' . __( 'Commission', $WCMp->text_domain ) . ':</label>
													<span class="variable_commission_cls">' . $commission . '</span>
												</div>
											</td>
										</tr>';
				}
			} else {
				$html .= '<tr>
										<td>
											<div class="_product_vendors_commission">
												<label for="_product_vendors_commission_' . $loop . '">' . __( 'Commission', $WCMp->text_domain ) . ':</label>
												<input size="4" type="text" name="variable_product_vendors_commission[' . $loop . ']" id="_product_vendors_commission_' . $loop . '" value="' . $commission . '" />
											</div>
										</td>
									</tr>';
			}
		}
		
		echo $html;
	}
	
	/**
	* Add vendor tab on single product page
	*
	* @return void
	*/
	function product_vendor_tab( $tabs ) {
		global $product, $WCMp;
		$vendor = get_wcmp_product_vendors( $product->id );
		if( $vendor ) {
			$title = __( 'Vendor', $WCMp->text_domain );
			$tabs['vendor'] = array(
						'title' => $title,
						'priority' => 20,
						'callback' => array($this, 'woocommerce_product_vendor_tab')
					);
		}
		return $tabs;
	}
	
	/**
	* Add vendor tab html
	*
	* @return void
	*/
	function woocommerce_product_vendor_tab() {
		global $woocommerce, $WCMp;
		$WCMp->template->get_template( 'vendor_tab.php' );
	}
	
	/**
	* Add policies tab on single product page
	*
	* @return void
	*/
	function product_policy_tab( $tabs ) {
		global $product, $WCMp;
		$product_settings = get_option('wcmp_product_settings_name');		
		$product_level_policies_on = isset($product_settings['product_level_policies_on']) ? $product_settings['product_level_policies_on'] : '';
		$policies_can_override_by_vendor = isset($product_settings['policies_can_override_by_vendor']) ? $product_settings['policies_can_override_by_vendor'] : '';
		$title = __( 'Policies', $WCMp->text_domain );
		$product_id = $product->id;
		$product_vendors = get_wcmp_product_vendors($product_id);
		if( $product_vendors ) {
			$author_id = $product_vendors->id;
		}
		else {
			$author_id = get_post_field('post_author',$product_id);
		}
		$tab_title_by_vendor = get_user_meta($author_id, '_vendor_policy_tab_title', true);
		$hide_by_vendor = get_user_meta($author_id, '_vendor_is_policy_off', true);
		
		if( isset($product_settings['policy_tab_title']) && (!empty($product_settings['policy_tab_title'])) )  {
			$title = $product_settings['policy_tab_title'];	
		}
		if($policies_can_override_by_vendor != '' && (!empty($tab_title_by_vendor))) {
			$title = $tab_title_by_vendor;			
		}		
		$is_hide_for_product = get_post_meta( $product->id, '_wcmp_enable_policy_tab', true ) ? get_post_meta( $product->id, '_wcmp_enable_policy_tab', true ) : '';
				
		if( $is_hide_for_product == '' && $hide_by_vendor == '' ) {	
			
			$tabs['policies'] = array(
				'title' => $title,
				'priority' => 30,
				'callback' => array($this, 'woocommerce_product_policies_tab')
			);
		}					
		return $tabs;		
	}
	/**
	* Add Polices tab html
	*
	* @return void
	*/
	
	function woocommerce_product_policies_tab () {
		global $woocommerce, $WCMp;
		$WCMp->template->get_template( 'policies_tab.php' );		
	}
	
	
	/**
	* add tax query on product page
	* @return void
	*/
	function convert_business_id_to_taxonomy_term_in_query($query) {
    global $pagenow;
    if( is_admin() ) {
			if(isset($_GET['post_type']) && $_GET['post_type'] == 'product' && $pagenow == 'edit.php') {
				$current_user_id = get_current_user_id();
				$current_user = get_user_by('id', $current_user_id );
				if(!in_array( 'dc_vendor', $current_user->roles )) return $query;
				$term_id = get_user_meta($current_user_id, '_vendor_term_id', true);
				$taxquery = array(
						array(
								'taxonomy' => 'dc_vendor_shop',
								'field' => 'id',
								'terms' => array( $term_id ),
								'operator'=> 'IN'
						)
				);
		
				$query->set( 'tax_query', $taxquery );
			}
		} else {
			if(isset($query->query['post_type']) && $query->query['post_type'] == 'product') {
				$get_block_array = array();
				$get_blocked = wcmp_get_all_blocked_vendors();
				if(!empty($get_blocked)) {
					foreach($get_blocked as $get_block) {
						$get_block_array[] = (int)$get_block->term_id;
					}
					$taxquery = array(
								array(
										'taxonomy' => 'dc_vendor_shop',
										'field' => 'id',
										'terms' => $get_block_array,
										'operator'=> 'NOT IN'
								)
					);
				
					$query->set( 'tax_query', $taxquery );
				}
			}
		}
		
		return $query;
  }
  
  /**
   * Vendor report abuse option
   */
  function add_report_abuse_link() { 
  	global $product, $WCMp;
  	$is_display = false; 
  	$settings_is_display = $WCMp->vendor_caps->frontend_cap;
  	if(isset($settings_is_display['show_report_abuse'])) {
  		if($settings_is_display['show_report_abuse'] == 'all_products') {
  			$is_display = true;
  			
  		} else if($settings_is_display['show_report_abuse'] == 'only_vendor_products') {
  			
  			if(get_wcmp_product_vendors($product->id)) $is_display = true;
  			
  		} else if($settings_is_display['show_report_abuse'] == 'disable') {
  			
  			$is_display = false;
  			
  		}
  	} else {
  		$is_display = true;
  	}
  	
  	$report_abuse_text = $WCMp->vendor_caps->frontend_cap;
  	if(isset($report_abuse_text['report_abuse_text'])) {
  		$display_text = $report_abuse_text['report_abuse_text'];
  	} else {
  		$display_text = __('Report Abuse', $WCMp->text_domain); 
  	}
  	
  	if($is_display) {
			?>
			<a href="#" id="report_abuse"><?php echo $display_text; ?></a>
			<div id="report_abuse_form" class="simplePopup"> 
				<h3 class="wcmp-abuse-report-title"><?php _e('Report an abuse for product', $WCMp->text_domain) .' '. the_title(); ?> </h3>
				<form action="#" method="post" id="report-abuse" class="report-abuse-form">
					<table>
						<tbody>
							<tr>
								<td>
									<input type="text" class="report_abuse_name" name="report_abuse[name]" value="" style="width: 100%;" placeholder="<?php _e('Name', $WCMp->text_domain); ?>" required="">
								</td>
							</tr>
							<tr>
								<td>
									<input type="email" class="report_abuse_email" name="report_abuse[email]" value="" style="width: 100%;" placeholder="<?php _e('Email', $WCMp->text_domain); ?>" required="">
								</td>
							</tr>
							<tr>
								<td>
									<textarea name="report_abuse[message]" class="report_abuse_msg" rows="5" style="width: 100%;" placeholder="<?php _e('Leave a message explaining the reasons for your abuse report', $WCMp->text_domain); ?>" required=""></textarea>
								</td>
							</tr>
							<tr>
								<td>
									<input type="hidden" class="report_abuse_product_id" value="<?php echo $product->id; ?>">
									<input type="submit" class="submit-report-abuse submit" name="report_abuse[submit]" value="<?php _e('Report', $WCMp->text_domain); ?>">
								</td>
							</tr>
						</tbody>
					</table>
				</form>
			</div> 							
			<?php
		}
  }
  
}
