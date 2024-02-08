<?php

if ( ! is_callable( 'zxcvbnPHPAutoloader' ) ) {

	/**
	 * Zxcvbn-PHP autoloader.
	 *
	 * @since 1.6.7
	 *
	 * @param string $class Class name to be autoloaded.
	 *
	 * @return bool
	 */
	function zxcvbnPHPAutoloader( $class ) {

		$namespace = 'ZxcvbnPhp';

		// Does the class use the namespace prefix?
		$len = strlen( $namespace );

		if ( strncmp( $namespace, $class, $len ) !== 0 ) {
			// no, move to the next registered autoloader
			return false;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Replace the namespace prefix with the base directory, replace namespace
		// separators with directory separators in the relative class name, append
		// with .php
		$file = __DIR__ . '/src/' . str_replace( [ '_', '\\' ], '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;

			return true;
		}

		return false;
	}

	spl_autoload_register( 'zxcvbnPHPAutoloader' );
}
