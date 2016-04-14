<?php
/**
 * Abstract Helper Plugin Class.
 * Consistent plugin structure for Thunderhead Plugins.
 * Also provides some helper functions.
 */

namespace HM\Limit_Login_Attempts;

abstract class Plugin {

	protected static $instances;

	protected function __construct() {}

	public static function get_instance() {
		$class = get_called_class();
		if ( ! isset( static::$instances[ $class ] ) ) {
			self::$instances[$class] = $instance = new $class;
			$instance->load();
		}
		return self::$instances[ $class ];
	}

	abstract function load();

	/**
	 * Get a given view (if it exists)
	 *
	 * @param string     $view      The slug of the view
	 * @return string
	 */
	public function get_view( $view, $data = array() ) {

		$namespace = 'HM\Limit_Login_Attempts';
		$class     = ltrim( get_called_class(), '\\' );

		if ( 0 !== stripos( $class, $namespace ) ) {
			return '';
		}

		$class = substr( $class, strlen( $namespace ) + 1 );
		$parts = explode( '\\', $class );
		array_pop( $parts );

		$parts[] = 'templates';
		$parts[] = $view  . '.tpl.php';

		$file = __DIR__ . '/' . str_replace( '_', '-', strtolower( implode( $parts, '/' ) ) );

		if ( ! file_exists( $file ) )
			return '';

		ob_start();
		include $file;

		return ob_get_clean();

	}

}
