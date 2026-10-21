# The Hack Repair Guy's Plugin Archiver: project notes

Developer notes. For what the plugin does, see [README.md](README.md "What the plugin does and how to install it").

Current published release: **3.1.1** on WordPress.org (SVN tag `3.1.1`, September 11, 2025). Repository state: 3.1.1 plus unreleased code-style cleanups imported from the November 30, 2025 working folder. Next planned version: **3.1.2** (not yet bumped).

## Distribution (different from the GitHub-updated plugins)

This plugin is distributed by **WordPress.org**, not GitHub releases. That changes the shared Hack Repair plugin standard:

- **No bundled `plugin-update-checker/` and no updater class.** WordPress.org serves updates; plugins in the directory must not ship their own update mechanism.
- Release = SVN, not `gh release`. Steps are below. The ZIP built by `scripts/build-release.py` is for local testing and for the `releases/` archive; the directory builds its own download from the SVN tag.
- Directory images live in `.wordpress-org/` here and are copied to SVN `assets/` (never into the plugin ZIP).

## GitHub repo

`hackrepair/hackrepairpluginarchiver` is the source and history mirror: contributors Jim Walker (91 commits) and Arūnas Liuiza (15 commits, 2015, including "randomize default plugin archive directory"). Tags 1.0.5 to 2.0.3 and 3.1.1; 3.0.x and 3.1.0 were never tagged. GitHub is not an update channel for this plugin: WordPress.org SVN is. Push source/tags to GitHub after each SVN release so the two stay in step.

## Layout (outside the repo, in `../`)

- `svn/hackrepair-plugin-archiver/`: live Subversion working copy of `https://plugins.svn.wordpress.org/hackrepair-plugin-archiver` (trunk, tags, assets). Last known: trunk r3359560, tag 3.1.1 r3359562, repo head seen r3360019. Run `svn up` before anything else.
- `releases/`: zip snapshots of 2.0.4, 3.0.1, 3.0.2, 3.1.0, 3.1.1 and the 2025.11.30 cleanup build. Never committed.
- `legacy/`: 2015 original (0.1.0), the 2.0.4 source folder, banner PSDs, older artwork.
- `_to_delete/`: leftovers waiting for the delete-permission tool.

## Repo layout

- `hackrepair-plugin-archiver.php`: header, `HackRepair_Plugin_Archiver` class. Archive/restore/delete actions, Plugins-screen "Archive" row link and bulk actions, Plugins > Archived Plugins page, Settings link, admin notices.
- `includes/bulk.php`: `HackRepair_Plugin_Archiver_Bulk_Action` (bulk action registration and handling on the Plugins screen).
- `includes/list.php`: `WP_Plugins_Archive_List_Table` (the Archived Plugins table).
- `includes/options.php`: `HackRepair_Plugin_Archiver_Options` (Settings > Plugin Archiver, archive directory management).
- `includes/pointers.php`, `includes/notes.php`: admin pointer and the notes panel.
- `.wordpress-org/`: banner (772x250) and icons (128, 256) for the directory. A 1544x500 retina banner is not yet made.
- `docs/audits/`: the November 2024 security and PHP 8.3 audits (of 2.0.4). Historical; 3.x addressed most findings.
- `scripts/build-release.py`, `tests/package-permissions.py`: ZIP builder (0644 modes, verified byte-for-byte) and its test.
- `phpcs.xml.dist`: WordPress rules + PHPCompatibilityWP 7.4+. Run the shared install: `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"`.

## How it works (design facts)

- Archiving moves the plugin folder from `wp-content/plugins` into an archive directory under `wp-content/` (default `plugins-archive-<6 chars of md5(site url)>`; multiple archive dirs supported) using the WordPress Filesystem API. Nothing is deleted from the database.
- Option key: `hackrepair-plugin-archiver_options` (`archive_dir`, `archive_dir_add`, `deactivate`).
- Capability `install_plugins` for archive/restore/delete; `manage_options` for settings. Nonces on every action; path validation rejects `..` and unexpected characters (3.0.x hardening).
- No outbound requests, no scheduled jobs.

## Release process (WordPress.org SVN)

1. `cd ../svn/hackrepair-plugin-archiver && svn up && svn stat` (should be clean).
2. In this repo: bump `Version:` in `hackrepair-plugin-archiver.php`, `Stable tag` and a changelog entry in `readme.txt`; review `Tested up to` / `Requires at least` / `Requires PHP`; write `releases/vX.Y.Z.md`.
3. Run PHPCS and a manual test (archive, restore, bulk, settings, multiple dirs) on a local site; run Plugin Check on the built ZIP; `python3 tests/package-permissions.py`.
4. Commit in git (plain message, author tvcnet, no trailers).
5. Sync into trunk: copy the plugin files (everything the build script ships, not `.wordpress-org/`) into `../svn/hackrepair-plugin-archiver/trunk/`, then `svn add --force trunk`, `svn rm` anything removed (`svn stat` shows `!`), `svn diff`.
6. `svn ci -m "Release X.Y.Z: ..." --username <wp.org user>` (SVN password, separate from the login password; two-factor required).
7. `svn cp trunk tags/X.Y.Z && svn ci -m "Tagging version X.Y.Z"`. `Stable tag` in trunk `readme.txt` must equal the tag.
8. Image-only or readme-only changes: commit to `assets/` or trunk without a new tag.
9. Verify on https://wordpress.org/plugins/hackrepair-plugin-archiver/ (allow ~15 min) and test an update from the previous version.

## Status at project setup (October 20, 2026)

- Source imported from `Archiver/2025.11.30/hackrepair-plugin-archiver/`, the newest working copy. It differs from SVN trunk 3.1.1 only by code-style changes: `index.php` docblock, strict `in_array(..., true)` and `===`, whitespace, a few reformatted blocks in `hackrepair-plugin-archiver.php`, `bulk.php`, `list.php`, `notes.php`, `pointers.php`. No behavior change intended; verify before release.
- Git repo is linked to the public GitHub repo `https://github.com/hackrepair/hackrepairpluginarchiver` (remote `origin`). Its history (106 commits since July 21, 2015; latest is `v3.1.1`, September 10, 2025, matching SVN trunk) is loaded under the working files, so `git status` shows the unreleased cleanups and new project files as changes. Local branch is `main`, GitHub's default is `master`: decide which to keep before the first push. Nothing has been committed or pushed.
- Not yet done: no tests beyond packaging; no `uninstall.php` (decide whether to remove the options on uninstall); header lacks `Requires at least`, `Requires PHP`, `License`, `Text Domain`; `Plugin URI`/`Author URI` use http; `readme.txt` says `Tested up to: 6.8.2`.
- Old SVN tags 3.0.1, 3.0.2 and 3.1.0 were never created; only 3.1.1 exists above 2.0.4.

## Open questions

- WordPress.org username to commit with (`tvcnet` is the committer on past commits).
- Support destination: GitHub Discussions/Issues on the repo above, or the WordPress.org support forum (the README currently points to the forum).
- Branch name: keep `main` locally and rename the GitHub default branch, or work on `master`.
- Scope of 3.1.2: cleanups only, or also header/readme modernization and an uninstall routine.
