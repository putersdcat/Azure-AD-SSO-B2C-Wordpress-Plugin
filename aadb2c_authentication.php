<?php

/**
 * Plugin Name: No Prod Use - Azure AD SSO B2C w/ WC Support
 * Plugin URI: https://github.com/putersdcat/Azure-B2C-Wordpress-Plugin
 * Description: A nasty fork in progress of a plugin that allows users to log in using B2C policies 
 * Version: 0.1
 * Author: Microsoft Alumni
 * Author URI: https://azure.microsoft.com/en-us/documentation/services/active-directory-b2c/
 * License: MIT License (https://raw.githubusercontent.com/AzureAD/active-directory-b2c-wordpress-plugin-openidconnect/master/LICENSE)
 */


//*****************************************************************************************


/** 
 * Requires the autoloaders.
 */
require 'autoload.php';
require 'vendor/autoload.php';

/**
 * Defines the response string posted by B2C.
 */
define('AADB2C_RESPONSE_MODE', 'id_token');

// Adds the B2C Options page to the Admin dashboard, under 'Settings'.
if (is_admin()) $aadb2c_settings_page = new AADB2C_Settings_Page();
$aadb2c_settings = new AADB2C_Settings();

//*****************************************************************************************

class AADB2C {

	//static $instance = FALSE;

	private $settings = null;
	
	public function __construct( $settings) {
		$this->settings = $settings;
	}
}

/**
 * Redirects to B2C on a user login request.
 */
function aadb2c_login()
{
	try {
		$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$generic_policy);
		$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint() . "&state=generic";
		wp_redirect($authorization_endpoint);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	exit;
}

/** 
 * Redirects to B2C on user logout.
 */
function aadb2c_logout()
{
	try {
		$signout_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$generic_policy);
		$signout_uri = $signout_endpoint_handler->get_end_session_endpoint();
		wp_redirect($signout_uri);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	exit;
}


/** 
 * Verifies the id_token that is POSTed back to the web app from the 
 * B2C authorization endpoint. 
 */
