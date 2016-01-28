<?php
class WCMp_Settings_Gneral_Policies {
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;
  
  private $tab;
  
  private $subsection;

  /**
   * Start up
   */
  public function __construct($tab,$subsection) {
    $this->tab = $tab;
    $this->subsection = $subsection;
    $this->options = get_option( "wcmp_{$this->tab}_{$this->subsection}_settings_name" );
    $this->settings_page_init();
  }
  
  /**
   * Register and add settings
   */
  public function settings_page_init() {
    global $WCMp;
    
    $settings_tab_options = array("tab" => "{$this->tab}",
                                  "ref" => &$this,
                                  "subsection" => "{$this->subsection}",
                                  "sections" => array(
                                                      "wcmp_store_policies_settings_section" => array("title" =>  '', // Section one
                                                                                         "fields" => array( 
                                                                                         	 								 "is_policy_on" => array('title' => __('Policies Enable/Disable :', $WCMp->text_domain), 'type' => 'checkbox', 'id' => 'is_policy_on', 'label_for' => 'is_policy_on', 'name' => 'is_policy_on', 'value' => 'Enable'), // Checkbox
                                                                                         	 								 
                                                                                                           ),
                                                                                         ),
                                                      ),
                                                     
                                  );
    
    $WCMp->admin->settings->settings_field_withsubtab_init(apply_filters("settings_{$this->tab}_{$this->subsection}_tab_options", $settings_tab_options));
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   */
  public function wcmp_general_policies_settings_sanitize( $input ) {
    global $WCMp;
    $new_input = array();
    
    $hasError = false;
    
    if( isset( $input['is_policy_on'] ) )
      $new_input['is_policy_on'] = sanitize_text_field( $input['is_policy_on'] );
    
    
    
    if(!$hasError) {
			add_settings_error(
			 "wcmp_{$this->tab}_{$this->subsection}_settings_name",
			 esc_attr( "wcmp_{$this->tab}_{$this->subsection}_settings_admin_updated" ),
			 __('Policies Settings Updated', $WCMp->text_domain),
			 'updated'
			);
    }
    return apply_filters("settings_{$this->tab}_{$this->subsection}_tab_new_input", $new_input , $input);
  }

   
  /** 
   * Print the Section text
   */
  public function wcmp_store_policies_settings_section_info() {
    global $WCMp;
    printf( __( 'Please configure the policies section.', $WCMp->text_domain ) );
  }
  
   /** 
   * Print the Section text
   */
  public function venor_frontend_settings_section_info() {
    global $WCMp;
    printf( __( 'These features are now available in the %sFrontend%s tab.', $WCMp->text_domain ), '<a target="_blank" href="?page=wcmp-setting-admin&tab=frontend">', '</a>' );
  }
  
}
