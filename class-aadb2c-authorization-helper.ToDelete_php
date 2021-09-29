<?php

/**
 * A helper class used to request authorization and access tokens Azure Active Directory.
 */
class AADB2C_Authorization_Helper
{
	/**
	 * @var string List of allowed algorithms. Currently, only RS256 is allowed and expected from AAD.
	 */
	private static $allowed_algorithms = array( 'RS256' );

	/**
	 * Exchanges an Authorization Code and obtains an Access Token and an ID Token.
	 *
	 * @param string $code The authorization code.
	 * @param \AADB2C_Settings $settings The settings to use.
	 *
	 * @return mixed The decoded authorization result.
	 */
	public static function get_access_token( $code, $settings ) {

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

		//if ( isset( $result->access_token ) ) {

			// Add the token information to the session so that we can use it later
			// TODO: these probably shouldn't be in SESSION...
			// EWA: Session will not be avalible, because this is in a class statement that gets included once at runtime.
			//$_SESSION['aadb2c_token_type'] = $result->token_type;
			//$_SESSION['aadb2c_access_token'] = $result->access_token;
		//}
		// token will be returned to main function and used in later calls to things.
		return $result;
	}

}
