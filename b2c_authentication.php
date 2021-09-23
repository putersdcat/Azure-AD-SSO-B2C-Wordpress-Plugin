<?php

/**
 * Plugin Name: Microsoft Azure Active Directory B2C Authentication
 * Plugin URI: https://github.com/AzureAD/active-directory-b2c-wordpress-plugin-openidconnect
 * Description: A plugin that allows users to log in using B2C policies
 * Version: 1.1
 * Author: Microsoft
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
define('B2C_RESPONSE_MODE', 'id_token');

// Adds the B2C Options page to the Admin dashboard, under 'Settings'.
if (is_admin()) $b2c_settings_page = new B2C_Settings_Page();
$b2c_settings = new B2C_Settings();


//*****************************************************************************************


/**
 * Redirects to B2C on a user login request.
 */
function b2c_login()
{
	try {
		$b2c_endpoint_handler = new B2C_Endpoint_Handler(B2C_Settings::$generic_policy);
		$authorization_endpoint = $b2c_endpoint_handler->get_authorization_endpoint() . "&state=generic";
		wp_redirect($authorization_endpoint);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	exit;
}

/** 
 * Redirects to B2C on user logout.
 */
function b2c_logout()
{
	try {
		$signout_endpoint_handler = new B2C_Endpoint_Handler(B2C_Settings::$generic_policy);
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
function b2c_verify_token()
{
	try {
		if (isset($_POST['error'])) {
			// If user requests the Password Reset flow from a Sign-in/Sign-up flow, the following is returned:
			//   Error: access_denied
			//   Description: AADB2C90118: The user has forgotten their password.
			if (preg_match('/.*AADB2C90118.*/i', $_POST['error_description'])) {
				// user forgot password so redirect to the password reset flow
				b2c_password_reset();
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

		if (isset($_POST[B2C_RESPONSE_MODE])) {
			// Check which authorization policy was used
			switch ($_POST['state']) {
				case 'generic':
					$policy = B2C_Settings::$generic_policy;
					break;
				case 'admin':
					$policy = B2C_Settings::$admin_policy;
					break;
				case 'edit_profile':
					$policy = B2C_Settings::$edit_profile_policy;
					break;
				default:
					// Not a B2C request, ignore.
					return;
			}

			// Verifies token only if the checkbox "Verify tokens" is checked on the settings page
			$token_checker = new B2C_Token_Checker($_POST[B2C_RESPONSE_MODE], B2C_Settings::$clientID, $policy);
			if (B2C_Settings::$verify_tokens) {
				$verified = $token_checker->authenticate();
				if ($verified == false) wp_die('Token validation error');
			}

			// First find the user by the B2C object ID
			$object_id = $token_checker->get_claim('sub');
			$users = get_users(array('meta_key' => 'b2c_object_id', 'meta_value' => $object_id));
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
				error_log('Duplicate users found for b2c_object_id ' . $object_id);
				exit;
			}

			// Get the userID for the user
			if ($user == false) { // User doesn't exist yet, create new userID

				$first_name = $token_checker->get_claim('given_name');
				$last_name = $token_checker->get_claim('family_name');
				$display_name = $token_checker->get_claim('displayName'); // <-- Need to debug this one, not sure, just guessed...
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
					//'display_name' => $first_name . ' ' . $last_name,
					'display_name' => $display_name,
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
					'shipping_phone' => $shipping_phone
					//'locale' => $locale
				);

				// EWA: Dev Notes
				/*

				woocommerce_checkout_after_customer_details <-- Edit profile in B2C

woocommerce_checkout_before_customer_details
woocommerce_checkout_process
woocommerce_checkout_update_order_meta
woocommerce_checkout_order_processed

woocommerce_registration_redirect <-- SingIn/SignUp profile in B2C
woocommerce_login_redirect <-- SingIn/SignUp profile in B2C
woocommerce_after_customer_login_form


woocommerce-edit_address
woocommerce_after_edit_address_form_{$load_address}
woocommerce_after_edit_account_address_form


				See wc-user-functions.php in DevRef
				https://github.com/luizbills/woo-force-authentification-before-checkout

				*/

				$userID = wp_insert_user($our_userdata);
				update_user_meta($userID, 'b2c_object_id', sanitize_text_field($object_id));
				do_action('b2c_new_userdata', $userID, $token_checker->get_payload());
			} else if ($policy == B2C_Settings::$edit_profile_policy) { // Update the existing user w/ new attritubtes

				// $name = $token_checker->get_claim('name'); // Is this a mistake, not referenced in the code anywhere?
				$first_name = $token_checker->get_claim('given_name');
				$last_name = $token_checker->get_claim('family_name');
				$display_name = $token_checker->get_claim('displayName'); // <-- Need to debug this one, not sure, just guessed...
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
					'display_name' => $first_name . ' ' . $last_name,
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
					'shipping_phone' => $shipping_phone
					//'locale' => $locale
				);

				$userID = wp_update_user($our_userdata);
				update_user_meta($userID, 'b2c_object_id', sanitize_text_field($object_id));
				do_action('b2c_update_userdata', $userID, $token_checker->get_payload());
			} else {
				$userID = $user->ID;
			}

			// Check if the user is an admin and needs MFA
			$wp_user = new WP_User($userID);
			if (in_array('administrator', $wp_user->roles)) {

				// If user did not authenticate with admin_policy, redirect to admin policy
				if (mb_strtolower($token_checker->get_claim('tfp')) != mb_strtolower(B2C_Settings::$admin_policy)) {
					$b2c_endpoint_handler = new B2C_Endpoint_Handler(B2C_Settings::$admin_policy);
					$authorization_endpoint = $b2c_endpoint_handler->get_authorization_endpoint() . '&state=admin';
					wp_redirect($authorization_endpoint);
					exit;
				}
			}

			// Set cookies to authenticate on WP side
			wp_set_auth_cookie($userID);

			// Add a hook for redirect after login
			do_action('b2c_post_login');

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
function b2c_edit_profile()
{

	// Check to see if user was requesting the edit_profile page, if so redirect to B2C
	$pagename = $_SERVER['REQUEST_URI'];
	$parts = explode('/', $pagename);
	$len = count($parts);
	if ($len > 1 && $parts[$len - 2] == "wp-admin" && $parts[$len - 1] == "profile.php") {

		// Return URL for edit_profile endpoint
		try {
			$b2c_endpoint_handler = new B2C_Endpoint_Handler(B2C_Settings::$edit_profile_policy);
			$authorization_endpoint = $b2c_endpoint_handler->get_authorization_endpoint() . '&state=edit_profile';
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
function b2c_password_reset()
{
	try {
		$b2c_endpoint_handler = new B2C_Endpoint_Handler(B2C_Settings::$password_reset_policy);
		$authorization_endpoint = $b2c_endpoint_handler->get_authorization_endpoint() . '&state=password_reset';
		wp_redirect($authorization_endpoint);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
	exit;
}

/** 
 * Hooks onto the WP login action, so when user logs in on WordPress, user is redirected
 * to B2C's authorization endpoint. 
 */
add_action('wp_authenticate', 'b2c_login');

/** 
 * Hooks onto the WP lost password action, so user is redirected
 * to B2C's password reset endpoint. 
 * 
 * example.com/wp-login.php?action=lostpassword
 */
add_action('login_form_lostpassword', 'b2c_password_reset');

/**
 * Hooks onto the WP page load action, so when user request to edit their profile, 
 * they are redirected to B2C's edit profile endpoint.
 */
add_action('wp_loaded', 'b2c_edit_profile');

/** 
 * Hooks onto the WP page load action. When B2C redirects back to WordPress site,
 * if an ID token is POSTed to a special path, b2c-token-verification, this verifies 
 * the ID token and authenticates the user.
 */
add_action('wp_loaded', 'b2c_verify_token');

/**
 * Hooks onto the WP logout action, so when a user logs out of WordPress, 
 * they are redirected to B2C's logout endpoint.
 */
add_action('wp_logout', 'b2c_logout');

//
// Start of new code hooks for WooCommerce integration 
//

/** 
 * Hooks onto the WC login action, so when user logs in on WordPress, user is redirected
 * to B2C's authorization endpoint. 
 */
add_action('woocommerce_login_redirect', 'b2c_login');

/** 
 * Hooks onto the WC password reset action, so user is redirected
 * to B2C's password reset endpoint. 
 * 
 * example.com/wp-login.php?action=lostpassword
 */
add_action('woocommerce_customer_reset_password', 'b2c_password_reset');

/**
 * Hooks onto the WC page load action, so when user edits at,
 * WooCommerce My Account Page – User Logged In – Edit Address
 * they are redirected to B2C's edit profile endpoint.
 */
add_action('woocommerce_after_edit_account_address_form', 'b2c_edit_profile');