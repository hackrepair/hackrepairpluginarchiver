=== The Hack Repair Guy's Plugin Archiver ===
Contributors: hackrepair, tvcnet
Tags: archive, plugins, deactivate, plugin manager, security
Donate link: https://hackrepair.com/about/hackrepair-plugin-archiver
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 3.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Support link: https://github.com/hackrepair/hackrepairpluginarchiver/discussions

Disable plugins without deleting them. Archive in one click, restore any time, and keep the archive hidden and blocked from the web.

== Description ==

Plugins you switch off but never delete pile up, and one day somebody clicks Activate on the one that broke the site, or the one that was hacked. Plugin Archiver moves a plugin's files out of wp-content/plugins into a separate archive folder. WordPress stops listing it, so it cannot be activated by accident, but nothing is deleted and its settings stay in the database. Restore it whenever you want, configured as you left it.

[youtube https://www.youtube.com/watch?v=i_Gn22bWLVs]

Under **Plugins > Installed Plugins** every plugin gets an **Archive** link, and Archive joins the bulk actions. Under **Plugins > Archived Plugins** you can:

* Restore a plugin to wp-content/plugins, one at a time or in bulk
* Delete a plugin's files from the archive, one at a time or in bulk
* Unarchive all at once
* Switch between several archive folders

**Archives are hard to find.** New archive folders get random names such as plugins-archive-k3x9a7q2m5zt, so the location cannot be guessed. Archives made by earlier versions keep working, and one click on **Secure this archive** gives them a random name.

**Archives are blocked from the web.** Every archive folder gets deny rules for Apache (2.2 and 2.4) and IIS plus an index file, rewritten if they go missing. The plugin checks that they work by requesting a small marker file from your own site, warns you on screen if a folder can still be read, and adds a test to Site Health. Archived plugins are often old and sometimes vulnerable, so they should never be downloadable.

**Respects locked-down sites.** If your site sets DISALLOW_FILE_MODS, archiving, restoring and deleting are turned off and the lists are read-only. The plugin never overwrites a plugin that already exists at the destination.

Good for preventing accidental re-activation, quarantining a buggy or compromised plugin, testing different plugin sets, and keeping occasional-use tools out of sight until needed.

= Privacy =

The plugin sends nothing about your site anywhere and adds nothing to the public side of your site. Its one network request goes to your own site: a check that the archive folder cannot be read from the web, made at most every 12 hours or when you press Check again. It uses the WordPress Filesystem API and checks capabilities and nonces on every action. Archived plugins stay on your server until you restore or delete them.

= What it is not =

Plugin Archiver is not a backup tool, a malware scanner or a plugin manager. Archiving removes a plugin from WordPress's view; it does not make a vulnerable plugin safe to keep on disk. If you archive a plugin because it is compromised or abandoned, delete it from the archive when you are done. If you want a deeper look at a site, [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair") can help.

= Other Hack Repair Guy plugins =

* [Site Inspector](https://github.com/hackrepair/thrg-site-inspector "Site Inspector on GitHub") keeps a history of read-only security checks, so you can see what is new and what is fixed.
* [Plugin Detective](https://github.com/hackrepair/hackrepair-plugin-detective "Plugin Detective on GitHub") finds the plugin that broke your site in a few quick checks, even when wp-admin is down.
* [Disable Other Plugin Notices](https://github.com/hackrepair/disable-other-plugin-notices "Disable Other Plugin Notices on GitHub") groups notices from other plugins and themes into one collapsed panel.
* [Group Password Reset](https://github.com/hackrepair/group-password-reset "Group Password Reset on GitHub") lets administrators bulk reset passwords and send secure reset links.
* [Admin Login Notifier](https://github.com/hackrepair/hackrepair-admin-login-notifier "Admin Login Notifier on GitHub") tells you the moment an Administrator logs in to your dashboard.

= Credits =

Created and maintained by [Jim Walker](https://hackrepair.com/ "Jim Walker, The Hack Repair Guy"). Source and history: [hackrepair/hackrepairpluginarchiver](https://github.com/hackrepair/hackrepairpluginarchiver "Plugin Archiver source on GitHub").

== Frequently Asked Questions ==

= Why was this plugin created? =
In my day‑to‑day work cleaning malware from hacked websites, I often saw sites break after a plugin update. Deactivating the problem plugin wasn’t enough—someone would “Activate” it later and take the site down again. Archiving removes the temptation entirely.

= If I deactivate or remove this plugin, what happens to archived plugins? =
Nothing changes. Archived plugins remain safely in your archive directories until you unarchive them or move them back manually.

= Are you planning an “unarchive all on plugin deletion” option? =
No. The Plugin Archiver supports multiple archive directories; since duplicates may exist, it wouldn’t know which version to unarchive. However, there is an “Unarchive All” button on the Archived Plugins tab while the plugin is active.

= Why not just delete plugins? =
Some plugins remove their settings/data on deletion. Archiving keeps everything intact so you can restore later without reconfiguration.

= Where are archived plugins stored? =
In a folder inside wp-content with a random name, such as plugins-archive-k3x9a7q2m5zt. Archives made by earlier versions keep their old names until you choose Secure this archive in Settings. You can create and switch archives in Settings → Plugin Archiver.

= Can someone download my archived plugins from the web? =
Each archive folder contains rules that tell the web server to refuse all requests (.htaccess for Apache 2.2 and 2.4, web.config for IIS) and an index.php. The plugin checks that the rules work by requesting a small marker file from your own site, and warns you on screen and in Site Health if it can be read. Nginx ignores .htaccess; add: location ^~ /wp-content/your-archive-folder { deny all; } (the warning shows your exact folder name).

= What does the plugin request from my own site? =
One request to the marker file inside your archive folder, at most every 12 hours or when you press Check again. Nothing is sent to any other site.

= What happens if my site sets DISALLOW_FILE_MODS? =
Archiving, restoring and deleting are turned off, the Archive links and bulk actions are hidden, and the lists stay visible.

= I upgraded and my archive folder has an old, predictable name. =
It keeps working. Open Settings → Plugin Archiver and choose Secure this archive to rename it to a random name.

= Does archiving delete plugin data? =
No. Archiving only moves plugin files.

== Installation ==

= Quick install from WordPress =
1. Go to Plugins → Add New.
2. Search for “The Hack Repair Guy’s Plugin Archiver”.
3. Install and Activate.

= Manual installation =
1. Upload the hackrepair-plugin-archiver folder to /wp-content/plugins/
2. Activate the plugin through the Plugins menu

= Quick start =
1. Go to Plugins → Installed Plugins and click “Archive” under any plugin you want to set aside.
2. Manage archived plugins at Plugins → Archived Plugins, or via Settings → Plugin Archiver → Archived Plugins.
3. Use “Unarchive” to restore a plugin to wp-content/plugins. Use “Delete” to remove it from the archive.

== Support ==

For questions, ideas and community support, visit GitHub Discussions:
https://github.com/hackrepair/hackrepairpluginarchiver/discussions

The WordPress.org support forum is also monitored:
https://wordpress.org/support/plugin/hackrepair-plugin-archiver/

Report reproducible bugs through Issues:
https://github.com/hackrepair/hackrepairpluginarchiver/issues

Report security concerns privately to Jim Walker at jim@hackrepair.com. Do not include credentials or sensitive site information in public posts.

== Screenshots ==
1. Archive action on the Plugins screen
2. Archived Plugins list with bulk actions
3. Settings screen with archive directory controls

== Changelog ==

= 3.2.0 =
- New archive folders get random names (for example plugins-archive-k3x9a7q2m5zt); archives from earlier versions are registered automatically and can be renamed with Secure this archive
- Archive folders are blocked from the web with .htaccess (Apache 2.2 and 2.4), web.config and index.php, rewritten if missing; a self-check warns if a folder can still be read, and a Site Health test reports it
- Archiving, restoring and deleting honor DISALLOW_FILE_MODS
- Archiving and restoring never overwrite an existing plugin at the destination
- Only registered archives can be selected on the Archived Plugins screen
- Code cleanup: removed unused commented-out code; tab navigation output is escaped
- Readme restructured to match the Hack Repair Guy plugin standard: plain-language description, Privacy, What it is not, Support (GitHub Discussions, Issues, private security contact), Credits and tooltip links

= 3.1.2 =
- Code-style cleanups: strict comparisons and consistent formatting; no behavior change
- Plugin header now declares required WordPress and PHP versions, license and text domain, and uses https links
- Readme refresh

= 3.1.1 =
- Removed all debug logging statements to reduce log clutter in production environments
- Debug logs were only active when WP_DEBUG is enabled but were unnecessary for production use

= 3.1.0 =
- Added PHP 8.3 compatibility by fixing strpos() calls with potential null/false values from realpath()
- Improved error handling in get_archived_plugins, bulk_remove, and bulk_restore functions
- Removed debug logging statements to reduce log clutter in production environments
- No breaking changes; maintains backward compatibility

= 3.0.2 =
- Security hardening: switched to wp_safe_redirect() and added exit after redirects; fortified path traversal defenses and CSRF protection
- Added nonce to "Unarchive All" action
- Additional path traversal checks and stricter input validation
- Output escaping improvements in admin list table
- Readme updates; no breaking changes

= 3.0.1 =
- Documentation polish and typo fixes; no functional changes

= 3.0 =
- Major version update: Hardened against path traversal and CSRF vulnerabilities from prior versions (up to 2.0.4) through enhanced validation, nonces, and safer filesystem operations
- Improved compatibility with WordPress 6.8
- Added support for custom archive directory management
- Enhanced user interface for better user experience
- Fixed minor bugs and improved overall performance

= 2.0.3 =
- 4.8.1 Compatible

= 2.0.1 =
- Fixed relative includes error on WordPress 4.4

= 2.0.0 =
- Randomized default plugin archive directory

= 1.0.9 =
- Fixed WP Rocket incompatibility.

= 1.0.8 =
- Fixed iThemes Sync incompatibility.

= 1.0.7 =
- Updated video in description.

= 1.0.5 =
- Updated description.

= 1.0.0 =
- The Hack Repair Guy’s Plugin Archiver is born!
