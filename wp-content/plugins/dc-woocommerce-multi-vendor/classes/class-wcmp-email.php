<?php
/**
 * WCMp Email Class
 *
 * @version		2.2.0
 * @package		WCMp
 * @author 		DualCube
 */
 
class WCMp_Email {
	
	public function __construct() {		
	  global $WCMp;
	  add_action( 'woocommerce_email_customer_details', array( $this, 'wcmp_vendor_messages_customer_support' ), 30, 3 );	 
	}
	
	public function wcmp_vendor_messages_customer_support( $order, $sent_to_admin = false, $plain_text = false ) {
		global $WCMp;
		$items = $order->get_items( 'line_item' );
		$vendor_array = array();
		$author_id = '';
		$capability_settings = get_option('wcmp_capabilities_settings_name');
		$customer_support_details_settings = get_option('wcmp_general_customer_support_details_settings_name');
		$is_csd_by_admin = '';
		
		foreach( $items as $item_id => $item ) {			
			$product_id = $order->get_item_meta( $item_id, '_product_id', true );
			if( $product_id ) {				
				$author_id = $order->get_item_meta( $item_id, '_vendor_id', true );
				if( empty($author_id) ) {
					$product_vendors = get_wcmp_product_vendors($product_id);
					if(isset($product_vendors) && (!empty($product_vendors))) {
						$author_id = $product_vendors->id;
					}
					else {
						$author_id = get_post_field('post_author', $product_id);
					}
				}
				if(isset($vendor_array[$author_id])){
					$vendor_array[$author_id] = $vendor_array[$author_id].','.$item['name'];
				}
				else {
					$vendor_array[$author_id] = $item['name'];
				}								
			}						
		}		
		if($plain_text) {
			
		}
		else {		
			if( isset( $capability_settings['can_vendor_add_message_on_email_and_thankyou_page'] ) ) {
				$WCMp->template->get_template( 'vendor_message_to_buyer.php', array( 'vendor_array'=>$vendor_array, 'capability_settings'=>$capability_settings, 'customer_support_details_settings'=>$customer_support_details_settings ));
			}
			elseif(isset($customer_support_details_settings['is_customer_support_details'])) {
				$WCMp->template->get_template( 'customer_support_details_to_buyer.php', array( 'vendor_array'=>$vendor_array, 'capability_settings'=>$capability_settings, 'customer_support_details_settings'=>$customer_support_details_settings ));
			}
		}		
	}
	
	public function get_custom_support_message_by_vendor_id($vendor_id, $products) {
		global $WCMp;
		$html = '';
		$user_meta = get_user_meta( $vendor_id );
		$capability_settings = get_option('wcmp_capabilities_settings_name');
		ob_start();
		echo '<td valign="top" align="left" style=" background:#f4f4f4; padding:0px 40px"><h3 style="color:#557da1;display:block;font-family:Arial,sans-serif; font-size:16px;font-weight:bold;line-height:130%;margin:16px 0 8px;text-align:left">';
		echo __('Customer Support Details of : ',$WCMp->text_domain);
		echo '<span style="color:#555;">';
		echo $products;
		echo '</span>';
		echo '<table style="width:100%;vertical-align:top;color:#a4a4a4; padding:10px 0 20px 0" border="0" cellpadding="2" cellspacing="0" >';
		echo '<tr>';
		echo '<td valign="top" align="left" >';
		echo __('Email : ',$WCMp->text_domain); 
		echo '</td>';
		echo '<td valign="top" align="left" >: <a style="color:#505050;" href="mailto:'.$user_meta['_vendor_customer_email'][0].'" target="_blank">';
    echo  $user_meta['_vendor_customer_email'][0];
		echo '</a></td>';
		echo '</tr>';		
		echo '<tr><td valign="top" align="left" >';
		echo  __('Phone : ',$WCMp->text_domain); 
		echo '</td><td valign="top" align="left" >:';
		echo $user_meta['_vendor_customer_phone'][0];
		echo '</td></tr>';		
		echo '<tr><td valign="top" align="left" >';
		echo __('Return Address of : ',$WCMp->text_domain);
		echo '</td><td valign="top" align="left" >: <b>';
		echo  $products;
		echo '</b></td></tr>';		
		echo '<tr><td valign="top" align="left" >';
		echo  __('Address Line 1 : ',$WCMp->text_domain); 
		echo '</td><td valign="top" align="left" >:';
		echo $user_meta['_vendor_csd_return_address1'][0];
		echo '</td></tr>';
    echo '<tr><td valign="top" align="left" >';
    echo  __('Address Line 2 : ',$WCMp->text_domain);
    echo '</td><td valign="top" align="left" >:';
    echo $user_meta['_vendor_csd_return_address2'][0];
    echo '</td></tr>'; 
    echo '<tr><td valign="top" align="left" >';
    echo  __('State : ',$WCMp->text_domain); 
    echo '</td><td valign="top" align="left" >:';
    echo $user_meta['_vendor_csd_return_state'][0];
    echo '</td></tr>'; 
    echo '<tr><td valign="top" align="left" >';
    echo  __('City : ',$WCMp->text_domain);
    echo '</td><td valign="top" align="left" >:';
    echo $user_meta['_vendor_csd_return_city'][0];
    echo '</td></tr>'; 
    echo '<tr><td valign="top" align="left" >';
    echo  __('Country : ',$WCMp->text_domain);  
    echo '</td><td valign="top" align="left" >:';
    echo $user_meta['_vendor_csd_return_country'][0];
    echo '</td></tr>'; 
    echo '<tr><td valign="top" align="left" >';
    echo  __('Zip Code : ',$WCMp->text_domain);
    echo '</td><td valign="top" align="left" >:';
    echo $user_meta['_vendor_csd_return_zip'][0];
    echo '</td></tr>';
		echo '</table></td>'; 	
		$html = ob_get_clean();		
		return $html;
		
	}
	
	public function get_csd_admin_address() {
		global $WCMp;
		$html = '';
		$capability_settings = get_option('wcmp_capabilities_settings_name');		
		ob_start();
		?>
		<table>
			<tr>
				<th colspan="2">
				<?php echo __('Customer Support Details :',$WCMp->text_domain); ?>
				</th>				
			</tr>
			<?php if(isset($capability_settings['csd_email'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Email : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_email']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_phone'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Phone : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_phone']; ?>
				</td>
			</tr>
			<?php }?>
			<tr>
				<th colspan="2">
				<?php echo __('Our Return Address :',$WCMp->text_domain); ?>
				</th>				
			</tr>
			
			<?php if(isset($capability_settings['csd_return_address_1'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Address Line 1 : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_address_1']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_return_address_2'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Address Line 2 : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_address_2']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_return_state'])) { ?>
			<tr>
				<td>
					<b><?php echo __('State : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_state']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_return_city'])) { ?>
			<tr>
				<td>
					<b><?php echo __('City : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_city']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_return_country'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Country : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_country']; ?>
				</td>
			</tr>
			<?php }?>
			<?php if(isset($capability_settings['csd_return_zipcode'])) { ?>
			<tr>
				<td>
					<b><?php echo __('Zip Code : ',$WCMp->text_domain); ?></b>
				</td>
				<td>
					<?php echo $capability_settings['csd_return_zipcode']; ?>
				</td>
			</tr>
			<?php }?>
		</table>				
		<?php	
		$html = ob_get_clean();
		return $html;		
	}
	
	
	
}


