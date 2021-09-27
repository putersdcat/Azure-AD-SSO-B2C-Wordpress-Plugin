<?php

/**
 * A helper class used to make calls to Microsoft Graph API.
 */
class AADB2C_GraphHelper
{

	private $graphSession = array();

    function __construct() {
        $this->graphSession;
    }

	//public static $graphSession = array();
	//private $settings;
	//private $code;
	
	// From outside, call this first to get access tokens for later function calls
	//$graph_helper = new AADB2C_GraphHelper($code, $settings);
	//function __construct($code, $settings)
	//{
	//	$this->$graphSession = $this->get_access_token( $this->code, $this->settings );
		// Looks like we got a valid authorization code, let's try to get an access token with it
		//$token = AADB2C_AuthorizationHelper::get_access_token( $_GET['code'], $this->settings );
		//$_SESSION['aadb2c_token_type'] = $result->token_type;
		//$_SESSION['aadb2c_access_token'] = $result->access_token;
	//}
	//public static $settings;
	//public static function get_settings() {
	//	return $settings = new AADB2C_Settings();
	//}

	/**
	 * Gets the the Microsoft Graph API base URL to use.
	 *
	 * @return string The base URL to the Microsoft Graph API.
	 */
	public static function get_base_url() {
		//$settings = new AADB2C_Settings();
		return AADB2C_Settings::$graph_endpoint . '/' . AADB2C_Settings::$graph_version;
	}

	/**
	 * Checks which of the given groups the given user is a member of.
	 *
	 * @return mixed The response to the checkMemberGroups request.
	 */
	public static function user_check_member_groups( $user_id, $group_ids ) {
		$url = self::get_base_url() . '/users/' . $user_id . '/checkMemberGroups';
		return self::post_request( $url, array(), array( 'groupIds' => $group_ids ) );
	}

	/**
	 * Gets the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function get_user( $user_id ) {
		$url = self::get_base_url() . '/users/' . $user_id;
		return self::get_request( $url );
	}

	/**
	 * Sets the specified attribute value on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	//CURLOPT_CUSTOMREQUEST => "PATCH",
	//CURLOPT_POSTFIELDS => "{\n    \"extension_d1f9ccbb2c4b43a2a5fd9755f80e360f_wc_shipping_phone\": \"555-555-5555\"\n}",
	public static function set_user_attribute( $user_id, $attribute_name, $attribute_value ) {
		$url = self::get_base_url() . '/users/' . $user_id;
		return self::patch_request( $url, array(), array( $attribute_name => $attribute_value ) );
	}

	/**
	 * Sets the specified custom extension value on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	//CURLOPT_CUSTOMREQUEST => "PATCH",
	//CURLOPT_POSTFIELDS => "{\n    \"extension_d1f9ccbb2c4b43a2a5fd9755f80e360f_wc_shipping_phone\": \"555-555-5555\"\n}",
	public static function set_user_custom_extension( $user_id, $extension_name, $extension_value ) {

		// flatten d1f9ccbb-2c4b-43a2-a5fd-9755f80e360f
		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';

		$url = self::get_base_url() . '/users/' . $user_id;
		
		//$extension_prefix = $settings->graph_custom_extension_prefix;
		//$extension_full_name = "{$extension_prefix}{$extension_name}";
		//$patch = array(
		//	$extension_full_name => $extension_value
		//);
		//return self::patch_request( $url, array(), $patch ) );

		return self::patch_request( $url, array(), array( "{$graph_custom_extension_prefix}{$extension_name}" => $extension_value ) );
	}

	/**
	 * Issues a GET request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function get_request( $url, $query_params = array() ) {

		// Build the full query URL, adding api-version if necessary
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;

		$graphSession['aadb2c_last_request'] = array(
			'method' => 'GET',
			'url' => $url,
		);

		AADB2C_DEBUG::debug_log( 'GET ' . $url, 50 );

		// Make the GET request
		$response = wp_remote_get( $url, array(
			'headers' => self::get_required_headers_and_settings(),
		) );

		return self::parse_and_log_response( $response );
	}

	/**
	 * Issues a POST request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function post_request( $url, $query_params = array(), $data = array() ) {

		// Build the full query URL and encode the payload
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;
		$payload = json_encode( $data );

		AADB2C_DEBUG::debug_log( 'POST ' . $url, 50 );
		AADB2C_DEBUG::debug_log( $payload, 99 );

		// Make the POST request
		$response = wp_remote_post( $url, array(
			'body' => $payload,
			'headers' => self::get_required_headers_and_settings(),
		) );

		return self::parse_and_log_response( $response );
	}


	/**
	 * Issues a PATCH request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function patch_request( $url, $query_params = array(), $data = array() ) {

		// Build the full query URL and encode the payload
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;
		$payload = json_encode( $data );

		AADB2C_DEBUG::debug_log( 'PATCH ' . $url, 50 );
		AADB2C_DEBUG::debug_log( $payload, 99 );

		$auth_headers = array(
			'Authorization' => $graphSession['aadb2c_token_type'] . ' ' . $graphSession['aadb2c_access_token'],
			//'Authorization' => $graphSession->token_type . ' ' . $graphSession->access_token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return-content',
		);

		// Make the PATCH request
		$response = wp_remote_request( $url, array(
			'method' => 'PATCH',
			'body' => $payload,
			//'headers' => self::get_required_headers_and_settings(),
			'headers' => $auth_headers,
		) );

		return self::parse_and_log_response( $response );
	}

	/**
	 * Logs the HTTP response headers and body and returns the JSON-decoded body.
	 *
	 * @return mixed The decoded response.
	 */
	private static function parse_and_log_response( $response ) {

		$response_headers = wp_remote_retrieve_headers( $response );
		$response_body = wp_remote_retrieve_body( $response );

		AADB2C_DEBUG::debug_log( 'Response headers: ' . json_encode( $response_headers ), 99 );
		AADB2C_DEBUG::debug_log( 'Response body: ' . json_encode( $response_body ), 50 );

		return json_decode( $response_body );
	}

