<?php

/**
 * Plugin Name: Azure AD SSO B2C w/ WooCommerce Support
 * Plugin URI: https://github.com/putersdcat/Azure-B2C-Wordpress-Plugin
 * Description: A fork in progress of a plugin that allows users to log in using Az Ad B2C policies, and sync WC MetaData from Custom attributes in Az Ad
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

/**
 * Adds a link to setting page in WP Plugin manager.
 */
function aadb2c_plugin_settings_link($links) { 
	$settings_link = '<a href="options-general.php?page=aad-b2c-settings-page">Settings</a>'; 
	array_unshift($links, $settings_link); 
	return $links; 
}
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'aadb2c_plugin_settings_link' );

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

function aadb2c_login_custom($custom_redirect_uri)
{
	try {
		$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$generic_policy);
		//$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint_set_redirect($custom_redirect_uri) . "&state=generic~" . urlencode($custom_redirect_uri);
		$authorization_endpoint = $aadb2c_endpoint_handler->get_authorization_endpoint() . "&state=generic~" . urlencode($custom_redirect_uri);
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
			$stateType = explode('~', $_POST['state']);
			//switch ($_POST['state']) {
			switch ($stateType[0]) {
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

			// Grab the ReturnUri if it was appended onto the state parameter
			if (!empty( $stateType[1] ) )
			{
					$ReturnUri = $stateType[1];
			} else {
					$ReturnUri = site_url() . '/';
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
				$display_name = $token_checker->get_claim('name');
				if (stripos($display_name, 'unknown') !== false) { // on new user first prov in b2c unknown is returned - Case insensitive
					$display_name = $first_name . ' ' . $last_name;
				}
				$billing_first_name = $token_checker->get_claim('extension_wc_billing_first_name');
				$billing_last_name = $token_checker->get_claim('extension_wc_billing_last_name');
				$billing_address_1 = $token_checker->get_claim('extension_wc_billing_address');
				$billing_postcode = $token_checker->get_claim('extension_wc_billing_postcode');
				$billing_city = $token_checker->get_claim('extension_wc_billing_city');
				$billing_state = $token_checker->get_claim('extension_wc_billing_state');
				$billing_country = $token_checker->get_claim('extension_wc_billing_country');
				$billing_phone = $token_checker->get_claim('extension_wc_billing_phone');
				$billing_email = $token_checker->get_claim('extension_wc_billing_email');
				$shipping_first_name = $token_checker->get_claim('extension_wc_shipping_first_name');
				$shipping_last_name = $token_checker->get_claim('extension_wc_shipping_last_name');
				$shipping_address_1 = $token_checker->get_claim('extension_wc_shipping_address');
				$shipping_postcode = $token_checker->get_claim('extension_wc_shipping_postcode');
				$shipping_city = $token_checker->get_claim('extension_wc_shipping_city');
				$shipping_state = $token_checker->get_claim('extension_wc_shipping_state');
				$shipping_country = $token_checker->get_claim('extension_wc_shipping_country');
				$shipping_phone = $token_checker->get_claim('extension_wc_shipping_phone');
				$locale = $token_checker->get_claim('extension_app_locale');

				// This is new user first logon
				$our_userdata = array(
					'ID' => 0,
					'user_login' => $email,
					'user_pass' => NULL,
					'user_registered' => date('Y-m-d H:i:s'),
					'user_status' => 0,
					'user_email' => $email,
					'first_name' => $first_name,
					'last_name' => $last_name,
					//'display_name' => $first_name . ' ' . $last_name,
					'display_name' => $display_name,
					//'role' => customer, it looks like in the WC code when this is null it will default to customer, lets see.
				);

				// This is new user first logon
				$our_wc_usermeta = array(
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

				// This is where the collected user data is joined into the WP / WC user data on sign in or sign up call.
				$userID = wp_insert_user($our_userdata);
				update_user_meta($userID, 'aadb2c_object_id', sanitize_text_field($object_id));
				// this is new
				aadb2c_update_wc_profile_meta_from_claims( $userID, $our_wc_usermeta);
				do_action('aadb2c_new_userdata', $userID, $token_checker->get_payload());
			} else if ($policy == AADB2C_Settings::$edit_profile_policy) { // Update the existing user w/ new attritubtes

				$first_name = $token_checker->get_claim('given_name');
				$last_name = $token_checker->get_claim('family_name');
				$display_name = $token_checker->get_claim('name');
				if (stripos($display_name, 'unknown') !== false) { // on new user first prov in b2c unknown is returned - Case insensitive
					$display_name = $first_name . ' ' . $last_name;
				}
				$billing_first_name = $token_checker->get_claim('extension_wc_billing_first_name');
				$billing_last_name = $token_checker->get_claim('extension_wc_billing_last_name');
				$billing_address_1 = $token_checker->get_claim('extension_wc_billing_address');
				$billing_postcode = $token_checker->get_claim('extension_wc_billing_postcode');
				$billing_city = $token_checker->get_claim('extension_wc_billing_city');
				$billing_state = $token_checker->get_claim('extension_wc_billing_state');
				$billing_country = $token_checker->get_claim('extension_wc_billing_country');
				$billing_phone = $token_checker->get_claim('extension_wc_billing_phone');
				$billing_email = $token_checker->get_claim('extension_wc_billing_email');
				$shipping_first_name = $token_checker->get_claim('extension_wc_shipping_first_name');
				$shipping_last_name = $token_checker->get_claim('extension_wc_shipping_last_name');
				$shipping_address_1 = $token_checker->get_claim('extension_wc_shipping_address');
				$shipping_postcode = $token_checker->get_claim('extension_wc_shipping_postcode');
				$shipping_city = $token_checker->get_claim('extension_wc_shipping_city');
				$shipping_state = $token_checker->get_claim('extension_wc_shipping_state');
				$shipping_country = $token_checker->get_claim('extension_wc_shipping_country');
				$shipping_phone = $token_checker->get_claim('extension_wc_shipping_phone');
				$locale = $token_checker->get_claim('extension_app_locale');
				
				// This is existing wp user running the edit profile routine, not really used!
				$our_userdata = array(
					'ID' => $user->ID,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'display_name' => $display_name,
				);

				// This is existing wp user running the edit profile routine, not really used!
				$our_wc_usermeta = array(
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
				
				// This is where the collected user data is joined into the WP / WC user data on edit profile call.

				$userID = wp_update_user($our_userdata);
				update_user_meta($userID, 'aadb2c_object_id', sanitize_text_field($object_id));
				// this is new
				aadb2c_update_wc_profile_meta_from_claims( $userID, $our_wc_usermeta);
				do_action('aadb2c_update_userdata', $userID, $token_checker->get_payload());
			} else {
				// else user exists and we did not call from edit whatever...
				$userID = $user->ID;
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
			//wp_safe_redirect(site_url() . '/');
			
			wp_safe_redirect($ReturnUri);
			
			exit;
		}
	} catch (Exception $e) {
		echo $e->getMessage();
		exit;
	}
}

/** 
 * 
 * This function can be called on logins to process the claims returned by Az B2C and apply them locally when possible.
 * 
 */
function aadb2c_update_wc_profile_meta_from_claims( $WcUserId, $UserClaims = array() )
{
	
    // Get all user meta data for $user_id
	$local_usermeta = get_user_meta( $WcUserId ); // It returns an Array or arrays, so we flatten below.
	
	// assoiciative flattner - with this we avoid the need to use $local_usermeta['billing_first_name'][0] for all values
	$local_usermeta = array_filter( array_map( function( $value ) { 
		return $value[0];
	}, $local_usermeta ) );

    
    /*
				// This is existing wp user running the edit profile routine, not really used!
				$our_wc_usermeta = array(
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
    */

    // blast through array and update any local values that are not equal.
    foreach($UserClaims as $key => $value) {
        //if ( isset($local_usermeta[$key]) && $local_usermeta[$key] != $value )
        if ( !empty($value) && $local_usermeta[$key] != $value ) {
            update_user_meta( $WcUserId, $key, $value );
        }
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


function aadb2c_token_authenticate() {

	$GraphToken = array();

	$token = aadb2c_get_access_token();
	//error_log( 'In aadb2c_token_authenticate: token' . print_r($GraphToken, 1 ) );
	
	if ( isset( $token->access_token ) ) {
		$GraphToken = array(
			'aadb2c_token_type' => $token->token_type,
			'aadb2c_access_token' => $token->access_token,
		);
		//error_log( 'In aadb2c_token_authenticate: GraphToken' . print_r($GraphToken, 1 ) );
	} else {
		error_log( 'In aadb2c_token_authenticate: access_token not set in token' . print_r($token, 1 ) );
	}
	return $GraphToken;
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
	$WcUserId = get_current_user_id();

	$AzUserId = get_user_meta( $WcUserId, 'aadb2c_object_id', true );
	//error_log( 'In aadb2c_patch_wc_meta_to_b2c - AzUserId' . print_r($AzUserId, 1 ) );
	
	$GraphToken = aadb2c_token_authenticate();
	//error_log( 'In aadb2c_patch_wc_meta_to_b2c: GraphToken' . print_r($GraphToken, 1 ) );

	$graph_helper = new AADB2C_Graph_Helper();

	//$remote_userdata = $graph_helper->get_user_attributes( $GraphToken, $AzUserId );
	$remote_userdata = $graph_helper->get_clean_user_attributes( $GraphToken, $AzUserId ); // Now - it returns an Object!
	//error_log( 'In aadb2c_patch_wc_meta_to_b2c - remote_userdata' . print_r($remote_userdata, 1 ) );

	//error_log( 'In aadb2c_patch_wc_meta_to_b2c - remote_userdata' . print_r($remote_userdata, 1 ) );
	$local_userdata = get_userdata( $WcUserId ); // this one is good - it returns an Object!
	//error_log( 'In aadb2c_patch_wc_meta_to_b2c - local_userdata' . print_r($local_userdata, 1 ) );

	// Get all user meta data for $user_id
	$local_usermeta = get_user_meta( $WcUserId ); // It returns an Array or arrays, so we flatten below.
	
	// assoiciative flattner - with this we avoid the need to use $local_usermeta['billing_first_name'][0] for all values
	$local_usermeta = array_filter( array_map( function( $value ) { 
		return $value[0];
	}, $local_usermeta ) );

	//var_dump($local_usermeta);
	//die;

	// build array of Custom user attributes to set
	$UpdateAttribs = array();
	//$data[$key] = $value;

	// Quick Sanity Test!
	//$UpdateAttribs['surname'] = 'MasterSon';

	//error_log( 'local_userdata' . print_r($local_userdata, 1 ) );

	// This should be optimized to just make one api call with array, but for now it is like it is :-(
    if ( isset($local_userdata->first_name) && $local_userdata->first_name != $remote_userdata->givenName )
		$UpdateAttribs['givenName'] = $local_userdata->first_name; // Good

	if ( isset($local_userdata->last_name) && $local_userdata->last_name != $remote_userdata->surname )
		$UpdateAttribs['surname'] = $local_userdata->last_name; // Good

	if ( isset($local_userdata->display_name) && $local_userdata->display_name != $remote_userdata->displayName )
		$UpdateAttribs['displayName'] = $local_userdata->display_name; // Good
	
	// here start the local user meta WC specific properties, that we just map to common AD user properties
	// I am going to move away from this and i made new custom attributes in b2c ad for the billing specific meta <values class=""></values>
	//if ( isset($local_usermeta['billing_address_1']) && $local_usermeta['billing_address_1'] != $remote_userdata->streetAddress )
	//	$UpdateAttribs['streetAddress'] = $local_usermeta['billing_address_1'];

	//if ( isset($local_usermeta['billing_postcode']) && $local_usermeta['billing_postcode'] != $remote_userdata->postalCode )
	//	$UpdateAttribs['postalCode'] = $local_usermeta['billing_postcode'];

	//if ( isset($local_usermeta['billing_city']) && $local_usermeta['billing_city'] != $remote_userdata->city )		
	//	$UpdateAttribs['city'] = $local_usermeta['billing_city'];

	//if ( isset($local_usermeta['billing_state']) && $local_usermeta['billing_state'] != $remote_userdata->state )
	//	$UpdateAttribs['state'] = $local_usermeta['billing_state'];
	
	//if ( isset($local_usermeta['billing_country']) && $local_usermeta['billing_country'] != $remote_userdata->country )
	//	$UpdateAttribs['country'] = $local_usermeta['billing_country'];

	//if ( $local_userdata->billing_email != $remote_userdata->billing_email )
	//	$UpdateAttribs['emails.0'] = $local_userdata->billing_email;
	
	if(!empty($UpdateAttribs)) {
		//error_log( 'UpdateAttribs' . print_r($UpdateAttribs, 1 ) );

		// Set the updated user attributes in a one shot array
		$graph_helper->set_user_attributes( $GraphToken, $AzUserId, $UpdateAttribs );
	}
	// build array of Custom user attributes to set
	$UpdateCustomAttribs = array();

	// Quick Sanity Test!
	//$UpdateCustomAttribs['wc_shipping_first_name'] = 'AssMann';
	// Well that worked, so the problem in sin the if statement logic below!

	//error_log( 'local_usermeta' . print_r($local_usermeta, 1 ) );

	// figure this our out maybe just overwrite, or just make this take an arry and not 5million calls and do the comparison work in the backend

	if ( isset($local_usermeta['billing_first_name']) && $local_usermeta['billing_first_name'] != $remote_userdata->wc_billing_first_name )
		$UpdateCustomAttribs['wc_billing_first_name'] = $local_usermeta['billing_first_name'];
	
	if ( isset($local_usermeta['billing_first_name']) && $local_usermeta['billing_last_name'] != $remote_userdata->wc_billing_first_name )
		$UpdateCustomAttribs['wc_billing_last_name'] = $local_usermeta['billing_last_name'];
	
	if ( isset($local_usermeta['billing_address_1']) && $local_usermeta['billing_address_1'] != $remote_userdata->wc_billing_first_name )	
		$UpdateCustomAttribs['wc_billing_address'] = $local_usermeta['billing_address_1'];
	
	if ( isset($local_usermeta['billing_city']) && $local_usermeta['billing_city'] != $remote_userdata->wc_billing_first_name )
		$UpdateCustomAttribs['wc_billing_city'] = $local_usermeta['billing_city'];
	
	if ( isset($local_usermeta['billing_postcode']) && $local_usermeta['billing_postcode'] != $remote_userdata->wc_billing_first_name )	
		$UpdateCustomAttribs['wc_billing_postcode'] = $local_usermeta['billing_postcode'];
	
	if ( isset($local_usermeta['billing_state']) && $local_usermeta['billing_state'] != $remote_userdata->wc_billing_first_name )	
		$UpdateCustomAttribs['wc_billing_state'] = $local_usermeta['billing_state'];
	
	if ( isset($local_usermeta['billing_country']) && $local_usermeta['billing_country'] != $remote_userdata->wc_billing_first_name )
		$UpdateCustomAttribs['wc_billing_country'] = $local_usermeta['billing_country'];

	if ( isset($local_usermeta['billing_phone']) && $local_usermeta['billing_phone'] != $remote_userdata->wc_billing_phone ) 
		$UpdateCustomAttribs['wc_billing_phone'] = $local_usermeta['billing_phone'];

	if ( isset($local_usermeta['billing_email']) && $local_usermeta['billing_email'] != $remote_userdata->wc_billing_email )
		$UpdateCustomAttribs['wc_billing_email'] = $local_usermeta['billing_email'];

	if ( isset($local_usermeta['shipping_first_name']) && $local_usermeta['shipping_first_name'] != $remote_userdata->wc_shipping_first_name )
		$UpdateCustomAttribs['wc_shipping_first_name'] = $local_usermeta['shipping_first_name'];
	
	if ( isset($local_usermeta['shipping_last_name']) && $local_usermeta['shipping_last_name'] != $remote_userdata->wc_shipping_last_name )
		$UpdateCustomAttribs['wc_shipping_last_name'] = $local_usermeta['shipping_last_name'];
	
	if ( isset($local_usermeta['shipping_address_1']) && $local_usermeta['shipping_address_1'] != $remote_userdata->wc_shipping_address )
		$UpdateCustomAttribs['wc_shipping_address'] = $local_usermeta['shipping_address_1'];

	if ( isset($local_usermeta['shipping_postcode']) && $local_usermeta['shipping_postcode'] != $remote_userdata->wc_shipping_postcode )
		$UpdateCustomAttribs['wc_shipping_postcode'] = $local_usermeta['shipping_postcode'];

	if ( isset($local_usermeta['shipping_city']) && $local_usermeta['shipping_city'] != $remote_userdata->wc_shipping_city )	
		$UpdateCustomAttribs['wc_shipping_city'] = $local_usermeta['shipping_city'];

	if ( isset($local_usermeta['shipping_state']) && $local_usermeta['shipping_state'] != $remote_userdata->wc_shipping_state )
		$UpdateCustomAttribs['wc_shipping_state'] = $local_usermeta['shipping_state'];

	if ( isset($local_usermeta['shipping_country']) && $local_usermeta['shipping_country'] != $remote_userdata->wc_shipping_country )
		$UpdateCustomAttribs['wc_shipping_country'] = $local_usermeta['shipping_country'];

	if ( isset($local_usermeta['shipping_phone']) && $local_usermeta['shipping_phone'] != $remote_userdata->wc_shipping_phone )
		$UpdateCustomAttribs['wc_shipping_phone'] = $local_usermeta['shipping_phone'];

	//$locale = $graph_helper->set_user_custom_extension( $GraphToken, $AzUserId, 'app_locale';
	if(!empty($UpdateCustomAttribs)) {
		//error_log( 'UpdateCustomAttribs' . print_r($UpdateCustomAttribs, 1 ) );

		// set Custom attributes as one call 
		$graph_helper->set_user_custom_extensions( $GraphToken, $AzUserId, $UpdateCustomAttribs );
	}

}

//add_action( 'profile_update', 'aadb2c_when_profile_update', 10, 2 );
if (AADB2C_Settings::$EnableGraphArrtibuteSync) {
	add_action( 'woocommerce_customer_object_updated_props', 'aadb2c_when_profile_update', 10, 2 );
}


function aadb2c_when_profile_update( $customer, $updated_props ) {
	//&& !is_page(get_option( 'woocommerce_checkout_page_id' ))
    if (is_user_logged_in()) { 
        // User Updating profile info when logged in 
		// The user log in and update some data within his profile and save (profile_update gets called here)
		// Here we call the function to sync this local update up to b2c
		aadb2c_patch_wc_meta_to_b2c();
    } else { 
        if (empty($updated_props->user_activation_key)) { 
			// Registering - user's first registration step
			// The user fills email and username and save (profile_update gets called here), 
			// being presented the request to check email for the verification process
			// So here we do nothing
        }
    }

	// Dont redirect on ever, what was this even for? 
	/*
	if  ( !is_page(get_option( 'woocommerce_checkout_page_id' )) ) {
		if ( isset($_GET['redirect_to']) ) {
			wp_safe_redirect( get_permalink($_GET['redirect_to']) );
		} else { 
			wp_safe_redirect($_SERVER['HTTP_REFERER']);
		}
	}
	*/
	//exit(); <----- THIS EXIT WAS VERY BAD BROKE CKECKOUT, KEPT FOR REMINDER
}

// 
if (!AADB2C_Settings::$ToggleOffHackyStuff) {
	add_filter ( 'woocommerce_account_menu_items', 'aadb2c_set_custom_WC_MyAccount_Password_Email_Links' );
	add_filter( 'woocommerce_get_endpoint_url', 'aadb2c_custom_wc_my_account_endpoints', 10, 2 );
	add_action( 'woocommerce_save_account_details_errors', 'remove_email_from_edit_account_process', 10, 2 );
	add_action( 'woocommerce_edit_account_form', 'aadb2c_remove_edit_account_links' );
	add_action( 'woocommerce_account_dashboard', 'aadb2c_remove_edit_password_link' );
}


// This dirty shit works, but form completion check on email address breaks it, so im resorting to re-direct to azure b2b
// but i just show firstname lastname and display name, except we cant do this, because will it sync back? well maybe,
// because we do try and get the claims and sync them back to local on an account edit call.
// but will the un-filtered nulls break something or over write good local values, who knows, im going to bed.
//add_action( 'woocommerce_edit_account_form', 'aadb2c_remove_edit_account_links' );
// Remove "Edit" links from My Account > Addresses
// 			jQuery('#account_email').remove();
// ok this is fun, now we keep the email address field, but ignore when it is changed!
function aadb2c_remove_edit_account_links() {
	if ( is_page(get_option( 'woocommerce_myaccount_page_id' ))) {
		wc_enqueue_js( "
			jQuery(document).ready(function() {
				jQuery('#post-56 > div.post-inner.thin > div > div > div > form > fieldset').remove();
			});
		" );
	}
}

// This strips out the dashboard text about resetting your password and just leaves, edit you account, but here hardcoded to german, maybe fix later. 
//add_action( 'woocommerce_account_dashboard', 'aadb2c_remove_edit_password_link' );
function aadb2c_remove_edit_password_link() {
    if ( is_page(get_option( 'woocommerce_myaccount_page_id' ))) {
		wc_enqueue_js( "
			jQuery(document).ready(function() {
				jQuery('#post-56 > div.post-inner.thin > div > div > div > p:nth-child(3) > a:nth-child(3)').text('Kontodetails bearbeiten');
			});
		" );
	}
}


//#post-56 > div.post-inner.thin > div > div > div > form > fieldset > legend
//#post-56 > div.post-inner.thin > div > div > div > form > fieldset > p:nth-child(2) > span

// This will surpress the editing of the email address on the my account page, user can change it but its rejected from the forum submit
//add_action( 'woocommerce_save_account_details_errors', 'remove_email_from_edit_account_process', 10, 2 );
function remove_email_from_edit_account_process( $errors, $user ) {
	if ( is_page(get_option( 'woocommerce_myaccount_page_id' ))) {
        if ( ! empty( $user->user_email ) ) {
			unset($user->user_email);
		}
	}
}


/*
add_filter( 'woocommerce_billing_fields', 'remove_billing_account_fields', 25, 1 );
function remove_billing_account_fields ( $billing_fields ) {
    // Only my account billing address for logged in users
    if( is_account_page() ){
        unset($billing_fields['billing_first_name']);
        unset($billing_fields['billing_last_name']);
        unset($billing_fields['billing_email']);
    }
    return $billing_fields;
}
*/

// Add new Custom Menu Links (URLs) in My Account Menu 
function aadb2c_set_custom_WC_MyAccount_Password_Email_Links( $menu_links ){

	//Kontodetails bearbeiten
	$new_links = array( 'kennwort-reset' => 'Kennwort zurücksetzen' );

	// array_slice() is good when you want to add an element between the other ones
	$menu_links = array_slice( $menu_links, 0, 1, true ) 
	+ $new_links 
	+ array_slice( $menu_links, 1, NULL, true );
 
	// Remove exisitng links
	unset( $menu_links['dashboard'] ); // Remove Dashboard
	//unset( $menu_links['orders'] ); // Remove Orders
	unset( $menu_links['downloads'] ); // Disable Downloads
	//unset( $menu_items['edit-address'] ); // Addresses
	//unset( $menu_links['edit-account'] ); // Remove Account details tab
	//unset( $menu_links['customer-logout'] ); // Remove Logout link
	//unset( $menu_links['payment-methods'] ); // Remove Payment Methods

	return $menu_links;
}


// point the endpoint to a custom URL
function aadb2c_custom_wc_my_account_endpoints( $url, $endpoint ){
	
	if( $endpoint == 'kennwort-reset' ) {
		$custom_redirect_uri = 'https://preorder.kekz.com/mein-konto/';
		// Return URL for password_reset endpoint
		$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$password_reset_policy);
		//return $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=password_reset'; // Your custom URL to add to the My Account menu
		return $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=password_reset~' . urlencode($custom_redirect_uri); // Your custom URL to add to the My Account menu	
	}

	// This one is not in use, and should not be used in normal operation.
	if( $endpoint == 'konto-details-bearbeiten' ) {
		$custom_redirect_uri = $_SERVER['HTTP_REFERER'];
		// Return URL for edit_profile endpoint
		$aadb2c_endpoint_handler = new AADB2C_Endpoint_Handler(AADB2C_Settings::$edit_profile_policy);
		//return $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=edit_profile'; // Your custom URL to add to the My Account menu
		return $aadb2c_endpoint_handler->get_authorization_endpoint() . '&state=edit_profile~' . urlencode($custom_redirect_uri); // Your custom URL to add to the My Account menu	
	}

	return $url;
}

// Try and force login to use WooCommerce Checkout - Tested and it works!!!! need to add taggle to settings page!
// also for this flow i have set the following in WC Config. e.g. /wp-admin/admin.php?page=wc-settings&tab=account
/*
Guest checkout	
	[UnChecked] - Guest checkout Allow customers to place orders without an account
	[UnChecked] - Login Allow customers to log into an existing account during checkout
Account creation	Account creation Allow customers to create an account during checkout
	[UnChecked] - Allow customers to create an account on the "My account" page
	[UnChecked] - When creating an account, automatically generate an account username for the customer based on their name, surname or email
	[UnChecked] - When creating an account, automatically generate an account password

	also needs hook - add_action('template_redirect','aadb2c_check_if_logged_in');
*/	
function aadb2c_check_if_logged_in()
{
	// https://rudrastyh.com/woocommerce/get-page-urls.html
	//$custom_redirect_uri = $_SERVER['HTTP_REFERER'];
	if(!is_user_logged_in() && is_page(get_option( 'woocommerce_checkout_page_id' )))
	{
		aadb2c_login_custom('https://preorder.kekz.com/kasse/');
		exit();
	}

	if(!is_user_logged_in() && is_page(get_option( 'woocommerce_myaccount_page_id' )))
	{
		aadb2c_login_custom('https://preorder.kekz.com/mein-konto/');
		exit();
	}
}


// later add a toggle is settings to enable / disable this
if (AADB2C_Settings::$RequireLoginToAccess_WC_Cart) {
	add_action('template_redirect','aadb2c_check_if_logged_in');
}



if (AADB2C_Settings::$Replace_WpLogin) {
	/** 
	 * Hooks onto the WP login action, so when user logs in on WordPress, user is redirected
	 * to B2C's authorization endpoint. 
	 */
	add_action('wp_authenticate', 'aadb2c_login'); // disabled to allow WP Login

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

}


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



// Utility functions removed from external class containers to try and debug token request process
/**
 * Exchanges an Authorization Code and obtains an Access Token and an ID Token.
 *
 * @param string $code The authorization code.
 * @param \AADB2C_Settings $settings The settings to use.
 *
 * @return mixed The decoded authorization result.
 * https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-client-creds-grant-flow#first-case-access-token-request-with-a-shared-secret
 * https://github.com/MicrosoftDocs/azure-docs/blob/master/articles/active-directory/azuread-dev/v1-oauth2-client-creds-grant-flow.md#service-to-service-access-token-request
 */
function aadb2c_get_access_token() {

	// Construct the body for the access token request
	$authentication_request_body = http_build_query(
		array(
			'grant_type'    => 'client_credentials',
			'scope' => 'Directory.ReadWrite.All',
			'resource' => AADB2C_Settings::$graph_endpoint,
			'client_id'     => AADB2C_Settings::$clientID,
			'client_secret' => AADB2C_Settings::$clientSecret,
		)
	);

	$authentication_request_header = array(
		//'Authorization' => 'Basic ' . base64_encode( AADB2C_Settings::$clientID . ':' . AADB2C_Settings::$clientSecret ),
		'Content-Type' => 'application/json',
	);

	return aadb2c_get_and_process_access_token( $authentication_request_body, $authentication_request_header );
}

/**
 * Makes the request for the access token and some does some basic processing of the result.
 *
 * @param array $authentication_request_body The body to use in the Authentication Request.
 * @param \AADB2C_Settings $settings The settings to use.
 *
 * @return mixed The decoded authorization result.
 */
function aadb2c_get_and_process_access_token( $authentication_request_body, $authentication_request_header ) {
	// https://login.microsoftonline.com/1c21b550-383a-44c4-b15a-ae55c2bf9415/oauth2/token
	$AccessTokenUrl = 'https://login.microsoftonline.com/' . AADB2C_Settings::$tenant_id_parent_azad . '/oauth2/token';
	//$AccessTokenUrl = 'https://login.microsoftonline.com/1c21b550-383a-44c4-b15a-ae55c2bf9415/oauth2/token';

	// Post the authorization code to the STS and get back the access token
	//$response = wp_remote_post( $settings->token_endpoint, array(
	$response = wp_remote_post( $AccessTokenUrl, array(
		'body' => $authentication_request_body,
		'header' => $authentication_request_header,
	) );
	if( is_wp_error( $response ) ) {
		return new WP_Error( $response->get_error_code(), $response->get_error_message() );
	}
	$output = wp_remote_retrieve_body( $response );
	//error_log( 'In aadb2c_get_and_process_access_token response:' . print_r($response, 1 ) );
	//error_log( 'In aadb2c_get_and_process_access_token: output' . print_r($output, 1 ) );
	// Decode the JSON response from the STS. If all went well, this will contain the access
	// token and the id_token (a JWT token telling us about the current user)
	$result = json_decode( $output );
	//error_log( 'In aadb2c_get_and_process_access_token: result' . print_r($result, 1 ) );

	// token will be returned to main function and used in later calls to things.
	return $result;
}
