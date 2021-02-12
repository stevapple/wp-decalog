<?php declare(strict_types=1);
/**
 * Raygun formatter for Monolog
 *
 * Handles all features of Raygun formatter for Monolog.
 *
 * @package Formatters
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.4.0
 */

namespace Decalog\Formatter;

use Decalog\Plugin\Feature\ClassTypes;
use Decalog\Plugin\Feature\EventTypes;
use Decalog\Plugin\Feature\ChannelTypes;
use Decalog\System\Environment;
use Decalog\System\Http;
use Decalog\System\UserAgent;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use PODeviceDetector\API\Device;

/**
 * Define the Monolog Raygun formatter.
 *
 * Handles all features of Raygun formatter for Monolog.
 *
 * @package Formatters
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.4.0
 */
class RaygunFormatter implements FormatterInterface {

	/**
	 * List of the available level classes.
	 *
	 * @var string[] $level_names Logging levels classes.
	 */
	public static $level_classes = [
		Logger::DEBUG     => 'Debug',
		Logger::INFO      => 'Event',
		Logger::NOTICE    => 'Event',
		Logger::WARNING   => 'Warning',
		Logger::ERROR     => 'Error',
		Logger::CRITICAL  => 'Error',
		Logger::ALERT     => 'Error',
		Logger::EMERGENCY => 'Error',
	];

	/**
	 * List of the available level severities.
	 *
	 * @var string[] $level_names Logging levels severities.
	 */
	public static $level_severities = [
		Logger::DEBUG     => 'info',
		Logger::INFO      => 'info',
		Logger::NOTICE    => 'info',
		Logger::WARNING   => 'warning',
		Logger::ERROR     => 'error',
		Logger::CRITICAL  => 'error',
		Logger::ALERT     => 'error',
		Logger::EMERGENCY => 'error',
	];

