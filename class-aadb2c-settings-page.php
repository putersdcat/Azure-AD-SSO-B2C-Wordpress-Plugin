<?php

/**
 * A class to create and manage the admin's B2C settings page.
 */
class AADB2C_Settings_Page
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    /**
     * Adds a B2C options page under "Settings"
     */
    public function add_plugin_page()
    {
        add_options_page(
            'Settings Admin',
            'AAD B2C Authentication Settings',
            'manage_options',
            'aad-b2c-settings-page',
            array($this, 'create_AADB2C_page')
        );
    }


    /**
     * B2C Options page callback
     */
    public function create_AADB2C_page()
    {
        // Set class property
        $this->options = get_option('aadb2c_config_elements');
?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Azure AD SSO B2C Settings</h2>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields('aadb2c_option_group');
                do_settings_sections('aadb2c-settings-page');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    /**
     * Register the B2C options page and add the B2C settings boxes
     */
    public function page_init()
    {
        register_setting(
            'aadb2c_option_group', // Option group
            'aadb2c_config_elements', // Option name
            array($this, 'sanitize') // Sanitize
        );

        add_settings_section(
            'service_config_section', // ID
            'Settings', // Title
            array($this, 'print_section_info'), // Callback
            'aadb2c-settings-page' // Page
        );

        add_settings_field(
            'aadb2c_aad_tenant_name', // ID
            'Tenant Name', // Title 
            array($this, 'aadb2c_aad_tenant_name_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section  
        );

        add_settings_field(
            'aadb2c_aad_tenant_domain', // ID
            'Tenant Domain', // Title 
            array($this, 'aadb2c_aad_tenant_domain_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section  
        );

        add_settings_field(
            'aadb2c_client_id', // ID
            'Client ID (Application ID)', // Title 
            array($this, 'aadb2c_client_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_subscriber_policy_id', // ID
            'Sign-in Policy for Users', // Title 
            array($this, 'aadb2c_subscriber_policy_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_admin_policy_id', // ID
            'Sign-in Policy for Admins', // Title 
            array($this, 'aadb2c_admin_policy_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_edit_profile_policy_id', // ID
            'Edit Profile Policy', // Title 
            array($this, 'aadb2c_edit_profile_policy_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_password_reset_policy_id', // ID
            'Password Reset Policy', // Title 
            array($this, 'aadb2c_password_reset_policy_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_client_secret', // ID
            'Client Secret for Client ID (Application ID)', // Title 
            array($this, 'aadb2c_client_secret_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            // "ApplicationId" (ClientId) of that "b2c-extensions-app"
            'aadb2c_extensions_app_client_id', // ID
            'ApplicationId (ClientId) of b2c-extensions-app in B2C AzAd', // Title 
            array($this, 'aadb2c_extensions_app_client_id_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            // The legacy graph endpoint url where patch api calls are sent for setting user properties in Az Ad
            'aadb2c_legacy_graph_endpoint', // ID
            'The legacy graph endpoint url where patch api calls are sent for setting user properties in Az Ad', // Title 
            array($this, 'aadb2c_legacy_graph_endpoint_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            // Parent AzAd Tenant ID where b2c AzAd resource exists 
            'aadb2c_tenant_id_parent_azad', // ID
            'Parent AzAd (Tenant ID) where b2c AzAd resource exists', // Title 
            array($this, 'aadb2c_tenant_id_parent_azad_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_verify_tokens', // ID
            'Verify ID Tokens', // Title 
            array($this, 'aadb2c_verify_tokens_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_Replace_WpLogin', // ID
            'Replace WordPress Login', // Title 
            array($this, 'aadb2c_Replace_WpLogin_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_RequireLoginToAccess_WC_Cart', // ID
            'Require User to Login to Access the WooCommerce Cart', // Title 
            array($this, 'aadb2c_RequireLoginToAccess_WC_Cart_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_EnableGraphArrtibuteSync', // ID
            'Enable the Use of Legacy Graph Calls to Sync B2C user attributes', // Title 
            array($this, 'aadb2c_EnableGraphArrtibuteSync_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );

        add_settings_field(
            'aadb2c_ToggleOffHackyStuff', // ID
            'Disable Some very custom settings not intended for general public use ;)', // Title 
            array($this, 'aadb2c_ToggleOffHackyStuff_callback'), // Callback
            'aadb2c-settings-page', // Page
            'service_config_section' // Section           
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input)
    {
        $new_input = array();
        if (isset($input['aadb2c_aad_tenant_name']))
            $new_input['aadb2c_aad_tenant_name'] = sanitize_text_field(strtolower($input['aadb2c_aad_tenant_name']));

        if (isset($input['aadb2c_aad_tenant_domain']))
            $new_input['aadb2c_aad_tenant_domain'] = sanitize_text_field(strtolower($input['aadb2c_aad_tenant_domain']));

        if (isset($input['aadb2c_client_id']))
            $new_input['aadb2c_client_id'] = sanitize_text_field($input['aadb2c_client_id']);

        if (isset($input['aadb2c_subscriber_policy_id']))
            $new_input['aadb2c_subscriber_policy_id'] = sanitize_text_field(strtolower($input['aadb2c_subscriber_policy_id']));

        if (isset($input['aadb2c_admin_policy_id']))
            $new_input['aadb2c_admin_policy_id'] = sanitize_text_field(strtolower($input['aadb2c_admin_policy_id']));

        if (isset($input['aadb2c_edit_profile_policy_id']))
            $new_input['aadb2c_edit_profile_policy_id'] = sanitize_text_field(strtolower($input['aadb2c_edit_profile_policy_id']));

        if (isset($input['aadb2c_password_reset_policy_id']))
        $new_input['aadb2c_password_reset_policy_id'] = sanitize_text_field(strtolower($input['aadb2c_password_reset_policy_id']));
        
        if (isset($input['aadb2c_client_secret']))
        $new_input['aadb2c_client_secret'] = sanitize_text_field($input['aadb2c_client_secret']);
        
        if (isset($input['aadb2c_extensions_app_client_id']))
        $new_input['aadb2c_extensions_app_client_id'] = sanitize_text_field($input['aadb2c_extensions_app_client_id']);
        
        if (isset($input['aadb2c_tenant_id_parent_azad']))
        $new_input['aadb2c_tenant_id_parent_azad'] = sanitize_text_field($input['aadb2c_tenant_id_parent_azad']);
        
        if (isset($input['aadb2c_legacy_graph_endpoint']))
        $new_input['aadb2c_legacy_graph_endpoint'] = sanitize_text_field($input['aadb2c_legacy_graph_endpoint']);

        $new_input['aadb2c_verify_tokens'] = $input['aadb2c_verify_tokens'];

        $new_input['aadb2c_Replace_WpLogin'] = $input['aadb2c_Replace_WpLogin'];

        $new_input['aadb2c_RequireLoginToAccess_WC_Cart'] = $input['aadb2c_RequireLoginToAccess_WC_Cart'];

        $new_input['aadb2c_EnableGraphArrtibuteSync'] = $input['aadb2c_EnableGraphArrtibuteSync'];

        $new_input['aadb2c_ToggleOffHackyStuff'] = $input['aadb2c_ToggleOffHackyStuff'];

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter the settings your created for your blog in the <a href="https://portal.azure.com" target="_blank">Azure Portal</a>';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_aad_tenant_name_callback()
    {
        printf(
            '<input type="text" id="aadb2c_aad_tenant_name" name="aadb2c_config_elements[aadb2c_aad_tenant_name]" value="%s" />'
                . '<br/><i>ex: contoso (used to create metadata endpoint uri like "contoso.b2clogin.com")</i>',
            isset($this->options['aadb2c_aad_tenant_name']) ? esc_attr($this->options['aadb2c_aad_tenant_name']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_aad_tenant_domain_callback()
    {
        printf(
            '<input type="text" id="aadb2c_aad_tenant_domain" name="aadb2c_config_elements[aadb2c_aad_tenant_domain]" value="%s" />'
                . '<br/><i>ex: contoso.onmicrosoft.com</i>',
            isset($this->options['aadb2c_aad_tenant_domain']) ? esc_attr($this->options['aadb2c_aad_tenant_domain']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_client_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_client_id" name="aadb2c_config_elements[aadb2c_client_id]" value="%s" />',
            isset($this->options['aadb2c_client_id']) ? esc_attr($this->options['aadb2c_client_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_admin_policy_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_admin_policy_id" name="aadb2c_config_elements[aadb2c_admin_policy_id]" value="%s" />'
                . '<br/><i>Can be the same as Sign-in Policy for Users but typically includes multi-factor authentication for extra protection of Wordpress administration mode.</i>',
            isset($this->options['aadb2c_admin_policy_id']) ? esc_attr($this->options['aadb2c_admin_policy_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_subscriber_policy_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_subscriber_policy_id" name="aadb2c_config_elements[aadb2c_subscriber_policy_id]" value="%s" />'
                . '<br/><i>Specify a Sign-in Policy if you manage creation of Wordpress subscriber accounts yourself.</i>'
                . '<br/><i>Specify a Sign-in/Sign-up policy to allow Wordpress users to create their own subscriber accounts.</i>',
            isset($this->options['aadb2c_subscriber_policy_id']) ? esc_attr($this->options['aadb2c_subscriber_policy_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_edit_profile_policy_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_edit_profile_policy_id" name="aadb2c_config_elements[aadb2c_edit_profile_policy_id]" value="%s" />',
            isset($this->options['aadb2c_edit_profile_policy_id']) ? esc_attr($this->options['aadb2c_edit_profile_policy_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_password_reset_policy_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_password_reset_policy_id" name="aadb2c_config_elements[aadb2c_password_reset_policy_id]" value="%s" />'
                . '<br/><i>Used if your Sign-in Policy for Users is using a sign-in/sign-up policy and the user clicks the forgotten password link.</i>',
            isset($this->options['aadb2c_password_reset_policy_id']) ? esc_attr($this->options['aadb2c_password_reset_policy_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_client_secret_callback()
    {
        printf(
            '<input type="text" id="aadb2c_client_secret_callback" name="aadb2c_config_elements[aadb2c_client_secret]" value="%s" />'
                . '<br/><i>The client secret matching the client ID above of the app configured for graph access, OpenId etc.</i>',
            isset($this->options['aadb2c_client_secret']) ? esc_attr($this->options['aadb2c_client_secret']) : ''
        );
    }
    
    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_extensions_app_client_id_callback()
    {
        printf(
            '<input type="text" id="aadb2c_extensions_app_client_id_callback" name="aadb2c_config_elements[aadb2c_extensions_app_client_id]" value="%s" />'
                . '<br/><i>The Parent AzAd (Tenant ID) where b2c AzAd resource exists.</i>',
            isset($this->options['aadb2c_extensions_app_client_id']) ? esc_attr($this->options['aadb2c_extensions_app_client_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_tenant_id_parent_azad_callback()
    {
        printf(
            '<input type="text" id="aadb2c_tenant_id_parent_azad_callback" name="aadb2c_config_elements[aadb2c_tenant_id_parent_azad]" value="%s" />'
                . '<br/><i>The ApplicationId (ClientId) of the default b2c-extensions-app in B2C AzAd.</i>',
            isset($this->options['aadb2c_tenant_id_parent_azad']) ? esc_attr($this->options['aadb2c_tenant_id_parent_azad']) : ''
        );
    }
    
    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_legacy_graph_endpoint_callback()
    {
        printf(
            '<input type="text" id="aadb2c_legacy_graph_endpoint_callback" name="aadb2c_config_elements[aadb2c_legacy_graph_endpoint]" value="%s" />'
                . '<br/><i>The legacy graph endpoint url where patch api calls are sent for getting and setting user properties in Az Ad.</i>',
            isset($this->options['aadb2c_legacy_graph_endpoint']) ? esc_attr($this->options['aadb2c_legacy_graph_endpoint']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_verify_tokens_callback()
    {

        if (empty($this->options['aadb2c_verify_tokens']))
            $this->options['aadb2c_verify_tokens'] = 0;

        $current_value = $this->options['aadb2c_verify_tokens'];

        echo '<input type="checkbox" id="aadb2c_verify_tokens" name="aadb2c_config_elements[aadb2c_verify_tokens]" value="1" class="code" ' . checked(1, $current_value, false) . ' />';
    }


    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_Replace_WpLogin_callback()
    {

        if (empty($this->options['aadb2c_Replace_WpLogin']))
            $this->options['aadb2c_Replace_WpLogin'] = 0;

        $current_value = $this->options['aadb2c_Replace_WpLogin'];

        echo '<input type="checkbox" id="aadb2c_Replace_WpLogin" name="aadb2c_config_elements[aadb2c_Replace_WpLogin]" value="1" class="code" ' . checked(1, $current_value, false) . ' />';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_RequireLoginToAccess_WC_Cart_callback()
    {

        if (empty($this->options['aadb2c_RequireLoginToAccess_WC_Cart']))
            $this->options['aadb2c_RequireLoginToAccess_WC_Cart'] = 0;

        $current_value = $this->options['aadb2c_RequireLoginToAccess_WC_Cart'];

        echo '<input type="checkbox" id="aadb2c_RequireLoginToAccess_WC_Cart" name="aadb2c_config_elements[aadb2c_RequireLoginToAccess_WC_Cart]" value="1" class="code" ' . checked(1, $current_value, false) . ' />';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_EnableGraphArrtibuteSync_callback()
    {

        if (empty($this->options['aadb2c_EnableGraphArrtibuteSync']))
            $this->options['aadb2c_EnableGraphArrtibuteSync'] = 0;

        $current_value = $this->options['aadb2c_EnableGraphArrtibuteSync'];

        echo '<input type="checkbox" id="aadb2c_EnableGraphArrtibuteSync" name="aadb2c_config_elements[aadb2c_EnableGraphArrtibuteSync]" value="1" class="code" ' . checked(1, $current_value, false) . ' />';
    }

        /** 
     * Get the settings option array and print one of its values
     */
    public function aadb2c_ToggleOffHackyStuff_callback()
    {

        if (empty($this->options['aadb2c_ToggleOffHackyStuff']))
            $this->options['aadb2c_ToggleOffHackyStuff'] = 0;

        $current_value = $this->options['aadb2c_ToggleOffHackyStuff'];

        echo '<input type="checkbox" id="aadb2c_ToggleOffHackyStuff" name="aadb2c_config_elements[aadb2c_ToggleOffHackyStuff]" value="1" class="code" ' . checked(1, $current_value, false) . ' />';
    }

}
