<?php

namespace li3_metrics\core;

use lithium\net\http\Service;
use lithium\core\Environment;

/**
 * Client for sending and recieving data from librato Metrics.
 *
 * This Library does the heavy lifting of trackings Metrics to the
 * awesome Metrics service, provided by librato. You can signup for
 * a free trial account here: `http://metrics.librato.com`
 *
 * Current API version implemented is `v1` - documentation on webservice
 * can be found here: `http://dev.librato.com/v1/metrics`
 *
 * You need to authenticate before data-submission can start. In order
 * to do that, you need your email-address and a password/token which you
 * can obtain via the `Account` tab within Metrics.
 *
 * You then track gauges and counters like that:
 *
 * {{{
 * Metrics::$username = 'foo@domain.org';
 * Metrics::$password = 'bar';
 * Metrics::post($data);
 * }}}
 *
 */
class Metrics {

	/**
	 * Hostname of remote endpoint
	 *
	 * @var string
	 */
	public static $host = 'metrics-api.librato.com';

	/**
	 * Timeout in seconds before connection attempt is closed
	 *
	 * Two seconds as default is not much, but the point here is, tracking metrics
	 * should not change the behavior of your application, which it would if response
	 * time would be dramatically reduced. This way, we make sure that posting
	 * data is omitted on connection attempts that take way to long.
	 * Under normal conditions every post should be done within this time-limit,
	 * if not, try to investigate why that is the case.
	 *
	 * @var integer
	 */
	public static $timeout = 2;

	/**
	 * Username to authenticate against Metrics webservice
	 *
	 * This is usually your email-address you entered while signing up
	 *
	 * @var string
	 */
	public static $username;

	/**
	 * Token to authenticate against Metrics webservice
	 *
	 * You can find your token in your `Account` tab on
	 * the librato Metrics website
	 *
	 * @var string
	 */
	public static $token;

	/**
	 * Controls whether to submit all data in an asyncronous fashion
	 *
	 * WARNING: While this is much faster, it may be that you fail to retrieve
	 *          data if anything is wrong. Make sure, you retrieve data as 
	 *          expected before using this in production.
	 *
	 * @var boolean
	 */
	protected static $async = true;

	/**
	 * Holds instance of socket connection
	 *
	 * @var object
	 */
	protected static $_service;

	/**
	 * Tracks event in librato Metrics
	 *
	 * @return boolean true on succeess, false otherwise
	 */
	public static function gauge($name, $value, $source = null, $params = array()) {
		$defaults = array(
			'measure_time' => time(),
			'source' => Environment::get(),
		);
		$source = (empty($source))
			? $defaults['source']
			: $source;
		$params = array_merge($defaults, $params, compact('name', 'value', 'source'));
		return static::post(array('gauges' => array($params)));
	}

	/**
	 * retrieve a list of all metrics you collect
	 *
	 * This list can be filtered on the name and on tags. Search
	 * is case-insensitive and may contain only a substring of a name
	 *
	 * @see http://dev.librato.com/v1/get/metrics
	 * @param string $name *optional to filter results by name (substring)
	 * @param string $tags *optional to filter results by tag
	 * @return array an array containing all metrics according to your search
	 */
	public static function get($name = null, $tags = array()) {
		$data = compact('name', 'tags');
		$result = static::_service()->get('/v1/metrics', $data, array('type' => 'json'));
		return json_decode($result, true);
	}

	/**
	 * Posts data to remote endpoint
	 *
	 * Depending on `static::$async` (see options, also) it can try to post its data
	 * in an asynchronous fashion which is way faster. In case, you want to turn
	 * that off, just set $async to false.
	 *
	 * @see http://dev.librato.com/v1/get/metrics
	 * @see li3_metrics\core\Metrics::_async_post()
	 * @see lithium\net\http\Service
	 * @param string $data data to be posted in the format, according to docs
	 * @param array $options all options which will be in turned passed into
	 *        lithium Service class or self::_async_post()
	 * @return array
	 */
	public static function post($data = array(), array $options = array()) {
		$defaults = array(
			'async' => static::$async,
			'type' => 'json',
		);
		$options += $defaults;
		if ($options['async']) {
			return static::_async_post('/v1/metrics', $data, $options);
		}
		$result = static::_service()->post('/v1/metrics', $data, $options);
		return json_decode($result, true);
	}

	/**
	 * Instantiates and returns a lithium Service class with correct config
	 *
	 * @see lithium\net\http\Service
	 * @return object Instance of Service class
	 */
	protected static function _service() {
		$config = array(
			'scheme' => 'https',
			'host' => static::$host,
			'timeout' => static::$timeout,
			'auth' => 'Basic',
			'username' => static::$username,
			'password' => static::$token,
		);
		return static::$_service = new Service($config);
	}

	/**
	 * This method handles the async submission to the remote endpoint
	 *
	 * It does that in an asynchronous fashion to prevent time-consuming
	 * interaction. It does that with a fire-and-forget approach: It simply
	 * opens a socket connection to the remote-point and as soon as that is 
	 * open it pushes through all data to be transmitted and returns right 
	 * after that. It may happen, that this leads to unexpected behavior or
	 * failure of data submission. double-check your token and everything else
	 * that can fail to make sure, everything works as expected.
	 *
	 * @param string $data all data to be submitted, must be in the form
	 *        of an array, containing exactly two keys: `event` and `properties`
	 *        which are of type string (event) and array (properties). You can
	 *        submit whatever properties you like. If no token is given, it will 
	 *        be automatically appended from `static::$host` which can be set in 
	 *        advance like this: `Mixpanel::$token = 'foo';`
	 * @return boolean true on succeess, false otherwise
	 *         actually, it just checks, if bytes sent is greater than zero. It
	 *         does _NOT_ check in any way if data is recieved sucessfully in
	 *         the endpoint and/or if given data is accepted by remote.
	 */
	protected static function _async_post($url, array $data = array(), array $options = array()) {
		$post_string = http_build_query($data);
		$fp = fsockopen('ssl://'.static::$host, 443, $errno, $errstr, static::$timeout);
		if ($errno != 0) {
			// TODO: make something useful with error
			return false;
		}
		$out = "POST ".$url." HTTP/1.1\r\n";
		$out.= "Host: ".static::$host."\r\n";
		$out.= "Accept: */*\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Authorization: Basic ".base64_encode(static::$username.':'.static::$token)."\r\n";
		$out.= "Content-Length: ".strlen($post_string)."\r\n";
		$out.= "Connection: Close\r\n\r\n";
		if (isset($post_string)) $out.= $post_string;
		$bytes = fwrite($fp, $out);
		fclose($fp);
		return ($bytes > 0);
	}
}