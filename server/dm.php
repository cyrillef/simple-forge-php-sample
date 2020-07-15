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
use Autodesk\Forge\Client\Api\HubsApi;
use Autodesk\Forge\Client\Api\ProjectsApi;
use Autodesk\Forge\Client\Api\FoldersApi;
use Autodesk\Forge\Client\Api\ItemsApi;
use Autodesk\Forge\Client\Api\VersionsAPI;

class DM {
	private $oauth = null;
	private $accessToken = null;
	
	public function __construct () {
		global $threeLeggedAuth;
		set_time_limit(0);
		$this->oauth = $threeLeggedAuth;
		$this->accessToken = $threeLeggedAuth->getTokenInternal();
	}

	public function tree ($hub_id, $project_id) {
		try {
			
			return $result;
		} catch(Exception $e) {
			echo 'Exception when building DM tree: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	public function hubsLs () {
		try {
			$hubapi = new HubsApi($this->accessToken);
			$hubs = $hubapi->getHubs();
			$hubs = $hubs['data'];

			$objectlist = [];
			foreach($hubs as $key => $hub) {
				$objectInfo = [
					'name' => $hub['attributes']['name'],
					'hub_id' => $hub['id'],
					'type' => $hub['attributes']['extension']['type'],
					'projects' => $this->projectsLs ($hub['id'])
				];
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when listing hubs: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	private function projectsLs ($hub_id) {
		try {
			$projectapi = new ProjectsApi($this->accessToken);
			$projects = $projectapi->getHubProjects($hub_id);
			$projects = $projects['data'];

			$objectlist = [];
			foreach($projects as $key => $project) {
				$objectInfo = [
					'name' => $project['attributes']['name'],
					'project_id' => $project['id'],
					//'folders' => $this->projectsRoots($hub_id, $project['id'])
				];
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when listing projects: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	public function projectsRoots ($hub_id, $project_id) {
		try {
			$projectapi = new ProjectsApi($this->accessToken);
			$root = $projectapi->getProjectTopFolders($hub_id, $project_id);
			$root = $root['data'];

			$objectlist = [];
			foreach($root as $key => $folder) {
				$objectInfo = [
					'name' => $folder['attributes']['name'],
					'folder_id' => $folder['id'],
					'content' => $this->foldersLs($project_id, $folder['id'])
				];
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when listing root folders: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	private function foldersLs ($project_id, $folder_id) {
		try {
			$folderapi = new FoldersApi($this->accessToken);
			$folders = $folderapi->getFolderContents($project_id, $folder_id);
			$folders = $folders['data'];

			$objectlist = [];
			foreach($folders as $key => $content) {
				$objectInfo = [
					'name' => $content['attributes']['name']
				];
				if ( $content['type'] === 'folders') {
					$objectInfo['folder_id'] = $content['id'];
					$objectInfo['folders'] = $this->foldersLs($project_id, $content['id']);
				} else {
					$objectInfo['item_id'] = $content['id'];
					$objectInfo['versions'] = $this->versionsLs($project_id, $content['id']);
					if ( $objectInfo['name'] === null && count($objectInfo['versions']) > 0 )
						$objectInfo['name'] = $objectInfo['versions'][0]['name'];
				}
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when listing folder content: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	private function versionsLs ($project_id, $item_id) {
		try {
			$itemapi = new ItemsApi($this->accessToken);
			$versions = $itemapi->getItemVersions($project_id, $item_id);
			$versions = $versions['data'];

			$objectlist = [];
			foreach($versions as $key => $version) {
				$objectInfo = [
					'name' => $version['attributes']['name'],
					'version_id' => $version['id']
				];
				array_push($objectlist, $objectInfo);
			}
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when listing versions: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

	public function ListObjects ($project_id, $folder_id) {
		try {
		
			if ( is_null($this->accessToken) )
				return [];
			$folderInstance = new FoldersApi($this->accessToken);
			$itemInstance = new ItemsApi($this->accessToken);
			
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
			return [];
		}
	}

}

?>
