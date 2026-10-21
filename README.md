<p align="center">
  <img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench logo" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Plugin Archiver</h1>

Disable plugins without deleting them. Archive any plugin in one click, restore it whenever you need it.

Sites collect plugins that are switched off but not quite ready to be deleted: something you are testing, a tool you use twice a year, a plugin that broke the site and that nobody should click Activate on again. Plugin Archiver moves the plugin's files out of `wp-content/plugins` into a separate archive folder. WordPress stops listing it, so it cannot be activated by accident, but nothing is deleted and its settings stay in the database.

Current release: **3.1.2**. See [release notes](releases/v3.1.2.md).

## What it does

**Plugins → Installed Plugins** gets an **Archive** link under every plugin, and **Archive** joins the bulk actions.

**Plugins → Archived Plugins** lists what is in the archive. From there you can:

- **Restore** a plugin to `wp-content/plugins`, one at a time or in bulk
- **Delete** a plugin's files from the archive, one at a time or in bulk
- **Unarchive all** at once

**Settings → Plugin Archiver** controls:

- Auto-deactivate before archiving (recommended, on by default)
- Several archive folders, and which one is active

Archiving moves files only. A plugin's saved settings and data stay where they were, so a restored plugin comes back configured.

## Privacy and scope

The plugin makes no outside requests, sends nothing about your site anywhere, and adds nothing to the public side of your site. It uses the WordPress Filesystem API and checks capabilities and nonces on every action. Archived plugins stay on your server in the archive folder until you restore or delete them.

It is not a backup tool, a malware scanner or a plugin manager. Archiving removes a plugin from WordPress's view; it does not make a vulnerable plugin safe to keep on disk. If you archive a plugin because it is compromised or abandoned, delete it from the archive when you are done with it.

## Install

Install from **Plugins → Add New** (search for "The Hack Repair Guy's Plugin Archiver") or from [WordPress.org](https://wordpress.org/plugins/hackrepair-plugin-archiver/ "Plugin Archiver on WordPress.org"), then activate it. To install by hand, upload the `hackrepair-plugin-archiver` folder to `wp-content/plugins/`.

Updates come from WordPress.org. This repository is the source and history of the plugin.

## Development

Requires WordPress 6.6+ and PHP 7.4+. Tests are plain scripts:

```
python3 tests/package-permissions.py
```

Run shared PHPCS with `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"`. Build a ZIP with `python3 scripts/build-release.py` (writes to `dist/`).

See [PROJECT.md](PROJECT.md "Architecture, release process, design decisions and known limits") for the architecture, the WordPress.org release process and known limits.

## Author and support

**[Jim Walker](https://hackrepair.com/)** — The Hack Repair Guy — author and maintainer.

[Questions and help](https://wordpress.org/support/plugin/hackrepair-plugin-archiver/ "Plugin Archiver support forum on WordPress.org") · [Report a bug](https://github.com/hackrepair/hackrepairpluginarchiver/issues) · [Security reporting](mailto:jim%40hackrepair%2Ecom) · [Buy Jim a coffee](https://hackrepair.com/donations/buy-jim-a-coffee)

## License and credits

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html "Read the GNU General Public License, version 3"). The default archive-folder randomization began as a 2015 contribution by Arūnas Liuiza.

Made by [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair").

## Changelog

See the **Changelog** section of [`readme.txt`](readme.txt).
