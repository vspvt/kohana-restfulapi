<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class RestfulAPI_Exception_400 extends RestfulAPI_Exception {

	public function __construct($message = NULL, array $variables = NULL, $errno = NULL, array $debug = NULL, Exception $previous = NULL)
	{
		parent::__construct(400, $message, $variables, $errno, $debug, $previous);
	}

}
