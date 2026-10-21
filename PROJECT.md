# The Hack Repair Guy's Plugin Archiver: project notes

Developer notes for contributors. For what the plugin does, see [README.md](README.md "What the plugin does and how to install it").

Current release: **3.3.2**, in preparation (WordPress.org SVN tag `3.3.2` not yet created; 3.2.0 to 3.3.2 are built and on GitHub only). Published release: **3.1.1** (SVN tag `3.1.1`, September 11, 2025). 3.1.2 was never published to WordPress.org; its changes ship in 3.2.0.

## Scope

Moves a plugin's files out of `wp-content/plugins` into an archive folder so WordPress no longer lists it, and moves them back on request. It never touches a plugin's database settings, never activates anything on restore, and makes no outside requests except one check of its own archive folder against your own site.

Built in 3.2.0: random archive folder names, web-access protection, `DISALLOW_FILE_MODS` support. Built in 3.3.0: a record of archived plugins with dates, an activity log and download as zip.

## Distribution

Distributed by **WordPress.org**, not GitHub releases. This differs from the GitHub-updated Hack Repair plugins:

- No bundled `plugin-update-checker/` and no updater class. WordPress.org serves updates, and plugins in the directory must not ship their own update mechanism.
- A release is an SVN commit to `trunk/` plus a copy to `tags/X.Y.Z`. The ZIP from `scripts/build-release.py` is for local testing and the `releases/` archive; the directory builds its own download from the tag.
- Directory images live in `.wordpress-org/` and are copied to SVN `assets/`. They never go into the plugin ZIP.
- This GitHub repo (`hackrepair/hackrepairpluginarchiver`, default branch `main`) holds source and history and mirrors what is released on WordPress.org. Push with `git push origin main`. Tag `vX.Y.Z` here only after the matching SVN tag exists. Version 3.1.1 and earlier tags have no `v`.

## Architecture

- `hackrepair-plugin-archiver.php`: plugin header and `HackRepair_Plugin_Archiver` (static). Archive, restore and delete actions (`bulk_archive`, `bulk_restore`, `bulk_remove`), the Plugins-screen Archive link, the Archived Plugins page, admin notices, Settings link.
- `includes/bulk.php`: `HackRepair_Plugin_Archiver_Bulk_Action`, bulk action registration and handling on the Plugins screen.
- `includes/list.php`: `HackRepair_Plugin_Archiver_List_Table` (renamed from `WP_Plugins_Archive_List_Table` in 3.3.0), the Archived Plugins table.
- `includes/options.php`: `HackRepair_Plugin_Archiver_Options`, Settings > Plugin Archiver and archive-folder management.
- `includes/security.php`: `HackRepair_Plugin_Archiver_Security`, archive naming, the registry of archives, legacy migration, protection files, the cached reachability check, the Site Health test, the Secure and Check-again admin-post handlers, and the settings panel and notices.
- `includes/records.php`: `HackRepair_Plugin_Archiver_Records`, the archived-on manifest and the capped activity log (Log tab, CSV export, clear).
- `includes/download.php`: `HackRepair_Plugin_Archiver_Download`, builds a zip of one archived plugin (`ZipArchive`) and sends it.
- `uninstall.php`: deletes the plugin's own options and transients only.
- `includes/pointers.php`, `includes/notes.php`: the admin pointer and the Author Notes panel.
- Option keys: `hackrepair-plugin-archiver_options` (`archive_dir`, `archive_dir_add`, `deactivate`); `hackrepair-plugin-archiver_archives` (registry: folder => label, created, legacy; not autoloaded); `hackrepair-plugin-archiver_manifest` (`<folder>|<plugin file>` => name, version, archived, user; capped at 1000; not autoloaded); `hackrepair-plugin-archiver_log` (newest 500 entries: t, u, a, d, p, n, x; not autoloaded); `hackrepair-plugin-archiver_db_version` (one-time migration marker). Transients `hrpa_reach_<md5>` cache the reachability result (12 hours, 5 minutes after an error).
- `.wordpress-org/`: the WordPress.org directory images, named to the WordPress.org standard: `banner-772x250.png`, `banner-1544x500.png` (a sharpened 2x of the original; re-export from the Photoshop file at 2x for a crisper one), `icon-128x128.png`, `icon-256x256.png`. They are copied into SVN `assets/` at release, never into the plugin ZIP. The old `banner-244x245.png` is not a WordPress.org size; it was dropped from the repo but still sits in SVN `assets/` until removed at the next release.
- `docs/audits/`: the November 2024 security and PHP 8.3 audits of 2.0.4. Historical; 3.x addressed most findings.

## Design rules