function aadb2c_verify_token()
{
	try {
		if (isset($_POST['error'])) {
			// If user requests the Password Reset flow from a Sign-in/Sign-up flow, the following is returned:
			//   Error: access_denied
			//   Description: AADB2C90118: The user has forgotten their password.
			if (preg_match('/.*AADB2C90118.*/i', $_POST['error_description'])) {
				// user forgot password so redirect to the password reset flow
				aadb2c_password_reset();
				exit;
			}

			// If user cancels the Sign-up portion of the Sign-in/Sign-up flow or
			// if user cancels the Profile Edit flow, the following is returned:
			//   Error: access_denied
			//   Description: AADB2C90091: The user has cancelled entering self-asserted information.
			if (preg_match('/.*AADB2C90091.*/i', $_POST['error_description'])) {
				// user cancelled profile editing or cancelled signing up
				// so redirect to the home page instead of showing an error
				wp_safe_redirect(site_url() . '/');
				exit;
			}

			echo 'Authentication error on ' . get_bloginfo('name') . '.';
			echo '<br>Error: ' . $_POST['error'];
			echo '<br>Description: ' . $_POST['error_description'];
			echo '<br><br><a href="' . site_url() . '">Go to ' . site_url() . '</a>';
			exit;
		}

		if (isset($_POST[AADB2C_RESPONSE_MODE])) {
			// Check which authorization policy was used
			switch ($_POST['state']) {
				case 'generic':
					$policy = AADB2C_Settings::$generic_policy;
					break;
				case 'admin':
					$policy = AADB2C_Settings::$admin_policy;
					break;
				case 'edit_profile':
					$policy = AADB2C_Settings::$edit_profile_policy;
					break;
				default:
					// Not a B2C request, ignore.
					return;
			}

			// Verifies token only if the checkbox "Verify tokens" is checked on the settings page
			$token_checker = new AADB2C_Token_Checker($_POST[AADB2C_RESPONSE_MODE], AADB2C_Settings::$clientID, $policy);
			if (AADB2C_Settings::$verify_tokens) {
				$verified = $token_checker->authenticate();
				if ($verified == false) wp_die('Token validation error');
			}


			// First find the user by the B2C object ID
			$object_id = $token_checker->get_claim('sub');
			$users = get_users(array('meta_key' => 'aadb2c_object_id', 'meta_value' => $object_id));
			if (is_array($users) && count($users) == 0) {
				// User not found, try to find them by email
				$email = $token_checker->get_claim('emails');
				$email = $email[0];
				$user = WP_User::get_data_by('email', $email);
			} else if (is_array($users) && count($users) == 1) {
				// User found
				$user = $users[0];
			} else if (is_array($users) && count($users) > 1) {
				// Duplicate users found log error and exit
				error_log('Duplicate users found for aadb2c_object_id ' . $object_id);
				exit;
			}
			

			// Get the userID for the user
			if ($user == false) { // User doesn't exist yet, create new userID

				$first_name = $token_checker->get_claim('given_name');
				$last_name = $token_checker->get_claim('family_name');
				// $display_name = $token_checker->get_claim('displayName'); // <-- Need to debug this one, not sure, just guessed... I was wrong!
				$display_name = $token_checker->get_claim('name');
				$billing_first_name = $token_checker->get_claim('given_name');
				$billing_last_name = $token_checker->get_claim('family_name');
				$billing_address_1 = $token_checker->get_claim('streetAddress');
				$billing_postcode = $token_checker->get_claim('postalCode');
				$billing_city = $token_checker->get_claim('city');
				$billing_state = $token_checker->get_claim('state');
				$billing_country = $token_checker->get_claim('country');
				$billing_phone = $token_checker->get_claim('extension_wc_billing_phone');
				$billing_email = $token_checker->get_claim('emails.0');
				$shipping_first_name = $token_checker->get_claim('extension_wc_shipping_first_name');
				$shipping_last_name = $token_checker->get_claim('extension_wc_shipping_last_name');
				$shipping_address_1 = $token_checker->get_claim('extension_wc_shipping_address');
				$shipping_postcode = $token_checker->get_claim('extension_wc_shipping_postcode');
				$shipping_city = $token_checker->get_claim('extension_wc_shipping_city');
				$shipping_state = $token_checker->get_claim('extension_wc_shipping_state');
				$shipping_country = $token_checker->get_claim('extension_wc_shipping_country');
				$shipping_phone = $token_checker->get_claim('extension_wc_shipping_phone');
				$locale = $token_checker->get_claim('extension_app_locale');

				$our_userdata = array(
					'ID' => 0,
					'user_login' => $email,
					'user_pass' => NULL,
					'user_registered' => date('Y-m-d H:i:s'),
					'user_status' => 0,
					'user_email' => $email,
					'display_name' => $first_name . ' ' . $last_name,
					//'display_name' => $display_name,
					'first_name' => $first_name,
					'last_name' => $last_name,
					//'role' => customer, it looks like in the WC code when this is null it will default to customer, lets see.
					'billing_first_name' => $billing_first_name,
					'billing_last_name' => $billing_last_name,
					'billing_address_1' => $billing_address_1,
					'billing_postcode' => $billing_postcode,
					'billing_city' => $billing_city,
					'billing_state' => $billing_state,
					'billing_country' => $billing_country,
					'billing_phone' => $billing_phone,
					'billing_email' => $billing_email,
					'shipping_first_name' => $shipping_first_name,
					'shipping_last_name' => $shipping_last_name,
					'shipping_address_1' => $shipping_address_1,
					'shipping_postcode' => $shipping_postcode,
					'shipping_city' => $shipping_city,
					'shipping_state' => $shipping_state,
					'shipping_country' => $shipping_country,
					'shipping_phone' => $shipping_phone,
					//'locale' => $locale,
				);

				// EWA: Dev Notes
				/**
				 * https://github.com/AzureAD/active-directory-b2c-wordpress-plugin-openidconnect/pull/20#issuecomment-466618039
				 * @peterspliid, I just started using this AD B2C Wordpress plugin and I like your changes for updating user meta fields from the AD B2C custom attributes.
				 * May I ask why you require the use of an action hook rather than just calling update_user_meta() in b2c_verify_token() after the calls to wp_insert_user() and wp_update_user().
				 * Is it so you can more easily control which AD B2C custom attributes are added to Wordpress?
				 * Thanks for the work on this plugin!
				 * @ArthurDumas Sorry for the late response. Yes you are correct. 
				 * You might want to map fields from AD B2C to your custom wordpress fields, or process or verify the data before inserting it. 
				 * It is generally good practice to use actions or filters when it comes to custom data
				 */
				
				update_option("aadb2c_wc_claims", $our_userdata);

				// This is where the collected user data is joined into the WP / WC user data on sign in or sign up call.
				$userID = wp_insert_user($our_userdata);
				update_user_meta($userID, 'aadb2c_object_id', sanitize_text_field($object_id));

				// blast through array of date pulled above and populate anything locally that is not already
				// This is disabled not until i know how or if it triggers the "profile_update" action
				//foreach($our_userdata as $key => $value) {
				//	update_user_meta( $userID, $key, $value );
				//}

				do_action('aadb2c_new_userdata', $userID, $token_checker->get_payload());
			} else if ($policy == AADB2C_Settings::$edit_profile_policy) { // Update the existing user w/ new attritubtes


				$name = $token_checker->get_claim('name'); // Is this a mistake, not referenced in the code anywhere?
				$first_name = $token_checker->get_claim('given_name');
				$last_name = $token_checker->get_claim('family_name');
				$display_name = $token_checker->get_claim('name');
				$billing_first_name = $token_checker->get_claim('given_name');
				$billing_last_name = $token_checker->get_claim('family_name');
				$billing_address_1 = $token_checker->get_claim('streetAddress');
				$billing_postcode = $token_checker->get_claim('postalCode');
				$billing_city = $token_checker->get_claim('city');
				$billing_state = $token_checker->get_claim('state');
				$billing_country = $token_checker->get_claim('country');
				$billing_phone = $token_checker->get_claim('extension_wc_billing_phone');
				$billing_email = $token_checker->get_claim('emails.0');
				$shipping_first_name = $token_checker->get_claim('extension_wc_shipping_first_name');
				$shipping_last_name = $token_checker->get_claim('extension_wc_shipping_last_name');
				$shipping_address_1 = $token_checker->get_claim('extension_wc_shipping_address');
				$shipping_postcode = $token_checker->get_claim('extension_wc_shipping_postcode');
				$shipping_city = $token_checker->get_claim('extension_wc_shipping_city');
				$shipping_state = $token_checker->get_claim('extension_wc_shipping_state');
				$shipping_country = $token_checker->get_claim('extension_wc_shipping_country');
				$shipping_phone = $token_checker->get_claim('extension_wc_shipping_phone');
				$locale = $token_checker->get_claim('extension_app_locale');

				$our_userdata = array(
					'ID' => $user->ID,
					'display_name' => $display_name,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'billing_first_name' => $billing_first_name,
					'billing_last_name' => $billing_last_name,
					'billing_address_1' => $billing_address_1,
					'billing_postcode' => $billing_postcode,
					'billing_city' => $billing_city,
					'billing_state' => $billing_state,
					'billing_country' => $billing_country,
					'billing_phone' => $billing_phone,
					'billing_email' => $billing_email,
					'shipping_first_name' => $shipping_first_name,
					'shipping_last_name' => $shipping_last_name,
					'shipping_address_1' => $shipping_address_1,
					'shipping_postcode' => $shipping_postcode,
					'shipping_city' => $shipping_city,
					'shipping_state' => $shipping_state,
					'shipping_country' => $shipping_country,
					'shipping_phone' => $shipping_phone,
					//'locale' => $locale,
				);

				update_option("aadb2c_wc_claims", $our_userdata);

				// blast through array of date pulled above and populate anything locally that is not 
				foreach($our_userdata as $key => $value) {
					update_user_meta( $userID, $key, $value );
				}

				// This is where the collected user data is joined into the WP / WC user data on edit profile call.

				$userID = wp_update_user($our_userdata);
				update_user_meta($userID, 'aadb2c_object_id', sanitize_text_field($object_id));
				do_action('aadb2c_update_userdata', $userID, $token_checker->get_payload());
			} else {
				// else user exists and we did not call from edit whatever...
				$userID = $user->ID;
				aadb2c_claims_to_wc($token_checker); // disabled for debugging of failed sign-in w/ exisitng wc / b2b user.
			}

			// Check if the user is an admin and needs MFA
			$wp_user = new WP_User($userID);
			if (in_array('administrator', $wp_user->roles)) {

				// If user did not authenticate with admin_policy, redirect to admin policy
				if (mb_strtolower($token_checker->get_claim('tfp')) != mb_strtolower(AADB2C_Settings::$admin_policy)) {
					$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$admin_policy);
					$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=admin';
					wp_redirect($authorization_endpoint);
					exit;
				}
			}

			// Set cookies to authenticate on WP side
			wp_set_auth_cookie($userID);

			// Add a hook for redirect after login
			do_action('aadb2c_post_login');

			// Redirect to home page
			wp_safe_redirect(site_url() . '/');
			exit;
		}
	} catch (Exception $e) {
		echo $e->getMessage();
		exit;
	}
}



