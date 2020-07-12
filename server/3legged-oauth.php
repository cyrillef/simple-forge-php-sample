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
use Autodesk\Auth\OAuth2\ThreeLeggedAuth;

// You should not store tokens in a cookie, that would expose your data
// it is acceptable to expose the public token because the viewables:read scope is very limited
// at least it sould be a secure cookie if you do so

// What is important here is that we do not mind much about concurrency calls when using Sessions
// since when we create a new 3legged token, it is stored in the user Session. Whereas in MySQL 
// you cannot share the token accross all user sessions.

// We should also not share the tokens to eveyone, or share our client and secret keys. For this
// reason, we will generate 2 tokens; the public token being very restrictive.

// Showing 2 technics PHP Session and MySQL (choose one one, by uncommenting 1 or the 2 lines below)
//define('TOKENS_STORAGE_3', 'PHPSession');
define('TOKENS_STORAGE_3', 'MySQL');

// It is really not recommended to do one single 3legged login. This is a security risk as you
// you would not be able to track user activities since you share one set of credentials. It is 
// however acceptable for viewing only. Defaults to false.
define('SHARED_3LEGGED_TOKEN', false); // if false, each session needs to log a user

// When using PHP Sessions, you do not really have the choice on controlling the refresh sequences
// However, using semaphore only works on a single server, but when usually Apache/Nginx routes
// HTTP requests from a client to teh same server. This semaphore approach will then work for
// PHP session and mysql if when using mysql, you are not sharing the same tokens. DO not use the
// combinatin USE_SEMAPHORE=true and SHARED_3LEGGED_TOKEN=true ever on balancing servers.
// Defaults to false.
define('USE_SEMAPHORE', false); // if false, it uses mysql locks

define('_3leggedPublic', '3leggedPublic');
define('_3leggedInternal', '3leggedInternal');
define('AccessToken3Public', 'AccessToken3Public');
define('AccessToken3Internal', 'AccessToken3Internal');
define('ExpiresTime3', 'ExpiresTime3');
define('RefreshToken', 'RefreshToken');

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

class AuthClientThreeLegged {
	private $threeLeggedAuth = null;
	private $semaphore = null;
	private $publicKey = _3leggedPublic;
	private $internalKey = _3leggedInternal;
	private $conn = null;

	// We always have a pair (Public / Internal) tokens that we refresh at teh same time
	// We always refresh the public token last, so we should only use the Public refresh token.

	public function __construct () {
		set_time_limit(0);

		if ( USE_SEMAPHORE === true )
			$this->semaphore = sem_get(SemaphoreID, 1, 0666, 1);
			// 1- The number of processes that can acquire this semaphore
			// 1- Auto release the semaphore if the request shuts down

		if ( SHARED_3LEGGED_TOKEN === false ) {
			$this->publicKey .= session_id();
			$this->internalKey .= session_id();
		}
	
		Configuration::getDefaultConfiguration()
			->setClientId(ForgeConfig::getForgeID())
			->setClientSecret(ForgeConfig::getForgeSecret())
			->setRedirectUrl(ForgeConfig::getForgeCallback());

		if ( TOKENS_STORAGE_3 !== PHPSession ) {
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

	public function authorizeUrl () {
		$this->threeLeggedAuth = new ThreeLeggedAuth();
		$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
		return $this->threeLeggedAuth->createAuthUrl();
	}

	public function fetchTokens ($code) {
		try {
			if ( USE_SEMAPHORE === true )
				sem_acquire($this->semaphore);
			else
				$this->lockTokensTable();
			$this->threeLeggedAuth = new ThreeLeggedAuth();
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
			$this->threeLeggedAuth->fetchToken($code);
			$this->storeTokenInternal($this->threeLeggedAuth);
			// Immediatelly fetch a public token (downgrade scope)
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopePublic());
			$this->threeLeggedAuth->refreshToken($this->threeLeggedAuth->getRefreshToken());
			$this->storeTokenPublic($this->threeLeggedAuth);
			if ( USE_SEMAPHORE === true )
				sem_release($this->semaphore);
			else
				$this->unlockTokensTable();
		} catch (Throwable $e) { // PHP 7 compatibility
            echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
        } catch (Exception $e) {
			echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
		}
	}

	public function refreshTokens () {
		try {
			if ( USE_SEMAPHORE === true )
				sem_acquire($this->semaphore);
			else
				$this->lockTokensTable();
			$refreshToken = $this->getLastRefreshToken();
			// Start with the internal token, so we know we keep the public refresh token
			$this->threeLeggedAuth = new ThreeLeggedAuth();
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
			$this->threeLeggedAuth->refreshToken($refreshToken);
			$this->storeTokenInternal($this->threeLeggedAuth);
			// Immediatelly fetch a private token
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopePublic());
			$this->threeLeggedAuth->refreshToken($this->threeLeggedAuth->getRefreshToken());
			$this->storeTokenPublic($this->threeLeggedAuth);
			if ( USE_SEMAPHORE === true )
				sem_release($this->semaphore);
			else
				$this->unlockTokensTable();
		} catch (Throwable $e) { // PHP 7 compatibility
            echo 'Exception when calling AuthClientThreeLegged->refreshTokens: ', $e->getMessage(), PHP_EOL;
        } catch (Exception $e) {
			echo 'Exception when calling AuthClientThreeLegged->refreshTokens: ', $e->getMessage(), PHP_EOL;
		}
	}

