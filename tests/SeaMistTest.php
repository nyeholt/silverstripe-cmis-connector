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
 * Tests the SeaMist project
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * 
 */
class SeaMistTest extends SapphireTest
{
	private $config = array(
		'repo' => 'http://localhost:8080/alfresco/s/api/cmis',
		'username' => 'admin',
		'password' => 'admin',
	);
	
	public function testGetSeaMist()
	{
		$seaMist = SeaMist::getInstance();
		$this->assertNotNull($seaMist);
	}
	
	public function testGetAlfrescoImplementation()
	{
		$seaMist = SeaMist::getInstance();
		$this->assertNotNull($seaMist);
		
		$repo = $seaMist->getRepository('Alfresco');
		
		$this->assertNotNull($repo);
		
		$this->assertTrue($repo instanceof SeaMistRepository);
		$this->assertTrue($repo instanceof AlfrescoSeaMistRepository);
	}
	
	public function testAlfrescoLogin()
	{
		$api = $this->getMock('WebApiClient', array('callMethod'), array('nourl'));
		$api->expects($this->once())
			->method('callUrl')
			->will($this->returnValue(new SimpleXMLElement('<ticket>THISISATICKET</ticket>')));
			
		$repo = new AlfrescoSeaMistRepository($api);

		$repo->connect($this->config['repo'], '', $this->config['username'], $this->config['password']);
		$this->assertEquals('THISISATICKET', $repo->getTicket());
	}

	public function testCMISObjectCreate()
	{
		$xml = getSingleEntry();
		$obj = new SeaMistObject($xml);
		$this->assertEquals('CMISOBJECTID', $obj->ObjectId);
		$this->assertEquals($obj->getLink('children'), 'http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/children');
		$this->assertEquals(1.1, $obj->Number);
		$this->assertEquals(1, $obj->Integer);
	}

	public function testCMISObjectListCreate()
	{
		$xml = getMultipleEntries();
		$obj = new SeaMistObjectList($xml);
		$this->assertEquals(4, $obj->count());
	}

	public function testGetRepository()
	{
		$repo = new AlfrescoSeaMistRepository();
		$repo->connect($this->config['repo'], '', $this->config['username'], $this->config['password']);
		if (!$repo->isConnected()) {
			// return so we don't fail... it just means the repo isn't there
			return;
		}
		$this->assertNotNull($repo->getTicket());
		
		// call getRepositoryInfo 
		$info = $repo->getRepositoryInfo();
	}
	
	public function getRepositoryRoot()
	{
		// get the root node of the repository
		$repo = new AlfrescoSeaMistRepository();
		$repo->connect($this->config['repo'], '', $this->config['username'], $this->config['password']);
		if (!$repo->isConnected()) {
			// return so we don't fail... it just means the repo isn't there
			return;
		}
		$this->assertNotNull($repo->getTicket());
		
		// call getRepositoryInfo 
		$root = $repo->getRepositoryRoot();

		$this->assertEquals('Company Home', $root->Name);
		$this->assertEquals('folder', $root->ObjectType);
		
		// make sure its URL is set as expected (to the SELF item)
	}
	
	
	public function testGetRepositoryChildren()
	{
		$repo = new AlfrescoSeaMistRepository();
		$repo->connect($this->config['repo'], '', $this->config['username'], $this->config['password']);
		if (!$repo->isConnected()) {
			// return so we don't fail... it just means the repo isn't there
			return;
		}

		$this->assertNotNull($repo->getTicket());

		// call getRepositoryInfo 
		$root = $repo->getRepositoryRoot();
		
		// get all of its children
	}
	