/** 
 * Redirects to B2C's edit profile policy when user edits their profile.
 */
function aadb2c_edit_profile()
{

	// Check to see if user was requesting the edit_profile page, if so redirect to B2C
	$pagename = $_SERVER['REQUEST_URI'];
	$parts = explode('/', $pagename);
	$len = count($parts);
	if ($len > 1 && $parts[$len - 2] == "wp-admin" && $parts[$len - 1] == "profile.php") {

		// Return URL for edit_profile endpoint
		try {
			$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$edit_profile_policy);
			$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=edit_profile';
			wp_redirect($authorization_endpoint);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		exit;
	}
}

/**
 * Redirects to B2C on a password reset request.
 */
function aadb2c_password_reset()
{
	try {
		$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$password_reset_policy);
		$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=password_reset';
		wp_redirect($authorization_endpoint);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	exit;
}


/** 
 * gets claim calues form OAuth B2C token and returns them in an option 
 */
// This does now work broken out like this, because its missing the $_POST i think...
function aadb2c_claims_to_wc($token_checker)
{
	try {
		// define things we need for token checker
		//$policy = AADB2C_Settings::$generic_policy;
		// define token checker
		//$token_checker = new AADB2C_Token_Checker($_POST[AADB2C_RESPONSE_MODE], AADB2C_Settings::$clientID, $policy);

        $name = $token_checker->get_claim('name'); // Is this a mistake, not referenced in the code anywhere?
        $first_name = $token_checker->get_claim('given_name');
        $last_name = $token_checker->get_claim('family_name');
        $display_name = $token_checker->get_claim('name');
        $billing_first_name = $token_checker->get_claim('given_name');
        $billing_last_name = $token_checker->get_claim('family_name');
        $billing_address_1 = $token_checker->get_claim('streetAddress');
        $billing_postcode = $token_checker->get_claim('postalCode');
        $billing_city = $token_checker->get_claim('city');
        $billing_state = $token_checker->get_claim('state');
        $billing_country = $token_checker->get_claim('country');
        $billing_phone = $token_checker->get_claim('extension_wc_billing_phone');
        $billing_email = $token_checker->get_claim('emails.0');
        $shipping_first_name = $token_checker->get_claim('extension_wc_shipping_first_name');
        $shipping_last_name = $token_checker->get_claim('extension_wc_shipping_last_name');
        $shipping_address_1 = $token_checker->get_claim('extension_wc_shipping_address');
        $shipping_postcode = $token_checker->get_claim('extension_wc_shipping_postcode');
        $shipping_city = $token_checker->get_claim('extension_wc_shipping_city');
        $shipping_state = $token_checker->get_claim('extension_wc_shipping_state');
        $shipping_country = $token_checker->get_claim('extension_wc_shipping_country');
        $shipping_phone = $token_checker->get_claim('extension_wc_shipping_phone');
        $locale = $token_checker->get_claim('extension_app_locale');

        $our_userdata = array(
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'billing_first_name' => $billing_first_name,
            'billing_last_name' => $billing_last_name,
            'billing_address_1' => $billing_address_1,
            'billing_postcode' => $billing_postcode,
            'billing_city' => $billing_city,
            'billing_state' => $billing_state,
            'billing_country' => $billing_country,
            'billing_phone' => $billing_phone,
            'billing_email' => $billing_email,
            'shipping_first_name' => $shipping_first_name,
            'shipping_last_name' => $shipping_last_name,
            'shipping_address_1' => $shipping_address_1,
            'shipping_postcode' => $shipping_postcode,
            'shipping_city' => $shipping_city,
            'shipping_state' => $shipping_state,
            'shipping_country' => $shipping_country,
            'shipping_phone' => $shipping_phone,
            //'locale' => $locale,
        );
		
        update_option("aadb2c_wc_claims", $our_userdata);
        exit;
        
	} catch (Exception $e) {
		echo $e->getMessage();
		exit;
	}
}

