# The Hack Repair Guy's Plugin Archiver: project notes

Developer notes for contributors. For what the plugin does, see [README.md](README.md "What the plugin does and how to install it").

Current release: **3.3.2**, published to WordPress.org on October 21, 2026 (SVN revision 3759198, tag `3.3.2`) and released on GitHub as `3.3.2`. Earlier published release: **3.1.1** (SVN tag `3.1.1`, September 11, 2025). 3.1.2, 3.2.0, 3.3.0 and 3.3.1 were built but never published on their own; their changes ship in 3.3.2.

## Scope

Moves a plugin's files out of `wp-content/plugins` into an archive folder so WordPress no longer lists it, and moves them back on request. It never touches a plugin's database settings, never activates anything on restore, and makes no outside requests except one check of its own archive folder against your own site.

Built in 3.2.0: random archive folder names, web-access protection, `DISALLOW_FILE_MODS` support. Built in 3.3.0: a record of archived plugins with dates, an activity log and download as zip.

## Distribution

Distributed by **WordPress.org**, not GitHub releases. This differs from the GitHub-updated Hack Repair plugins:

- No bundled `plugin-update-checker/` and no updater class. WordPress.org serves updates, and plugins in the directory must not ship their own update mechanism.
- A release is an SVN commit to `trunk/` plus a copy to `tags/X.Y.Z`. The ZIP from `scripts/build-release.py` is for local testing and the `releases/` archive; the directory builds its own download from the tag.
- Directory images live in `.wordpress-org/` and are copied to SVN `assets/`. They never go into the plugin ZIP.
- This GitHub repo (`hackrepair/hackrepairpluginarchiver`, default branch `main`) holds source and history and mirrors what is released on WordPress.org. Push with `git push origin main`. The latest GitHub release must always equal the WordPress.org Stable tag. Create the GitHub tag and release only after the matching SVN tag exists. Tags are the plain version (`3.3.2`), the same as the SVN tag and every earlier tag here: no `v` prefix. Docs-only commits after a release are fine because `README.md` and `PROJECT.md` are not in the plugin ZIP.

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
- `.wordpress-org/`: the WordPress.org directory images, named to the WordPress.org standard: `banner-772x250.png`, `banner-1544x500.png` (a sharpened 2x of the original; re-export from the Photoshop file at 2x for a crisper one), `icon-128x128.png`, `icon-256x256.png`. They are copied into SVN `assets/` at release, never into the plugin ZIP. The old `banner-244x245.png` is not a WordPress.org size; it was removed from the repo and from SVN `assets/` in the 3.3.2 release. A small `hi2.gif` still sits in SVN `assets/`; WordPress.org ignores it and it is harmless.
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

Last run end to end for 3.3.2 on October 21, 2026. The longer runbook, with the test recipes, is in the project documents (`wporg-update-plan.md`).

### Requirements before touching SVN

- **Versions agree everywhere:** `Version:` in the plugin header, `Stable tag` in `readme.txt`, a changelog entry, `README.md` ("Current release"), this file, and `releases/vX.Y.Z.md`.
- **readme.txt:** `Tested up to` is major.minor only (`7.1`, not `7.1.3`; Plugin Check reports the full number as an error). Keep `Requires at least` and `Requires PHP` accurate. No GitHub references in the WordPress.org readme.
- **Code rules for the directory:** no update mechanism and no bundled updater; no private WordPress functions (for example `_get_plugin_data_markup_translate()` is forbidden); no `load_plugin_textdomain()` (the directory loads translations); unslash and sanitize every submitted value; escape every output; nonce and capability check on every action; GPL licence file; no tracking.
- **Quality gates, all must pass:** PHP lint on every file; PHPCS with zero findings; `php tests/records-download-logic.php` and `php tests/security-logic.php` with no FAIL; `python3 tests/package-permissions.py`; WordPress.org **Plugin Check** with no errors (the only accepted warning is the word "plugin" in the name and slug, which cannot change for an existing plugin); a manual pass on a local site (archive, restore, bulk actions, delete with confirmation, settings, a second archive and switching, log, CSV, download, read-only mode with `DISALLOW_FILE_MODS`, duplicate-folder skip).
- **Hygiene scan, no hits allowed:** text files, images (strip embedded metadata from any generated image first), the built ZIP and every commit message must have no mention of AI tools and no co-author trailers. The pattern below does not match itself:

```sh
grep -rIil -e 'cl[a]ude' -e 'anthrop[i]c' -e 'co-authore[d]' --exclude-dir=.git --exclude-dir=dist .
```

- **Commit style:** plain message, author `tvcnet`, no trailers or attribution lines, push `main` before building.

### Steps

