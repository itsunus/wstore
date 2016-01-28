<?php
global $WCMp;
?>
<input type="hidden" name="wcmp_msg_tab_to_be_refrash" id="wcmp_msg_tab_to_be_refrash" value="" />
<input type="hidden" name="wcmp_msg_tab_to_be_refrash2" id="wcmp_msg_tab_to_be_refrash2" value="" />
<input type="hidden" name="wcmp_msg_tab_to_be_refrash3" id="wcmp_msg_tab_to_be_refrash3" value="" />
<div class="wcmp_main_holder toside_fix">
	<div class="wcmp_headding1">
		<ul>
			<li><?php _e('Messages',$WCMp->text_domain); ?></li>
		</ul>
		<div class="clear"></div>
	</div>
	<div id = "tabs-1">
		<ul class="wcmp_msg_tab_nav">
			<li data-element="_all"><a href = "#wcmp_msg_tab_1"><?php _e('All',$WCMp->text_domain);?></a></li>
			<li data-element="_read"><a href = "#wcmp_msg_tab_2"><?php _e('Read',$WCMp->text_domain);?></a></li>
			<li data-element="_unread" ><a href = "#wcmp_msg_tab_3"><?php _e('Unread',$WCMp->text_domain);?></a></li>
			<li data-element="_archive"><a href = "#wcmp_msg_tab_4"><?php _e('Archive',$WCMp->text_domain);?></a></li>
		</ul>
		<!--...................... start tab1 .......................... -->
		<div id = "wcmp_msg_tab_1" data-element="_all">
			<div class="ajax_loader_class_msg"><img src="<?php echo $WCMp->plugin_url ?>assets/images/fpd/ajax-loader.gif" alt="ajax-loader" /></div>
			<div class="msg_container" >			
				<?php							
					//show all messages
					$WCMp->template->get_template( 'shortcode/vendor_messages_all.php');
				?>			
			</div>
		</div>
		<!--...................... end of tab1 .......................... -->
		<!--...................... start tab2 .......................... -->
		<div id = "wcmp_msg_tab_2" data-element="_read">
			<div class="ajax_loader_class_msg"><img src="<?php echo $WCMp->plugin_url ?>assets/images/fpd/ajax-loader.gif" alt="ajax-loader" /></div>
			<div class="msg_container" >							
				<?php							
					//show read messages
					$WCMp->template->get_template( 'shortcode/vendor_messages_read.php');
				?>			
			</div>
		</div>
		<!--...................... end of tab2 .......................... -->
		<!--...................... start tab3 .......................... -->
		<div id = "wcmp_msg_tab_3" data-element="_unread">
			<div class="ajax_loader_class_msg"><img src="<?php echo $WCMp->plugin_url ?>assets/images/fpd/ajax-loader.gif" alt="ajax-loader" /></div>
			<div class="msg_container" >				
				<?php							
					//show unread messages
					$WCMp->template->get_template( 'shortcode/vendor_messages_unread.php');
				?>				
			</div>
		</div>
		<!--...................... end of tab3 .......................... -->
		<!--...................... start tab4 .......................... -->
		<div id = "wcmp_msg_tab_4" data-element="_archive">
			<div class="ajax_loader_class_msg"><img src="<?php echo $WCMp->plugin_url ?>assets/images/fpd/ajax-loader.gif" alt="ajax-loader" /></div>
			<div class="msg_container">				
				<?php							
					//show unread messages
					$WCMp->template->get_template( 'shortcode/vendor_messages_archive.php');
				?>				
			</div>
		</div>
		<!--...................... end of tab4 .......................... -->
</div>