//if ( isset( $_GET['code'] ) ) {
//}



// The authenticate filter
add_filter( 'authenticate', 'aadb2c_authenticate', 1, 3 );
//add_filter( 'authenticate', 'aadb2c_authenticate', 30, 3 );

function aadb2c_authenticate( $user, $username, $password ) {

	global $GraphToken;
	$GraphToken = array();

	// Don't re-authenticate if already authenticated
	if ( is_a( $user, 'WP_User' ) ) { return $user; }

	// If we're mapping Azure AD groups to WordPress roles, make the Graph API call here
	AADB2C_Graph_Helper::$settings  = $this->settings;

	/* If 'code' is present, this is the Authorization Response from Azure AD, and 'code' has
	 * the Authorization Code, which will be exchanged for an ID Token and an Access Token.
	 */
	if ( isset( $_GET['code'] ) ) {
		// Looks like we got a valid authorization code, let's try to get an access token with it
		//$token = $graph_helper->get_access_token( $_GET['code'], $settings );
		$token = AADB2C_Authorization_Helper::get_access_token( $_GET['code'], $this->settings );
		$GraphToken = array(
			'aadb2c_token_type' => $token->token_type,
			'aadb2c_access_token' => $token->access_token,
		);
	}
}	


/** 
 * 
 * Trigger on 
 * add_action( 'profile_update', 'aadb2c_patch_wc_meta_to_b2c' ); // Fires immediately after an existing user is updated.
 * add_action( 'user_register', 'aadb2c_patch_wc_meta_to_b2c' );  // Fires immediately after a new user is registered.
 * 
 */
