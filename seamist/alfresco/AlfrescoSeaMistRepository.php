<?php

class AlfrescoSeaMistRepository extends AbstractSeaMistRepository {

	const AUTH_TICKET_PARAM = 'alf_ticket';

	/**
	 * The ticket used for authenticating with Alfresco
	 * 
	 * @var String
	 */
	protected $ticket;

	/**
	 * Has this repository connected?
	 * 
	 * @return boolean
	 */
	public function isConnected() {
		return $this->ticket != null && $this->baseUrl != null;
	}

	/**
	 * Clear any existing session details
	 */
	public function disconnect() {
		$this->ticket = null;
	}

	/**
	 * Hacky method to retrieve the API URL base for Alfresco
	 * based on the configured URLs. Needs to handle a few different possible
	 * structures 
	 * 
	 * @return String
	 */
	protected function getAlfrescoApiBase() {
		$urlStub = '';

		if (strpos($this->repoUrl, '/api')) {
			$urlStub = substr($this->repoUrl, 0, strpos($this->repoUrl, '/api'));
		} else if (strpos($this->repoUrl, '/s/')) {
			$urlStub = substr($this->repoUrl, 0, strpos($this->repoUrl, '/s/')) . '/s';
		} else if (strpos($this->repoUrl, '/service/')) {
			$urlStub = substr($this->repoUrl, 0, strpos($this->repoUrl, '/service/')) . '/service';
		} else if (strpos($this->repoUrl, '/cmis/')) {
			$urlStub = substr($this->repoUrl, 0, strpos($this->repoUrl, '/cmis/'));
		} else {
			$urlStub = $this->repoUrl;
		}

		return $urlStub;
	}

	/**
	 * Login to alfresco 
	 * 
	 * @param String $username
	 * @param String $password
	 */
	protected function login($username, $password) {
		$ticket = null;

		// figure out the login url based on the existing base url - for
		// alfresco, it's up to the first /api that we then backtrack
		$urlStub = $this->getAlfrescoApiBase() . '/api/login';

		try {
			// execute the login method and store the result in the session
			$ticket = $this->api->callUrl($urlStub, array('u' => $username, 'pw' => $password), 'xml');
		} catch (Zend_Http_Client_Adapter_Exception $ex) {
			// failboat
			error_log("Failed logging in with $username, $password: " . $ex->getMessage());
		}

		if ($ticket) {
			// different php versions treat SimpleXML objects differently;
			// the one in 5.1 will have ticket == string here, whereas 5.2+ 
			// has it as the first entry when referencing by array... go figure
			if (strlen((string) $ticket)) {
				$ticket = (string) $ticket;
			} else {
				$ticket = $ticket[0];
			}

			$this->setTicket($ticket);
		}
	}

	/**
	 * Append the ticket before streaming a URL
	 * 
	 * @param String $url
	 * @return String
	 */
	public function modifyStreamUrl($url) {
		return $url . '?alf_ticket=' . $this->ticket;
	}

	/**
	 * Set the ticket for this connection, along with the other
	 * information about the URL and root path that this ticket 
	 * is associated with
	 * 
	 * @param String $ticket
	 */
	protected function setTicket($ticket) {
		$this->ticket = $ticket;

		if ($this->api) {
			$this->api->setGlobalParam(self::AUTH_TICKET_PARAM, $this->ticket);
		}
	}

	/**
	 * Get a single object by slash separated path, hacked for now 
	 * until the proper CMIS query stuff is in place to query by object ID
	 * 
	 * @param String $path
	 * @return SeaMistObject
	 */
	public function getObject($id) {
		$pieces = $this->nodeRefToPieces($id);
		$url = $this->getAlfrescoApiBase() . '/api/node/' . $pieces['workspace'] . '/' . $pieces['store'] . '/' . $pieces['id'];
		$object = $this->api->callUrl($url, array(), 'cmisobject');
		return $object;
	}

	/**
	 * Convert a nodeRef from workspace://SpacesStore/uuid format into an array
	 * of array(
	 * 	'workspace' => 'workspace',
	 * 	'store' => 'SpacesStore',
	 * 	'id' => 'uuid'
	 * )
	 * @param unknown_type $id
	 * @return unknown_type
	 */
	protected function nodeRefToPieces($id) {
		$pieces = array(
			'workspace' => 'workspace',
			'store' => 'SpacesStore',
			'id' => $id
		);

		if (strpos($id, '://') !== false) {
			// split it up
			$bits = explode("://", $id);
			$pieces['workspace'] = $bits[0];
			$bits = explode('/', $bits[1]);
			$pieces['store'] = $bits[0];
			$pieces['id'] = $bits[1];
		}

		return $pieces;
	}

	/**
	 * Used only for testing... 
	 * 
	 * @return String
	 */
	public function getTicket() {
		return $this->ticket;
	}

}
