<?php
/**
 * WCMp MassPay Cron Class
 *
 * @version		2.2.0
 * @package		WCMp
 * @author 		DualCube
 */
 
class WCMp_MassPay_Cron {

	public function __construct() {
		add_action('paypal_masspay_cron_start', array(&$this, 'do_paypal_mass_payment') );
	}	

	/**
		* Initialize paypal masspay cron
		*/
	function do_paypal_mass_payment() {
		global $WCMp;

		$payment_admin_settings = get_option('wcmp_payment_settings_name');

		if(array_key_exists('is_mass_pay', $payment_admin_settings)) {
			doProductVendorLOG("Cron Run Start for array creatation @ " . date('d/m/Y g:i:s A', time()));
			update_option('paypal_masspay_cron_running', 1);
			$commissions = $WCMp->paypal_masspay->get_query_commission();
			if(!empty($commissions)) {
				$WCMp->paypal_masspay->do_paypal_masspay();
			}
			doProductVendorLOG("Cron Run Finish @ " . date('d/m/Y g:i:s A', time()));
			doProductVendorLOG("Next Payment import cron @ " . date('d/m/Y g:i:s A', wp_next_scheduled( 'paypal_masspay_cron_start' )) . "::". date('d/m/Y g:i:s A', time()));
		}
		delete_option( 'paypal_masspay_cron_running' );
	}
}