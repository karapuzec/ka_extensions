# Ka Extensions tests

Tests of the Ka Extensions library for OpenCart 3 (tested on OpenCart 3.0.5.1, PHP 8.4).

| Folder | What |
| --- | --- |
| `unit/` | PHPUnit tests of library classes loaded from this working copy, with small OpenCart stubs (`unit/stubs/opencart.php`) and a recording database adaptor. No store, no database, about a second |
| `integration/` | PHPUnit tests on a test store: OpenCart 3.0.5.1 with the Ka Extensions package built from the committed `HEAD` of this working copy, real MySQL |
| `fixtures/store/` | test modules copied into the test store before the integration tests: `ka_lib_test` (settings page, installer with an `upgradeExtension()` migration, list, form, recordset) and `ka_lib_kamod` (kamod child class and kapatches), `ka_lib_lite` (a Lite module, `isLite()` true), `ka_lib_vendor/vtest` (settings page of a vendor other than `ka_extensions`) |
| `e2e/` | only `package.json`: Playwright used by the platform to install the package in the store admin |

The shared platform https://svn.ka-station.com/ka_oc3_testing gives PHPUnit, the store setup and the
integration base classes. git has no `svn:externals`, so `run.sh` exports it into `tests/platform`
(pinned revision, `KA_TEST_PLATFORM_REV`) on the first run; `KA_TEST_PLATFORM_DIR` points to another copy.
The `tests/` folder is not packed (`exclude_files[] = 'tests/**'` in `manifest.ini`) and is closed for web
access by `tests/.htaccess`.

## Run

```sh
cp tests/config.local.sh.dist tests/config.local.sh   # database access and other settings
tests/run.sh                                  # unit + setup + integration
tests/run.sh unit                             # unit tests only (no store needed)
tests/run.sh integration --group=orm          # one group on the already prepared store
tests/run.sh unit --group=known-bug           # tests reproducing known bugs (they fail until fixed),
tests/run.sh integration --group=known-bug    # one step per call: a failing step stops run.sh
```

- `setup` builds the package of the committed `HEAD` (`ka_build.php` in a temporary git worktree, cached per
  commit) and installs a fresh store: `/tmp/ka_extensions_lib_store`, `http://localhost:8177/`, database
  `kat_ka_extensions_lib`. Commit before the setup step: uncommitted changes are not packed.
  The store and the url are separate from the stores of other modules.
- `integration` copies `fixtures/store` into the store, the platform restores the database snapshot once
  per run. Every test runs in its own PHP process; tests remove the tables, settings and permissions they create.

## Groups

| Group | Unit | Integration |
| --- | --- | --- |
| `forms` | `TraitControllerForm`: values with and without POST, checkbox, checkboxes (all unchecked, 5e83bff), select, defaults, date, price, image; validation messages; language fields; `ControllerForm` saving and redirects | settings page (`ControllerSettings`): defaults, stored values, escaping of values (`ka_escape`), saving, all boxes unchecked, required fields, modify permission; form page |
| `lists` | `ControllerList`: page from the URL, start/limit, sort links, actions, delete / deleteRecord by POST only; `ModelRecordset` sorting whitelist and paging | list page: pages, sorting, wrong pages, injections in parameters, actions, deleting |
| `urlparams` | `UrlParams`: declared parameters, trimming, arrays, encoding, placeholders, sort links | |
| `orm` | `QB` SQL and escaping, `ADBTable`, `ModelRecordset`, `ka_insert` / `ka_update` / `ka_delete`, `TraitDBFilter` filters (`setFilter` ids, nested `removeFilter`) | the same on MySQL with quotes, backslashes and Cyrillic values, injection attempts |
| `installer` | | `addPermission` once (802ae61), install / reinstall / uninstall from Extensions > Ka Extensions, DB patching of a new and an old table, incompatible library version |
| `settings` | `ControllerSettings::getSettingCode()`: install, save and uninstall use one setting code (vendor `ka_extensions`, another vendor, an override) | `ControllerSettings`; `SettingCodeTest`: defaults installed to the group the page saves, reinstall keeps saved values, uninstall keeps them, for vendor `ka_extensions` and another vendor |
| `upgrade` | hidden values of a module (`getConfigHidden` / `setConfigHidden`: missing value, neighbour keys kept, arrays, null), installed version (`0.0.0.0` when not stored), default `upgradeExtension()` runs `install()`, the settings page and uninstall keep the hidden values, version comparison | the 'Refresh' button of Extensions > Ka Extensions: installation stores the version, the button and hint for newer files and for an old installation, POST upgrade keeps settings, adds defaults and tables, stores the version, invalidates the kamod cache; GET, no modify permission, a failed upgrade and a not installed module change nothing; ru-ru strings |
| `kamod` | `KaPatch` replace / regexp / errors; module list fields of `kamod.ini` (`depends_on`, `install_before`, `install_after`: string, array, bare codes) and the order of child classes in the kamod chain | kamod cache rebuild, child class of an OpenCart controller, PHP and Twig kapatches |
| `helpers` | top messages (`addTopMessage` types), `Arrays`, `Pagination` | |
| `notifications` | `Notifications`: SQL of add / addForAll / remove / get, user_id 0 without an administrator, no table | the table on the Ka Extensions page, add / addForAll / remove / get of two administrators, lifetime and cleanup, the header bell (escaping, access to Ka Extensions, no table), the cross (POST, modify permission, another administrator) |
| `license` | | the license column of Extensions > Ka Extensions (server not reachable, `kareg` records written by the test): a Lite module (`ka_lib_lite`) reads its `<code>_lite` record only, a full module is unchanged |
| `smoke` | | the store runs this library and the test module |
| `known-bug` | reproduces a found bug that is not fixed yet; excluded by default (no such tests at the moment: the bugs found by the first version of the tests are fixed in 4.1.1.29 and their tests are normal tests now) | |
