<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class Kohana_RestfulAPI_Validation extends Validation
{
	protected $errorsFile;

	/**
	 * @param array $array
	 *
	 * @return Kohana_RestfulAPI_Validation
	 */
	public static function factory(array $array)
	{
		return new self($array);
	}

	public function __construct(array $array)
	{
		parent::__construct($array);
		$this->setErrorsFile();
	}


	/**
	 * @param null $file
	 * @param bool $translate
	 *
	 * @return array
	 */
	public function errors($file = NULL, $translate = TRUE)
	{
		return parent::errors(NULL === $file ? $this->errorsFile : $file, $translate);
	}


	/**
	 * @param null $key
	 * @param null $default
	 * @param null $removeKeyPrefix
	 *
	 * @return array|mixed
	 */
	public function data($key = NULL, $default = NULL, $removeKeyPrefix = NULL)
	{
		$data = parent::data();
		if (NULL !== $key && !Kohana_Arr::is_array($key)) return Kohana_Arr::get($data, $key, $default);

		if (NULL === $key) $key = array_keys($data);

		$result = [];
		foreach ($key as $_key) {
			$newKey = NULL !== $removeKeyPrefix && strpos($_key, $removeKeyPrefix) === 0
				? substr($_key, strlen($removeKeyPrefix))
				: $_key
			;
			$result[$newKey] = Kohana_Arr::get($data, $_key, $default);
		}

		return $result;
	}


	/**
	 * @param bool $throwException
	 *
	 * @throws RestfulAPI_Exception_422
	 * @return bool
	 */
	public function check($throwException = TRUE)
	{
		$result = parent::check();
		if (!$result && $throwException) {
//			var_dump($this->data(), $this->getRules());
//			die();
			throw new RestfulAPI_Exception_422($this->errors());
		}

		return $result;
	}

	/**
	 * @param string $errorsFile
	 *
	 * @return $this
	 */
	public function setErrorsFile($errorsFile = NULL)
	{
		if (NULL === $errorsFile) $errorsFile = 'request';
		$this->errorsFile = $errorsFile;

		return $this;
	}

}
