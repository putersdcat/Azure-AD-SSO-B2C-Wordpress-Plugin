<?php

/** 
 * Parses the class name and requires the correct file.
 *
 *  @param $class
 */
function autoload($class) {
	// only try to autoload AD B2C classes or their vendor dependencies
	if ( 0 !== strpos( $class, 'B2C' )) {
		return;
	}
	
	$class_filename = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

	$plugin_directory = plugin_dir_path( __FILE__ );

	if ( file_exists( $plugin_directory.$class_filename ) ) {
		require_once $class_filename;
	}

	
	require_once 'b2c-user-settings.php';	
}

/**
 * Registers the autoloader.
 */
spl_autoload_register('autoload');
