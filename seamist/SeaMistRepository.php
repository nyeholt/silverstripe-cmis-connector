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

/**
 * Represents a CMIS implementing repository 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
interface SeaMistRepository
{
	/**
	 * Connect to the CMIS repository
	 * 
	 * @param $repoUrl
	 * 			The URL of the repository information XML file. This is required
	 * @param $baseUrl
	 * 			The URL of the 'base' url in the repository to use as the root
	 * 			of all data retrieved. Optional. 
	 * @param $username
	 * @param $password
	 * @return unknown_type
	 */
	public function connect($repoUrl, $baseUrl = null, $username, $password);

	/**
	 * Indicate whether this repository is connected
	 * 
	 * @return boolean
	 */
	public function isConnected();
	
	/**
	 * Disconnect from the repository
	 */
	public function disconnect();
	
	/**
	 * Get the root node of the repository
	 * 
	 * @return SeaMistObject
	 */
	public function getRepositoryRoot();
	
	/**
	 * Stream a content object to the browser
	 * 
	 * @return String
	 */
	public function streamObject(SeaMistObject $object);
	
	/**
	 * Return the CMIS information about this repository
	 * 
	 * @return SimpleXML
	 */
	public function getRepositoryInfo();
	
	/**
	 * Get all the seamist properties of an object, typically by ID,
	 * but it is up to each specific implementation to determine 
	 * how to use the $id variable
	 * 
	 * @param $id
	 * 			A unique identifier for this object, typically 
	 * 			the CMIS ObjectId property. 
	 *
	 * @return SeaMistObject
	 */
	public function getProperties($id);

	/**
	 * Get the children of a SeaMistObject
	 * 
	 * @param SeaMistObject $object
	 * @return SeaMistObjectList
	 */
	public function getChildren(SeaMistObject $object);
}


?>