- Capabilities: viewing the archive, the archive screens and Download need `activate_plugins` (`manage_network_plugins` on multisite, where archive folders are shared), via `HackRepair_Plugin_Archiver_Security::can_view()`. Archive, restore, delete and Secure need `install_plugins` plus `wp_is_file_mod_allowed()`. Do not gate viewing on `install_plugins`: WordPress removes it from everyone when `DISALLOW_FILE_MODS` is set. Settings, the log and its export/clear need `manage_options`. Every action also needs a nonce. Plugin and archive paths are validated and must stay inside their own folders. Moves use the WordPress Filesystem API.
- Nothing is overwritten: a move to an existing destination is refused, counted as skipped and explained in a notice.
- Switching the active archive from the list screen needs a nonce (`_hrpa_switch`).
- The Plugins-screen archive count is cached per archive for ten minutes (`hrpa_count_<md5>`) and cleared after every archive, restore and delete.
- Settings validation keeps only known keys, accepts only registered archives, and saves an unchecked Auto-deactivate box as off.
- No outside requests (the only request is to the site's own archive marker file), no scheduled jobs, no tracking.
- Archive folder names are `plugins-archive-<12 random chars>` or `plugins-archive-<label>-<8 random chars>`. Only folders in the registry can be selected. Protection files are rewritten before archive/restore. Renaming a legacy folder (Secure this archive) needs direct filesystem access.
- Download needs `can_view()` and a nonce tied to archive and plugin; the archive must be in the registry; the path must resolve inside the archive; symbolic links are skipped; the zip is built in a temporary file that is deleted after sending; the size cap is filterable (`hackrepair_plugin_archiver_download_max_bytes`). The log and its export/clear need `manage_options`; CSV cells that start with `=`, `+`, `-`, `@` or a tab are prefixed with an apostrophe.
- Plugins archived before 3.3.0 have no manifest entry; the list shows the folder's modified time marked "about". Restore counts were dropped: the log records every restore.
- With `DISALLOW_FILE_MODS` (`wp_is_file_mod_allowed()`), every write path stops and the screens are read-only; the one-time migration keeps re-running until protection files can be written.
- The `hackrepair_plugin_archiver_base_dir` filter planned earlier was not built; the archive stays in `wp-content`, in the same place as in all earlier builds, and moving it elsewhere is not supported.
- Archived plugins are left in place if this plugin is deactivated or deleted.

## Tests and tooling

`php tests/records-download-logic.php` tests CSV safety, log capping and filtering, plugin-file validation, path confinement and real zip building (symbolic links skipped, size cap, single-file plugins).

`php tests/security-logic.php` tests naming, name validation, protection file contents, response classification and the registry with stubbed WordPress functions (no WordPress install needed). It does not exercise the WordPress screens; check those on a test site.

`tests/package-permissions.py` checks that the built ZIP uses mode 0644 for every entry and matches the source byte for byte.

Run the shared PHPCS installation before committing PHP changes:

```sh
/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"
```

`phpcs.xml.dist` checks against WordPress-Extra and PHPCompatibilityWP for PHP 7.4 and newer. As of the 3.3.0 review every PHP file passes with zero findings. The few exceptions are marked in place with a reason: the list table deliberately shares core's list-table globals and fires core's plugin-row hooks, and the bulk handlers rely on a nonce verified by their caller. Keep it at zero.

## Releasing (WordPress.org SVN)

1. `cd ../svn/hackrepair-plugin-archiver && svn up && svn stat` (should be clean). SVN is not installed by default: `brew install subversion`.
2. In this repo: bump `Version:` in the plugin header and `Stable tag` in `readme.txt`, add a changelog entry, update `README.md` ("Current release") and this file, write `releases/vX.Y.Z.md`.
3. Run PHPCS, the tests, and a manual pass on a local site (archive, restore, bulk actions, settings, multiple archives).
4. Commit with a plain message and push (`git push origin main`).
5. Build with `python3 scripts/build-release.py`. Copy the files it ships (not `.wordpress-org/`) into the SVN `trunk/`, then `svn add --force trunk`, `svn rm` anything removed, `svn diff`.
6. `svn ci -m "Release X.Y.Z: ..." --username <wp.org user>` (SVN password, separate from the login password; two-factor required).
7. `svn cp trunk tags/X.Y.Z && svn ci -m "Tagging version X.Y.Z"`. `Stable tag` in trunk `readme.txt` must equal the tag.
8. Image-only or readme-only changes: commit to SVN `assets/` or trunk without a new tag.
9. Verify on https://wordpress.org/plugins/hackrepair-plugin-archiver/ (allow about 15 minutes) and test an update from the previous version.
10. Tag the same commit here: `git tag vX.Y.Z && git push origin vX.Y.Z`.

## Roadmap

Decided October 20, 2026 after reviewing a competing plugin. Ideas only; nothing is copied from it.

- **3.1.2**: header and readme refresh, version bump. No behavior change.
- **3.2.0** (security, built): random 12-character archive folder names (existing archives registered and offered a "Secure this archive" rename), web-access protection files (`.htaccess`, `web.config`, `index.php`, marker) with a self-request reachability check and a Site Health test, `DISALLOW_FILE_MODS` support, a direct-write precheck, the nested-function fix.
- **3.3.2** (fix release, built): Delete asks for confirmation; settings messages no longer doubled; no "0 plugins ... successfully" when all were skipped; read-only mode disables New Archive and hides list checkboxes; refused actions return 403. Plugin Check clean (only the "plugin" in name/slug warning remains; the slug is fixed). Found by testing every feature on the local site.
- **3.3.1** (fix release, built): no "Archive" link inside the Archived Plugins list; log shows plugin names for plugins archived before 3.3.0. Found by testing the upgrade from 3.1.1 on a live site.
- **3.3.0** (records, built): a record of archived plugins with "Archived on" and "In the archive for" columns, an activity log (capped, with CSV export), download as zip, `uninstall.php`.
- Rejected for now: health flags, WP-CLI, email digest, auto-archive, auto-delete, clean-up-and-delete, update on restore, multisite automation.

The full specs are in the project documents (`build-plan-3.2-3.3.md`).

## Third-party code

None bundled.

## Limits and open work

- Multisite: archive screens and Download need a network administrator; uninstall cleans every site. Not yet tested on a real network.
- Not yet tested: Secure this archive (rename), nginx, multisite.
- No translations are shipped yet (`languages/` has only the index file).
- Old SVN tags 3.0.1, 3.0.2 and 3.1.0 were never created.
