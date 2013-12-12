<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */

return [
	'version'=> 1,
	'directory_prefix' => 'Api',
	'route' => [
		'name' => 'api',
		'url_prefix' => 'api(/v<version>)',
		'params' => [
			'id' => '(\d+|[a-zA-Z][a-zA-Z0-9_]*)',
			'custom_name' => '[a-zA-Z][a-zA-Z0-9_]*',
			'custom_id' => '(\d+|[a-zA-Z][a-zA-Z0-9_]*)',
		],
		'defaults' => [
			'directory' => '{directory_prefix}' . DIRECTORY_SEPARATOR . 'V{version}',
		],
	],
	'onerror' => [
		'log' => [
			'http_codes' => [304, 400, 401, 422, 500], // FALSE - disable, TRUE - all codes, array - list of codes to log
		],
		'debug' => [
			'exception' => Helpers_Core::isProduction() ? FALSE : 'string', // FALSE|NULL - disable, 'array' - Exception->getTrace(), other values Exception->getTraceAsString()
		],
	],
];
