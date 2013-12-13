<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */

abstract class Kohana_Controller_RestfulAPI extends Controller
{
	/** @var bool Change to FALSE for testing purpose ONLY */
	protected $responseAsJSON = TRUE;

	/** @var array Map of HTTP methods -> actions */
	protected $_actionMap = array
	(
		Request::POST => 'post', // Typically Create..
		Request::GET => 'get',
		Request::PUT => 'put', // Typically Update..
		Request::DELETE => 'delete',
		'PATCH' => 'patch',
		Request::OPTIONS => 'options',
	);

	/** @var string Name of callable function in controller */
	protected $_actionName;

	/** @var array List of HTTP methods which support body content */
	protected $_methodsWithBodyContent = [
		Request::POST,
		Request::PUT,
		'PATCH',
	];

	/** @var NULL|array decoded JSON request body */
	private $_requestBody;
	/** @var NULL|array */
	private $_requestData;

	/** @var NULL|string Model_*::MODEL - for model initialization */
	protected $_controllerModel;

	protected $collectionAction = FALSE;
	protected $collectionActionValidation = [['digit']];
	protected $collectionMethod = FALSE;

	protected $debugToJSON;
	/** @var array */
	protected $debugRequestParams = [];

	/** @var array of RestfulAPI_Validation */
	private $_validations;

	/** @var null|string */
	private $responseLocation;

	// REQUEST
	protected function _parse_request()
	{
		// Override the method if needed.
		$this->request->method(Kohana_Arr::get(
			$_SERVER,
			'HTTP_X_HTTP_METHOD_OVERRIDE',
			$this->request->method()
		));

		$requestMethod = $this->request->method();

		// Is supporting method?
		if (!isset($this->_actionMap[$requestMethod])) {
			$this->error(new HTTP_Exception_405('The :method method is not supported. Supported methods are :allowed_methods', [
				':method' => $requestMethod,
				':allowed_methods' => implode(', ', array_keys($this->_actionMap)),
			]));
		}

		$addCollection = FALSE;
		$this->_actionName = 'action_' . $this->_actionMap[$requestMethod];

		if ($this->request->action() !== 'index') {
			$this->_actionName .= '_' . $this->request->action();
			$this->request->action('index');
		} else {

			// If we are acting on a collection, append _collection to the action name.
			if (FALSE !== $this->request->param('id', FALSE)) {
				$addCollection = TRUE;
				$this->collectionAction = TRUE;
			}
			// If this is a subaction, lets make sure we use it.
			if (FALSE !== $this->request->param('method', FALSE)) {
				$addCollection = FALSE;
				$this->_actionName .= '_' . $this->request->param('method');
				// If this is a subaction collection, lets make sure we use it.
				if (FALSE !== ($this->collectionMethod = $this->request->param('method_id', FALSE))) {
					$addCollection = TRUE;
					$this->collectionMethod = Helpers_Text::trimAsNULL($this->collectionMethod);
				} else{
					if (FALSE !== ($extMethod = $this->request->param('method_ext', FALSE))) {
						$this->_actionName .= '_' . $extMethod;
					}
				}
			}
			if ($addCollection) $this->_actionName .= '_collection';
		}

		// Exists method action function?
		if (!method_exists($this, $this->_actionName)) {
			$errorStr = 'The :method method is not implemented';
			if (Kohana::$environment !== Kohana::PRODUCTION) $errorStr .= ' [:action]';
			$this->error(new HTTP_Exception_501($errorStr, [
				':method' => $requestMethod,
				':action' => $this->_actionName,
			]));
		}

		//if (in_array($method, $this->_methodsWithBodyContent))
		//$this->_parse_request_body();
	}