	private function lockTokensTable () {
		$sql = "LOCK TABLES `tokens` WRITE";
		$this->conn->exec($sql);
	}

	private function unlockTokensTable () {
		$sql = "UNLOCK TABLES";
		$this->conn->exec($sql);
	}

	private function getLastRefreshToken () {
		if ( TOKENS_STORAGE_3 === PHPSession )
			return $_SESSION[RefreshToken];
		$sql = "SELECT `refresh` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, $this->publicKey);
		$stmt->execute();
		$all = $stmt->fetchAll();
		return $all[0]['refresh'];
	}

	private function storeTokenPublic ($oauth) {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->storeTokenPublicSession ($oauth)
			: $this->storeTokenPublicMysql ($oauth)
		);
	}

	private function storeTokenPublicSession ($oauth) {
		$_SESSION[AccessToken3Public] = $oauth->getAccessToken();
		$_SESSION[ExpiresTime3] = time() + $oauth->getExpiresIn() - 120; // minus 2min
		$_SESSION[RefreshToken] = $oauth->getRefreshToken();
	}

	public function storeTokenPublicMysql ($oauth) {
		$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`, `refresh`, `session`) VALUES (?, ?, ?, ?, ?)"
			. " ON DUPLICATE KEY UPDATE `token` = ?, `expirestime` = ?, `refresh` = ?, `session` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, $oauth->getAccessToken());
		$stmt->bindValue(2, time() + $oauth->getExpiresIn() - 120); // minus 2min
		$stmt->bindValue(3, $this->publicKey);
		$stmt->bindValue(4, $oauth->getRefreshToken());
		$stmt->bindValue(5, session_id());

		$stmt->bindValue(6, $oauth->getAccessToken());
		$stmt->bindValue(7, time() + $oauth->getExpiresIn() - 120); // minus 2min
		$stmt->bindValue(8, $oauth->getRefreshToken());
		$stmt->bindValue(9, session_id());
		
		$stmt->execute();
	}

	public function getTokenPublic () {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->getTokenPublicSession ()
			: $this->getTokenPublicMysql ()
		);
	}

	public function getTokenPublicSession () {
		if ( !isset($_SESSION[AccessToken3Public]) || $_SESSION[ExpiresTime3] < time() )
			$this->refreshTokens();
		return [
			'access_token' => $_SESSION[AccessToken3Public],
			'expires_in' => $_SESSION[ExpiresTime3] - time(),
		];
	}

	public function getTokenPublicMysql () {
		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, $this->publicKey);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 )
			return [];
		if ( $all[0]['expirestime'] < time() ) {
			$this->refreshTokens();
			$stmt->execute();
			$all = $stmt->fetchAll();
		}
		return [
			'access_token' => $all[0]['token'],
			'expires_in' => $all[0]['expirestime'] - time(),
		];
	}

	private function storeTokenInternal ($oauth) {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->storeTokenInternalSession ($oauth)
			: $this->storeTokenInternalMysql ($oauth)
		);
	}

	private function storeTokenInternalSession ($oauth) {
		$_SESSION[AccessToken3Internal] = $oauth->getAccessToken();
		$_SESSION[ExpiresTime3] = time() + $oauth->getExpiresIn() - 120; // minus 2min
		$_SESSION[RefreshToken] = $oauth->getRefreshToken();
	}

	public function storeTokenInternalMysql ($oauth) {
		$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`, `refresh`, `session`) VALUES (?, ?, ?, ?, ?)"
			. " ON DUPLICATE KEY UPDATE `token` = ?, `expirestime` = ?, `refresh` = ?, `session` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, $oauth->getAccessToken());
		$stmt->bindValue(2, time() + $oauth->getExpiresIn() - 120); // minus 2min
		$stmt->bindValue(3, $this->internalKey);
		$stmt->bindValue(4, $oauth->getRefreshToken());
		$stmt->bindValue(5, session_id());

		$stmt->bindValue(6, $oauth->getAccessToken());
		$stmt->bindValue(7, time() + $oauth->getExpiresIn() - 120); // minus 2min
		$stmt->bindValue(8, $oauth->getRefreshToken());
		$stmt->bindValue(9, session_id());
		
		$stmt->execute();
	}

	public function getTokenInternal () {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->getTokenInternalSession ()
			: $this->getTokenInternalMysql ()
		);
	}

	public function getTokenInternalSession () {
		if ( !isset($_SESSION[AccessToken3Internal]) || $_SESSION[ExpiresTime3] < time() )
			$this->refreshTokens();
		$this->threeLeggedAuth = new ThreeLeggedAuth();
		$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
		$this->threeLeggedAuth->setAccessToken($_SESSION[AccessToken3Internal]);
		return $this->threeLeggedAuth;
	}

	public function getTokenInternalMysql () {
		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, $this->internalKey);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 )
			return null;
		if ( $all[0]['expirestime'] < time() ) {
			$this->refreshTokens();
			$stmt->execute();
			$all = $stmt->fetchAll();
		}
		$this->threeLeggedAuth = new ThreeLeggedAuth();
		$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
		$this->threeLeggedAuth->setAccessToken($all[0]['token']);
		return $this->threeLeggedAuth;
	}

}

$threeLeggedAuth = new AuthClientThreeLegged();

?>
