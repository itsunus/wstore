<?php
/**
 * The template for displaying single product page vendor tab 
 *
 * Override this template by copying it to yourtheme/dc-product-vendor/vendor_tab.php
 *
 * @author 		dualcube
 * @package 	dc-product-vendor/Templates
 * @version   2.2.0
 */
global $product, $WCMp;
$product_settings = get_option('wcmp_product_settings_name');
$product_level_policies_on = isset($product_settings['product_level_policies_on']) ? $product_settings['product_level_policies_on'] : '';
$policies_can_override_by_vendor = isset($product_settings['policies_can_override_by_vendor']) ? $product_settings['policies_can_override_by_vendor'] : '';
$cancellation_policy = isset($product_settings['cancellation_policy']) ? $product_settings['cancellation_policy'] : '';
$refund_policy = isset($product_settings['refund_policy']) ? $product_settings['refund_policy'] : '';
$shipping_policy = isset($product_settings['shipping_policy']) ? $product_settings['shipping_policy'] : '';

$cancellation_policy_label = isset($product_settings['cancellation_policy_label']) ? $product_settings['cancellation_policy_label'] :  __('Cancellation/Return/Exchange Policy',$WCMp->text_domain);
$refund_policy_label = isset($product_settings['refund_policy_label']) ? $product_settings['refund_policy_label'] :  __('Refund Policy',$WCMp->text_domain);
$shipping_policy_label = isset($product_settings['shipping_policy_label']) ? $product_settings['shipping_policy_label'] :  __('Shipping Policy',$WCMp->text_domain);

$product_id = $product->id;
if($policies_can_override_by_vendor != '') {	
	$product_vendors = get_wcmp_product_vendors($product_id);
	if( $product_vendors ) {
		$author_id = $product_vendors->id;
	}
	else {
		$author_id = get_post_field('post_author',$product_id);
	}
	$cancellation_policy_vendor = get_user_meta($author_id, '_vendor_cancellation_policy', true);
	$refund_policy_vendor = get_user_meta($author_id, '_vendor_refund_policy', true);
	$shipping_policy_vendor = get_user_meta($author_id, '_vendor_shipping_policy', true);
	if(!empty($cancellation_policy_vendor)) {
		$cancellation_policy = $cancellation_policy_vendor;
	}
	if(!empty($refund_policy_vendor)) {
		$refund_policy = $refund_policy_vendor;
	}
	if(!empty($shipping_policy_vendor)) {
		$shipping_policy = $shipping_policy_vendor;
	}
	if($product_level_policies_on !='') {
		$cancellation_policy_product = get_post_meta($product_id, '_wcmp_cancallation_policy', true);
		$refund_policy_product = get_post_meta($product_id, '_wcmp_refund_policy', true);
		$shipping_policy_product = get_post_meta($product_id, '_wcmp_shipping_policy', true);
		if(!empty($cancellation_policy_product)) {
			$cancellation_policy = $cancellation_policy_product;
		}
		if(!empty($refund_policy_product)) {
			$refund_policy = $refund_policy_product;
		}
		if(!empty($shipping_policy_product)) {
			$shipping_policy = $shipping_policy_product;
		}				
	}	
}
?>
<div class="wcmp-product-policies">
<h2 class="wcmp_policies_heading"><?php echo $cancellation_policy_label; ?></h2>
<div class="wcmp_policies_description" ><?php echo $cancellation_policy; ?></div>
<h2 class="wcmp_policies_heading"><?php echo $refund_policy_label; ?></h2>
<div class="wcmp_policies_description" ><?php echo $refund_policy; ?></div>
<h2 class="wcmp_policies_heading"><?php echo $shipping_policy_label; ?></h2>
<div class="wcmp_policies_description" ><?php echo $shipping_policy; ?></div>
</div>