	/**
	 * Parse raw HTTP request data
	 *
	 * Pass in $a_data as an array. This is done by reference to avoid copying
	 * the data around too much.
	 *
	 * Any files found in the request will be added by their field name to the
	 * $_FILES array
	 *
	 * @param   string $input Input data
	 *
	 * @return  array  Associative array of request data
	 */
	function _parse_raw_requst_body($input)
	{
		$data = [];
		// grab multipart boundary from content type header
		preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
		// content type is probably regular form-encoded
		if (!count($matches)) {
			Kohana::$log->add(Log::DEBUG, 'RAW BODY boundry - not found');
			// we expect regular puts to containt a query string containing data
			//parse_str(urldecode($input), $data);
		} else {
			$boundary = preg_quote($matches[1]);

			// split content by boundary and get rid of last -- element
			$blocks = preg_split("/-+$boundary/", $input);

			// loop data blocks
			foreach ($blocks as $block) {
				if (empty($block))
					continue;

				$parts = preg_split('/[\r\n][\r\n]/', trim($block, "\r\n"), 2, PREG_SPLIT_NO_EMPTY);

				if (count($parts) != 2) continue;

				list($raw_headers, $body) = $parts;

				if (
				preg_match(
					'/name="([^"]+)"(; *filename="([^"]+)")?/',
					$raw_headers,
					$matches
				)
				) {
					$name = rawurldecode($matches[1]);
					$filename = isset($matches[3]) ? $matches[3] : NULL;

					if (!isset($filename)) {
						$body = Helpers_Text::trimAsNULL($body);
						$_tmp = "{$name}={$body}";
						$_data = NULL;
						parse_str($_tmp, $_data);
						$data = Helpers_Arr::merge($data, $_data);
					} else {
						$_tmpname = tempnam(NULL, 'tmp');
						if (FALSE !== $_tmpname) {
							if (preg_match('@^Content-Type:@im', $body)) {
								$body = trim(preg_replace('@^Content-Type:[^\n]*@i', "", $body));
							}

							file_put_contents($_tmpname, $body);
							chmod($_tmpname, 0666);
							$deleteOnShutdown = TRUE;
							$_FILES[$name] = [
								'name' => $filename,
								'type' => mime_content_type($_tmpname),
								'tmp_name' => $_tmpname,
								'error' => UPLOAD_ERR_OK,
								'size' => filesize($_tmpname),
							];
						}
					}

				}
			}

			if (isset($deleteOnShutdown)) {
				register_shutdown_function(function () {
					foreach ($_FILES as $row) @unlink($row['tmp_name']);
				});
			}
		}

		return $data;
	}

	/**
	 * @return array
	 * @throws RestfulAPI_Exception_400
	 */
	protected function _parse_request_body()
	{
		if (NULL === $this->_requestBody) {
			$this->_requestBody = [];

			if (strlen($this->request->body())) {
				if (FALSE !== strpos($_SERVER['CONTENT_TYPE'], 'application/json')) {
					$this->_requestBody = json_decode($this->request->body(), TRUE);
					if (NULL === $this->_requestBody) throw new RestfulAPI_Exception_400('Invalid json supplied');
				} else {
					$this->_requestBody = $this->_parse_raw_requst_body($this->request->body());
				}
				$this->_requestBody = Helpers_Arr::asArray($this->_requestBody);
			}
		}

		return $this->_requestBody;
	}

	/**
	 * @param null|string|array $key
	 * @param null              $default
	 * @param null              $delimiter
	 *
	 * @return array|mixed
	 */
	protected function requestData($key = NULL, $default = NULL, $delimiter = NULL)
	{
		if (NULL === $this->_requestData) {
			;
			$this->_requestData = Helpers_Arr::merge(
				$this->request->query(),
				$this->request->post(),
				$this->_parse_request_body()
			);
		}

		if (Kohana_Arr::is_array($key)) {
			$result = [];
			foreach ($key as $idx => $value) {
				if (!Valid::digit($idx)) {
					$_key = $idx;
					$_default = $value;
				} else {
					$_key = $value;
					$_default = $default;
				}
				$result[$_key] = Kohana_Arr::path($this->requestData(), $_key, $_default, $delimiter);
			}
		} else {
			$result = NULL === $key ? $this->_requestData : Kohana_Arr::path($this->requestData(), $key, $default, $delimiter);
		}

		return $result;
	}

	protected function getAction()
	{
		return $this->_actionName;
	}

