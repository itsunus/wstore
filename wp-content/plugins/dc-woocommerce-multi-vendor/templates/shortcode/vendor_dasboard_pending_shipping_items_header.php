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
global $woocommerce, $WCMp;
?>
<tr>
	<td align="center" ><?php echo __('Product Name',$WCMp->text_domain); ?></td>
	<td  align="center" ><?php echo __('Order Date',$WCMp->text_domain); ?><br>
		<span style="font-size:12px;"><?php echo __('dd/mm',$WCMp->text_domain); ?></span></td>
	<td  align="center" ><?php echo __('L/B/H/W',$WCMp->text_domain); ?></td>
	<td align="left" ><?php echo __('Address',$WCMp->text_domain); ?></td>
	<td align="center" ><?php echo __('Charges',$WCMp->text_domain); ?></td>
</tr>
