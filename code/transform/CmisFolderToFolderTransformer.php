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
 * Transform a folder asset
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class CmisFolderToFolderTransformer implements ExternalContentTransformer
{
	public function transform($item, $parentObject, $duplicateStrategy)
	{
		$pageChildren = $item->stageChildren();

		// okay, first we'll create the new page item, 
		// and map a bunch of child information across
		$newFolder = new Folder();
		
		$parentId = $parentObject ? $parentObject->ID : 0;
		$existing = DataObject::get_one('File', '"ParentID" = \''.Convert::raw2sql($parentId).'\' and "Name" = \''.Convert::raw2sql($item->Title).'\'');
		if ($existing && $duplicateStrategy == ExternalContentTransformer::DS_SKIP) {
			// just return the existing children
			return new TransformResult($existing, $pageChildren);
		} else if ($existing && $duplicateStrategy == ExternalContentTransformer::DS_OVERWRITE) {
			$newFolder = $existing;
		}

		$newFolder->Name = $item->Title;
		$newFolder->Title = $item->Title;
		$newFolder->MenuTitle = $item->MenuTitle;

		// what else should we map across?
		// $newPage->MatrixId = $item->id;
		// $newPage->OriginalProperties = serialize($item->getRemoteProperties());

		$newFolder->ParentID = $parentObject->ID;
		$newFolder->Sort = 0;
		$newFolder->write();
		
		if(!file_exists($newFolder->getFullPath())) {
			mkdir($newFolder->getFullPath(),Filesystem::$folder_create_mask);
		}
		return new TransformResult($newFolder, $pageChildren);
	}
}
?>