<?php

class SeaMist {

	/**
	 * Singleton class, use getInstance()
	 */
	private function __construct() {
		
	}

	/**
	 * The class that's actually implementing the various API features
	 * 
	 * @var SeaMistRepository
	 */
	protected $impl;

	/**
	 * The range of different implementations
	 * 
	 * @var array
	 */
	protected $implementations = array();

	/**
	 * Set the implementing class
	 * 
	 * @param String $className 
	 * 			The implementor of SeaMistRepository. 
	 */
	public function registerImplementation($type, $className) {
		$this->implementations[$type] = $className;
	}

	/**
	 * Call the method on the SeaMistRepository
	 * 
	 * @param String $method
	 * @param mixed $args
	 * 
	 * @return mixed
	 */
	public function __call($method, $args) {
		if ($this->impl) {
			return call_user_func_array(array($this->impl, $method), $args);
		}

		throw new Exception($method . " has not been implemented yet");
	}

	/**
	 * A cache of all the repositories
	 * @var array
	 */
	protected $repositories = array();

	/**
	 * Get the actual implementer of the functionality
	 * 
	 * @param $type
	 * 			The type of seamist repository to return
	 * @param $name
	 * 			A unique name so that multiple instances of the same repository
	 * 			type can be created and used
	 * 
	 * @return SeaMistRepository
	 */
	public function getRepository($type = null, $name = 0) {
		if (!$type) {
			return $this->impl;
		}

		if (!isset($this->repositories[$type])) {
			$this->repositories[$type] = array();
		}
		$repositories = $this->repositories[$type];
		$this->impl = isset($repositories[$name]) ? $repositories[$name] : null;

		if (!$this->impl) {
			if (!isset($this->implementations[$type])) {
				throw new Exception("Missing SeaMist implementation for $type");
			}

			$class = $this->implementations[$type];
			$this->impl = new $class();

			$repositories[$name] = $this->impl;
			$this->repositories[$type] = $repositories;
		}

		return $this->impl;
	}

	private static $instance;

	/**
	 * 
	 * @return SeaMist
	 */
	public static function getInstance() {
		if (!self::$instance) {
			self::$instance = new SeaMist();
		}

		return self::$instance;
	}

}
