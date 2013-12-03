<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class Kohana_RestfulAPI_Exception extends Kohana_Exception {

	protected $code = 0;
	private $_errno = 0;
	private $_debug;

	public function __construct($code = NULL, $message = NULL, array $variables = NULL, $errno = NULL, array $debug = NULL, Exception $previous = NULL)
	{
		$this->_errno = (int) $errno;
		$this->_debug = $debug;
		$code = NULL === $code ? $this->code : $code;
		if (NULL === $message) {
			if (isset(Response::$messages[$code])) {
				$message = Response::$messages[$code];
			} else {
				$message = 'RestfulAPI_Exception [ :code ] - unknown';
				$variables = [':code' => $code];
				$code = 500;
			}
		}
		parent::__construct($message, $variables, $code, $previous);
	}

	public function getErrno()
	{
		return $this->_errno;
	}

	public function getDebug()
	{
		return $this->_debug;
	}

}
