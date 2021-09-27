
<?php





defined( 'AADB2C_DEBUG' ) or define( 'AADB2C_DEBUG', FALSE );
defined( 'AADB2C_DEBUG_LEVEL' ) or define( 'AADB2C_DEBUG_LEVEL', 0 );

class AADB2C_DEBUG {

	/*** View ***/

	/**
	 * Renders some debugging data.
	 */
	function print_debug() {
		echo '<p>SESSION</p><pre>' . var_export( $_SESSION, TRUE ) . '</pre>';
		echo '<p>GET</pre><pre>' . var_export( $_GET, TRUE ) . '</pre>';
		echo '<p>Database settings</p><pre>' .var_export( get_option( 'aadb2c_settings' ), true ) . '</pre>';
		echo '<p>Plugin settings</p><pre>' . var_export( $this->settings, true ) . '</pre>';
	}

	/**
	 * Emits debug details to the logs. The higher the level, the more verbose.
	 *
	 * If there are multiple lines in the message, they will each be emitted as a log line.
	 */
	public static function debug_log( $message, $level = 0 ) {
		/**
		 * Fire an action when logging.
		 *
		 * This allows external services to tie into these logs. We're adding it here so this can be used in prod for services such as Stream
		 *
		 * @since 0.6.2
		 *
		 * @param string $message The message being logged.
		 */
		do_action( 'aadb2c_debug_log', $message );

		/**
		 * Allow other plugins or themes to set the debug status of this plugin.
		 *
		 * @since 0.6.3
		 * @param bool The current debug status.
		 */
		$debug_enabled = apply_filters( 'aadb2c_debug', AADB2C_DEBUG );


		/**
		 * Allow other plugins or themes to set the debug level
		 * @since 0.6.3
		 * @param int
		 */
		$debug_level = apply_filters( 'aadb2c_debug_level', AADB2C_DEBUG_LEVEL );


		if ( true === $debug_enabled && $debug_level >= $level ) {
			if ( false === strpos( $message, "\n" ) ) {
				error_log( 'AADB2C: ' . $message );
			} else {
				$lines = explode( "\n", str_replace( "\r\n", "\n", $message ) );
				foreach ( $lines as $line ) {
					AADB2C::debug_log( $line, $level );
				}
			}
		}
    }
}