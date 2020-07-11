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
/*jshint esversion: 9 */
let viewer =null;

$(document).ready(function () {
	getURNs();
	launchViewer();
});

function launchViewer () {
	var options = {
		env: 'AutodeskProduction',
		getAccessToken: getForgeToken
	};

	Autodesk.Viewing.Initializer(options, () => {
		viewer = new Autodesk.Viewing.GuiViewer3D(document.getElementById('viewer'), { extensions: ['Autodesk.DocumentBrowser'] });
		viewer.start();

		viewer.disableHighlight(true);
		viewer.autocam.shotParams.destinationPercent = 1;
		viewer.autocam.shotParams.duration = 3;
		viewer.prefs.tag('ignore-producer'); // Ignore the model default environment
		viewer.prefs.tag('envMapBackground'); // Ignore the model background image

		getForgeURN(urn => {
			let documentId = 'urn:' + urn;
			Autodesk.Viewing.Document.load(documentId, onDocumentLoadSuccess, onDocumentLoadFailure);
		});
	});
}

function onDocumentLoadSuccess (doc) {
	var viewables = doc.getRoot().getDefaultGeometry();
	viewer.loadDocumentNode(doc, viewables).then(i => {
		viewer.setLightPreset(0);
		viewer.setLightPreset(2);
		viewer.setQualityLevel( /* ambient shadows */ false, /* antialiasing */ true);
		viewer.setGroundShadow(false);
		viewer.setGroundReflection(false);
		viewer.setGhosting(true);
		viewer.setEnvMapBackground(false);
		viewer.setSelectionColor(new THREE.Color(0xEBB30B));
	});
}

function onDocumentLoadFailure (viewerErrorCode) {
	console.error('onDocumentLoadFailure() - errorCode:' + viewerErrorCode);
}

function getForgeToken (callback) {
	try {
		// better than the old $.ajax()
		fetch('/api/forge/public/2legged').then(res => {
			res.json().then(data => {
				callback(data.access_token, data.expires_in);
			});
		});
	} catch (ex) {
		console.error (ex.message);
	}
}

function getForgeURN (callback) {
	try {
		// better than the old $.ajax()
		fetch('/api/forge/public/2urn').then(res => {
			res.json().then(data => {
				callback(data.urn);
			});
		});
	} catch (ex) {
		console.error(ex.message);
	}
}

function htmlEntities (str) {
	return (String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'));
}

function switchURN (evt, role) {
	evt.stopPropagation();
	let urn = evt.target.id;
	
	let documentId = 'urn:' + urn;
	Autodesk.Viewing.Document.load(documentId, onDocumentLoadSuccess, onDocumentLoadFailure);
}

function getURNs () {
	try {
		fetch('/api/forge/public/2urn-list').then(res => {
			res.json().then(data => {
				let list = $('#urns');
				data.map (elt => {
					let r = $('<div><button id="' + elt.id + '" title="' +
						htmlEntities(elt.text) + '">' +
						htmlEntities(elt.text) + '</button></div>');
					list.append(r);
					$('#' + elt.id).click((e) => {
						switchURN(e);
					});
				});
			});
		});
	} catch (ex) {
		console.error(ex.message);
	}
}