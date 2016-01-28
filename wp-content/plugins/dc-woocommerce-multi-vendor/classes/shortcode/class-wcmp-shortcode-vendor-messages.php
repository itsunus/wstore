<?php
/**
 * WCMp Vendor Dashboard Shortcode Class
 *
 * @version		2.2.0
 * @package		WCMp/shortcode
 * @author 		DualCube
 */
 
class WCMp_Vendor_Messages_Shortcode {

	public function __construct() {

	}

	/**
	 * Output the vendor dashboard shortcode.
	 *
	 * @access public
	 * @param array $atts
	 * @return void
	 */
	public static function output( $attr ) {
		global $WCMp;
		
		$frontend_style_path = $WCMp->plugin_url . 'assets/frontend/css/';
		$frontend_style_path = str_replace( array( 'http:', 'https:' ), '', $frontend_style_path );
		$suffix 				= defined( 'WCMP_SCRIPT_DEBUG' ) && WCMP_SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style('wcmp_vandor_messages_css',  $frontend_style_path .'vendor_dashboard'.$suffix.'.css', array(), $WCMp->version);		
		wp_enqueue_style('font-vendor_message',  'https://fonts.googleapis.com/css?family=Lato:400,100,100italic,300,300italic,400italic,700,700italic,900,900italic', array(), $WCMp->version);
		wp_enqueue_style('font-awesome_message',  $frontend_style_path . 'font-awesome.min.css', array(), $WCMp->version);
		wp_enqueue_style('ui_vendor_message',  'http://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css', array(), $WCMp->version);
		
		$frontend_script_path = $WCMp->plugin_url . 'assets/frontend/js/';
		$frontend_script_path = str_replace( array( 'http:', 'https:' ), '', $frontend_script_path );
		$pluginURL = str_replace( array( 'http:', 'https:' ), '', $WCMp->plugin_url );
		$suffix 				= defined( 'WCMP_SCRIPT_DEBUG' ) && WCMP_SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script('wcmp_new_vandor_messages_js', $frontend_script_path.'wcmp_vendor_messages'.$suffix.'.js', array('jquery'), $WCMp->version, true);
		wp_enqueue_script('wcmp_new_vandor_messages_js_lib', $frontend_script_path.'jquery-2.1.3.min'.$suffix.'.js', array('jquery'), $WCMp->version, true);
		wp_enqueue_script('wcmp_new_vandor_messages_js_lib_ui', 'http://code.jquery.com/ui/1.10.4/jquery-ui.js', array('jquery'), $WCMp->version, true);
		echo '<div class="wcmp_main_page">';		
		$WCMp->template->get_template( 'vendor_dashboard_menu.php' );
		$WCMp->template->get_template( 'shortcode/vendor_messages.php' );
		echo '</div>';
	}
}