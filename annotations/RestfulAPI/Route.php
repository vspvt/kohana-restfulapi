<?php
namespace RestfulAPI;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class Route
{
	/** @var string */
	public $value;

	/** @var string */
	public $name;

	/** @var array */
	public $defaults = [];

	/** @var array */
	public $regexp = [];

	/** @var array */
	public $models = [];
}