	// RESPONSE
	private $_response_operationCode = 0;
	private $_response_httpCode = 501;
	private $_response_data = 'Not Implemented';
	protected $_response_errors;
	private $_response_debug = [];

	/**
	 * @return bool
	 */
	protected function isError()
	{
		return !Helpers_Arr::inArray($this->_response_httpCode, [200, 201, 204]);
	}

	/**
	 * @param string|exception $error
	 * @param int              $http_code
	 * @param null             $operation_code
	 * @param null|array       $debugData
	 */
	protected function error($error = NULL, $http_code = 400, $operation_code = NULL, array $debugData = NULL)
	{
		$this->apiSetResponse($error, $http_code, $operation_code, $debugData, TRUE);
		$this->after();
	}

	protected function getErrorResponse($message, $code = FALSE)
	{
		$data = [
			'message' => $message,
			'code' => intval(FALSE === $code ? $this->_response_operationCode : $code),
		];

		return $data;
	}

	/**
	 * @param mixed      $data
	 * @param int        $http_code
	 * @param null|array $debugData
	 * @param bool       $forcedData
	 */
	protected function setSuccess($data = NULL, $http_code = 200, array $debugData = NULL, $forcedData = FALSE)
	{
		$this->apiSetResponse($data, $http_code, NULL, $debugData, $forcedData);
	}

	/**
	 * @param mixed      $data
	 * @param int        $http_code
	 * @param null|array $debugData
	 * @param bool       $forcedData
	 */
	protected function success($data = NULL, $http_code = 200, array $debugData = NULL, $forcedData = FALSE)
	{
		$this->setSuccess($data, $http_code, NULL, $debugData, $forcedData);
		$this->after();
	}

	/**
	 * @param mixed $data
	 * @param bool  $forcedData
	 */
	public function setResponseData($data, $forcedData = FALSE)
	{
		if ($forcedData) {
			$this->_response_data = $data;
		} else if (NULL !== $data || NULL !== $this->_response_data) {
			$this->_response_data = Helpers_Arr::merge($this->_response_data, $data);
		}
	}

	/**
	 * @param mixed      $data
	 * @param int        $http_code
	 * @param null       $operation_code
	 * @param null|array $debugData
	 * @param bool       $forcedData
	 */
	private function apiSetResponse($data, $http_code = 400, $operation_code = NULL, array $debugData = NULL, $forcedData = FALSE)
	{
		$this->setResponseData($data, $forcedData);
		$this->_response_httpCode = (int) $http_code;
		$this->_response_operationCode = NULL !== $operation_code ? (int) $operation_code : NULL;
		$this->setDebugData($debugData);
	}

	/**
	 * @param null $data
	 * @param bool $replace
	 *
	 * @return array
	 */
	protected function setDebugData($data = NULL, $replace = FALSE)
	{
		$this->_response_debug = $replace
			? $data
			: Helpers_Arr::merge($this->_response_debug, $data);

		return $this->_response_debug;
	}