	public function testParseRootXml()
	{
		$repo = new SeaMistRepositoryInfo(getRepositoryXml('061-repository-alfresco.xml'));
		$this->assertEquals('http://localhost:8080/alfresco/s/api/path/workspace/SpacesStore/Company%20Home/children', $repo->getCollection('rootchildren'));

		$repo = new SeaMistRepositoryInfo(getRepositoryXml('061c-repository-knowledgetree.xml'));
		$this->assertEquals('http://kt.localhost/webservice/atompub/cmis/?dms/folder/Root%20Folder/children/', $repo->getCollection('rootchildren'));

		$repo = new SeaMistRepositoryInfo(getRepositoryXml('1.0d4-repository-alfresco.xml'));
		$this->assertEquals('http://localhost:8080/alfresco/s/cmis/s/workspace:SpacesStore/i/d0d41482-303c-4144-af02-f5acfb651917/children', $repo->getCollection('root'));
		
		// 0.62 doesn't have rootdescendants?
		$repo = new SeaMistRepositoryInfo(getRepositoryXml('062-repository-nuxeo.xml'));
		$this->assertEquals('http://localhost:8080/nuxeo/site/cmis/descendants/0da750e8-4235-46ec-aafc-7787f866f2bd', $repo->getCollection('rootdescendants'));
	}

	public function testGetUriTemplate()
	{
		$repo = new SeaMistRepositoryInfo(getRepositoryXml('1.0d4-repository-alfresco.xml'));
		$this->assertEquals('http://localhost:8080/alfresco/s/cmis/arg/n?noderef={id}&filter={filter}&includeAllowableActions={includeAllowableActions}&includePolicyIds={includePolicyIds}&includeRelationships={includeRelationships}&includeACL={includeACL}&renditionFilter={renditionFilter}', $repo->getUriTemplate('objectbyid'));
	}
}

class DummySuccessfulReturn
{
	public function isSuccessful()
	{
		return true;
	}

	public function getBody()
	{
		return 'DUMMYBODY';
	}
}

function getRepositoryXml($file)
{
	return file_get_contents(dirname(__FILE__).'/'.$file);
}

function getSingleEntry()
{
	$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app" xmlns:cmis="http://docs.oasis-open.org/ns/cmis/core/200901" xmlns:alf="http://www.alfresco.org">
<author><name>System</name></author>
<content>d0d41482-303c-4144-af02-f5acfb651917</content>
<id>urn:uuid:d0d41482-303c-4144-af02-f5acfb651917</id>
<link rel="self" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917"/>
<link rel="edit" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917"/>
<link rel="allowableactions" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/permissions"/>
<link rel="relationships" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/rels"/>
<link rel="children" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/children"/>
<link rel="descendants" href="http://localhost:8080/alfresco/wcs/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/descendants"/>
<link rel="type" href="http://localhost:8080/alfresco/wcs/api/type/folder"/>
<link rel="repository" href="http://localhost:8080/alfresco/wcs/api/repository"/>
<published>2009-10-16T16:12:40.307+11:00</published>
<summary>The company root space</summary>
<title>Company Home</title>
<updated>2009-10-16T16:12:40.439+11:00</updated>
<cmis:object>
<cmis:properties>
<cmis:propertyString cmis:name="BaseType"><cmis:value>folder</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="Name"><cmis:value>Company Home</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ParentId"/>
<cmis:propertyNumber cmis:name="Number"><cmis:value>1.1</cmis:value></cmis:propertyNumber>
<cmis:propertyNumber cmis:name="OtherNumber"><cmis:value>2.1</cmis:value></cmis:propertyNumber>
<cmis:propertyInteger cmis:name="Integer"><cmis:value>1</cmis:value></cmis:propertyInteger>
<cmis:propertyDateTime cmis:name="LastModificationDate"><cmis:value>2009-10-16T16:12:40.439+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyUri cmis:name="Uri"/>
<cmis:propertyId cmis:name="AllowedChildObjectTypeIds"/>
<cmis:propertyString cmis:name="CreatedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyDateTime cmis:name="CreationDate"><cmis:value>2009-10-16T16:12:40.307+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="ChangeToken"/>
<cmis:propertyString cmis:name="LastModifiedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ObjectTypeId"><cmis:value>folder</cmis:value></cmis:propertyId>
<cmis:propertyId cmis:name="ObjectId"><cmis:value>CMISOBJECTID</cmis:value></cmis:propertyId>
</cmis:properties>
</cmis:object>
<cmis:terminator/>
<app:edited>2009-10-16T16:12:40.439+11:00</app:edited>
<alf:icon>http://localhost:8080/alfresco/images/icons/space-icon-default-16.gif</alf:icon>
</entry>

XML;
	return trim($xml);
}

