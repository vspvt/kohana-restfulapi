<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @author: Vad Skakov <vad.skakov@gmail.com>
 */

return [
	'version'=> 1,
	'directory_prefix' => 'Api' . DIRECTORY_SEPARATOR,
	'route' => [
		'name' => 'api',
		'url' => [
			'base' => 'api',
			'version' => '/v{version}',
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
	'response' => [
		'messages' => [
			102 => 'Processing',
			207 => 'Multi-Status',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			507 => 'Insufficient Storage',
		],
	],
];
