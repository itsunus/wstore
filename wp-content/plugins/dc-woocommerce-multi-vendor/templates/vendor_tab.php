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
 
global $WCMp, $product;
	$html = '';
	$vendor = get_wcmp_product_vendors( $product->id );
	if( $vendor ) {
		$html .= '<div class="product-vendor">';
		$html .= apply_filters('wcmp_before_seller_info_tab', ''); 
		$html .= '<h2>' . $vendor->user_data->display_name . '</h2>';
		if( '' != $vendor->description ) {
				$html .= '<p>' . $vendor->description . '</p>';
		}
		$html .= '<p><a href="' . $vendor->permalink . '">' . sprintf( __( 'More Products from %1$s', $WCMp->text_domain ), $vendor->user_data->display_name ) . '</a></p>';
		$html .= apply_filters('wcmp_after_seller_info_tab', ''); 
		$html .= '</div>';
	}
	echo $html;
?>