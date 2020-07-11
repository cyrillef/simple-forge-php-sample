<?php
//
// Copyright (c) Autodesk, Inc. All rights reserved
//
// Permission to use, copy, modify, and distribute this software in
// object code form for any purpose and without fee is hereby granted,
// provided that the above copyright notice appears in all copies and
// that both that copyright notice and the limited warranty and
// restricted rights notice below appear in all supporting
// documentation.
//
// AUTODESK PROVIDES THIS PROGRAM 'AS IS' AND WITH ALL FAULTS.
// AUTODESK SPECIFICALLY DISCLAIMS ANY IMPLIED WARRANTY OF
// MERCHANTABILITY OR FITNESS FOR A PARTICULAR USE.  AUTODESK, INC.
// DOES NOT WARRANT THAT THE OPERATION OF THE PROGRAM WILL BE
// UNINTERRUPTED OR ERROR FREE.
//

namespace Autodesk\ForgeServices;
use Autodesk\Forge\Client\Api\FoldersApi;
use Autodesk\Forge\Client\Api\ItemsApi;

class BIM360 {

	public function __construct () {
		set_time_limit(0);
	}

	public function ListObjects ($project_id, $folder_id) {
		global $threeLeggedAuth;
		try {
			$accessToken = $threeLeggedAuth->getTokenInternal();

			$folderInstance = new FoldersApi($accessToken);
			$itemInstance = new ItemsApi($accessToken);
			
			$result = $folderInstance->getFolderContents(
				$project_id, $folder_id,
				/*$filter_type =*/ [ 'items' ],
				/*$filter_id =*/ null, /*$filter_extension_type =*/ null,
				/*$page_number =*/ null, /*$page_limit =*/ null
			);
			$result = json_decode($result, true);
			$objects = $result['data'];
			
			$objectlist = [];
			$objectsLength = count($objects);
			for ( $i =0; $i < $objectsLength; $i++ ) {
				$obj = $objects[$i];
				// if ( $obj ['type'] !== 'items' )
				// 	continue;
				$item_id = $obj['id'];
				// $result = $itemInstance->getItem($project_id, $item_id);
				// $result = json_decode($result, true);
				$result = $itemInstance->getItemVersions($project_id, $item_id);
				$result = json_decode($result, true);
				$objectInfo = [
					'id' => str_replace(
						['+', '/', '='],
						['-', '_', ''],
						base64_encode($result['data'][0]['id'])),
					'text' => $result['data'][0]['attributes']['name'],
				];
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when calling FolderApi->getFolderContents: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

}

?>
