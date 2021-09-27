<?php

/**
 * A helper class used to make calls to Microsoft Graph API.
 */
class AADB2C_Graph_Helper
{
	/**
	 * @var \AADB2C_Settings The instance of AADB2C_Settings to use.
	 */
	public static $settings;

	/**
	 * Gets the the Microsoft Graph API base URL to use.
	 *
	 * @return string The base URL to the Microsoft Graph API.
	 */
	public static function get_base_url() {
		return AADB2C_Settings::$graph_endpoint . '/' . AADB2C_Settings::$graph_version;
		//return self::$settings->graph_endpoint . '/' . self::$settings->graph_version;
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
	public static function get_user( $GraphToken = array(), $user_id ) {
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
	public static function set_user_attribute( $GraphToken = array(), $user_id, $attribute_name, $attribute_value ) {
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
	public static function set_user_custom_extension( $GraphToken = array(), $user_id, $extension_name, $extension_value ) {

		// flatten d1f9ccbb-2c4b-43a2-a5fd-9755f80e360f
		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';
		//$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', self::$settings->extensions_app_client_id)) . '_';
		

		$url = self::get_base_url() . '/users/' . $user_id;
		
		//$extension_prefix = $settings->graph_custom_extension_prefix;
		//$extension_full_name = "{$extension_prefix}{$extension_name}";
		//$patch = array(
		//	$extension_full_name => $extension_value
		//);
		//return self::patch_request( $url, array(), $patch ) );

		return self::patch_request( $GraphToken, $url, array(), array( "{$graph_custom_extension_prefix}{$extension_name}" => $extension_value ) );
	}

	/**
	 * Issues a GET request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function get_request( $GraphToken = array(), $url, $query_params = array() ) {

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
			'headers' => self::get_required_headers_and_settings($GraphToken),
		) );

		return self::parse_and_log_response( $response );
	}

	/**
	 * Issues a POST request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function post_request( $GraphToken = array(), $url, $query_params = array(), $data = array() ) {

		// Build the full query URL and encode the payload
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;
		$payload = json_encode( $data );

		AADB2C_DEBUG::debug_log( 'POST ' . $url, 50 );
		AADB2C_DEBUG::debug_log( $payload, 99 );

		// Make the POST request
		$response = wp_remote_post( $url, array(
			'body' => $payload,
			'headers' => self::get_required_headers_and_settings($GraphToken),
		) );

		return self::parse_and_log_response( $response );
	}


	/**
	 * Issues a PATCH request to the Microsoft Graph API.
	 *
	 * @return mixed The decoded response.
	 */
	public static function patch_request($GraphToken = array(), $url, $query_params = array(), $data = array() ) {

		// Fun for debug purposes
		/*
		if (is_array($GraphToken)) {
			if (isset($GraphToken['aadb2c_token_type'])) { // 1-dimensional, turn into 2-dimensional
				$GraphToken = array($GraphToken);
			}
		}
		*/

		// Build the full query URL and encode the payload
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;
		$payload = json_encode( $data );

		AADB2C_DEBUG::debug_log( 'PATCH ' . $url, 50 );
		AADB2C_DEBUG::debug_log( $payload, 99 );

		$auth_headers = array(
			'Authorization' => $GraphToken['aadb2c_token_type'] . ' ' . $GraphToken['aadb2c_access_token'],
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return-content',
		);

		// Make the PATCH request
		$response = wp_remote_request( $url, array(
			'method' => 'PATCH',
			'body' => $payload,
			//'headers' => self::get_required_headers_and_settings($GraphToken),
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
	private static function get_required_headers_and_settings($GraphToken = array())
	{
		// Generate the authentication header
		return array(
			'Authorization' => $GraphToken['aadb2c_token_type'] . ' ' . $GraphToken['aadb2c_access_token'],
			//'Authorization' => $graphSession->token_type . ' ' . $graphSession->access_token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return-content',
		);
	}

}