function aadb2c_patch_wc_meta_to_b2c()
{
	// make this function use the az b2c app creds, patch function to set the values from wc to b2c whenever the profile is touched, profile_update
	//update_option("aadb2c_wc_claims", $our_userdata);
	
	// Get current user id
	$user_id = get_current_user_id();

	global $GraphToken;

	$graph_helper = new AADB2C_Graph_Helper();
	//$graph_helper = new AADB2C_GraphHelper($_GET['code'], $settings);

	// Get the data from the last claim pull
	//aadb2c_claims_to_wc(); <-- dropping this because the function is not working broken out as is.

	$remote_userdata = get_option("aadb2c_wc_claims");
	$local_userdata = get_userdata( $user_id );

	// Get all user meta data for $user_id
	$local_usermeta = get_user_meta( $user_id );
	
	// i think this is bad to have a function in a funciton, so i will try and break it up. or maybe not this is anonomous funciton, should work.
	// Old Code - Filter out empty meta data
	$local_usermeta = array_filter( array_map( function( $value ) {
		return $value[0];
	}, $local_usermeta ) );

	// Dev Ref Code
	// PHP 7.4 and later
	// print_r(array_filter($linksArray, fn($value) => !is_null($value) && $value !== ''));
	// PHP 5.3 and later
	// print_r(array_filter($linksArray, function($value) { return !is_null($value) && $value !== ''; }));
	// PHP < 5.3
	//print_r(array_filter($linksArray, create_function('$value', 'return $value !== "";')));
	
	$graph_helper->set_user_attribute( $GraphToken, $user_id, 'given_name', 'Bob' );
	$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_last_name', 'Balogna' );

	// This should be optimized to just make one api call with array, but for now it is like it is :-(
    if ( $local_userdata->first_name != $remote_userdata->first_name )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'given_name', $local_userdata->first_name );

	if ( $local_userdata->last_name != $remote_userdata->last_name )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'family_name', $local_userdata->last_name );

	if ( $local_userdata->display_name != $remote_userdata->display_name )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'name', $local_userdata->display_name );
	
	if ( $local_userdata->billing_first_name != $remote_userdata->billing_first_name )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'given_name', $local_userdata->billing_first_name );

	if ( $local_userdata->billing_last_name != $remote_userdata->billing_last_name )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'family_name', $local_userdata->billing_last_name );

	if ( $local_userdata->billing_address_1 != $remote_userdata->billing_address_1 )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'streetAddress', $local_userdata->billing_address_1 );

	if ( $local_userdata->billing_postcode != $remote_userdata->billing_postcode )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'postalCode', $local_userdata->billing_postcode );

	if ( $local_userdata->billing_city != $remote_userdata->billing_city )		
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'city', $local_userdata->billing_city );

	if ( $local_userdata->billing_state != $remote_userdata->billing_state )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'state', $local_userdata->billing_state );
	
	if ( $local_userdata->billing_country != $remote_userdata->billing_country )
		$graph_helper->set_user_attribute( $GraphToken, $user_id, 'country', $local_userdata->billing_country );

	//if ( $local_userdata->billing_email != $remote_userdata->billing_email )
	//	$graph_helper->set_user_attribute( $GraphToken, $user_id, 'emails.0', $local_userdata->billing_email );
	
	// set Custom attributes
	if ( $local_userdata->billing_phone != $remote_userdata->billing_phone )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_billing_phone', $local_usermeta->billing_phone );

	if ( $local_userdata->shipping_first_name != $remote_userdata->shipping_first_name )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_first_name', $local_usermeta->shipping_first_name );
	
	if ( $local_userdata->shipping_last_name != $remote_userdata->shipping_last_name )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_last_name', $local_usermeta->shipping_last_name );
	
	if ( $local_userdata->shipping_address_1 != $remote_userdata->shipping_address_1 )
		$shipping_address_1 = $graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_address', $local_usermeta->shipping_address_1 );

	if ( $local_userdata->shipping_postcode != $remote_userdata->shipping_postcode )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_postcode', $local_usermeta->shipping_postcode );

	if ( $local_userdata->shipping_city != $remote_userdata->shipping_city )	
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_city', $local_usermeta->shipping_city );

	if ( $local_userdata->shipping_state != $remote_userdata->shipping_state )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_state', $local_usermeta->shipping_state );

	if ( $local_userdata->shipping_country != $remote_userdata->shipping_country )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_country', $local_usermeta->shipping_country );

	if ( $local_userdata->shipping_phone != $remote_userdata->shipping_phone )
		$graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'wc_shipping_phone', $local_usermeta->shipping_phone );
	//$locale = $graph_helper->set_user_custom_extension( $GraphToken, $user_id, 'app_locale', );

	// If we're mapping Azure AD groups to WordPress roles, make the Graph API call here
	//AADB2C_GraphHelper::$settings  = $this->settings;

	//$token_checker = new AADB2C_Token_Checker($_POST[AADB2C_RESPONSE_MODE], AADB2C_Settings::$clientID, $policy);
	//$graph_helper = new AADB2C_GraphHelper();
	//graph_helper
	//AADB2C_GraphHelper::set_user_custom_extension( $user_id, $extension_name, $extension_value );

	// Of the AAD groups defined in the settings, get only those where the user is a member
	//$group_ids         = array_keys( $this->settings->aad_group_to_wp_role_map );
	//$group_memberships = AADB2C_GraphHelper::user_check_member_groups( $jwt->oid, $group_ids );
	exit;

}

