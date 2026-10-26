# Ka Extensions tests

Tests of the Ka Extensions library for OpenCart 3 (tested on OpenCart 3.0.5.1, PHP 8.4).

| Folder | What |
| --- | --- |
| `unit/` | PHPUnit tests of library classes loaded from this working copy, with small OpenCart stubs (`unit/stubs/opencart.php`) and a recording database adaptor. No store, no database, about a second |
| `integration/` | PHPUnit tests on a test store: OpenCart 3.0.5.1 with the Ka Extensions package built from the committed `HEAD` of this working copy, real MySQL |
| `fixtures/store/` | test modules copied into the test store before the integration tests: `ka_lib_test` (settings page, installer, list, form, recordset) and `ka_lib_kamod` (kamod child class and kapatches) |
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
| `forms` | `TraitControllerForm`: values with and without POST, checkbox, checkboxes (all unchecked, 5e83bff), select, defaults, date, price, image; validation messages; language fields; `ControllerForm` saving and redirects | settings page (`ControllerSettings`): defaults, stored values, saving, all boxes unchecked, required fields, modify permission; form page |
| `lists` | `ControllerList`: page from the URL, start/limit, sort links, actions, delete / deleteRecord; `ModelRecordset` sorting whitelist and paging | list page: pages, sorting, wrong pages, injections in parameters, actions, deleting |
| `urlparams` | `UrlParams`: declared parameters, trimming, arrays, encoding, placeholders, sort links | |
| `orm` | `QB` SQL and escaping, `ADBTable`, `ModelRecordset`, `ka_insert` / `ka_update` / `ka_delete` | the same on MySQL with quotes, backslashes and Cyrillic values, injection attempts |
| `installer` | | `addPermission` once (802ae61), install / reinstall / uninstall from Extensions > Ka Extensions, DB patching of a new and an old table, incompatible library version |
| `settings` | | `ControllerSettings` |
| `kamod` | `KaPatch` replace / regexp / errors | kamod cache rebuild, child class of an OpenCart controller, PHP and Twig kapatches |
| `helpers` | top messages (`addTopMessage` types), `Arrays`, `Pagination` | |
| `smoke` | | the store runs this library and the test module |
| `known-bug` | reproduces a found bug; excluded by default | |
