<?php

/**
 * Provides a wrapper around a CMIS Object returned in Atom XML form
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class SeaMistObject {

	private static $prop_names = array('propertyString', 'propertyNumber', 'propertyInteger', 'propertyId', 'propertyDateTime', 'propertyUri', 'propertyBoolean');

	/**
	 * The URI to retrieve the object
	 * 
	 * @var String
	 */
	private $url;

	/**
	 * The list of links this object is aware of (eg self, children, content etc)
	 * @var unknown_type
	 */
	private $links = array();

	/**
	 * Content src if provided
	 * 
	 * @var String
	 */
	private $contentUrl;

	/**
	 * A Map of all this object's properties
	 * 
	 * @var array
	 */
	private $properties = array();

	/**
	 * Has this item been modified?
	 * 
	 * @var boolean
	 */
	private $dirty = false;

	/**
	 * The raw XML for this object
	 * 
	 * @var String
	 */
	private $rawXml;

	public function __construct($xml = null) {
		if ($xml) {
			// first, lets find the cmis namespace to use
			$sx = new SimpleXMLElement($xml);
			$namespaces = $sx->getDocNamespaces();
			$ns = isset($namespaces['cmis']) ? $namespaces['cmis'] : 'http://docs.oasis-open.org/ns/cmis/core/200901';

			// go through the XML and pull out the things we're interested in
			Zend_Feed::registerNamespace('cmis', $ns); // 'http://www.cmis.org/2008/05');
			$this->rawXml = $xml;
			$feed = Zend_Feed::importString($this->rawXml);

			foreach ($feed as $item) {
				$this->loadFromFeed($item);
			}
		}
	}

	/**
	 * Load from an Atom feed entry 
	 * 
	 * @param Zend_Feed_Abstract $item
	 */
	public function loadFromFeed($item) {
		// see if there's a content element, use that for the url
		if ($item->content) {
			if ($item->content['src']) {
				$this->contentUrl = $item->content['src'];
			}
			if ($item->content['href']) {
				$this->contentUrl = $item->content['href'];
			}
		}

		// otherwise search for a link rel=self tag	
		if ($this->url == null) {
			foreach ($item->link as $link) {
				$linkName = $link['rel'];
				// account for alfresco v3.0's cmis- prepending
				if (strpos($linkName, 'cmis-') === 0) {
					$linkName = substr($linkName, 5);
				}
				if ($linkName) {
					$this->links[$linkName] = $link['href'];
				}
			}
		}

		// see what version it is to bel oading
		$this->loadProperties($item);
	}

	/**
	 * Load content from a feed that maps to version .6 or greater
	 * 
	 * @param $item
	 * 			Zend_Feed_Entry
	 */
	protected function loadProperties($item) {
		// for now, just store as straight strings
		// TODO: Map to correct object types if needbe
		foreach (self::$prop_names as $propFieldName) {
			$props = $item->object->properties->{'cmis:' . $propFieldName};
			if (is_array($props)) {
				foreach ($item->object->properties->{'cmis:' . $propFieldName} as $prop) {
					$propName = $prop['cmis:name'];
					if (!$propName)
						$propName = $prop['propertyDefinitionId'];
					// this ugly bit of stuff means we can handle older versions better
					$propName = lcfirst(str_replace('cmis:', '', $propName));
					$this->properties[$propName] = $prop->value();
				}
			} else {
				// assuming here that there was a single one that we can call
				// value() on directly
				if ($props instanceof Zend_Feed_Element) {
					$propName = $prop['cmis:name'];
					if (!$propName)
						$propName = $prop['propertyDefinitionId'];
					$propName = lcfirst(str_replace('cmis:', '', $propName));
					$this->properties[$propName] = $prop->value();
				}
			}
		}

	}

	/**
	 * Return the URL directly to the XML for this object
	 * 
	 * @return String
	 */
	public function getUrl() {
		return $this->getLink('url');
	}

	/**
	 * Get a named link
	 * 
	 * @param $name
	 * 			The name of the link
	 * 
	 * @return String
	 */
	public function getLink($name) {
		return isset($this->links[$name]) ? $this->links[$name] : '';
	}

	/**
	 * Return the URL used to download this object's content stream
	 * 
	 * @return String
	 */
	public function getContentUrl() {
		return $this->contentUrl;
	}

	/**
	 * Indicates whether this object is a document or not
	 * 
	 * @return boolean
	 */
	public function isDocument() {
		return $this->contentUrl != null;
	}

	/**
	 * Get the raw XML for this object
	 * 
	 * @return String
	 */
	public function getXml() {
		return $this->rawXml;
	}

	/**
	 * Get a property value
	 * 
	 * @param string $prop
	 * @return mixed
	 */
	public function __get($prop) {
		return $this->getProperty($prop);
	}

	/**
	 * Retrieve a property value
	 * 
	 * @param String $name
	 * @return mixed
	 */
	public function getProperty($name) {
		$v = isset($this->properties[$name]) ? $this->properties[$name] : null;

		// account for old names
		if (!$v) {
			$name = ucfirst($name);
			$v = isset($this->properties[$name]) ? $this->properties[$name] : null;
		}

		return $v;
	}

	/**
	 * Does this object have the given property?
	 * 
	 * @param String $name
	 * @return boolean
	 */
	public function hasProperty($name) {
		return isset($this->properties[$name]);
	}

	/**
	 * Set a property to a particular value
	 * 
	 * Flags the object as being dirty, helps with the saving process...
	 * 
	 * @param String $name
	 * @param mixed $value
	 */
	public function setProperty($name, $value) {
		$this->properties[$name] = $value;
		$this->dirty = true;
	}

	/**
	 * Get all the properties of this object so we can iterate over them
	 * 
	 * @return array
	 */
	public function getProperties() {
		return $this->properties;
	}

}

class CMISObjectReturnHandler implements ReturnHandler {

	public function handleReturn($rawResponse) {
		return new SeaMistObject(trim($rawResponse));
	}

}

?>