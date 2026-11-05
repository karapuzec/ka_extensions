/*
	Check of the admin kaextensions.js click handler (data-ka-post links and legacy delete links), of
	the confirmation text set by ka_extensions.setLabels() of the footer and of getLabel(key, params).
	Run: node tests/e2e/common_js_post_links.js  (needs php and the jquery file of an OpenCart 3 store,
	KA_TEST_JQUERY=/path/to/admin/view/javascript/jquery/jquery-2.1.1.min.js)
*/
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const jquery = process.env.KA_TEST_JQUERY || '/tmp/ka_extensions_lib_store/admin/view/javascript/jquery/jquery-2.1.1.min.js';
const port = 8299;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'ka-common-js-'));
fs.copyFileSync(jquery, path.join(dir, 'jquery.js'));
fs.copyFileSync(path.join(root, 'admin/view/javascript/extension/ka_extensions/kaextensions.js'), path.join(dir, 'kaextensions.js'));
// a theme without the library parts of header.twig / footer.twig: only the files (admin and storefront)
fs.copyFileSync(path.join(root, 'catalog/view/javascript/extension/ka_extensions/kaextensions.js'), path.join(dir, 'kaextensions_catalog.js'));
fs.writeFileSync(path.join(dir, 'echo.php'), '<?php echo $_SERVER["REQUEST_METHOD"], " ", $_SERVER["QUERY_STRING"];');
const links = `<a id="post" href="echo.php?route=x/deleteRecord&amp;id=1" data-ka-post="1" data-confirm="Sure?">1</a>
<a id="post-noconfirm" href="echo.php?id=2" data-ka-post="1">2</a>
<a id="legacy" class="btn btn-danger" href="echo.php?route=x/deleteRecord&amp;id=3">3</a>
<a id="no-danger" class="btn btn-primary" href="echo.php?route=x/deleteRecord&amp;id=4">4</a>
<a id="no-delete" class="btn btn-danger" href="echo.php?route=x/edit&amp;id=5">5</a>
<a id="onclick" class="btn btn-danger" href="echo.php?route=x/deleteRecord&amp;id=6" onclick="window.location='echo.php?own=6'; return false;">6</a>
`;
const page = (scripts) => `<html><head><script src="jquery.js"></script>${scripts}</head><body>\n${links}</body></html>`;
const create = `<script>if (typeof ka_extensions === 'undefined' && typeof KaExtensions !== 'undefined') { var ka_extensions = new KaExtensions(); }</script>`;
// the library header: the class and the object in the head
fs.writeFileSync(path.join(dir, 'index.html'), page('<script src="kaextensions.js"></script>' + create));
// the footer labels: JSON as the footer prints it (json_encode with JSON_HEX_*)
fs.writeFileSync(path.join(dir, 'labels.html'), page('<script src="kaextensions.js"></script>' + create).replace('</body>',
	String.raw`<script>ka_extensions.setLabels({"txt_confirm":"\u0412\u044b \u0022\u0443\u0432\u0435\u0440\u0435\u043d\u044b\u0022? \\ \n\u003C\/script\u003E"});</script></body>`));

const cases = [
	// id, confirm answer, expected result, expected dialog text
	['post', true, 'POST route=x/deleteRecord&id=1', 'Sure?'],
	['post', false, 'stayed', 'Sure?'],
	['post-noconfirm', true, 'POST id=2', null],
	['legacy', true, 'POST route=x/deleteRecord&id=3', 'Are you sure?'],
	['legacy', false, 'stayed', 'Are you sure?'],
	['no-danger', true, 'GET route=x/deleteRecord&id=4', null],
	['no-delete', true, 'GET route=x/edit&id=5', null],
	['onclick', true, 'GET own=6', null],
	// txt_confirm of ka_js_labels instead of the English default
	['legacy', true, 'POST route=x/deleteRecord&id=3', 'Вы "уверены"? \\ \n</script>', 'labels.html'],
	['post', false, 'stayed', 'Sure?', 'labels.html'],
];

