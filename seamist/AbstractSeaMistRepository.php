<?php
/**

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
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
 * An Abstract CMIS repository. 
 * 
 * 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class AbstractSeaMistRepository implements SeaMistRepository
{
	protected $baseUrl;
	protected $repoUrl;

	/**
	 * @var WebApiClient
	 */
	protected $api;

	public function __construct($api=null) {
		if (!$api) {
			$this->api = new WebApiClient('');
		} else {
			$this->api = $api;
		}
		$this->attachHandlers();
	}

	/**
	 * Attach the seamist object handlers
	 */
	protected function attachHandlers()
	{
		// Bind in the return handlers for our types
		$this->api->addReturnHandler('cmisobject', new CMISObjectReturnHandler());
		$this->api->addReturnHandler('cmisobjectlist', new CMISObjectListReturnHandler());
		$this->api->addReturnHandler('cmisrepoinfo', new CMISRepositoryInfoHandler());
	}

	/**
	 * Connect to the repository 
	 * 
	 * @see alfresco-connector/seamist/SeaMistRepository#connect($url, $username, $password)
	 */
	public function connect($repoUrl, $baseUrl, $username, $password)
	{
		$this->repoUrl = $repoUrl;
		$this->baseUrl = $baseUrl;
		$this->login($username, $password);
		try {
			$info = $this->getRepositoryInfo();
			if (!$this->baseUrl) {
				$rootId = $info->getRootFolderId();

				// In older systems, the root ID is actually a URL directly.
				if (strpos($rootId, 'http') === 0) {
					$this->baseUrl = $rootId;
				} else {
					// translate it to a URL using the template
					$this->baseUrl = $this->mapToTemplate(array('id' => $rootId), 'objectbyid');
				}
			}
		} catch (Zend_Http_Client_Adapter_Exception $e) {
			// we might not have connected yet!
			error_log("Failed connecting to receive repository info: ".$e->getMessage());
		}
	}

	/**
	 * Login to the CMIS repository. This should eventually be standard
	 * for all repositories (ie HTTP Basic or similar).
	 *
	 * @param String $username
	 * @param String $password
	 */
	protected function login($username, $password)
	{
		$this->api->setAuthInfo($username, $password);
	}

	/**
	 * Is this repository connected?
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->baseUrl != null;
	}

	/**
	 * Disconnect from the repository.
	 * 
	 */
	public function disconnect()
	{
		$this->baseUrl = null;
	}

	/**
	 * Cache the value of the repository info
	 * 
	 * @var SimpleXMLElement
	 */
	protected $repositoryInfo;

	/**
	 * Return the CMIS information about this repository
	 * @return SeaMistRepositoryInfo
	 */
	public function getRepositoryInfo()
	{
		if (!$this->repositoryInfo) {
			$this->repositoryInfo = $this->api->callUrl($this->repoUrl, array(), 'cmisrepoinfo');
		} 

		return $this->repositoryInfo;
	}

	/**
	 * Get the root node of the repository
	 * 
	 * @return SeaMistObject
	 */
	public function getRepositoryRoot()
	{
		// get the baseUrl
		$repositoryRoot = $this->api->callUrl($this->baseUrl, array(), 'cmisobject');
		return $repositoryRoot;
	}

	/**
	 * Streams the object based on its details
	 * 
	 * @see external-content/seamist/SeaMistRepository#streamObject()
	 */
	public function streamObject(SeaMistObject $object, $toFile=null)
	{
		// TODO: Not implementing until local caching of content files is possible, as otherwise
		// we need to copy file locally first, THEN forward it to the user's browser. 
		// not going to bother with that just yet.
		$contentUrl = $object->getContentUrl();
		$contentType = strlen($object->contentStreamMimeType) ? $object->contentStreamMimeType : 'application/octet-stream';

		// Allow the URL for streaming content to be modified by any child impl
		$contentUrl = $this->modifyStreamUrl($contentUrl); //  "?alf_ticket=$this->ticket";
		$session = curl_init($contentUrl);

		if (!strlen($toFile)) {
			// QUICK HACK
			$n = $object->name;
			$filename = rawurlencode($n);

			header("Content-Disposition: atachment; filename=$filename");
			header("Content-Type: $contentType");
			// header("Content-Length: ".filesize("$path/$filename"));
			header("Pragma: no-cache");
			header("Expires: 0");
			curl_exec($session);
		} else {
			// get the file and store it into a local item
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($session);
			$fp = fopen($toFile, 'w');
			if (!$fp) {
				throw new Exception("Could not write file to $toFile");
			}
			fwrite($fp, $response);
			fclose($fp);
		}

		curl_close($session);
	}

	/**
	 * Lets various repositories modify the URL used to stream content
	 * 
	 * For example
	 * 
	 * - Alfresco needs an Auth ticket appended
	 * 
	 * @param String $url
	 * @return String
	 */
	protected function modifyStreamUrl($url)
	{
		return $url;
	}

	/**
	 * Get the children of a SeaMistObject
	 * 
	 * @param SeaMistObject $object
	 * @return SeaMistObjectList
	 */
	public function getChildren(SeaMistObject $object)
	{
		$url = $object->getLink('children');
		if (!$url) {
			$url = $object->getLink('down');
		}

		if (!$url) {
			return null;
		}
		$children = $this->api->callUrl($url, array(), 'cmisobjectlist');
		return $children;
	}

	/**
	 * Get all the properties of a given ID. 
	 * 
	 * Note that this expects an implementation via a query; to negate
	 * the need for that just yet, we'll make a call to 'getObject' which
	 * the various implementations can provide a workaround... for now
	 * 
	 * TODO Use a proper CMIS query here
	 * 
	 * @see alfresco-connector/seamist/SeaMistRepository#getProperties($id)
	 * 
	 * @return SeaMistObject
	 */
	public function getProperties($id)
	{
		$object = $this->getObject($id);
		if ($object) {
			return $object;
		}
	}

	/**
	 * Temporary workaround method for getting an object by ObjectId
	 * @param String $id
	 * @return SeaMistObject
	 */
	public function getObject($id)
	{
		
	}

	/**
	 *
	 * Maps a set of values onto a URI template
	 *
	 * @param String $values
	 * @param String $uriName
	 */
	protected function mapToTemplate($values, $uriName)
	{
		$uritemplate = $this->getRepositoryInfo()->getUriTemplate($uriName);
		if (!$uritemplate) {
			return null;
		}
		// not using array replace because we'd need to walk the array to handle { chars anyway...
		foreach ($values as $key => $replacement) {
			$uritemplate = str_replace('{'.$key.'}', $replacement, $uritemplate);
		}

		//nullify all others
		$uritemplate = preg_replace('/{[A-Za-z0-9-_.]*}/', '', $uritemplate);

		return $uritemplate;
	}
}


?>