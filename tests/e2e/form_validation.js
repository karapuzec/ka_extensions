/*
	Check of the form validation of kaextensions.js (ka_extensions.initFormValidation(), called by the footer
	unless KA_DISABLE_FORM_VALIDATION is defined), admin and storefront files.
	Run: node tests/e2e/form_validation.js  (needs php and the jquery file of an OpenCart 3 store,
	KA_TEST_JQUERY=/path/to/admin/view/javascript/jquery/jquery-2.1.1.min.js)
*/
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const jquery = process.env.KA_TEST_JQUERY || '/tmp/ka_extensions_lib_store/admin/view/javascript/jquery/jquery-2.1.1.min.js';
const port = 8298;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'ka-form-validation-'));
fs.copyFileSync(jquery, path.join(dir, 'jquery.js'));
fs.copyFileSync(path.join(root, 'admin/view/javascript/extension/ka_extensions/kaextensions.js'), path.join(dir, 'admin.js'));
fs.copyFileSync(path.join(root, 'catalog/view/javascript/extension/ka_extensions/kaextensions.js'), path.join(dir, 'catalog.js'));
fs.writeFileSync(path.join(dir, 'echo.php'), '<?php echo "submitted ", http_build_query($_POST);');

/*
	A page like a form page of the library: the Save button outside of the form (form="form-item"),
	required fields with a label, with data-title only, a field in a hidden tab.
*/
const page = (js, opts) => `<html><head><script src="jquery.js"></script><script src="${js}"></script></head><body>
<div id="container">
<button id="save" type="submit" form="form-item">Save</button>
${opts.second ? '<button id="save2" type="submit" form="form-item">Save & Stay</button>' : ''}
<form id="form-item" method="post" action="echo.php" ${opts.novalidate ? 'novalidate' : ''}>
	<div class="form-group"><label class="control-label">Name</label><input name="name" required value="${opts.name ?? ''}"></div>
	<div><input name="code" data-title="Code" required value="${opts.code ?? 'c'}"></div>
	<div style="${opts.hidden ? 'display: none' : ''}"><input name="hidden_field" required value="${opts.hidden_value ?? 'h'}"></div>
</form>
</div>
<script>
${opts.labels ? "ka_extensions.setLabels({'txt_field_validation_error': 'Not filled: '});" : ''}
${opts.init ? 'ka_extensions.initFormValidation();' : ''}
${opts.init2 ? 'ka_extensions.initFormValidation();' : ''}
</script>
</body></html>`;

// file, page options, action (click on #save or a jQuery submit), expected [result, messages]
const cases = [];
for (const js of ['admin.js', 'catalog.js']) {
	cases.push(
		// the browser checks required fields before the submit event: its own tooltip, no message of the library
		[js, 'browser validation first', { init: true }, 'click', ['stayed', []]],
		[js, 'hidden invalid field, browser blocks', { init: true, name: 'n', hidden: true, hidden_value: '' }, 'click', ['stayed', []]],
		// novalidate: the library handler checks the fields
		[js, 'novalidate, empty field', { init: true, novalidate: true }, 'click', ['stayed', ['Some data is not filled in. Please check the form fields: Name']]],
		[js, 'novalidate, translated label', { init: true, novalidate: true, labels: true }, 'click', ['stayed', ['Not filled: Name']]],
		[js, 'novalidate, filled', { init: true, novalidate: true, name: 'n' }, 'click', ['submitted', []]],
		[js, 'novalidate, no initFormValidation', { novalidate: true }, 'click', ['submitted', []]],
		[js, 'two calls, one handler', { init: true, init2: true, novalidate: true }, 'click', ['stayed', ['Some data is not filled in. Please check the form fields: Name']]],
		// a submit from code (jQuery) skips the browser validation, the library handler checks the fields
		[js, 'jQuery submit()', { init: true }, 'jquery', ['stayed', ['Some data is not filled in. Please check the form fields: Name']]],
		// the name: label.control-label of .form-group, data-title (storefront only), the name attribute
		[js, 'field names', { init: true, novalidate: true, name: 'n', code: '' }, 'click', ['stayed', ['Some data is not filled in. Please check the form fields: ' + (js == 'catalog.js' ? 'Code' : 'code')]]],
	);
}

(async () => {
	const server = spawn('php', ['-S', 'localhost:' + port, '-t', dir], { stdio: 'ignore' });
	await new Promise(r => setTimeout(r, 700));
	const browser = await chromium.launch();
	const bpage = await browser.newPage();
	let failed = 0;
	try {
		let n = 0;
		for (const [js, title, opts, action, expected] of cases) {
			const file = 'p' + (n++) + '.html';
			fs.writeFileSync(path.join(dir, file), page(js, opts));
			await bpage.goto(`http://localhost:${port}/${file}`);
			if (action == 'click') {
				await bpage.click('#save');
			} else {
				await bpage.evaluate(() => $('#form-item').submit());
			}
			await bpage.waitForTimeout(400);
			let result, messages = [];
			if (bpage.url().includes('echo.php')) {
				result = 'submitted';
			} else {
				result = 'stayed';
				messages = await bpage.$$eval('#ka-alert .alert-danger', els => els.map(e => e.textContent.replace(/\s+/g, ' ').replace('×', '').trim()));
			}
			const ok = JSON.stringify([result, messages]) === JSON.stringify(expected);
			if (!ok) failed++;
			console.log((ok ? 'ok   ' : 'FAIL ') + js + ', ' + title + ': ' + JSON.stringify([result, messages]));
		}
		// two buttons of one form: a handler per button (as the old footer script), two messages
		fs.writeFileSync(path.join(dir, 'two.html'), page('admin.js', { init: true, novalidate: true, second: true }));
		await bpage.goto(`http://localhost:${port}/two.html`);
		await bpage.click('#save');
		await bpage.waitForTimeout(400);
		console.log('info two buttons of one form, messages: ' + (await bpage.$$('#ka-alert .alert-danger')).length);
	} finally {
		await browser.close();
		server.kill();
		fs.rmSync(dir, { recursive: true, force: true });
	}
	console.log(failed ? failed + ' failed' : 'OK (' + cases.length + ' cases)');
	process.exit(failed ? 1 : 0);
})();
