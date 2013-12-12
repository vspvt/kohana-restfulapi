<?php
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */
  
class Kohana_RestfulAPI
{
	protected static $_config;
	protected static $_routes;
	protected static $_initialized = FALSE;

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

	// ROUTES
	/**
	 * @return array
	 */
	private static function apiRouteDefaults()
	{
		$defaults = self::config('route.defaults', []);

		return Helpers_Arr::merge(
			$defaults,
			[
				'version' => (int) self::config('version', 1),
				'directory' => Kohana_Arr::get($defaults, 'directory', 'Api_V{version}'),
				'controller' => Kohana_Arr::get($defaults, 'controller'),
				'action' => 'index',
			]
		);
	}

	/**
	 * @return array
	 */
	static function routes()
	{
		if (!static::$_routes) {
			$defaults = self::apiRouteDefaults();

			$params = (array) self::config('route.params', []);
			$uri = self::config('route.url_prefix', 'api') . '(/<controller>';
			foreach (array_keys($params) as $_paramKey) $uri .= sprintf('(/<%s>', $_paramKey);
			$uri .= str_repeat(')', count($params) + 1);
			$params['version'] = '\d+';

			$routeName = self::config('route.name', 'api');
			Route::set($routeName, $uri, $params)
				->defaults($defaults)
				->filter([get_class(), 'route']);

			static::$_routes = [
				$routeName => $uri,
			];
		}

		return static::$_routes;
	}

	static function route(Route $route, $params, Request $request)
	{
		$params['directory'] = strtr($params['directory'], [
			'{directory_prefix}' => self::config('directory_prefix'),
			'{version}' => $params['version'],
		]);

		return $params;
	}

}
