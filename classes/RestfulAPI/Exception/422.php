<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class RestfulAPI_Exception_422 extends RestfulAPI_Exception {

	private $_errors;

	public function __construct($errors, $message = NULL, $variables = NULL, $errno = NULL, array $debug = NULL, Exception $previous = NULL)
	{
		if (class_exists('ORM_Validation_Exception') && $errors instanceof ORM_Validation_Exception) $errors = $errors->errors('model');
		elseif ($errors instanceof RestfulAPI_Validation) $errors = $errors->errors();
		elseif ($errors instanceof Validation) $errors = $errors->errors('validation');

		$this->_errors = Kohana_Arr::flatten($errors);
		parent::__construct(422, $message, $variables, $errno, $debug, $previous);
	}

	public function getErrors()
	{
		return Helpers_Arr::asArray($this->_errors);
	}

}