(async () => {
	const server = spawn('php', ['-S', 'localhost:' + port, '-t', dir], { stdio: 'ignore' });
	await new Promise(r => setTimeout(r, 700));
	const browser = await chromium.launch();
	const bpage = await browser.newPage();
	let failed = 0;
	try {
		for (const [id, accept, expected, dialog_text, file = 'index.html'] of cases) {
			await bpage.goto(`http://localhost:${port}/${file}`);
			let dialog = null;
			bpage.removeAllListeners('dialog');
			let dialogs = 0;
			bpage.on('dialog', d => { dialogs++; dialog = d.message(); accept ? d.accept() : d.dismiss(); });
			await bpage.click('#' + id);
			await bpage.waitForTimeout(500);
			const result = bpage.url().includes('echo.php') ? (await bpage.textContent('body')).trim() : 'stayed';
			// one question per click (one handler)
			const ok = result === expected && dialog === dialog_text && dialogs <= 1;
			if (!ok) failed++;
			console.log((ok ? 'ok   ' : 'FAIL ') + id + (accept ? '' : ' (cancel)') + ': ' + result + ', dialog: ' + JSON.stringify(dialog) + ', dialogs: ' + dialogs);
		}
		// getLabel(key, params): str_replace of Language::getka() (all occurrences, keys in order)
		await bpage.goto(`http://localhost:${port}/index.html`);
		const labels = await bpage.evaluate(() => {
			ka_extensions.setLabels({'txt_t': 'Deleted %count% of %total%, %count%', 'txt_ab': 'ab'});
			return [
				ka_extensions.getLabel('txt_t', {'%count%': 2, '%total%': 5}),
				ka_extensions.getLabel('txt_t'),
				ka_extensions.getLabel('txt_ab', {'a': 'b', 'b': 'c'}),
				ka_extensions.getLabel('txt_missing'),
				ka_extensions.getLabel('txt_confirm', {}),
			];
		});
		const expected_labels = ['Deleted 2 of 5, 2', 'Deleted %count% of %total%, %count%', 'cc', 'txt_missing', 'Are you sure?'];
		const labels_ok = JSON.stringify(labels) === JSON.stringify(expected_labels);
		if (!labels_ok) failed++;
		console.log((labels_ok ? 'ok   ' : 'FAIL ') + 'getLabel params: ' + JSON.stringify(labels));
		// the object exists without the inline script of the header (any theme), one object for both scripts
		for (const [file, scripts] of [['nohead.html', '<script src="kaextensions.js"></script>'],
			['nohead_catalog.html', '<script src="kaextensions_catalog.js"></script>'],
			['twice.html', '<script src="kaextensions.js"></script>' + create]]) {
			fs.writeFileSync(path.join(dir, file), page(scripts));
			await bpage.goto(`http://localhost:${port}/${file}`);
			const r = await bpage.evaluate(() => {
				const first = window.ka_extensions;
				// $.ka.alert(text, type) calls ka_extensions.showMessage(text, type)
				const calls = [];
				const show = window.ka_extensions.showMessage;
				window.ka_extensions.showMessage = (text, type) => calls.push([text, type]);
				$.ka.alert('Saved', 'S');
				window.ka_extensions.showMessage = show;
				return [typeof window.ka_extensions, window.ka_extensions instanceof KaExtensions, first === window.ka_extensions,
					typeof window.ka_extensions.showMessage, typeof $.ka.alert, JSON.stringify(calls)];
			});
			const ok = JSON.stringify(r) === JSON.stringify(['object', true, true, 'function', 'function', '[["Saved","S"]]']);
			if (!ok) failed++;
			console.log((ok ? 'ok   ' : 'FAIL ') + 'object created by the file (' + file + '): ' + JSON.stringify(r));
		}
	} finally {
		await browser.close();
		server.kill();
		fs.rmSync(dir, { recursive: true, force: true });
	}
	console.log(failed ? failed + ' failed' : 'OK (' + cases.length + ' cases)');
	process.exit(failed ? 1 : 0);
})();
