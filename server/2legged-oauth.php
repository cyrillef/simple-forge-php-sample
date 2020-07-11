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

use Doctrine\DBAL\DriverManager;
use Autodesk\Auth\Configuration;
use Autodesk\Auth\OAuth2\TwoLeggedAuth;

// You should not store tokens in a cookie, that would expose your data
// it is acceptable to expose the public token because the viewables:read scope is very limited
// at least it sould be a secure cookie if you do so

// What is important here is that we do not mind much about concurrency calls since when we
// create a new 2legged token, we do not invalidate older token. If we are unlucky, and 2 HTTP
// request comes in at the same time, the PHP code below will continue to work with one or the
// other token. And when using Sessions, you got 1 token for each session, whereas in MySQL
// you could decide to share the token accross all user sessions. Doing this is fine only 
// if you work within the same Forge Application which is usually the case but not always.
// It is all different for 3legged! Checkout the 3legged-oauth.php file.

// We should also not share the tokens to eveyone, or share our client and secret keys. For this
// reason, we will generate 2 tokens; the public token being very restrictive.

// Showing 2 technics PHP Session and MySQL (choose one one, by uncommenting 1 or the 2 lines below)
define('TOKENS_STORAGE_2', 'PHPSession');
//define('TOKENS_STORAGE_2', 'MySQL');

define('_2leggedPublic', '2leggedPublic');
define('_2leggedInternal', '2leggedInternal');
define('AccessToken2Public', 'AccessToken2Public');
define('ExpiresTime2Public', 'ExpiresTime2Public');
define('AccessToken2Internal', 'AccessToken2Internal');
define('ExpiresTime2Internal', 'ExpiresTime2Internal');

// -- CREATE TABLE "tokens" ---------------------------------------
// CREATE TABLE `tokens`( 
// 	`type` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
// 	`token` Text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
// 	`expirestime` BigInt( 20 ) NOT NULL,
// 	`refresh` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
// 	`session` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
// 	CONSTRAINT `type` UNIQUE( `type` ) )
// CHARACTER SET = utf8
// COLLATE = utf8_general_ci
// ENGINE = InnoDB;
// -- -------------------------------------------------------------

class AuthClientTwoLegged {
	private $twoLeggedAuthPublic = null;
	private $twoLeggedAuthInternal = null;
	private $conn = null;

	public function __construct () {
		set_time_limit(0);
		Configuration::getDefaultConfiguration()
			->setClientId(ForgeConfig::getForgeID())
			->setClientSecret(ForgeConfig::getForgeSecret())
			->setRedirectUrl(ForgeConfig::getForgeCallback());

		if ( TOKENS_STORAGE_2 !== PHPSession ) {
			$secret = ForgeConfig::getMySQLPWD();

			$this->conn = DriverManager::getConnection([
				'dbname' => 'zphp',
    			'user' => 'root',
    			'password' => ForgeConfig::getMySQLPWD(),
				'host' => 'localhost',
				'driver' => 'mysqli',
			]);
			// or 'url' => 'mysql://user:secret@localhost/mydb',
		}
	}    

	public function getTokenPublic () {
		return ( TOKENS_STORAGE_2 === PHPSession ?
			$this->getTokenPublicSession ()
			: $this->getTokenPublicMysql ()
		);
	}

	public function getTokenPublicSession () {
		if ( !isset($_SESSION[AccessToken2Public]) || $_SESSION[ExpiresTime2Public] < time() ) {
			$this->twoLeggedAuthPublic = new TwoLeggedAuth();
			$this->twoLeggedAuthPublic->setScopes(ForgeConfig::getScopePublic());
			$this->twoLeggedAuthPublic->fetchToken();
			$_SESSION[AccessToken2Public] = $this->twoLeggedAuthPublic->getAccessToken();
			$_SESSION[ExpiresTime2Public] = time() + $this->twoLeggedAuthPublic->getExpiresIn() - 120; // minus 2min
		}
		return [
			'access_token' => $_SESSION[AccessToken2Public],
			'expires_in' => $_SESSION[ExpiresTime2Public] - time(),
		];
	}

