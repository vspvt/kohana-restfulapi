<?php
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */

class Kohana_RestfulAPI
{
	protected static $_config;
	protected static $_routes = FALSE;
	protected static $_initialized = FALSE;

	const annotationRoute = 'RestfulAPI\Route';

	// CONFIG
	/**
	 * @param null $path
	 * @param null $default
	 *
	 * @return array|mixed
	 */
	static final function config($path = NULL, $default = NULL)
	{
		if (!static::$_config)
			static::$_config = Kohana::$config->load('restfulapi')->as_array();

		return NULL !== $path ? Kohana_Arr::path(static::$_config, $path, $default) : static::$_config;
	}

	// INIT
	static function initialize()
	{
		if (!static::$_initialized) {
			foreach (self::config('response.messages') as $key=>$value) {
				Response::$messages[$key] = $value;
			}

			static::$_initialized = TRUE;
		}

	}

	/**
	 * @param bool $parseActions
	 * @param bool $forced
	 */
	static function routes($parseActions = TRUE, $forced = FALSE)
	{
		if ($forced || !static::$_routes) {
			$pathPrefix = 'classes' . DIRECTORY_SEPARATOR;
			$directoryPrefix = self::config('directory_prefix');
			$path = rtrim($pathPrefix . 'Controller' . DIRECTORY_SEPARATOR . $directoryPrefix, '/');
			$controllers = array_keys(Arr::flatten(Kohana::list_files($path)));

			$urlPrefix = $uri = self::config('route.url_prefix', 'api');

			foreach ($controllers as $controller) {
				$className = str_replace([$pathPrefix, DIRECTORY_SEPARATOR, EXT], ['', '_', ''], $controller);
				self::getClassRoutes($className, $directoryPrefix, $urlPrefix, $parseActions);
			}
			static::$_routes = TRUE;
		}
	}

	protected static function getClassRoutes($className, $directoryPrefix, $urlPrefix, $parseActions = TRUE)
	{
		if (preg_match('@_V(\d+)_([^_]+)$@', $className, $matches)) {
			$version = $matches[1];
			$urlPrefix = str_replace('{version}', $version, $urlPrefix) . '/';
			$controllerName = $matches[2];

			/** @var RestfulAPI\Route $route */
			$route = Annotations::getClassAnnotation($className, self::annotationRoute);
			if (NULL === $route) {
				$route = Annotations::annotationClass(self::annotationRoute);
			}
			$route->name = $className;
			$route->value = $urlPrefix . $route->value;

			$routeDefaults = [
				'directory' => $directoryPrefix . 'V' . $version,
				'controller' => $controllerName,
			];

			if ($parseActions) {
				$classReflection = new ReflectionClass($className);
				$classActions = $classReflection->getMethods(ReflectionMethod::IS_PUBLIC);
				foreach ($classActions as $method) {
					if (!$method->isFinal() && preg_match('@^action_(([^_]+)(?:_(.+))?)$@', $method->name, $actionMatches)) {
						/** @var RestfulAPI\Route $actionRoute */
						$actionRoute = Annotations::getMethodAnnotation($method->name, self::annotationRoute, $className);
						if (NULL !== $actionRoute) {
							$actionName = Arr::get($actionMatches, 1, $method->name);
							$actionRoute->name = $className . '::' . $actionName;
							$actionRoute->value = $urlPrefix . $actionRoute->value;
							$actionRoute->defaults['action'] = $actionName;

							self::makeRoute($actionRoute, $routeDefaults);
						}
					}
				}
			}

			self::makeRoute($route, $routeDefaults);
		}
	}

	public static function makeRoute(RestfulAPI\Route $annotation, $defaults = [])
	{
		$defaults = Helpers_Arr::merge($annotation->defaults, $defaults, ['models' => $annotation->models]);

		return Route::set($annotation->name, $annotation->value, $annotation->regexp)
			->defaults($defaults)
			->filter([get_class(), 'route'])
			;
	}

	/**
	 * @param \Route   $route
	 * @param array    $params
	 * @param \Request $request
	 *
	 * @return array
	 */
	static function route(Route $route, $params, Request $request)
	{
		foreach (Arr::get($params, 'models', []) as $key=>$modelName) {
			if (isset($params[$key])) {
				$modelName = "Model_{$modelName}";
				$params[$key] = new $modelName($params[$key]);
			}
		}

		return $params;
	}
}
