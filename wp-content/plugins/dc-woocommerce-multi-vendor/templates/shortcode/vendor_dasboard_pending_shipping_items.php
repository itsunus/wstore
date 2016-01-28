<?php
/**
 * The template for displaying vendor dashboard
 *
 * Override this template by copying it to yourtheme/dc-product-vendor/shortcode/vendor_dasboard_pending_shipping_items.php
 *
 * @author 		dualcube
 * @package 	WCMp/Templates
 * @version   2.2.0
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $woocommerce, $WCMp, $wpdb;
$prefix = $wpdb->prefix;
$current_user = wp_get_current_user();
$current_user_id =  $current_user->ID;
$today_date = @date('Y-m-d');
$curent_week_range = wcmp_rangeWeek($today_date);

if($today_or_weekly == 'today') {	
	$pending_orders_items = $wpdb->get_results( "SELECT * FROM ".$prefix."wcmp_vendor_orders WHERE vendor_id = ". $current_user_id ." and `created` like '".$today_date."%' and `commission_id` != 0 and `commission_id` != ''   order by order_id desc LIMIT ".$start.",".$to." ", OBJECT );
}
elseif($today_or_weekly == 'weekly') {
	$pending_orders_items = $wpdb->get_results( "SELECT * FROM ".$prefix."wcmp_vendor_orders WHERE vendor_id = ". $current_user_id ." and `created` >= '".$curent_week_range['start']."' and `created` <= '".$curent_week_range['end']."' and `commission_id` != 0 and `commission_id` != ''  order by order_id desc LIMIT ".$start.",".$to." ", OBJECT );	
}
foreach ($pending_orders_items as $pending_orders_item ) {
	$product_id = $pending_orders_item->product_id;
	$order_id = $pending_orders_item->order_id;
	$commission_id = $pending_orders_item->commission_id;
	$order = wc_get_order($order_id);
	$product_meta = get_post_meta($product_id);
	$product = get_post($product_id);
	$commission_meta = get_post_meta($commission_id);	
	$vendot_term_id = get_user_meta($vendor->id,'_vendor_term_id',true);
	$commission_obj = new WCMp_Calculate_Commission();
	$vendor_commission_data = $vendor->wcmp_vendor_get_order_item_totals($order, $vendot_term_id);
	$shipping_val = $pending_orders_item->shipping; //$vendor_commission_data['shipping_subtotal'];
	$order_item_meta = get_metadata( 'order_item', $pending_orders_item->order_item_id);
	if(empty($shipping_val)){
	  $shipping_val = 0;
	}
	
	
	//echo "<pre>";
	//print_r($vendor_commission_data);
	//echo "</pre>";
	
	
?>
<tr>
	<td align="center" ><?php echo $product->post_title; ?> (<?php if(!empty($product_meta['_sku'][0])) { echo "#".$product_meta['_sku'][0]; } else { echo "#---"; } ?>)</td>
	<td align="center" ><?php echo @date('d/m',strtotime($pending_orders_item->created)); ?></td>
	<td align="center" > <?php if(!empty($product_meta['_length'][0])) { echo $product_meta['_length'][0];}else {echo '--'; } ?>/<?php if(!empty($product_meta['_width'][0])) { echo $product_meta['_width'][0];}else {echo '--'; } ?>/<?php if(!empty($product_meta['_height'][0])) { echo $product_meta['_height'][0];}else {echo '--'; } ?>/<?php if(!empty($product_meta['_weight'][0])) { echo $product_meta['_weight'][0];}else {echo '--'; } ?> </td>
	<td align="left" ><?php echo $order->shipping_address_1; ?>, <?php echo $order->shipping_address_2; ?> <br>
		<?php echo $order->shipping_city; ?> , <?php echo $order->shipping_state; ?> <br/> <?php echo $order->shipping_postcode; ?> , <?php echo $order->shipping_country; ?></td>
	<td align="center" ><?php if(!empty($shipping_val)) { echo get_woocommerce_currency_symbol().number_format($shipping_val,2);} else {echo 'N/A';};?></td>
</tr>
<?php }?>