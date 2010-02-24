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
 
class SeaMistObjectList extends ArrayObject
{
	/**
	 * The Raw XML for this list of objects
	 * @var String
	 */
	private $rawXml;
	
	function __construct($xml)
	{
		parent::__construct();
		
		if ($xml) {
			// first, lets find the cmis namespace to use
			$sx = new SimpleXMLElement($xml);
			$namespaces = $sx->getDocNamespaces();
			$ns = isset($namespaces['cmis']) ? $namespaces['cmis'] : 'http://docs.oasis-open.org/ns/cmis/core/200901';
	
			// go through the entries and create some objects
			Zend_Feed::registerNamespace('cmis', $ns); // 'http://www.cmis.org/2008/05');
			$this->rawXml = $xml;
			$feed = Zend_Feed::importString($this->rawXml);
	
			foreach ($feed as $entry) {
				$obj = new SeaMistObject();
				$obj->loadFromFeed($entry);
				$this[] = $obj;
			}
		}
	}
}


class CMISObjectListReturnHandler implements ReturnHandler
{

	public function handleReturn ($rawResponse) 
	{
		return new SeaMistObjectList(trim($rawResponse));
	}
}

?>