// disabled while testing basic login functionality
// 1. Sign Up & Sign In - Works! w/ FirstName and LastName on signup, getting passed back to WP. This was new user in both WP and B2C - so like first time customer.
// Flow Notes, I triggered the login via using the WC login form presented when clicking "my account", while not logged in, then putting in dummy 
// email and password, hitting login redirects to B2C login, for final flow in need to disable all the WC login pages, 
// ideal would be WC login / register buttons redirect to B2C
// 2. Sign In w/ existing WC / B2C user just created... - This FAILS! Redirect left at https://preorder.kekz.com/ & generic failure message from WP
// WP FAIL MESSAGE: Es gab einen kritischen Fehler auf deiner Website. Erfahre mehr über die Problembehandlung in WordPress. - https://wordpress.org/support/article/faq-troubleshooting/
// found source in 
//add_action( 'profile_update', 'aadb2c_patch_wc_meta_to_b2c' ); // Fires immediately after an existing user is updated.
//add_action( 'user_register', 'aadb2c_patch_wc_meta_to_b2c' );  // Fires immediately after a new user is registered.
// ^ The action hooks on profile update above are two general and 

add_action( 'profile_update', 'aadb2c_when_profile_update', 10, 2 );

function aadb2c_when_profile_update( $user_id, $old_user_data ) {
    if (is_user_logged_in()) { 
        // User Updating profile info when logged in 
		// The user log in and update some data within his profile and save (profile_update gets called here)
		// Here we call the function to sync this local update up to b2c
		aadb2c_patch_wc_meta_to_b2c();
    } else { 
        if (empty($old_user_data->user_activation_key)) { 
			// Registering - user's first registration step
			// The user fills email and username and save (profile_update gets called here), 
			// being presented the request to check email for the verification process
			// So here we do nothing
        }
    }
	exit;
}



