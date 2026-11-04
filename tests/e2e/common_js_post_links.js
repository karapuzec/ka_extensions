/*
	Check of the admin common.js click handler (data-ka-post links and legacy delete links) and of
	the confirmation text taken from ka_js_labels['txt_confirm'] of the footer.
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
fs.copyFileSync(path.join(root, 'admin/view/javascript/extension/ka_extensions/common.js'), path.join(dir, 'common.js'));
fs.writeFileSync(path.join(dir, 'echo.php'), '<?php echo $_SERVER["REQUEST_METHOD"], " ", $_SERVER["QUERY_STRING"];');
fs.writeFileSync(path.join(dir, 'index.html'), `<html><body><script src="jquery.js"></script><script src="common.js"></script>
<a id="post" href="echo.php?route=x/deleteRecord&amp;id=1" data-ka-post="1" data-confirm="Sure?">1</a>
<a id="post-noconfirm" href="echo.php?id=2" data-ka-post="1">2</a>
<a id="legacy" class="btn btn-danger" href="echo.php?route=x/deleteRecord&amp;id=3">3</a>
<a id="no-danger" class="btn btn-primary" href="echo.php?route=x/deleteRecord&amp;id=4">4</a>
<a id="no-delete" class="btn btn-danger" href="echo.php?route=x/edit&amp;id=5">5</a>
<a id="onclick" class="btn btn-danger" href="echo.php?route=x/deleteRecord&amp;id=6" onclick="window.location='echo.php?own=6'; return false;">6</a>
</body></html>`);
// the page with the labels of the footer (ka_js_labels, escaped as footer.twig does with |e('js'))
fs.writeFileSync(path.join(dir, 'labels.html'), fs.readFileSync(path.join(dir, 'index.html'), 'utf8').replace('</body>',
	`<script>var ka_js_labels = []; ka_js_labels['txt_confirm'] = "\\u0412\\u044B \\x22\\u0443\\u0432\\u0435\\u0440\\u0435\\u043D\\u044B\\x22\\x3F";</script></body>`));

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
	['legacy', true, 'POST route=x/deleteRecord&id=3', 'Вы "уверены"?', 'labels.html'],
	['post', false, 'stayed', 'Sure?', 'labels.html'],
];

(async () => {
	const server = spawn('php', ['-S', 'localhost:' + port, '-t', dir], { stdio: 'ignore' });
	await new Promise(r => setTimeout(r, 700));
	const browser = await chromium.launch();
	const page = await browser.newPage();
	let failed = 0;
	try {
		for (const [id, accept, expected, dialog_text, file = 'index.html'] of cases) {
			await page.goto(`http://localhost:${port}/${file}`);
			let dialog = null;
			page.removeAllListeners('dialog');
			page.on('dialog', d => { dialog = d.message(); accept ? d.accept() : d.dismiss(); });
			await page.click('#' + id);
			await page.waitForTimeout(500);
			const result = page.url().includes('echo.php') ? (await page.textContent('body')).trim() : 'stayed';
			const ok = result === expected && dialog === dialog_text;
			if (!ok) failed++;
			console.log((ok ? 'ok   ' : 'FAIL ') + id + (accept ? '' : ' (cancel)') + ': ' + result + ', dialog: ' + dialog);
		}
	} finally {
		await browser.close();
		server.kill();
		fs.rmSync(dir, { recursive: true, force: true });
	}
	console.log(failed ? failed + ' failed' : 'OK (' + cases.length + ' cases)');
	process.exit(failed ? 1 : 0);
})();
