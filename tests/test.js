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

const superagent = require('superagent');

const test1 = async () => {
	try {
		//const url = `http://zphp.local/test`;
		const url = `http://zphp.local/api/forge/public/3legged`;
		let jobs = [];
		for (let i = 0; i < 5; i++) {
			const job = superagent
				.get(url)
				.accept('application/json')
				.send();
			jobs.push (job);
		}
		let results = await Promise.all (jobs);
		results = results.map(elt => {
			return(JSON.parse(elt.text));
		});
		console.log(JSON.stringify(results));
	} catch (ex) {
		console.error(ex.message);
	}
};

test1();