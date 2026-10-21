# The Hack Repair Guy's Plugin Archiver: project notes

Developer notes for contributors. For what the plugin does, see [README.md](README.md "What the plugin does and how to install it").

Current release: **3.2.0**, in preparation (WordPress.org SVN tag `3.2.0` not yet created). Published release: **3.1.1** (SVN tag `3.1.1`, September 11, 2025). 3.1.2 was never published to WordPress.org; its changes ship in 3.2.0.

## Scope

Moves a plugin's files out of `wp-content/plugins` into an archive folder so WordPress no longer lists it, and moves them back on request. It never touches a plugin's database settings, never activates anything on restore, and makes no outside requests except one check of its own archive folder against your own site.

Built in 3.2.0: random archive folder names, web-access protection, `DISALLOW_FILE_MODS` support. Planned for 3.3.0 (see "Roadmap"): a record of archived plugins with dates, an activity log, and download as zip.

## Distribution

Distributed by **WordPress.org**, not GitHub releases. This differs from the GitHub-updated Hack Repair plugins:

- No bundled `plugin-update-checker/` and no updater class. WordPress.org serves updates, and plugins in the directory must not ship their own update mechanism.
- A release is an SVN commit to `trunk/` plus a copy to `tags/X.Y.Z`. The ZIP from `scripts/build-release.py` is for local testing and the `releases/` archive; the directory builds its own download from the tag.
- Directory images live in `.wordpress-org/` and are copied to SVN `assets/`. They never go into the plugin ZIP.
- This GitHub repo (`hackrepair/hackrepairpluginarchiver`, default branch `master`) holds source and history. Local `main` pushes with `git push origin main:master`. Tag releases here as `vX.Y.Z` after the SVN tag exists. Version 3.1.1 and earlier tags have no `v`.

## Architecture

- `hackrepair-plugin-archiver.php`: plugin header and `HackRepair_Plugin_Archiver` (static). Archive, restore and delete actions (`bulk_archive`, `bulk_restore`, `bulk_remove`), the Plugins-screen Archive link, the Archived Plugins page, admin notices, Settings link.
- `includes/bulk.php`: `HackRepair_Plugin_Archiver_Bulk_Action`, bulk action registration and handling on the Plugins screen.
- `includes/list.php`: `WP_Plugins_Archive_List_Table`, the Archived Plugins table.
- `includes/options.php`: `HackRepair_Plugin_Archiver_Options`, Settings > Plugin Archiver and archive-folder management.
- `includes/security.php`: `HackRepair_Plugin_Archiver_Security`, archive naming, the registry of archives, legacy migration, protection files, the cached reachability check, the Site Health test, the Secure and Check-again admin-post handlers, and the settings panel and notices.
- `includes/pointers.php`, `includes/notes.php`: the admin pointer and the Author Notes panel.
- Option keys: `hackrepair-plugin-archiver_options` (`archive_dir`, `archive_dir_add`, `deactivate`); `hackrepair-plugin-archiver_archives` (registry: folder => label, created, legacy; not autoloaded); `hackrepair-plugin-archiver_db_version` (one-time migration marker). Transients `hrpa_reach_<md5>` cache the reachability result (12 hours, 5 minutes after an error).
- `.wordpress-org/`: banner (772x250, 244x245) and icons (128, 256). A 1544x500 retina banner has not been made.
- `docs/audits/`: the November 2024 security and PHP 8.3 audits of 2.0.4. Historical; 3.x addressed most findings.

## Design rules

- All actions need a capability (`install_plugins` today) and a nonce. Plugin and archive paths are validated and must stay inside their own folders. Moves use the WordPress Filesystem API.
- Nothing is overwritten: a move to an existing destination is refused.
- No outside requests (the only request is to the site's own archive marker file), no scheduled jobs, no tracking.
- Archive folder names are `plugins-archive-<12 random chars>` or `plugins-archive-<label>-<8 random chars>`. Only folders in the registry can be selected. Protection files are rewritten before archive/restore. Renaming a legacy folder (Secure this archive) needs direct filesystem access.
- With `DISALLOW_FILE_MODS` (`wp_is_file_mod_allowed()`), every write path stops and the screens are read-only.
- The `hackrepair_plugin_archiver_base_dir` filter planned earlier was not built; moving the archive outside the web root is not supported yet.
- Archived plugins are left in place if this plugin is deactivated or deleted.

## Tests and tooling

`php tests/security-logic.php` tests naming, name validation, protection file contents, response classification and the registry with stubbed WordPress functions (no WordPress install needed). It does not exercise the WordPress screens; check those on a test site.

`tests/package-permissions.py` checks that the built ZIP uses mode 0644 for every entry and matches the source byte for byte.

Run the shared PHPCS installation before committing PHP changes:

```sh
/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"
```

`phpcs.xml.dist` checks against WordPress-Extra and PHPCompatibilityWP for PHP 7.4 and newer. The legacy files still carry style findings (about 119 errors at the start of the 3.1.2 work, mostly output escaping, nonce warnings and comment style). New code must be clean; legacy findings are cleaned up as files are reworked.

## Releasing (WordPress.org SVN)

1. `cd ../svn/hackrepair-plugin-archiver && svn up && svn stat` (should be clean). SVN is not installed by default: `brew install subversion`.
2. In this repo: bump `Version:` in the plugin header and `Stable tag` in `readme.txt`, add a changelog entry, update `README.md` ("Current release") and this file, write `releases/vX.Y.Z.md`.
3. Run PHPCS, the tests, and a manual pass on a local site (archive, restore, bulk actions, settings, multiple archives).
4. Commit with a plain message and push (`git push origin main:master`).
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
- **3.3.0** (records): a record of archived plugins with "Archived on" and "In the archive for" columns, an activity log (capped, with CSV export), download as zip, `uninstall.php`.
- Rejected for now: health flags, WP-CLI, email digest, auto-archive, auto-delete, clean-up-and-delete, update on restore, multisite automation.

The full specs are in the project documents (`build-plan-3.2-3.3.md`).

## Third-party code

None bundled.

## Limits and open work

- Style debt in the legacy files (see Tests and tooling).
- `generate_tabs()` is declared inside a method in the main class and would fatal if the page rendered twice (fixed in 3.2.0).
- Archived plugins are located by scanning folders whose name starts with `plugins-`, which can pick up unrelated folders (replaced by a registered list in 3.2.0).
- Multisite behavior and cross-version compatibility need dedicated tests.
- No translations are shipped yet (`languages/` has only the index file).
- Old SVN tags 3.0.1, 3.0.2 and 3.1.0 were never created.
