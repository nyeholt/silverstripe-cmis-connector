<?php
/**

Copyright (c) 2009, SilverStripe Australia Limited - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the 
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software 
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE 
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
OF SUCH DAMAGE.
 
*/


class SeaMist 
{
	/**
	 * Singleton class, use getInstance()
	 */
	private function __construct()
	{
		
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
	public function registerImplementation($type, $className)
	{
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
	public function __call($method, $args)
	{
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
	public function getRepository($type=null, $name=0)
	{
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
	public function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new SeaMist();
		}

		return self::$instance;
	}
}


?>