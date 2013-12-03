<?php
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class Exception_Conflict extends Kohana_Exception
{
	protected $code = 0;

	public function __construct($message = "", array $variables = NULL, $code = NULL, Exception $previous = NULL)
	{
		if (NULL === $code) $code = $this->code;

		parent::__construct($message, $variables, $code, $previous);
	}
}
