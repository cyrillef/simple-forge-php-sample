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

class AccessToken2Legged {

	public function __construct() {
		set_time_limit(0);
	}    

	public function getAccessToken() {
		global $twoLeggedAuth;
		try {
			$accessToken = $twoLeggedAuth->getTokenPublic();
			return json_encode($accessToken);
		} catch (Exception $e) {
			echo 'Exception when calling twoLeggedAuth->getTokenPublic: ', $e->getMessage(), PHP_EOL;
			return null;
		}
	}
}

?>
