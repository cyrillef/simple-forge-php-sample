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

session_start();

include_once './vendor/autoload.php';
include_once './server/constants.php';
include_once './server/config.php';
include_once './server/2legged.php';
include_once './server/2legged-oauth.php';
include_once './server/3legged.php';
include_once './server/3legged-oauth.php';
include_once './server/dm.php';
include_once './server/oss.php';

use Klein\Klein;
use Autodesk\ForgeServices\AccessToken2Legged;
use Autodesk\ForgeServices\AccessToken3Legged;
use Autodesk\ForgeServices\OSS;
use Autodesk\ForgeServices\DM;

$klein = new Klein();

//---------------------------- 2 Legged ----------------------------

// Get the 2legged access token
$klein->respond('GET', '/api/forge/public/2legged', function () {
	$accessToken = new AccessToken2Legged();
	return $accessToken->getAccessToken();
});

// Get 1 default urn under OSS which requires a 2legged token
$klein->respond('GET', '/api/forge/public/2urn', function () {
	// object_id = urn:adsk.objects:os.object:cyrille-models/rac_basic_sample_project.rvt
	// urn => dXJuOmFkc2sub2JqZWN0czpvcy5vYmplY3Q6Y3lyaWxsZS1tb2RlbHMvcmFjX2Jhc2ljX3NhbXBsZV9wcm9qZWN0LnJ2dA
	return json_encode([
		'urn' => 'dXJuOmFkc2sub2JqZWN0czpvcy5vYmplY3Q6Y3lyaWxsZS1tb2RlbHMvcmFjX2Jhc2ljX3NhbXBsZV9wcm9qZWN0LnJ2dA'
	]);
});

// Get a list of files in OSS (this time using the private/internal 2legged token)
$klein->respond ('GET', '/api/forge/public/2urn-list', function () {
	$oss = new OSS ();
	$list = $oss->listObjects(-1);
	return json_encode($list);
});

//---------------------------- 3 Legged ----------------------------

// Get the 3legged access token
$klein->respond('GET', '/api/forge/public/3legged', function () {
	$accessToken = new AccessToken3Legged();
	return $accessToken->getAccessToken();
});

// Get 1 default model urn which requires a 3legged token
$klein->respond('GET', '/api/forge/public/3urn', function () {
	return json_encode([

		// My default BIM360 model
		// hub_id = b.a4f95080-84fe-4281-8d0a-bd8c885695e0
		// project_id = b.dd31c918-027a-4a29-9946-ec292facdf7a
		// folder_id = urn:adsk.wipprod:fs.folder:co.QSOHk5y8RoKk9_bt4PYibg
		// item_id = urn:adsk.wipprod:dm.lineage:-TV6-JSsTxmcKq0IvfN6_w
		// version_id = urn:adsk.wipprod:fs.file:vf.-TV6-JSsTxmcKq0IvfN6_w?version=1
		// urn => dXJuOmFkc2sud2lwcHJvZDpmcy5maWxlOnZmLi1UVjYtSlNzVHhtY0txMEl2Zk42X3c_dmVyc2lvbj0x
		'urn' => 'dXJuOmFkc2sud2lwcHJvZDpmcy5maWxlOnZmLi1UVjYtSlNzVHhtY0txMEl2Zk42X3c_dmVyc2lvbj0x'
		
		// My default Fusion team model
		// hub_id = a.YnVzaW5lc3M6ZnJlZTM4ODc
		// project_id = a.YnVzaW5lc3M6ZnJlZTM4ODcjMjAyMDA3MTUzMTU4OTMyODE
		// folder_id = urn:adsk.wipprod:fs.folder:co.CZJD4srGS-2LBdIsvTPObA
		// item_id = urn:adsk.wipprod:dm.lineage:BMPQ4UMpR9GSmgmTvedlIg
		// version_id = urn:adsk.wipprod:fs.file:vf.BMPQ4UMpR9GSmgmTvedlIg?version=1
		// urn => dXJuOmFkc2sud2lwcHJvZDpmcy5maWxlOnZmLkJNUFE0VU1wUjlHU21nbVR2ZWRsSWc_dmVyc2lvbj0x
		//'urn' => 'dXJuOmFkc2sud2lwcHJvZDpmcy5maWxlOnZmLkJNUFE0VU1wUjlHU21nbVR2ZWRsSWc_dmVyc2lvbj0x'

	]);
});

// Get a list of files from a folder in a Hub/Project (this time using the private/internal 3legged token)
$klein->respond ('GET', '/api/forge/public/3urn-list', function () {
	$dm = new DM ();

	// BIM360 example
	// hub_id = b.a4f95080-84fe-4281-8d0a-bd8c885695e0
	// project_id = b.dd31c918-027a-4a29-9946-ec292facdf7a
	// folder_id = urn:adsk.wipprod:fs.folder:co.QSOHk5y8RoKk9_bt4PYibg
	$project_id = 'b.dd31c918-027a-4a29-9946-ec292facdf7a';
	$folder_id = 'urn:adsk.wipprod:fs.folder:co.QSOHk5y8RoKk9_bt4PYibg';
	
	// Fusion Team example
	// hub_id = a.YnVzaW5lc3M6ZnJlZTM4ODc
	// project_id = a.YnVzaW5lc3M6ZnJlZTM4ODcjMjAyMDA3MTUzMTU4OTMyODE
	// folder_id = urn:adsk.wipprod:fs.folder:co.CZJD4srGS-2LBdIsvTPObA
	// $project_id = 'a.YnVzaW5lc3M6ZnJlZTM4ODcjMjAyMDA3MTUzMTU4OTMyODE';
	// $folder_id = 'urn:adsk.wipprod:fs.folder:co.CZJD4srGS-2LBdIsvTPObA';

	// Use endpoint '/api/forge/public/3hubs-tree' to get your own IDs

	$list = $dm->listObjects($project_id, $folder_id);

	return json_encode($list);
});

// Build a HUB/Project/Folder/Item/Version tree (this time using the private/internal 3legged token)
$klein->respond ('GET', '/api/forge/public/3hubs-tree', function ($request) {
	// hub_id / project_id / folder_id item_id / version_id
	$dm = new DM ();
	if ( is_null ($request->paramsGet()['hub_id']) )
		return json_encode($dm->hubsLs());
	else if ( !is_null ($request->paramsGet()['hub_id']) && !is_null ($request->paramsGet()['project_id']) )
		return json_encode($dm->projectsRoots($request->paramsGet()['hub_id'], $request->paramsGet()['project_id']));
	else
		return json_encode (null);
});

// Forge 3legged callback
$klein->respond('GET', '/login', function ($request, $response) {
	$oauth = new AccessToken3Legged();
	$url = $oauth->authorizeUrl();
	return $response->redirect($url);
});

//$klein->respond('GET', '/api/forge/oauth/callback', function () {
$klein->respond('GET', '/callback', function ($request, $response) {
	$oauth = new AccessToken3Legged();
	$oauth->fetchTokens($request->paramsGet()['code']);
	return $response->redirect('/www/view.html');
});

//---------------------------- Test ----------------------------

$klein->respond('GET', '/test', function () {
	$t0 = time();
	sleep(3);
	$t1 = time();
	return json_encode([ 'msg' => "$t0 -> $t1" ]);
});

$klein->dispatch();

?>
