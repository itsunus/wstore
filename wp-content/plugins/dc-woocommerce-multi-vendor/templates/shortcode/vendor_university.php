<?php
/**
 * The template for displaying vendor dashboard
 *
 * Override this template by copying it to yourtheme/dc-product-vendor/shortcode/vendor_university.php
 *
 * @author 		dualcube
 * @package 	WCMp/Templates
 * @version   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $woocommerce, $WCMp;
$university_args = array(
	'posts_per_page'   => -1,	
	'orderby'          => 'date',
	'order'            => 'DESC',	
	'post_type'        => 'wcmp_university',	
	'post_status'      => 'publish',
	'suppress_filters' => true
	);
$university_posts = get_posts($university_args);
?>
<link href = "http://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<script src = "http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
<div class="wcmp_main_holder toside_fix">
	<div class="wcmp_headding1">
		<ul>
			<li><?php echo __('University',$WCMp->text_domain); ?></li>
		</ul>
		<div class="clear"></div> 
	</div>
	<div id="wcmp_frontend_accordian">
		<?php wp_reset_postdata(); foreach($university_posts as $university_post) { setup_postdata( $university_post ); ?>
			<div>				
				<div class="msg_title_box2"><span class="title"><?php echo $university_post->post_title; ?></span><br> </div>
				<div class="msg_arrow_box2"><a href="#" class="msg_stat_click"><i class="fa fa-caret-down"></i></a></div>
				<div class="clear"></div>
			</div>
			<div>
				<div class="university_text"> 
					<?php the_content(); ?>
				</div>
			</div>
		<?php } wp_reset_postdata(); ?>			
	</div>


<script type="text/javascript"> 
	jQuery(document).ready(function($){	 
		$(function() { 
			$( "#wcmp_frontend_accordian" ).accordion({ 
				speed: 'slow',	
				heightStyle: "content"
			});		
		});
	}); 
</script>		
	