<?php

/**
 * Represents a CMIS implementing repository 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
interface SeaMistRepository {

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