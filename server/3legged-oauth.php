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
define('TOKENS_STORAGE_3', 'PHPSession');
//define('TOKENS_STORAGE_3', 'MySQL');

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
// 	`session` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL )
// CHARACTER SET = utf8
// COLLATE = utf8_general_ci
// ENGINE = InnoDB;
// -- -------------------------------------------------------------

class AuthClientThreeLegged {
	private $threeLeggedAuth = null;
	private $conn = null;

	public function __construct () {
		set_time_limit(0);
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
			$this->threeLeggedAuth = new ThreeLeggedAuth();
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
			$this->threeLeggedAuth->fetchToken($code);
			$this->storeTokenInternal($this->threeLeggedAuth);
			// Immediatelly fetch a public token (downgrade scope)
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopePublic());
			$this->threeLeggedAuth->refreshToken($this->threeLeggedAuth->getRefreshToken());
			$this->storeTokenPublic($this->threeLeggedAuth);
		} catch (Throwable $e) { // PHP 7 compatibility
            echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
        } catch (Exception $e) {
			echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
		}
	}

	public function refreshTokens ($refreshToken) {
		try {
			// Start with the public token, so we know we keep the internal refresh token
			$this->threeLeggedAuth = new ThreeLeggedAuth();
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopePublic());
			$this->threeLeggedAuth->refreshToken($refreshToken);
			$this->storeTokenPublic($this->threeLeggedAuth);
			// Immediatelly fetch a private token
			$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
			$this->threeLeggedAuth->refreshToken($this->threeLeggedAuth->getRefreshToken());
			$this->storeTokenInternal($this->threeLeggedAuth);
		} catch (Throwable $e) { // PHP 7 compatibility
            echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
        } catch (Exception $e) {
			echo 'Exception when calling AuthClientThreeLegged->fetchTokens: ', $e->getMessage(), PHP_EOL;
		}
	}

	private function storeTokenPublic ($oauth) {
		$_SESSION[AccessToken3Public] = $oauth->getAccessToken();
		$_SESSION[ExpiresTime3] = time() + $oauth->getExpiresIn() - 120; // minus 2min
		$_SESSION[RefreshToken] = $oauth->getRefreshToken();
	}

	public function getTokenPublic () {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->getTokenPublicSession ()
			: $this->getTokenPublicMysql ()
		);
	}

	public function getTokenPublicSession () {
		if ( !isset($_SESSION[AccessToken3Public]) || $_SESSION[ExpiresTime3] < time() )
			$this->refreshTokens($_SESSION[RefreshToken]);
		return [
			'access_token' => $_SESSION[AccessToken3Public],
			'expires_in' => $_SESSION[ExpiresTime3] - time(),
		];
	}

	public function getTokenPublicMysql () {
		// $queryBuilder = $this->conn->createQueryBuilder();
		// $queryBuilder
		// 	->select('token', 'expirestime')
		// 	->from('tokens')
		// 	->where('type = ?')
		// 	->setParameter(0, _3leggedPublic);

		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, _3leggedPublic);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 || $all[0]['expirestime'] < time() ) {
			$this->threeLeggedAuthPublic = new ThreeLeggedAuth();
			$this->threeLeggedAuthPublic->setScopes(ForgeConfig::getScopePublic());
			$this->threeLeggedAuthPublic->fetchToken();
			$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`) VALUES (?, ?, ?)";
			if ( count($all) > 0 )
				$sql = "UPDATE `tokens` SET `token` = ?, `expirestime` = ? WHERE `type` = ?";
			$stmt = $this->conn->prepare($sql);
			$stmt->bindValue(1, $this->threeLeggedAuthPublic->getAccessToken());
			$stmt->bindValue(2, time() + $this->threeLeggedAuthPublic->getExpiresIn() - 120); // minus 2min
			$stmt->bindValue(3, _3leggedPublic);
			$stmt->execute();
			return [
				'access_token' => $this->threeLeggedAuthPublic->getAccessToken(),
				'expires_in' => $this->threeLeggedAuthPublic->getExpiresIn(),
			];
		}
		return array(
			'access_token' => $all[0]['token'],
			'expires_in' => $all[0]['expirestime'] - time(),
		);
	}

	private function storeTokenInternal ($oauth) {
		$_SESSION[AccessToken3Internal] = $oauth->getAccessToken();
		$_SESSION[ExpiresTime3] = time() + $oauth->getExpiresIn() - 120; // minus 2min
		$_SESSION[RefreshToken] = $oauth->getRefreshToken();
	}

	public function getTokenInternal () {
		return ( TOKENS_STORAGE_3 === PHPSession ?
			$this->getTokenInternalSession ()
			: $this->getTokenInternalMysql ()
		);
	}

	public function getTokenInternalSession () {
		if ( !isset($_SESSION[AccessToken3Internal]) || $_SESSION[ExpiresTime3] < time() )
			$this->refreshTokens($_SESSION[RefreshToken]);
		$this->threeLeggedAuth = new ThreeLeggedAuth();
		$this->threeLeggedAuth->setScopes(ForgeConfig::getScopeInternal3());
		$this->threeLeggedAuth->setAccessToken($_SESSION[AccessToken3Internal]);
		return $this->threeLeggedAuth;  
	}

	public function getTokenInternalMysql () {
		$this->threeLeggedAuthInternal = new ThreeLeggedAuth();
		$this->threeLeggedAuthInternal->setScopes(ForgeConfig::getScopeInternal3());

		$sql = "SELECT `token`, `expirestime` FROM `tokens` WHERE `type` = ?";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue(1, _3leggedInternal);
		$stmt->execute();

		$all = $stmt->fetchAll();
		if ( count($all) === 0 || $all[0]['expirestime'] < time() ) {
			$this->threeLeggedAuthInternal->fetchToken();
			$sql = "INSERT INTO `tokens` (`token`, `expirestime`, `type`) VALUES (?, ?, ?)";
			if ( count($all) > 0 )
				$sql = "UPDATE `tokens` SET `token` = ?, `expirestime` = ? WHERE `type` = ?";
			$stmt = $this->conn->prepare($sql);
			$stmt->bindValue(1, $this->threeLeggedAuthInternal->getAccessToken());
			$stmt->bindValue(2, time() + $this->threeLeggedAuthInternal->getExpiresIn() - 120); // minus 2min
			$stmt->bindValue(3, _3leggedInternal);
			$stmt->execute();
			$this->threeLeggedAuthInternal->setAccessToken($this->threeLeggedAuthInternal->getAccessToken());
		} else {
			$this->threeLeggedAuthInternal->setAccessToken($all[0]['token']);
		}
		return $this->threeLeggedAuthInternal; 
	}

}

$threeLeggedAuth = new AuthClientThreeLegged();

?>
