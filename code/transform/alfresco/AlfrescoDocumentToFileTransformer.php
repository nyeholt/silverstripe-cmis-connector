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
 * Transform a standard page asset
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class AlfrescoDocumentToFileTransformer implements ExternalContentTransformer
{
	public function transform($item, $parentObject, $duplicateStrategy)
	{
		// no children of docs
		$pageChildren = new DataObjectSet();
		
		// okay, first we'll create the new page item, 
		// and map a bunch of child information across
		$newPage = new File();
		
		// write the content item to a location on disk
		$filename = $item->Name;
		$folderPath = $parentObject->getRelativePath();
		
		// see if there's an existing one, and what we should do if there is
		$parentId = $parentObject ? $parentObject->ID : 0;

		// Use the title, because SS sometimes converts filenames
		$filter = 'ParentID = \''.Convert::raw2sql($parentId).'\' and Title = \''.Convert::raw2sql($filename).'\'';

		$existing = DataObject::get_one('File', $filter);

		if ($existing && $duplicateStrategy == ExternalContentTransformer::DS_SKIP) {
			// just return the existing children
			return new TransformResult($existing, $pageChildren);
		} else if ($existing && $duplicateStrategy == ExternalContentTransformer::DS_OVERWRITE) {
			$newPage = $existing;
		}

		$folderPath = trim(substr($folderPath, strpos($folderPath, '/')), '/');

		// write it to a cache directory first
		$path = $this->getCacheDir();
		$item->streamContent($path.'/'.$filename);

		$size = filesize($path.'/'.$filename);

		$details = array(
			'size' => $size,
			'name' => $item->Name,
			'tmp_name' => $path.'/'.$filename
		);

		$newPage->Name = $item->Name;
		$newPage->Title = $item->Title;
		$newPage->MenuTitle = $item->MenuTitle;

		// what else should we map across?
		// $newPage->MatrixId = $item->id;
		// $newPage->OriginalProperties = serialize($item->getRemoteProperties());
		$upload = new FileLoader();
		$upload->loadIntoFile($details, $newPage, $folderPath);

		return new TransformResult($newPage, $pageChildren);
	}

	private function getCacheDir()
	{
		$path = ASSETS_PATH.'/.alfresco_cache';
		if (!is_dir($path)) {
			mkdir($path, Filesystem::$folder_create_mask);
		}

		return $path;
	}
}
?>