	/**
	 * Formats a log record.
	 *
	 * @param  array $record A record to format.
	 * @return string The formatted record.
	 * @since   2.4.0
	 */
	public function format( array $record ): string {
		$event      = [];
		$exception  = [];
		$stacktrace = [];
		$request    = [];
		$user       = [];
		$device     = [];
		$app        = [];
		if ( array_key_exists( 'channel', $record ) ) {
			$event['context'] = ChannelTypes::$channel_names_en[ strtoupper( $record['channel'] ) ];
		} else {
			$event['context'] = ChannelTypes::$channel_names_en['UNKNOWN'];
		}
		if ( array_key_exists( 'level', $record ) ) {
			if ( array_key_exists( $record['level'], self::$level_classes ) ) {
				$level_class = self::$level_classes[ $record['level'] ];
			} else {
				$level_class = 'Unknown';
			}
			if ( array_key_exists( $record['level'], self::$level_severities ) ) {
				$event['severity'] = self::$level_severities[ $record['level'] ];
			} else {
				$event['severity'] = 'error';
			}
		}
		else {
			$level_class = 'Unknown';
		}
		$exception['message'] = '';
		$app['releaseStage']  = Environment::stage();
		$app['id']            = str_replace( '/', '_', str_replace( [ 'https://', 'http://' ], '', get_site_url() ) );
		$app['version']       = Environment::wordpress_version_text( true );
		$device['hostname']   = gethostname();
		// Context formatting.
		if ( array_key_exists( 'context', $record ) ) {
			$context = $record['context'];
			if ( array_key_exists( 'class', $context ) ) {
				$event['unhandled']      = ( 'PHP' === strtoupper( $context['class'] ) );
				$app['type']             = strtolower( $context['class'] );
				$exception['type']       = 'php';
				$exception['errorClass'] = ucfirst( strtolower( $context['class'] ) );
			}
			if ( array_key_exists( 'code', $context ) ) {
				$exception['message'] .= '[' . $context['code'] . '] ';
			}
			if ( array_key_exists( 'component', $context ) ) {
				$exception['errorClass'] = str_replace( ' ', '', $context['component'] ) . $level_class;
			}
		}
		if ( array_key_exists( 'message', $record ) ) {
			$exception['message'] .= substr( $record['message'], 0, 1000 );
		}

		// Extra formatting.
		if ( array_key_exists( 'extra', $record ) ) {
			$extra = $record['extra'];
			if ( array_key_exists( 'file', $extra ) && $extra['file'] && is_string( $extra['file'] ) ) {
				$stacktrace['file'] = $extra['file'];
			} else {
				$stacktrace['file'] = 'unknown';
			}
			if ( array_key_exists( 'line', $extra ) && $extra['line'] ) {
				$stacktrace['lineNumber'] = (int) $extra['line'];
			} else {
				$stacktrace['lineNumber'] = 0;
			}
			$method = '';
			if ( array_key_exists( 'class', $extra ) && $extra['class'] && is_string( $extra['class'] ) ) {
				$method = $extra['class'] . '::';
			}
			if ( array_key_exists( 'function', $extra ) && $extra['function'] && is_string( $extra['function'] ) ) {
				if ( '' === $method ) {
					$method = '<' . $extra['function'] . '>';
				} else {
					$method .= $extra['function'];
				}
			}
			if ( '' === $method ) {
				$stacktrace['method'] = 'unknown';
			} else {
				$stacktrace['method'] = $method;
			}
			if ( array_key_exists( 'ip', $extra ) && is_string( $extra['ip'] ) ) {
				$request['remote_ip'] = substr( $extra['ip'], 0, 66 );
			}
			if ( array_key_exists( 'http_method', $extra ) && is_string( $extra['http_method'] ) ) {
				if ( in_array( strtolower( $extra['http_method'] ), Http::$verbs, true ) ) {
					$request['httpMethod'] = strtoupper( $extra['http_method'] );
				}
			}
			if ( array_key_exists( 'url', $extra ) && is_string( $extra['url'] ) ) {
				$request['url'] = substr( $extra['url'], 0, 2083 );
			}
			if ( array_key_exists( 'referrer', $extra ) && $extra['referrer'] && is_string( $extra['referrer'] ) ) {
				$request['referrer'] = substr( $extra['referrer'], 0, 250 );
			}
			if ( array_key_exists( 'userid', $extra ) && is_numeric( $extra['userid'] ) ) {
				$user['id'] = substr( (string) $extra['userid'], 0, 66 );
			}
			if ( array_key_exists( 'username', $extra ) && is_string( $extra['username'] ) ) {
				$user['name'] = substr( $extra['username'], 0, 250 );
			}
			if ( array_key_exists( 'server', $extra ) && is_string( $extra['server'] ) ) {
				$values['server'] = substr( $extra['server'], 0, 250 );
			}

			if ( class_exists( 'PODeviceDetector\API\Device' ) && array_key_exists( 'ua', $extra ) && $extra['ua'] && is_string( $extra['ua'] ) ) {
				$ua = UserAgent::get( $extra['ua'] );
				if ( ! $ua->class_is_bot ) {
					$device['manufacturer'] = $ua->brand_name;








				}
			}
		}
		$exception['stacktrace'] = [ (object) $stacktrace ];
		$event['exceptions']     = [ (object) $exception ];
		if ( 0 < count( $request ) ) {
			$event['request'] = (object) $request;
		}
		if ( 0 < count( $user ) ) {
			$event['user'] = (object) $user;
		}
		if ( 0 < count( $device ) ) {
			$event['device'] = (object) $device;
		}
		if ( 0 < count( $app ) ) {
			$event['app'] = (object) $app;
		}
		// phpcs:ignore
		return serialize( (object) $event );
	}
	/**
	 * Formats a set of log records.
	 *
	 * @param  array $records A set of records to format.
	 * @return string The formatted set of records.
	 * @since   2.4.0
	 */
	public function formatBatch( array $records ): string {
		$messages = [];
		foreach ( $records as $record ) {
			$messages[] = maybe_unserialize( $this->format( $record ) );
		}
		// phpcs:ignore
		return serialize( $messages );
	}
}