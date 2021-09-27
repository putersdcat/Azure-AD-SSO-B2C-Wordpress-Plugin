## Azure-AD-SSO-B2C-Wordpress-Plugin
This Fork is a dissaster, run away now!

Attempt at smashing https://github.com/psignoret/aad-sso-wordpress into https://github.com/w00dbury/Azure-B2C-Wordpress-Plugin

for the purpose of using ClientID and ClientSecret against b2c_extension_app to make patch calls and set user accont properties.


This repo contains the code for a WordPress plugin that allows users to authenticate with Azure AD B2C using OpenID Connect. Admins have the ability to configure several B2C policies: general sign-in/sign-up without multifactor authetication, admin sign-in/sign-up with multifactor authentication (optional), and profile editing.

This is based on the [plugin](https://github.com/AzureAD/active-directory-b2c-wordpress-plugin-openidconnect) from Microsoft. That project seems to have gone stale so several improvements have been merged into this repository.

## Pre-requisites
+ Install WordPress ([download link](https://codex.wordpress.org/Installing_WordPress))
+ Optional: Deploy your WordPress site to Azure ([instructions](https://azure.microsoft.com/en-us/documentation/articles/app-service-web-create-web-app-from-marketplace/))

## Use the Azure Portal to Create B2C Policies
+ Create a sign-in/sign-up policy and an edit profile policy.
+ Optional: Create a different sign-in policy for admins.
+ For detailed instructions, see [here](https://azure.microsoft.com/en-us/documentation/articles/active-directory-b2c-reference-policies/).

## Downloading and Installing this Plugin
+ Download this source code from github as a zip file.
+ Login to your WordPress site as an admin.
+ Navigate to your Dashboard > Plugins > Add New > Upload Plugin.
+ Upload the zip file, then activate the plugin.
+ On your Admin dashboard, a new options page called "B2C Authentication Settings" should appear under the Settings button. 
+ Click on that page and fill in the prompts for tenant, clientID, etc.

## Custom Actions and Filters

### Updating Custom Fields
As suggested by [@peterspliid](https://github.com/peterspliid) in the Microsoft [repo](https://github.com/AzureAD/active-directory-b2c-wordpress-plugin-openidconnect/pull/20), administraters can now update custom fields upon user creation or profile editing. 
```
function custom_ms_fields($userID, $payload) {
    if (isset($payload['jobTitle']))
        update_user_meta($userID, 'job_title', $payload['jobTitle']);
}
add_action('b2c_new_userdata', 'custom_ms_fields', 10, 2);
add_action('b2c_update_userdata', 'custom_ms_fields', 10, 2);
```
### Displaying Custom Fields
You may want to display B2C specific fields or custom fields on the user profile. By default the B2C Object ID for the user will be displayed but this can be controlled through the filter.
```
function b2c_custom_user_fields( $fields ) {
	$fields["job_title"] = array(
    				"label" => "Job Title",
					"meta" 	=> "job_title",
				);
    return $fields;
}
add_filter( 'b2c_update_user_fields_filter', 'b2c_custom_user_fields', 10, 1 );
add_filter( 'b2c_user_fields_filter', 'b2c_custom_user_fields', 10, 1 );
```
### Changing the Post Login Redirect Behavior
By default this plugin will redirect to the homepage after successful login. You may want to change this behavior. Below is an example of redirecting to the WooCommerce shopping cart if it has items in it or to the /my-account page if the cart is empty.
```
function custom_login_redirect() {
    // Redirect to checkout if woocommerce is activated and items in cart
	if (class_exists('woocommerce') && WC()->cart->get_cart_contents_count() > 0) {
		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}
	
	// Redirect to My Account
	wp_safe_redirect(site_url() . '/my-account');
}
add_action('b2c_post_login', 'custom_login_redirect', 10, 2);
```
## More information
B2C is an identity management service for both web applications and mobile applications. Developers can rely on B2C for consumer sign up and sign in, instead of relying on their own code. Consumers can sign in using brand new credentials or existing accounts on various social platforms (Facebook, for example). 

Learn more about B2C here: https://azure.microsoft.com/en-us/services/active-directory-b2c/
