<p align="center">
  <img src=".wordpress-org/banner-772x250.png" alt="The Hack Repair Guy's Plugin Archiver" width="772" height="250">
</p>

<h1 align="center">The Hack Repair Guy's Plugin Archiver</h1>

Disable plugins without deleting them. Archive any plugin in one click, restore it whenever you need it.

Sites collect plugins that are switched off but not quite ready to be deleted: something you are testing, a tool you use twice a year, a plugin that broke the site and that nobody should click Activate on again. Plugin Archiver moves the plugin's files out of `wp-content/plugins` into a separate archive folder. WordPress stops listing it, so it cannot be activated by accident, but nothing is deleted and its settings stay in the database.

Current release: **3.3.0**. See [release notes](releases/v3.3.0.md).

## What it does

**Plugins → Installed Plugins** gets an **Archive** link under every plugin, and **Archive** joins the bulk actions.

**Plugins → Archived Plugins** lists what is in the archive. From there you can:

- **Restore** a plugin to `wp-content/plugins`, one at a time or in bulk
- **Delete** a plugin's files from the archive, one at a time or in bulk
- **Unarchive all** at once
- **Download** a plugin as a zip file, without leaving the dashboard
- See **Archived on** and **In the archive for** for every plugin, and sort by either

**Settings → Plugin Archiver** controls:

- Auto-deactivate before archiving (recommended, on by default)
- Several archive folders, and which one is active
- An **Activity Log** tab: who archived, restored, deleted or downloaded what, and when, with filters, paging, CSV export and a clear button
- Archive folder protection: whether each folder is blocked from the web, with **Check again** and **Secure this archive**

**Records and downloads (3.3.0):**

- **Dates.** The plugin remembers when each plugin went into the archive and by whom. Plugins archived before 3.3.0 show an estimate marked "about", taken from the folder's date.
- **Activity log.** The newest 500 actions are kept in your database and never leave your site. Export them as CSV, or clear them (clearing is logged too).
- **Download as zip.** One click builds a zip with a single top-level folder, ready to keep or hand to someone. It needs a user who can activate plugins (a network administrator on multisite) and a valid nonce, skips symbolic links, refuses plugins over 200 MB (change with the `hackrepair_plugin_archiver_download_max_bytes` filter) and works even when `DISALLOW_FILE_MODS` is set, since it reads files only.
- **Clean uninstall.** Deleting the plugin removes its own settings, dates and log, and leaves archived plugins and their folders alone.

**Safer by default (3.2.0):**

- **Random archive folder names.** New archives are named like `plugins-archive-k3x9a7q2m5zt`, so the location cannot be guessed. Archives made by earlier versions are found automatically and kept as they are; **Secure this archive** renames one to a random name.
- **Blocked from the web.** Every archive folder gets deny rules for Apache (`.htaccess`, 2.2 and 2.4), IIS (`web.config`) and an `index.php`. The plugin requests a small marker file from your own site to confirm the rules work, warns you on screen if the folder can be read, and adds a **Site Health** test. On nginx, add `location ^~ /wp-content/<folder> { deny all; }` (the warning shows the exact line).
- **`DISALLOW_FILE_MODS` respected.** When a site turns off file changes, the Archive links, bulk actions and restore/delete buttons are hidden. The Archived Plugins list stays visible, read-only, and Download still works.
- **Nothing is overwritten.** Archiving or restoring is skipped if a plugin with the same folder name is already at the destination, and a notice says which and why.

Archiving moves files only. A plugin's saved settings and data stay where they were, so a restored plugin comes back configured.

## Privacy and scope

The plugin sends nothing about your site anywhere and adds nothing to the public side of your site. Its only network request goes to your own site: an occasional check (at most every 12 hours, or when you press Check again) that the archive folder cannot be read from the web. It uses the WordPress Filesystem API and checks capabilities and nonces on every action. Archived plugins stay on your server in the archive folder until you restore or delete them.

It is not a backup tool, a malware scanner or a plugin manager. Archiving removes a plugin from WordPress's view; it does not make a vulnerable plugin safe to keep on disk. If you archive a plugin because it is compromised or abandoned, delete it from the archive when you are done with it.

## Install

Install from **Plugins → Add New** (search for "The Hack Repair Guy's Plugin Archiver") or from [WordPress.org](https://wordpress.org/plugins/hackrepair-plugin-archiver/ "Plugin Archiver on WordPress.org"), then activate it. To install by hand, upload the `hackrepair-plugin-archiver` folder to `wp-content/plugins/`.

Updates come from WordPress.org. This repository is the source and history of the plugin.

## More from The Hack Repair Guy

- [Site Inspector](https://github.com/hackrepair/thrg-site-inspector "Read-only security checks with report history")
- [Plugin Detective](https://github.com/hackrepair/hackrepair-plugin-detective "Find the plugin that broke your site")
- [Disable Other Plugin Notices](https://github.com/hackrepair/disable-other-plugin-notices "Group other plugins' admin notices into one panel")
- [Group Password Reset](https://github.com/hackrepair/group-password-reset "Bulk reset passwords and send secure reset links")
- [Admin Login Notifier](https://github.com/hackrepair/hackrepair-admin-login-notifier "Get told when an Administrator logs in")

## Author and support

**[Jim Walker](https://hackrepair.com/)** — The Hack Repair Guy — author and maintainer. Developer notes: [PROJECT.md](PROJECT.md "Architecture, release process, design decisions and known limits").

[Questions and help](https://wordpress.org/support/plugin/hackrepair-plugin-archiver/ "Plugin Archiver support forum on WordPress.org") · [Report a bug](https://github.com/hackrepair/hackrepairpluginarchiver/issues) · [Security reporting](mailto:jim%40hackrepair%2Ecom) · [Buy Jim a coffee](https://hackrepair.com/donations/buy-jim-a-coffee)

## License and credits

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html "Read the GNU General Public License, version 3"). The default archive-folder randomization began as a 2015 contribution by Arūnas Liuiza.

Made by [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair").

## Changelog

See the **Changelog** section of [`readme.txt`](readme.txt).
