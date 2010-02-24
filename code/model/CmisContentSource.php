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
 * Retrieves content from an Cmis server
 * 
 * Uses the CMIS APIs to retrieve data
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class CmisContentSource extends ExternalContentSource implements ExternalContentRepositoryProvider
{
	public static $db = array(
		'RepositoryType' => "Enum('Alfresco,KnowledgeTree','Alfresco')",
		// 'RepositoryType' => "Enum('Alfresco','Alfresco')",
		'RepositoryInfoUrl' => 'Varchar(255)',
		'RootNodeUrl' => 'Varchar(255)',
		'Username' => 'Text',
		'Password' => 'Text',
	);

	public static $icon = array("cmis-connector/images/cmis", "folder");

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		$fields->addFieldToTab('Root.Main', new DropdownField('RepositoryType', _t('CmisContentSource.REPO_TYPE', 'Repository Type'), array('Alfresco' => 'Alfresco', 'KnowledgeTree' => 'KnowledgeTree')));

		$fields->addFieldToTab('Root.Main', new TextField('RepositoryInfoUrl', _t('CmisContentSource.REPO_INFO_URL', 'Repository Information URL')));
		$fields->addFieldToTab('Root.Main', new TextField('RootNodeUrl', _t('CmisContentSource.ROOT_NODE_URL', 'Root Node URL (Optional)')));

		$fields->addFieldToTab('Root.Main', new TextField('Username', _t('CmisContentSource.USER', 'Username')));
		$fields->addFieldToTab('Root.Main', new PasswordField('Password', _t('CmisContentSource.PASS', 'Password')));

		return $fields;
	}

	/**
	 * Return an CMIS content importer
	 * 
	 * @see external-content/code/dataobjects/ExternalContentSource#getContentImporter()
	 */
	public function getContentImporter($target=null)
	{
		return new CmisImporter();
	}
	
	/**
	 * Cmis content can only be imported into
	 * the file tree for now. 
	 * 
	 * @see external-content/code/dataobjects/ExternalContentSource#allowedImportTargets()
	 */
	public function allowedImportTargets()
	{
		return array('file' => true);
	}
	
	/**
	 * Get the Cmis seamistrepository connector.
	 * 
	 * This is used by this object directly, but also 
	 * via CmisContentItems via composition
	 * 
	 * @return SeaMistRepository
	 */
	public function getRemoteRepository()
	{
		// For the first batch, just get all the immediate children of the
		// top level 
		if (!$this->RepositoryType) {
			return null;
		}

		$repo = SeaMist::getInstance()->getRepository($this->RepositoryType, $this->ID);

		if (!$repo->isConnected() && $this->RepositoryInfoUrl) {
			try {
				$repo->connect($this->RepositoryInfoUrl, $this->RootNodeUrl, $this->Username, $this->Password);
			} catch (Zend_Uri_Exception $zue) {
				error_log("Failed connecting to repository: ".$zue->getMessage()."\n".SS_Backtrace::backtrace(true));
			} catch (FailedRequestException $fre) {
				error_log("Failed request: ". $fre->getMessage());
			}
		}

		return $repo;
	}
	
	/**
	 * Whenever we save the content source, we want to disconnect 
	 * the repository so that it reconnects with whatever new connection
	 * details are provided
	 * 
	 * @see sapphire/core/model/DataObject#onBeforeWrite()
	 */
	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		$repo = $this->getRemoteRepository();
		if ($repo) {
			$repo->disconnect();
		}
	}
	
	/**
	 * Get the object represented by ID
	 * 
	 * @param String $objectId
	 * @return DataObject
	 */
	public function getObject($objectId)
	{
		// get the object from the repository
		$repo = $this->getRemoteRepository();
		$item = null;
		if ($repo->isConnected()) {
			// convert ';' characters back to / characters
			$objectId = $this->decodeId($objectId);
			$obj = $repo->getObject($objectId);
			if ($obj) {
				$item = new CmisContentItem($this, $obj);
			}
		}

		return $item;
	}

	/**
	 * Gets the root Cmis repository
	 * 
	 * @see external-content/code/model/ExternalContentSource#getRoot()
	 */
	public function getRoot()
	{
		$repo = $this->getRemoteRepository();
		if ($repo && $repo->isConnected()) {
			try {
				$root = $repo->getRepositoryRoot();
				$item = new CmisContentItem($this, $root);
				return $item;
			} catch (FailedRequestException $re) {
				error_log("Failed getting the repository root: ".$re->getMessage());
			} catch (Zend_Http_Client_Adapter_Exception $zc) {
				error_log("Zend client failed connecting: ".$zc->getMessage());
			}
		}
	}

	/**
	 * Override to fool hierarchy.php
	 * 
	 * @param boolean $showAll
	 * @return DataObjectSet
	 */
	public function stageChildren($showAll = false) {
		// if we don't have an ID directly, we should load and return ALL the external content sources
		if (!$this->ID) {
			return DataObject::get('CmisContentSource');
		}

		$children = new DataObjectSet();
		$root = $this->getRoot();
		if ($root) {
			// defer to the root node's children
			$children = $root->stageChildren();
		} 

		return $children;
	}
	
	/**
	 * Cmis ID encoding can be less aggressive
	 * @see external-content/code/model/ExternalContentSource#encodeId($id)
	 */
	public function encodeId($id)
	{
		return str_replace('/', ';', $id); 
	}
	
	/**
	 * Cmis ID encoding can be less aggressive
	 */
	public function decodeId($id)
	{
		return str_replace(';', '/', $id); 
	}
}
?>