// Try and force login to use WooCommerce Checkout - Tested and it works!!!! need to add taggle to settings page!
// later add a toggle is settings to enable / disable this
// also for this flow i have set the following in WC Config. e.g. /wp-admin/admin.php?page=wc-settings&tab=account
/*
Guest checkout	
	[UnChecked] - Guest checkout Allow customers to place orders without an account
	[UnChecked] - Login Allow customers to log into an existing account during checkout
Account creation	Account creation Allow customers to create an account during checkout
	[UnChecked] - Allow customers to create an account on the "My account" page
	[UnChecked] - When creating an account, automatically generate an account username for the customer based on their name, surname or email
	[UnChecked] - When creating an account, automatically generate an account password

*/	
add_action('template_redirect','aadb2c_check_if_logged_in');


function aadb2c_check_if_logged_in()
{
	$pageid = get_option( 'woocommerce_checkout_page_id' );
	if(!is_user_logged_in() && is_page($pageid))
	{
		/*
		$url = add_query_arg(
			'redirect_to',
			get_permalink($pagid),
			site_url('/my-account/') // your my account url
		);
		wp_redirect($url);
		*/

		aadb2c_login();
		exit;
	}
	/*
	if(is_user_logged_in())
	{
	if(is_page(get_option( 'woocommerce_myaccount_page_id' )))
	{
		
		$redirect = $_GET['redirect_to'];
		if (isset($redirect)) {
		echo '<script>window.location.href = "'.$redirect.'";</script>';
		}

	}
	}
	*/
}


/** 
 * Hooks onto the WP login action, so when user logs in on WordPress, user is redirected
 * to B2C's authorization endpoint. 
 */
//add_action('wp_authenticate', 'aadb2c_login'); // disabled to allow WP Login

/** 
 * Hooks onto the WP lost password action, so user is redirected
 * to B2C's password reset endpoint. 
 * 
 * example.com/wp-login.php?action=lostpassword
 */
add_action('login_form_lostpassword', 'aadb2c_password_reset');

/**
 * Hooks onto the WP page load action, so when user request to edit their profile, 
 * they are redirected to B2C's edit profile endpoint.
 */
add_action('wp_loaded', 'aadb2c_edit_profile');

/** 
 * Hooks onto the WP page load action. When B2C redirects back to WordPress site,
 * if an ID token is POSTed to a special path, b2c-token-verification, this verifies 
 * the ID token and authenticates the user.
 */
add_action('wp_loaded', 'aadb2c_verify_token');

/**
 * Hooks onto the WP logout action, so when a user logs out of WordPress, 
 * they are redirected to B2C's logout endpoint.
 */
add_action('wp_logout', 'aadb2c_logout');


