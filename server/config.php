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
use Dotenv\Dotenv;

class ForgeConfig {
	private static $forge_id = null;
	private static $forge_secret = null;
	private static $forge_callback = null;
	private static $secret = null;
	private static $bucket = null;
	public static $prepend_bucketkey = true; // toggle client ID prefix to avoid conflict with existing buckets

	public static function getForgeID() {
		$forge_id = getenv('FORGE_CLIENT_ID');
		if  (!$forge_id ) {
			// load the environment variable from .env into your application
			$dotenv = Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$forge_id = getenv('FORGE_CLIENT_ID');
		}
		return $forge_id;
	}

	public static function getForgeSecret() {
		$forge_secret = getenv('FORGE_CLIENT_SECRET');
		if ( !$forge_secret ) {
			// load the environment variable from .env into your application
			$dotenv = Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$forge_secret = getenv('FORGE_CLIENT_SECRET');
		}
		return $forge_secret;
	}

	public static function getForgeCallback() {
		$forge_callback = getenv('FORGE_CALLBACK');
		if ( !$forge_callback ) {
			// load the environment variable from .env into your application
			$dotenv = Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$forge_callback = getenv('FORGE_CALLBACK');
		}
		return $forge_callback;
	}

	// Required scopes for your application on server-side
	public static function getScopeInternal2(){
		return ['viewables:read', 'bucket:create', 'bucket:read', 'data:read', 'data:create', 'data:write'];
	}

	public static function getScopeInternal3(){
		return ['viewables:read', 'data:read', 'data:create', 'data:write'];
	}

	// Required scope of the token sent to the client
	public static function getScopePublic(){
		return ['viewables:read'];
	}

	public static function getMySQLPWD() {
		$secret = getenv('MYSQL_PWD');
		if  ( !$secret ) {
			// load the environment variable from .env into your application
			$dotenv = Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$secret = getenv('MYSQL_PWD');
		}
		return $secret;
	}

	public static function getBucket() {
		$bucket = getenv('BUCKET');
		if  ( !$bucket ) {
			// load the environment variable from .env into your application
			$dotenv = Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$bucket = getenv('BUCKET');
		}
		return $bucket;
	}

}

?>