	public function getTokenPublicMysql () {
		// $queryBuilder = $this->conn->createQueryBuilder();
		// $queryBuilder
		// 	->select('token', 'expirestime')
		// 	->from('tokens')
		// 	->where('type = ?')
		// 	->setParameter(0, _2leggedPublic);

		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, _2leggedPublic);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 || $all[0]['expirestime'] < time() ) {
			$this->twoLeggedAuthPublic = new TwoLeggedAuth();
			$this->twoLeggedAuthPublic->setScopes(ForgeConfig::getScopePublic());
			$this->twoLeggedAuthPublic->fetchToken();
			$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`) VALUES (?, ?, ?)";
			if ( count($all) > 0 )
				$sql = "UPDATE `tokens` SET `token` = ?, `expirestime` = ? WHERE `type` = ?";
			$stmt = $this->conn->prepare($sql);
			$stmt->bindValue(1, $this->twoLeggedAuthPublic->getAccessToken());
			$stmt->bindValue(2, time() + $this->twoLeggedAuthPublic->getExpiresIn() - 120); // minus 2min
			$stmt->bindValue(3, _2leggedPublic);
			$stmt->execute();
			return [
				'access_token' => $this->twoLeggedAuthPublic->getAccessToken(),
				'expires_in' => $this->twoLeggedAuthPublic->getExpiresIn(),
			];
		}
		return [
			'access_token' => $all[0]['token'],
			'expires_in' => $all[0]['expirestime'] - time(),
		];
	}

	public function getTokenInternal () {
		return ( TOKENS_STORAGE_2 === PHPSession ?
			$this->getTokenInternalSession ()
			: $this->getTokenInternalMysql ()
		);
	}

	public function getTokenInternalSession () {
		$this->twoLeggedAuthInternal = new TwoLeggedAuth();
		$this->twoLeggedAuthInternal->setScopes(ForgeConfig::getScopeInternal2());

		if ( !isset($_SESSION[AccessToken2Internal]) || $_SESSION[ExpiresTime2Internal] < time() ) {
			$this->twoLeggedAuthInternal->fetchToken();
			$_SESSION[AccessToken2Internal] = $this->twoLeggedAuthInternal->getAccessToken();
			$_SESSION[ExpiresTime2Internal] = time() + $this->twoLeggedAuthInternal->getExpiresIn() - 120; // minus 2min 
		}

		$this->twoLeggedAuthInternal->setAccessToken($_SESSION[AccessToken2Internal]);
		return $this->twoLeggedAuthInternal;  
	}

	public function getTokenInternalMysql () {
		$this->twoLeggedAuthInternal = new TwoLeggedAuth();
		$this->twoLeggedAuthInternal->setScopes(ForgeConfig::getScopeInternal2());

		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, _2leggedInternal);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 || $all[0]['expirestime'] < time() ) {
			$this->twoLeggedAuthInternal->fetchToken();
			$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`) VALUES (?, ?, ?)";
			if ( count($all) > 0 )
				$sql = "UPDATE `tokens` SET `token` = ?, `expirestime` = ? WHERE `type` = ?";
			$stmt = $this->conn->prepare($sql);
			$stmt->bindValue(1, $this->twoLeggedAuthInternal->getAccessToken());
			$stmt->bindValue(2, time() + $this->twoLeggedAuthInternal->getExpiresIn() - 120); // minus 2min
			$stmt->bindValue(3, _2leggedInternal);
			$stmt->execute();
			$this->twoLeggedAuthInternal->setAccessToken($this->twoLeggedAuthInternal->getAccessToken());
		} else {
			$this->twoLeggedAuthInternal->setAccessToken($all[0]['token']);
		}
		return $this->twoLeggedAuthInternal; 
	}

}

$twoLeggedAuth = new AuthClientTwoLegged();

?>
