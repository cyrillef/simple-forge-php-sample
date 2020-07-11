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
use Autodesk\Forge\Client\Api\ObjectsApi;

class OSS {
	private $bucketId = '';

	public function __construct () {
		set_time_limit(0);

		// $dotenv = Dotenv::createImmutable(__DIR__);
		// $dotenv->load();
		// $this->bucketId = ForgeConfig::getBucket();
		$this->bucketId = ForgeConfig::getBucket();
	}

	public function ListObjects ($_limit = 10) {
		global $twoLeggedAuth;
		try {
			$limit = $_limit === -1 ? 100 : $limit;
			$accessToken = $twoLeggedAuth->getTokenInternal();

			// get the request body
			//$body = json_decode(file_get_contents('php://input', 'r'), true);

			$apiInstance = new ObjectsApi($accessToken);
			// $bucketId, $limit = null, $begins_with = null, $start_at = null
			$startAt = null;
			$objectlist = [];
			do {
				$result = $apiInstance->getObjects($this->bucketId, $limit, null, $startAt);
				$startAt = null;
				$result = json_decode($result, true);
				if ( isset($result['next']) ) {
					$next = parse_url ($result['next']);
					parse_str ($next['query'], $output);
					//list($startAt, $limit) = $output;
					extract ($output);
				}
				$objects = $result['items'];

				$objectsLength = count($objects);
				for ( $i =0; $i < $objectsLength; $i++ ) {
					$objectInfo = [
						'id' => str_replace(
								['+', '/', '='],
								['-', '_', ''],
								base64_encode($objects[$i]['objectId'])),
						'text' => $objects[$i]['objectKey'],
					];
					array_push($objectlist, $objectInfo);
				}
				array_push($objectlist, $objectInfo);
			} while ( $startAt !== null && $_limit === -1 );
			return $objectlist;
		} catch(Exception $e) {
			echo 'Exception when calling ObjectsApi->getObjects: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}

}

?>
