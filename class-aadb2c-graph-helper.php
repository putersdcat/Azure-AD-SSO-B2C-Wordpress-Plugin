<?php

/**
 * A helper class used to make calls to Microsoft Graph API.
 */
class AADB2C_Graph_Helper
{

	/**
	 * Gets the the Microsoft Graph API base URL to use.
	 *
	 * @return string The base URL to the Microsoft Graph API.
	 */
	public static function get_base_url() {
		// https://graph.windows.net/kekzclub.onmicrosoft.com/users/5a539747-b8ba-40e5-b06b-fcb208bec88e?api-version=1.6
		// https://graph.microsoft.com/kekzclub.onmicrosoft.com/users/5a539747-b8ba-40e5-b06b-fcb208bec88e?api-version=1.6
		return AADB2C_Settings::$graph_endpoint . '/' . AADB2C_Settings::$tenant_domain;
		//return self::$settings->graph_endpoint . '/' . self::$settings->graph_version;
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
	 * Takes the long custom extension attribut from parent level graph call and outputs something closer to the claim level string.
	 *
	 * @return string The value sting celaned up.
	 */
	public static function cleanup_custom_extension_prefix( $Value ) {
		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';
		return str_replace($graph_custom_extension_prefix, '', $Value);
	}
	
	/**
	 * takes an array and does a search and replace on the start of the key string
	 * 
	 * @return array the input array re-keyed.
	 */
	private static function array_multi_search_and_replace_keys($searchKey, $replaceKey, array $input){
		$updatedArray = array(); 
		foreach ($input as $key => $value) {
			
			if (substr( $key, 0, strlen($searchKey) ) === $searchKey )
				$key = str_replace($searchKey, $replaceKey, $key);
	
			if (is_array($value))
				$value = self::array_multi_search_and_replace_keys( $searchKey, $replaceKey, $value);
	
			$updatedArray[$key] = $value;
		}
		
		return $updatedArray; 
	}

	/**
	 * Gets all attribute values on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function get_clean_user_attributes( $GraphToken = array(), $user_id ) {
		$url = self::get_base_url() . '/users/' . $user_id;
		$query_params = array(
			'api-version' => AADB2C_Settings::$graph_version,
		);

		$response_obj = self::get_request( $GraphToken, $url, $query_params );

		// convert object to array to fit with existing code
		if (!is_array($response_obj)) 
			$response_ary = json_decode(json_encode($response_obj), true);

		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';

		//return self::array_multi_search_and_replace_keys( $graph_custom_extension_prefix, '', $response_ary );

		// convert array back to object to fit with existing code
		$cleanResponse_ary = self::array_multi_search_and_replace_keys( $graph_custom_extension_prefix, '', $response_ary );

		if (is_array($cleanResponse_ary)) 
			return json_decode(json_encode($cleanResponse_ary));

	}

	/**
	 * Gets all attribute values on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function get_user_attributes( $GraphToken = array(), $user_id ) {
		$url = self::get_base_url() . '/users/' . $user_id;

		$query_params = array(
			//'api-version' => '1.6',
			'api-version' => AADB2C_Settings::$graph_version,
		);

		return self::get_request( $GraphToken, $url, $query_params );
	}

	/**
	 * Sets the specified attribute value on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function set_user_attribute( $GraphToken = array(), $user_id, $attribute_name, $attribute_value ) {
		$url = self::get_base_url() . '/users/' . $user_id;

		$query_params = array(
			//'api-version' => '1.6',
			'api-version' => AADB2C_Settings::$graph_version,
		);

		return self::patch_request( $GraphToken, $url, $query_params, array( $attribute_name => $attribute_value ) );
	}


	/**
	 * Sets the specified attributes values on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function set_user_attributes( $GraphToken = array(), $user_id, $attribute_ary = array() ) {
		$url = self::get_base_url() . '/users/' . $user_id;

		$query_params = array(
			//'api-version' => '1.6',
			'api-version' => AADB2C_Settings::$graph_version,
		);

		return self::patch_request( $GraphToken, $url, $query_params, $attribute_ary );
	}


	/**
	 * Sets the specified custom extension value on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function set_user_custom_extension( $GraphToken = array(), $user_id, $extension_name, $extension_value ) {

		// flatten d1f9ccbb-2c4b-43a2-a5fd-9755f80e360f
		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';
		
		$url = self::get_base_url() . '/users/' . $user_id;

		$query_params = array(
			//'api-version' => '1.6',
			'api-version' => AADB2C_Settings::$graph_version,
		);
		
		return self::patch_request( $GraphToken, $url, $query_params, array( "{$graph_custom_extension_prefix}{$extension_name}" => $extension_value ) );
	}


		/**
	 * Sets the specified custom extension value on the requested user.
	 *
	 * @return mixed The response to the user request.
	 */
	public static function set_user_custom_extensions( $GraphToken = array(), $user_id, $extensions_ary = array() ) {

		// flatten d1f9ccbb-2c4b-43a2-a5fd-9755f80e360f
		$graph_custom_extension_prefix = 'extension_' . strtolower(str_replace('-', '', AADB2C_Settings::$extensions_app_client_id)) . '_';
		
		// Re-Add the graph_custom_extension_prefix to array values, a lot o in and out on this page i know :-)
		$updatedArray = array(); 
		foreach ($extensions_ary as $key => $value) {
			$nuKey = "{$graph_custom_extension_prefix}{$key}";
			$updatedArray[$nuKey] = $value;
		}
		
		$url = self::get_base_url() . '/users/' . $user_id;

		$query_params = array(
			//'api-version' => '1.6',
			'api-version' => AADB2C_Settings::$graph_version,
		);
		
		return self::patch_request( $GraphToken, $url, $query_params, $updatedArray );
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

		//error_log( 'In AADB2C_Graph_Helper - get_request: response' . print_r($response, 1 ) );		
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

		//error_log( 'patch_request' . print_r($data, 1 ) );
		// Build the full query URL and encode the payload
		$query_params = http_build_query( $query_params );
		$url = $url . '?' . $query_params;
		$payload = json_encode( $data );

		AADB2C_DEBUG::debug_log( 'PATCH ' . $url, 50 );
		AADB2C_DEBUG::debug_log( $payload, 99 );

		$auth_headers = array(
			'Authorization' => $GraphToken['aadb2c_token_type'] . ' ' . $GraphToken['aadb2c_access_token'],
			//'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			//'Prefer'        => 'return-content',
		);

		// Make the PATCH request
		$response = wp_remote_request( $url, array(
			'method' => 'PATCH',
			'body' => $payload,
			//'headers' => self::get_required_headers_and_settings($GraphToken),
			'headers' => $auth_headers,
		) );
		
		//error_log( 'In AADB2C_Graph_Helper - patch_request: response' . print_r($response, 1 ) );
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
			//'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			//'Prefer'        => 'return-content',
		);
	}


}
