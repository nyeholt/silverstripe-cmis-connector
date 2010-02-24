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
 * CmisContentItem that uses the SeaMist
 * connector to be retrieve information about this object
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class CmisContentItem extends ExternalContentItem
{
	public static $icon = array("cmis-connector/images/cmis-item", "folder");

	/**
	 * The cmis object wrapper
	 * 
	 * @var unknown_type
	 */
	protected $cmisObject;

	/**
	 * On creation, bind to the cmisobj if provided
	 * 
	 * @param ExternalContentSource $source
	 * 					Where this item was loaded from
	 * @param SeaMistObject $cmisObj
	 * 					A seamist object representing the CMIS data
	 */
	public function __construct($source=null, $cmisObj=null)
	{
		if (is_object($cmisObj)) {
			$this->cmisObject = $cmisObj;
			$this->Title = $this->MenuTitle = $cmisObj->name;
		}

		parent::__construct($source, is_object($cmisObj) ? $cmisObj->objectId : $cmisObj);
	}

	/**
	 * Return the asset type
	 * @see external-content/code/model/ExternalContentItem#getType()
	 */
	public function getType()
	{
		$type = $this->baseType;
		if ($type) {
			return $type;
		}

		return str_replace('cmis:', '', $this->baseTypeId);
	}
	
	/**
	 * Overridden to pass the content through as its downloaded (if it's not cached locally)
	 * 
	 * We call the specific repository implementation to stream this content 
	 * however it would like to
	 */
	public function streamContent($toFile='')
	{
		$contentUrl = $this->cmisObject->getContentUrl();
		// $contentUrl = $this->cmisObject->getLink('stream');
		if (!$contentUrl) {
			throw new Exception("Cannot stream a folder's content");
		}

		$repo = $this->source->getRemoteRepository();
		// Maybe this should actually call the object to stream itself... will think about it
		$repo->streamObject($this->cmisObject, $toFile);
	}


	/**
	 * Overridden to load all children from Cmis instead of this node
	 * directly
	 * 
	 * @param boolean $showAll
	 * @return DataObjectSet
	 */
	public function stageChildren($showAll = false) {
		if (!$this->ID) {
			return DataObject::get('CmisContentSource');
		}

		$repo = $this->source->getRemoteRepository();
		$children = new DataObjectSet();
		if ($repo->isConnected()) {
			if(isset($_GET['debug_profile'])) Profiler::mark("CmisContentItem", "getChildren");
			$childItems = $repo->getChildren($this->cmisObject);
			if ($childItems) {
				foreach ($childItems as $child) {
					$item = new CmisContentItem($this->source, $child);
					$children->push($item);
				}
			}
			if(isset($_GET['debug_profile'])) Profiler::unmark("CmisContentItem", "getChildren");
		}

		return $children;
	}
	
	/**
	 * Check the object type; if it's a Document, return 0, otherwise 
	 * return one as we don't know whether this type has children or not
	 * 
	 * @return int
	 */
	public function numChildren()
	{
		if ($this->cmisObject) {
			if ($this->getType() == 'document') {
				return 0;
			}
		}

		// if it's not a document, then lets return an arbitrary number
		return 1;
	}

	/**
	 * Set a property value
	 * 
	 * @see sapphire/core/ViewableData#__set($property, $value)
	 */
	public function __set($prop, $val)
	{
		// see if the cmis object has this property. If so, 
		// set it
		if ($this->cmisObject && $this->cmisObject->getProperty($prop)) {
			$this->cmisObject->$prop = $val;
		} else {
			parent::__set($prop, $val);
		}
	}

	/**
	 * Return from the parent object if it's not in here...
	 * 
	 * @see sapphire/core/ViewableData#__get($property)
	 */
	function __get($prop)
	{
		
		$val = $this->cmisObject ? $this->cmisObject->getProperty($prop) : null;
		
		// added to handle the change to lowercase first property names for v1.0 of cmis
		if (!$val) {
			$val = $this->cmisObject ? $this->cmisObject->getProperty(lcfirst($prop)) : null;
		}

		if (!$val) {
			$val = parent::__get($prop);
			
			if (!$val) {
				if ($this->source) {
					// get it from there
					return $this->source->$prop;
				}
			}
		}

		return $val;		
	}
	
	/**
	 * Override to let remote objects figure out whether they have a 
	 * field or not
	 * 
	 * @see sapphire/core/model/DataObject#hasField($field)
	 */
	public function hasField($field) 
	{
		$existing = parent::hasField($field); 
		// $val = $this->__get($field);
		// return !empty($val);
		return $existing || ($this->cmisObject ? $this->cmisObject->hasProperty($field) : false);
	}


	/**
	 * Create an iterable list of properties to display to end users
	 * 
	 * @see sapphire/core/model/DataObject#getCMSFields($params)
	 */
	public function getCMSFields()
	{
		$fields = new FieldSet(
			new TabSet("Root",
				new Tab('Details',
					new LiteralField("ExternalContentItem_Alert", _t('ExternalContent.REMOTE_ITEM', 'This is a remote content item and therefore cannot be edited')),
					new LiteralField("ExternalContentItem_LINK", _t('ExternalContent.LINK', '<a target="_blank" href="'.$this->cmisObject->getUrl().'">View this item</a>'))
				)
			)
		);

		if ($this->cmisObject) {
			$props = $this->cmisObject->getProperties();
			foreach ($props as $name => $value) {
				$fields->addFieldToTab('Root.Details', new ReadonlyField($name, _t('CmisContentItem.'.$name, $name), $value));
			}
		}

		return $fields;
	}
}

?>