1. Commit and push `main` (clean tree). Build: `python3 scripts/build-release.py` writes `dist/hackrepair-plugin-archiver.X.Y.Z.zip` (ignored by git). The same source builds the same hash.
2. SVN working copy: `/Volumes/BACKUP8TB2/plugins/hackrepair-plugin-archiver/svn/hackrepair-plugin-archiver`. Run `svn up`, then `svn status -u` (nothing out of date, nothing local). SVN 1.15 is installed at `/usr/local/bin/svn`.
3. Unzip the build to a temporary folder and sync trunk: `rsync -a --delete --exclude='.svn' tmp/hackrepair-plugin-archiver/ trunk/`, then `svn add --force -q trunk`, then `svn rm` every file that shows `!`. `diff -rq --exclude=.svn trunk tmp/hackrepair-plugin-archiver` must print nothing.
4. Images, only if changed: copy from `.wordpress-org/` to `assets/`, `svn add` new files and set `svn propset svn:mime-type image/png`, `svn rm` retired ones. Standard names only.
5. Tag: `svn copy trunk tags/X.Y.Z`, then `diff -rq trunk tags/X.Y.Z` must print nothing. `Stable tag` in `trunk/readme.txt` and the tag's readme must equal X.Y.Z. Trunk, assets and the tag can go in one commit.
6. Review `svn status`, then commit **in Terminal yourself**: `svn commit --username tvcnet -m "Release X.Y.Z"`. It needs the WordPress.org SVN password (set under Account & Security in the WordPress.org profile; required if two-factor is on). The login SVN had saved was rejected with E215004, so a non-interactive commit fails. Never share the password in a chat.
7. Verify straight away: `svn ls https://plugins.svn.wordpress.org/hackrepair-plugin-archiver/tags/` lists X.Y.Z; the plugin-information API (`https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=hackrepair-plugin-archiver`) shows version, tested, requires, icons and banners; the download zip (`https://downloads.wordpress.org/plugin/hackrepair-plugin-archiver.X.Y.Z.zip`) matches the build with `diff -rq`.
8. Verify later (the page and the update-check service lag the API by minutes up to about an hour): the public plugin page shows the new version, banner and changelog; `https://api.wordpress.org/plugins/update-check/1.1/` offers the new version for an installed older copy; then update a test site from the previous version through the WordPress updater (Dashboard > Updates) and run the plugin.
9. GitHub, only now: `gh release create X.Y.Z --target main --title "Version X.Y.Z" --notes-file releases/vX.Y.Z.md` (plain tag, no attached files, matching earlier releases). Confirm `gh api repos/hackrepair/hackrepairpluginarchiver/releases/latest --jq .tag_name` equals the Stable tag.
10. Readme-only or image-only fixes: commit to SVN trunk or `assets/` without a new tag.

## Roadmap

Decided October 20, 2026 after reviewing a competing plugin. Ideas only; nothing is copied from it.

- **3.1.2**: header and readme refresh, version bump. No behavior change. Never published on its own.
- **3.2.0** (security): random 12-character archive folder names (existing archives registered and offered a "Secure this archive" rename), web-access protection files (`.htaccess`, `web.config`, `index.php`, marker) with a self-request reachability check and a Site Health test, `DISALLOW_FILE_MODS` support, a direct-write precheck, the nested-function fix.
- **3.3.0** (records): a record of archived plugins with "Archived on" and "In the archive for" columns, an activity log (capped, with CSV export), download as zip, `uninstall.php`.
- **3.3.1** (fix): no "Archive" link inside the Archived Plugins list; the log shows plugin names for plugins archived before 3.3.0. Found by testing the upgrade from 3.1.1 on a live site.
- **3.3.2** (fix and polish, **published**): Delete asks for confirmation; settings messages no longer doubled; no "0 plugins ... successfully" when all were skipped; read-only mode disables New Archive and hides list checkboxes; refused actions return 403; unnamed archives are labelled "Archive" plus six characters of the folder name; Plugin Check clean-up (private core function removed, submitted values sanitized, `Tested up to` 7.1, `load_plugin_textdomain()` removed).
- Rejected for now: health flags, WP-CLI, email digest, auto-archive, auto-delete, clean-up-and-delete, update on restore, multisite automation.

The full specs are in the project documents (`build-plan-3.2-3.3.md`).

## Third-party code

None bundled.

## Limits and open work

- Multisite: archive screens and Download need a network administrator; uninstall cleans every site. Not yet tested on a real network.
- nginx: `.htaccess` has no effect there; the reachability check and notice are the safeguard. Not yet tested on a real nginx site.
- Tested on a live site (hackguard.com): upgrade from 3.1.1, archive, restore, bulk actions, dates, sorting, log, CSV export, download, Check again, Secure this archive. Tested on the local sandbox: delete, a second archive and switching, clear log, archive an active plugin, duplicate-folder skip, read-only mode.
- No translations are shipped yet (`languages/` has only the index file).
- Old SVN tags 3.0.1, 3.0.2 and 3.1.0 were never created.
- Old commits that carried embedded image metadata were removed from `main`'s history in October 2026; GitHub may still serve the unreferenced objects by exact SHA until its own cleanup runs. A support request is drafted if it is ever wanted.


## Companion-plugin guide — October 25, 2026 (pending release)

The native View Details **Other THRG Plugins** tab lists the other five tools: Disable Other Plugin Notices, Group Password Reset, Plugin Detective, Site Inspector, Post Update Email Notifier and Plugin Archiver, excluding the current plugin. Plugin Archiver always links to https://wordpress.org/plugins/hackrepair-plugin-archiver/; the GitHub-distributed tools link to their own repositories. Cards are informational links, with no automatic installs or external requests. Archiver retains WordPress.org updates and metadata. No published version, tag or release asset is changed by this source update.

Verification: shared PHPCS and `php tests/companion-plugins.php` pass for this checkout. Native WordPress View Details on Zendogs renders five distinct companion cards, with no self-link and Archiver linked only to WordPress.org. Archiver uses `plugins_api_result` for official metadata and preserves ratings/version/update controls; the test used a temporary loader beside the older installed Archiver, not a full upgrade. Temporary preview files are restored after testing; no reset, archive, delete or email action was performed. Published releases remain unchanged.


## Security reporting standard — October 26, 2026

Added the common SECURITY.md policy, repository-specific private reporting destination, fallback email, qualified response targets and product limits. GitHub private reporting is enabled and API-verified. The reusable verifier and `docs/security-reporting-verification.md` describe repeat checks and separate API, browser, inbox and application-security evidence. The maintainer browser forms are now verified for all six repositories; outside-reporter access and fallback mailbox delivery remain untested. See `docs/security-reporting-verification-2026-10-26.md`. This updates repository policy/docs and packaging exclusions only; no plugin version or release changes.