	/**
	 * @param string $key
	 * @param null   $value
	 *
	 * @return array
	 */
	protected function setDebug($key, $value = NULL)
	{
		return $this->setDebugData([$key => $value], FALSE);
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return array
	 */
	protected function bindDebug($key, &$value)
	{
		return $this->setDebugData([$key => $value]);
	}

	/**
	 * @param null $value
	 *
	 * @return null|string
	 */
	protected function setResponseLocation($value = NULL)
	{
		$this->responseLocation = Helpers_Text::trimAsNULL($value);

		return $this->responseLocation;
	}

	/**
	 * @param mixed $data
	 *
	 * @return mixed
	 */
	protected function setResponseBody($data)
	{
		$isSuccess = $this->response->status() >= 200 && $this->response->status() < 300;
		//$this->setDebug('profiler', Profiler::application());
		if (Helpers_Arr::count($this->debugRequestParams)) {
			$this->setDebugData(['requestParams' => $this->requestData($this->debugRequestParams)]);
		}

		if ($isSuccess) $this->debugToJSON = FALSE;
		if ($this->debugToJSON === TRUE && Helpers_Arr::count($this->_response_debug)) {
			$data = Helpers_Arr::merge($data, ['debug' => $this->_response_debug]);
		}

		if ($isSuccess && (!Helpers_Arr::count($data) || $this->response->status() == 204)) {
			$this->response->body(NULL);
			$this->response->status(204);
		} else {
			$this->response->body(json_encode($data, JSON_UNESCAPED_UNICODE));
		}

		return $data;
	}


	/**
	 * @param Exception $exception
	 * @param null      $message
	 *
	 * @return string
	 */
	static function exceptionString(Exception $exception, $message = NULL)
	{
		return strtr(':class [ :code ]: :message [ :file::line ]', [
			':class' => get_class($exception),
			':code' => $exception->getCode(),
			':message' => NULL === $message ? $exception->getMessage() : $message,
			':file' => Debug::path($exception->getFile()),
			':line' => $exception->getLine(),
		]);
	}

	private function _response()
	{
		if (!isset(Response::$messages[$this->_response_httpCode])) $this->_response_httpCode = 400;

		if ($this->responseAsJSON) {
			if (!$this->isError()) {
				// SUCCESS
				$jsonData = $this->_response_data;
				if (NULL !== $this->responseLocation) {
					//$this->response->headers('Location', $this->responseLocation);
				}
			} else {
				// ERROR
				if ($this->_response_data instanceof Exception) {
					$exception = $this->_response_data;
					$message = $exception->getMessage();

					if (!($exception instanceof RestfulAPI_Exception)) {
						$this->_response_httpCode = $exception->getCode();
					}
				} else {
					$message = $this->_response_data;
				}

				if (!is_string($message)) $message = isset($exception) ? get_class($exception) : 'Undefined error';

				$jsonData = $this->getErrorResponse($message, $this->_response_operationCode);
				if (isset($this->_response_errors)) {
					$errors = [];
					foreach ((array) $this->_response_errors as $field => $error) {
						$errors[] = [
							'key' => $field,
							'description' => $error,
						];
					}
					$jsonData['parameters'] = $errors;
				}
			}

			$this->response->headers('cache-control', 'no-cache, no-store, max-age=0, must-revalidate');
			$this->response->headers('content-type', 'application/json; charset=utf-8');
			try {
				$this->response->status($this->_response_httpCode);
			} catch (Exception $e) {
				$this->response->status(500);
				$this->_response_operationCode = $this->_response_httpCode;
			}

			if (isset($exception)) {
				$_traceType = RestfulAPI::config('onerror.debug.exception', 'string');
				if ($_traceType) $this->setDebugData([
					'exception' => [
						'source' => self::exceptionString($exception),
						'trace' => $_traceType !== 'array' ? $exception->getTraceAsString() : $exception->getTrace(),
					]
				]);
			}

			try {
				$this->setResponseBody($jsonData);
			} catch (Exception $e) {
				$exception = !isset($exception) ? $e : new Exception($e->getMessage(), $e->getCode(), $exception);

				$this->response->status(500);
				$this->setResponseBody([
					'message' => strtr('Error while formatting response:message', [
						':message' => !Helpers_Core::isProduction() ? ': ' . $e->getMessage() : '',
					]),
					'code' => 0,
				]);
			}

			// LOGGING EXCEPTION
			if (isset($exception)) {
				$_logCodes = RestfulAPI::config('onerror.log.http_codes', []);
				if (TRUE === $_logCodes || Helpers_Arr::inArray($this->response->status(), $_logCodes)) {
					Kohana::$log->add(
						($this->response->status() == 500 ? Log::ERROR : Log::INFO),
						self::exceptionString($exception),
						NULL,
						[
							'exception' => $exception,
						]
					);
				}
			}
			$this->response->send_headers(TRUE);
			exit($this->response);
		} else {
			if ($this->isError()) {
				if ($this->_response_data instanceof Exception) Kohana_Exception::response($this->_response_data);
				throw HTTP_Exception::factory($this->_response_httpCode, '[ :errno ] :error', [
					':errno' => $this->_response_operationCode,
					':error' => $this->_response_data,
				]);
			} else {
				$this->response->body($this->_response_data);
			}
		}
	}

	// HELPERS
	/**
	 * @param Exception $e
	 */
	protected function exception(Exception $e)
	{
		if (class_exists('ORM_Validation_Exception') && $e instanceof ORM_Validation_Exception) $e = new RestfulAPI_Exception_422($e);
		elseif ($e instanceof Exception_Conflict) $e = new RestfulAPI_Exception(409, $e->getMessage(), NULL, $e->getCode(), NULL, $e);

		if ($e instanceof RestfulAPI_Exception) {
			if ($e instanceof RestfulAPI_Exception_422) {
				$this->_response_errors = Helpers_Arr::asArray($e->getErrors());
			}
			$this->error($e, $e->getCode(), $e->getErrno(), $e->getDebug());
		} else {
			$this->error($e, $e->getCode());
		}
	}

	/**
	 * @param mixed  $value
	 * @param array  $validationRules
	 * @param string $file Validation errors file (default: validation)
	 *
	 * @return mixed
	 * @throws Validation_Exception
	 */
	protected function validateValue($value, array $validationRules = NULL, $file = NULL)
	{
		$validationRules = Helpers_Arr::merge(
			[
				['not_empty']
			],
			NULL === $validationRules ? [] : $validationRules
		);
		$validation = Validation::factory(['key' => $value])->rules('key', $validationRules);
		if (!$validation->check()) {
			if (NULL === $file) $file = 'validation';
			$errors = Kohana_Arr::flatten($validation->errors($file));
			throw new Validation_Exception($validation, Kohana_Arr::get($errors, 'key', 'Undefined validation error'));
		}

		return $value;
	}

	/**
	 * @param string     $key
	 * @param mixed|null $default
	 * @param array      $validationRules
	 * @param null       $file
	 *
	 * @throws RestfulAPI_Exception
	 * @return mixed
	 */
	protected function getParam($key = 'id', $default = NULL, array $validationRules = NULL, $file = NULL)
	{
		try {
			return $this->validateValue($this->request->param($key, $default), $validationRules, NULL === $file ? 'request'
				: $file);
		} catch (Validation_Exception $e) {
			throw new RestfulAPI_Exception(404, '{key}: {error}', [
				'{key}' => $key,
				'{error}' => $e->getMessage(),
			]);
		}
	}

	/**
	 * @param array $validationRules
	 * @param null  $file
	 *
	 * @throws RestfulAPI_Exception_422
	 * @return mixed
	 */
	protected function getID(array $validationRules = NULL, $file = NULL)
	{
		return $this->getParam('id', NULL, $validationRules, $file);
	}

	/**
	 * @param array $validationRules
	 * @param null  $file
	 *
	 * @throws RestfulAPI_Exception_422
	 * @return mixed
	 */
	protected function getMethodID(array $validationRules = NULL, $file = NULL)
	{
		return $this->getParam('method_id', NULL, $validationRules, $file);
	}

	/**
	 * @param mixed       $data
	 * @param null|string $group
	 *
	 * @return RestfulAPI_Validation
	 */
	protected function &validation($data = NULL, $group = NULL)
	{
		if (NULL === $group) $group = 'default';
		if (!isset($this->_validations[$group]) || NULL !== $data) {
			$this->_validations[$group] = RestfulAPI_Validation::factory(Helpers_Arr::asArray($data));
		}

		return $this->_validations[$group];
	}

	protected function validationDeleteGroup($group)
	{
		unset($this->_validations[$group]);
	}

	// REQUEST ACTIONS
	public function before()
	{
		try {
			parent::before();
			$this->_parse_request();

			$this->validation(Helpers_Arr::flattenExtended($this->requestData()));
		} catch (Exception $e) {
			$this->exception($e);
		}
	}

	public function after()
	{
		try {
			$this->_response();
		} catch (Exception $e) {
			$this->exception($e);
		}
	}

	public final function action_index()
	{
		try {
			$this->{$this->_actionName}();
		} catch (Exception $e) {
			$this->exception($e);
		}
	}

}
