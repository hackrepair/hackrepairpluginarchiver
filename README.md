<p align="center">
  <img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench logo" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Plugin Archiver</h1>

<p align="center">
  <strong>Disable plugins without deleting them. Archive and restore in one click.</strong><br>
  By Jim Walker, The Hack Repair Guy
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-3.1.1-b34700?style=flat-square" alt="Version 3.1.1">
  <img src="https://img.shields.io/badge/WordPress-6.6%2B-21759b?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress 6.6+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/license-GPLv3-2e7d32?style=flat-square" alt="License GPLv3">
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/"><img src="https://img.shields.io/badge/GET%20IT%20ON%20WORDPRESS.ORG-b34700?style=for-the-badge" alt="Get it on WordPress.org" height="64"></a>
</p>

---

Archive any plugin with one click. Archived plugins are hidden from your Plugins list and safely moved out of `wp-content/plugins` into a dedicated archive directory for easy restore later.

## Why use it?

- Keep a toolbox of utilities out of sight until you need them
- Quarantine buggy or compromised plugins so nobody can click Activate by accident
- Test different plugin sets by archiving and restoring groups
- Reduce "oops, I clicked Activate" when sharing admin access

## Features

- One-click **Archive** link on the Plugins screen
- Bulk Archive / Unarchive / Delete actions
- Optional auto-deactivate before archiving (recommended)
- Multiple archive directories, and switching between them
- "Unarchive All" button on the Archived Plugins tab
- Non-destructive: archiving moves files only and leaves a plugin's settings and data alone
- Uses the WordPress Filesystem API with capability and nonce checks

## Quick start

1. Go to **Plugins > Installed Plugins** and click **Archive** under any plugin.
2. Manage archived plugins at **Plugins > Archived Plugins**, or **Settings > Plugin Archiver > Archived Plugins**.
3. **Unarchive** restores to `wp-content/plugins`. **Delete** removes it from the archive.

Archived plugins live in `wp-content/plugins-archive-xxxxxx` by default. You can create and switch archives in Settings.

## Video

https://www.youtube.com/watch?v=i_Gn22bWLVs

## Development

Developer notes, release process (WordPress.org SVN) and project status are in [PROJECT.md](PROJECT.md "Project notes").

```sh
/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"
python3 tests/package-permissions.py
python3 scripts/build-release.py      # writes dist/<slug>.<version>.zip
```

## Support

Questions and help: the [WordPress.org support forum](https://wordpress.org/support/plugin/hackrepair-plugin-archiver/ "Plugin Archiver support forum"). Guide and documentation: https://hackrepair.com/about/hackrepair-plugin-archiver

Built by Jim Walker, The Hack Repair Guy: https://HackRepair.com

## License

GPLv3. See [LICENSE](LICENSE).