function getMultipleEntries()
{
	$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app" xmlns:cmis="http://docs.oasis-open.org/ns/cmis/core/200901" xmlns:alf="http://www.alfresco.org" xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/">
<author><name>System</name></author>
<generator version="3.2.0 (r 2384)">Alfresco (Community)</generator>
<icon>http://localhost:8080/alfresco/images/logo/AlfrescoLogo16.ico</icon>
<id>urn:uuid:d0d41482-303c-4144-af02-f5acfb651917-children</id>
<link rel="self" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/children?alf_ticket=TICKET_4534528b82f946a96c2854fd400c818064641e6d"/>
<link rel="source" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917"/>
<link rel="first" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/children?alf_ticket=TICKET_4534528b82f946a96c2854fd400c818064641e6d&amp;pageNo=1&amp;pageSize=0&amp;guest=&amp;format=atomfeed" type="application/atom+xml;type=feed"/>
<link rel="last" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/d0d41482-303c-4144-af02-f5acfb651917/children?alf_ticket=TICKET_4534528b82f946a96c2854fd400c818064641e6d&amp;pageNo=1&amp;pageSize=0&amp;guest=&amp;format=atomfeed" type="application/atom+xml;type=feed"/>
<title>Company Home Children</title>
<updated>2009-10-16T16:12:40.439+11:00</updated>
<entry>
<author><name>System</name></author>
<content>710dec98-f564-41fc-8d43-4b63ae96da3f</content>
<id>urn:uuid:710dec98-f564-41fc-8d43-4b63ae96da3f</id>
<link rel="self" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f"/>
<link rel="edit" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f"/>
<link rel="allowableactions" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f/permissions"/>
<link rel="relationships" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f/rels"/>
<link rel="parents" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f/parent"/>
<link rel="children" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f/children"/>
<link rel="descendants" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f/descendants"/>
<link rel="type" href="http://localhost:8080/alfresco/s/api/type/F/st_sites"/>
<link rel="repository" href="http://localhost:8080/alfresco/s/api/repository"/>
<published>2009-10-16T16:12:54.048+11:00</published>
<summary>Site Collaboration Spaces</summary>
<title>Sites</title>
<updated>2009-10-16T16:12:54.067+11:00</updated>
<cmis:object>
<cmis:properties>
<cmis:propertyString cmis:name="BaseType"><cmis:value>folder</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="Name"><cmis:value>Sites</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ParentId"><cmis:value>workspace://SpacesStore/d0d41482-303c-4144-af02-f5acfb651917</cmis:value></cmis:propertyId>
<cmis:propertyUri cmis:name="Uri"/>
<cmis:propertyDateTime cmis:name="LastModificationDate"><cmis:value>2009-10-16T16:12:54.067+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="CreatedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="AllowedChildObjectTypeIds"/>
<cmis:propertyDateTime cmis:name="CreationDate"><cmis:value>2009-10-16T16:12:54.048+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="LastModifiedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="ChangeToken"/>
<cmis:propertyId cmis:name="ObjectTypeId"><cmis:value>F/st_sites</cmis:value></cmis:propertyId>
<cmis:propertyId cmis:name="ObjectId"><cmis:value>workspace://SpacesStore/710dec98-f564-41fc-8d43-4b63ae96da3f</cmis:value></cmis:propertyId>
</cmis:properties>
</cmis:object>
<cmis:terminator/>
<app:edited>2009-10-16T16:12:54.067+11:00</app:edited>
<alf:icon>http://localhost:8080/alfresco/images/icons/space-icon-default-16.gif</alf:icon>
</entry>
<entry>
<author><name>System</name></author>
<content>b7d39e16-6f77-4666-848d-fffd10c0b4a5</content>
<id>urn:uuid:b7d39e16-6f77-4666-848d-fffd10c0b4a5</id>
<link rel="self" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5"/>
<link rel="edit" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5"/>
<link rel="allowableactions" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5/permissions"/>
<link rel="relationships" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5/rels"/>
<link rel="parents" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5/parent"/>
<link rel="children" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5/children"/>
<link rel="descendants" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5/descendants"/>
<link rel="type" href="http://localhost:8080/alfresco/s/api/type/folder"/>
<link rel="repository" href="http://localhost:8080/alfresco/s/api/repository"/>
<published>2009-10-16T16:12:40.499+11:00</published>
<summary>User managed definitions</summary>
<title>Data Dictionary</title>
<updated>2009-10-16T16:12:40.538+11:00</updated>
<cmis:object>
<cmis:properties>
<cmis:propertyString cmis:name="BaseType"><cmis:value>folder</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="Name"><cmis:value>Data Dictionary</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ParentId"><cmis:value>workspace://SpacesStore/d0d41482-303c-4144-af02-f5acfb651917</cmis:value></cmis:propertyId>
<cmis:propertyDateTime cmis:name="LastModificationDate"><cmis:value>2009-10-16T16:12:40.538+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyUri cmis:name="Uri"/>
<cmis:propertyId cmis:name="AllowedChildObjectTypeIds"/>
<cmis:propertyString cmis:name="CreatedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyDateTime cmis:name="CreationDate"><cmis:value>2009-10-16T16:12:40.499+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="ChangeToken"/>
<cmis:propertyString cmis:name="LastModifiedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ObjectTypeId"><cmis:value>folder</cmis:value></cmis:propertyId>
<cmis:propertyId cmis:name="ObjectId"><cmis:value>workspace://SpacesStore/b7d39e16-6f77-4666-848d-fffd10c0b4a5</cmis:value></cmis:propertyId>
</cmis:properties>
</cmis:object>
<cmis:terminator/>
<app:edited>2009-10-16T16:12:40.538+11:00</app:edited>
<alf:icon>http://localhost:8080/alfresco/images/icons/space-icon-default-16.gif</alf:icon>
</entry>
<entry>
<author><name>System</name></author>
<content>0697ddeb-ee75-40ee-86f1-a73bdbe767d4</content>
<id>urn:uuid:0697ddeb-ee75-40ee-86f1-a73bdbe767d4</id>
<link rel="self" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4"/>
<link rel="edit" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4"/>
<link rel="allowableactions" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4/permissions"/>
<link rel="relationships" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4/rels"/>
<link rel="parents" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4/parent"/>
<link rel="children" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4/children"/>
<link rel="descendants" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4/descendants"/>
<link rel="type" href="http://localhost:8080/alfresco/s/api/type/folder"/>
<link rel="repository" href="http://localhost:8080/alfresco/s/api/repository"/>
<published>2009-10-16T16:12:41.156+11:00</published>
<summary>The guest root space</summary>
<title>Guest Home</title>
<updated>2009-10-16T16:12:41.174+11:00</updated>
<cmis:object>
<cmis:properties>
<cmis:propertyString cmis:name="BaseType"><cmis:value>folder</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="Name"><cmis:value>Guest Home</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ParentId"><cmis:value>workspace://SpacesStore/d0d41482-303c-4144-af02-f5acfb651917</cmis:value></cmis:propertyId>
<cmis:propertyDateTime cmis:name="LastModificationDate"><cmis:value>2009-10-16T16:12:41.174+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyUri cmis:name="Uri"/>
<cmis:propertyId cmis:name="AllowedChildObjectTypeIds"/>
<cmis:propertyString cmis:name="CreatedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyDateTime cmis:name="CreationDate"><cmis:value>2009-10-16T16:12:41.156+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="ChangeToken"/>
<cmis:propertyString cmis:name="LastModifiedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ObjectTypeId"><cmis:value>folder</cmis:value></cmis:propertyId>
<cmis:propertyId cmis:name="ObjectId"><cmis:value>workspace://SpacesStore/0697ddeb-ee75-40ee-86f1-a73bdbe767d4</cmis:value></cmis:propertyId>
</cmis:properties>
</cmis:object>
<cmis:terminator/>
<app:edited>2009-10-16T16:12:41.174+11:00</app:edited>
<alf:icon>http://localhost:8080/alfresco/images/icons/space-icon-default-16.gif</alf:icon>
</entry>
<entry>
<author><name>System</name></author>
<content>5e921c06-fd3b-4e7f-aa08-667ff0385579</content>
<id>urn:uuid:5e921c06-fd3b-4e7f-aa08-667ff0385579</id>
<link rel="self" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579"/>
<link rel="edit" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579"/>
<link rel="allowableactions" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579/permissions"/>
<link rel="relationships" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579/rels"/>
<link rel="parents" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579/parent"/>
<link rel="children" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579/children"/>
<link rel="descendants" href="http://localhost:8080/alfresco/s/api/node/workspace/SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579/descendants"/>
<link rel="type" href="http://localhost:8080/alfresco/s/api/type/folder"/>
<link rel="repository" href="http://localhost:8080/alfresco/s/api/repository"/>
<published>2009-10-16T16:12:41.249+11:00</published>
<summary>User Homes</summary>
<title>User Homes</title>
<updated>2009-10-16T16:12:41.279+11:00</updated>
<cmis:object>
<cmis:properties>
<cmis:propertyString cmis:name="BaseType"><cmis:value>folder</cmis:value></cmis:propertyString>
<cmis:propertyString cmis:name="Name"><cmis:value>User Homes</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ParentId"><cmis:value>workspace://SpacesStore/d0d41482-303c-4144-af02-f5acfb651917</cmis:value></cmis:propertyId>
<cmis:propertyDateTime cmis:name="LastModificationDate"><cmis:value>2009-10-16T16:12:41.279+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyUri cmis:name="Uri"/>
<cmis:propertyId cmis:name="AllowedChildObjectTypeIds"/>
<cmis:propertyString cmis:name="CreatedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyDateTime cmis:name="CreationDate"><cmis:value>2009-10-16T16:12:41.249+11:00</cmis:value></cmis:propertyDateTime>
<cmis:propertyString cmis:name="ChangeToken"/>
<cmis:propertyString cmis:name="LastModifiedBy"><cmis:value>System</cmis:value></cmis:propertyString>
<cmis:propertyId cmis:name="ObjectTypeId"><cmis:value>folder</cmis:value></cmis:propertyId>
<cmis:propertyId cmis:name="ObjectId"><cmis:value>workspace://SpacesStore/5e921c06-fd3b-4e7f-aa08-667ff0385579</cmis:value></cmis:propertyId>
</cmis:properties>
</cmis:object>
<cmis:terminator/>
<app:edited>2009-10-16T16:12:41.279+11:00</app:edited>
<alf:icon>http://localhost:8080/alfresco/images/icons/space-icon-default-16.gif</alf:icon>
</entry>
<cmis:hasMoreItems>false</cmis:hasMoreItems>
<opensearch:totalResults>4</opensearch:totalResults>
<opensearch:startIndex>0</opensearch:startIndex>
<opensearch:itemsPerPage>0</opensearch:itemsPerPage>
</feed>
XML;
	return trim($xml);
}

?>