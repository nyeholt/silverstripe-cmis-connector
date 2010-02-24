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
 * Wraps around the CMIS repository information
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class SeaMistRepositoryInfo
{
	protected $rootFolder = '';
	
	protected $capabilities = array();

	protected $collections = array();

	protected $uritemplates = array();

	public function __construct($xml)
	{
		if ($xml) {
			// first, lets find the cmis namespace to use
			$sx = new SimpleXMLElement($xml);
			$namespaces = $sx->getDocNamespaces();
			unset($sx);
			
			$cmisra = isset($namespaces['cmisra']) ? $namespaces['cmisra'] : null;
			$cmis = isset($namespaces['cmis']) ? $namespaces['cmis'] : null;
			
			$doc = new DomDocument();
			$doc->loadXML($xml);

			$versionElem = $doc->getElementsByTagNameNS($cmis, 'cmisVersionSupported');
			if ($versionElem->item(0) == null) {
				// try 'versions'... knowledgetree has this misspelling
				$versionElem = $doc->getElementsByTagNameNS($cmis, 'cmisVersionsSupported');
			}

			$version = 0;
			if ($versionElem && $versionElem->item(0)) {
				// use the first one...
				$version = $versionElem->item(0)->nodeValue;
			}
			
			$rootFolderElem = $doc->getElementsByTagNameNS($cmis, 'rootFolderId');
			if ($rootFolderElem->item(0)) {
				$this->rootFolder = trim($rootFolderElem->item(0)->nodeValue);
			}

			$capabilities = $doc->getElementsByTagNameNS($cmis, 'capabilities');
			if ($capabilities->item(0)) {
				foreach ($capabilities->item(0)->childNodes as $capNode) {
					if ($capNode->nodeName == '#text') {
						continue;
					}
					/* @var $capNode DomElement */
					$this->capabilities[str_replace('cmis:', '', $capNode->nodeName)] = $capNode->nodeValue;
				}
			}

			$collectionElems = $doc->getElementsByTagName('collection');
			foreach ($collectionElems as $collectionElem) {
				$aNs = $cmis;
				if ((float) $version >= 0.62) {
					$aNs = $cmisra;
				}

				$collectionType = $collectionElem->getAttributeNS($aNs, 'collectionType');
				if (!$collectionType) {
					$collectionTypeElems = $collectionElem->getElementsByTagNameNS($cmisra, 'collectionType');
					$collectionType = $collectionTypeElems->item(0)->nodeValue;
				}

				// protected against duplicate collections for now... there can be two urls for the
				// 'down' link, and we only want the 'children' one for now as it has more info
				// returned? 
				if ($collectionType && !isset($this->collections[$collectionType])) {
					$this->collections[$collectionType] = $collectionElem->getAttribute('href');
				}
			}

			$uriTemplates = $doc->getElementsByTagNameNS($cmisra, 'uritemplate');
			if ($uriTemplates) {
				foreach ($uriTemplates as $uriTemplate) {
					$templateElem = $uriTemplate->getElementsByTagNameNS($cmisra, 'template');
					$templateNameElem = $uriTemplate->getElementsByTagNameNS($cmisra, 'type');

					$this->uritemplates[$templateNameElem->item(0)->nodeValue] = $templateElem->item(0)->nodeValue;
				}
			}

			unset($doc);
		}
	}

	/**
	 * Gets the ID of the root folder of the remote system
	 *
	 * @return String
	 *			The ID of the remote system's root folder
	 */
	public function getRootFolderId()
	{
		return $this->rootFolder;
	}


	
	public function getCapability($name)
	{
		return isset($this->capabilities[$name]) ? $this->capabilities[$name] : null;
	}
	
	public function getCollection($name)
	{
		$val = isset($this->collections[$name]) ? $this->collections[$name] : null;
		return $val;
	}

	/**
	 *
	 * Gets a specific URI template if it exists.
	 *
	 * @param String $name
	 *			The name of the URI template to get
	 * @return String
	 *			The URI template for a given type
	 */
	public function getUriTemplate($name)
	{
		$val = isset($this->uritemplates[$name]) ? $this->uritemplates[$name] : null;
		// handle old URI templates... not nice, but eh.
		if ($name == 'objectbyid' && !$val) {
			return $this->getUriTemplate('entrybyid');
		}

		return $val;
	}
}

class CMISRepositoryInfoHandler implements ReturnHandler
{
	public function handleReturn ($rawResponse) 
	{
		return new SeaMistRepositoryInfo(trim($rawResponse));
	}
}
?>