	/**
	  * Returns an array with the required headers like authorization header, service version etc.
	  *
	  * @return array An associative array with the HTTP headers for Microsoft Graph API calls.
	  */
	private static function get_required_headers_and_settings($graphSession)
	{
		// Generate the authentication header
		return array(
			'Authorization' => $graphSession['aadb2c_token_type'] . ' ' . $graphSession['aadb2c_access_token'],
			//'Authorization' => $graphSession->token_type . ' ' . $graphSession->access_token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return-content',
		);
	}

	/**
	 * Exchanges an Authorization Code and obtains an Access Token and an ID Token.
	 *
	 * @param string $code The authorization code.
	 * @param \AADB2C_Settings $settings The settings to use.
	 *
	 * @return mixed The decoded authorization result.
	 */
	public static function get_access_token( $code, $settings ) {
		// In this case settings is passed as a parameter from outside, so i dont change

		// Construct the body for the access token request
		$authentication_request_body = http_build_query(
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $settings->redirect_uri,
				'resource'      => $settings->graph_endpoint,
				'client_id'     => $settings->clientID,
				'client_secret' => $settings->clientSecret
			)
		);

		return self::get_and_process_access_token( $authentication_request_body, $settings );
	}

	/**
	 * Makes the request for the access token and some does some basic processing of the result.
	 *
	 * @param array $authentication_request_body The body to use in the Authentication Request.
	 * @param \AADB2C_Settings $settings The settings to use.
	 *
	 * @return mixed The decoded authorization result.
	 */
	public static function get_and_process_access_token( $authentication_request_body, $settings ) {
		// In this case settings is passed as a parameter from outside, so i dont change

		// Post the authorization code to the STS and get back the access token
		$response = wp_remote_post( $settings->token_endpoint, array(
			'body' => $authentication_request_body
		) );
		if( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message() );
		}
		$output = wp_remote_retrieve_body( $response );

		// Decode the JSON response from the STS. If all went well, this will contain the access
		// token and the id_token (a JWT token telling us about the current user)
		$result = json_decode( $output );

		if ( isset( $result->access_token ) ) {
			// Add the token information to the session so that we can use it later
			// TODO: these probably shouldn't be in SESSION...
			$graphSession['aadb2c_token_type'] = $result->token_type;
			$graphSession['aadb2c_access_token'] = $result->access_token;
		}

		return $result;
	}


}
