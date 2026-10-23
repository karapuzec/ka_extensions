# Development

The repository is a complete OpenCart store (OpenCart 3.0.0.0 based) with Ka Extensions installed.
Code of Ka Extensions lives in the standard OpenCart folders (`admin/`, `catalog/`, `system/`).

## Dev store from the repository

1. Clone the repository into the web server folder (or use `php -S localhost:8080` in the repo root).
2. Create the database and import the dump:
   ```
   mysql -u root -e "CREATE DATABASE ka_ext_oc3000a1"
   mysql -u root ka_ext_oc3000a1 < sql/dump.sql
   ```
   `sql/dump.sql` creates the database `ka_ext_oc3000a1` itself (`--databases`). `sql/dump_data.bat` re-creates the dump.
3. Config files are not in git (`.gitignore`). Copy the samples and fix URLs, paths and DB access:
   ```
   copy config.local.php config.php
   copy admin\config.local.php admin\config.php
   ```
4. `system/config/kamod.php` is tracked with the default `admin_dir = 'admin'`. It is regenerated on every
   kamod rebuild, do not commit local changes of it. If your admin folder has another name, kamod writes it there.
5. Writable folders: `system/storage/` (cache, logs, `cache.kamod`, `theme.kamod`, modification) and `image/cache/`.
6. Open the admin, then *Extensions > Modifications > Refresh* and *Extensions > Ka Extensions*
   (rebuilds the kamod cache). For development define `KAMOD_DEBUG` (see `docgen/guides/useful_constants.rst`).

The store is an old OpenCart version. Test releases on a clean store of the current OpenCart (3.0.5.1)
by installing the built package.

## Building the package

Requirements: PHP (CLI) and git in `PATH`.

```
build_version.bat
```
or directly:
```
php ka_build.php manifest.ini                    # package in tmp_pack/
php ka_build.php manifest.ini --release=true     # + copy to releases_dir (%PROJECTS_KA_DIR%)
```

- Only **committed** changes are packed (the file list comes from git), commit before building.
- `manifest.ini` uses `manifest_version = 2`, `vcs = "git"`, `start_revision = "root"`.
  Files of the original OpenCart patched by Ka Extensions are listed in `added_later_files[]` (they go to
  `install.xml`), other OpenCart files matched by upload masks in `exclude_files[]`.
  A new patched OpenCart file must be added to `added_later_files[]`.
- Manifest reference: `MANIFEST.md` in `ka_tools/ka_build.o2` (svn).

## Release

1. Update `version` in `manifest.ini` and `changelog.txt`.
2. Commit, build with `--release=true`.
3. Install the package on a clean OpenCart 3.0.5.1 store and check it.

## Documentation

`docgen/` contains the phpDocumentor configuration and hand-written guides (`docgen/guides/*.rst`,
reStructuredText, listed in `docgen/guides/index.rst`). `docgen/gen.bat` generates the documentation.
