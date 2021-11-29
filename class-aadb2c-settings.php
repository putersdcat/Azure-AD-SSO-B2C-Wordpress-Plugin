<?php

class AADB2C_Settings
{

	// These settings are configurable by the admin
	public static $tenant_name = ""; // ex: contoso
	public static $tenant_domain = ""; // ex: contoso.onmicrosoft.com
	public static $clientID = "";
	public static $generic_policy = "";
	public static $admin_policy = "";
	public static $edit_profile_policy = "";
	public static $password_reset_policy = "";
	public static $redirect_uri = "";
	public static $verify_tokens = 1;

	public static $Replace_WpLogin = 0;
	public static $RequireLoginToAccess_WC_Cart = 1;
	public static $RequireLoginToAccess_WC_MyAccount = 1;
	public static $OptionalLoginAtCheckout = 1;
	// added this to UI to be able to disable the janky stuff used in the Proofe Of Concept Dev Environment only, like jquery chopping up web code, etc. 
	public static $ToggleOffHackyStuff = 1;

	// These settings define the authentication flow, but are not configurable on the settings page
	// because this plugin is made to support OpenID Connect implicit flow with form post responses
	public static $response_type = "id_token";
	public static $response_mode = "form_post";
	public static $scope = "openid";

	// These settings are part of the new added functionality to set user attributs via graph authenticated with clientID and Secret
	public static $EnableGraphArrtibuteSync = 1;
	public static $clientSecret = "";
	public static $extensions_app_client_id = "";
	public static $graph_endpoint = '';	// The URI of the Microsoft Graph API.
	public static $graph_version = '1.6';	// v1.6 The version of the Microsoft Graph API to use.

	// The URL to redirect to after signing out (of Azure AD, not WordPress).
	public static $logout_redirect_uri = '';
	// The OAuth 2.0 token endpoint.
	public static $token_endpoint = '';

	// Parent AzAd Tenant ID where b2c AzAd resource exists: 
	// https://login.microsoftonline.com/1c21b550-383a-44c4-b15a-ae55c2bf9415/oauth2/token
	public static $tenant_id_parent_azad = '';



	// flatten d1f9ccbb-2c4b-43a2-a5fd-9755f80e360f
	// Moved to graph helper
	//public static $graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', self::$extensions_app_client_id)) . '_';

	function __construct()
	{

		// Get the inputs from the B2C Settings Page
		$config_elements = get_option('aadb2c_config_elements');

		if (isset($config_elements)) {
			// Parse the settings entered in by the admin on the b2c settings page
			self::$tenant_name = $config_elements['aadb2c_aad_tenant_name'];
			self::$tenant_domain = $config_elements['aadb2c_aad_tenant_domain'];
			self::$clientID = $config_elements['aadb2c_client_id'];
			self::$generic_policy = $config_elements['aadb2c_subscriber_policy_id'];
			self::$admin_policy = $config_elements['aadb2c_admin_policy_id'];
			self::$edit_profile_policy = $config_elements['aadb2c_edit_profile_policy_id'];
			self::$password_reset_policy = $config_elements['aadb2c_password_reset_policy_id'];
			self::$clientSecret = $config_elements['aadb2c_client_secret'];
			self::$extensions_app_client_id = $config_elements['aadb2c_extensions_app_client_id'];
			self::$tenant_id_parent_azad = $config_elements['aadb2c_tenant_id_parent_azad'];
			self::$redirect_uri = urlencode(site_url() . '/');
			if (!isset($config_elements['aadb2c_legacy_graph_endpoint'])) self::$graph_endpoint = 'https://graph.windows.net';
			else self::$graph_endpoint = $config_elements['aadb2c_legacy_graph_endpoint'];
			if ($config_elements['aadb2c_Replace_WpLogin']) self::$Replace_WpLogin = 1;
			else self::$Replace_WpLogin = 0;
			if ($config_elements['aadb2c_RequireLoginToAccess_WC_Cart']) self::$RequireLoginToAccess_WC_Cart = 1;
			else self::$RequireLoginToAccess_WC_Cart = 0;
			if ($config_elements['aadb2c_RequireLoginToAccess_WC_MyAccount']) self::$RequireLoginToAccess_WC_MyAccount = 1;
			else self::$RequireLoginToAccess_WC_MyAccount = 0;
			if ($config_elements['aadb2c_OptionalLoginAtCheckout']) self::$OptionalLoginAtCheckout = 1;
			else self::$OptionalLoginAtCheckout = 0;
			if ($config_elements['aadb2c_EnableGraphArrtibuteSync']) self::$EnableGraphArrtibuteSync = 1;
			else self::$EnableGraphArrtibuteSync = 0;
			if ($config_elements['aadb2c_ToggleOffHackyStuff']) self::$ToggleOffHackyStuff = 1;
			else self::$ToggleOffHackyStuff = 0;
			if ($config_elements['aadb2c_verify_tokens']) self::$verify_tokens = 1;
			else self::$verify_tokens = 0;
		}
	}

	// Examples
	/**
	* https://kekzclub.b2clogin.com/kekzclub.onmicrosoft.com/v2.0/.well-known/openid-configuration?p=B2C_1_app-SignUpOrSignIn-customer
	* https://kekzclub.b2clogin.com/kekzclub.onmicrosoft.com/v2.0/.well-known/openid-configuration?p=B2C_1_app-SignUpOrSignIn-admin
	* https://kekzclub.b2clogin.com/kekzclub.onmicrosoft.com/v2.0/.well-known/openid-configuration?p=B2C_1_app-PasswordReset
	* https://kekzclub.b2clogin.com/kekzclub.onmicrosoft.com/v2.0/.well-known/openid-configuration?p=B2C_1_app-EditProfile-customer
	*/
	static function metadata_endpoint_begin()
	{
		return 'https://' . self::$tenant_name . '.b2clogin.com/' . self::$tenant_domain . '/v2.0/.well-known/openid-configuration?p=';
	}


}
