=== The Hack Repair Guy's Plugin Archiver ===

Contributors: hackrepair, tvcnet
Tags: plugin archive, archive plugins, plugin backup, plugin restore, manage plugins, deactivate plugin
Donate link: https://hackrepair.com/about/hackrepair-plugin-archiver
Requires at least: 6.6
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 3.1.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Archive any plugin with one click. Archived plugins are hidden from your Plugins list and safely moved out of wp-content/plugins into a dedicated archive directory for easy restore later. Ideal for preventing accidental re-activation, testing different plugin sets, and keeping occasional-use tools out of sight until needed.

[youtube https://www.youtube.com/watch?v=i_Gn22bWLVs]

= Features =
- One‑click Archive link on the Plugins screen
- Bulk Archive / Unarchive / Delete actions
- Optional auto‑deactivate before archiving (recommended)
- Manage multiple archive directories and switch between them
- “Unarchive All” button on the Archived Plugins tab
- Non‑destructive: archiving does not remove a plugin’s settings/data
- Uses the built‑in WordPress Filesystem API with capability and nonce checks

= How it works =
- When you Archive a plugin, its folder is moved from wp-content/plugins to your chosen archive directory (e.g., wp-content/plugins-archive-xxxxxx).
- Archived plugins are removed from the Plugins list, so they can’t be accidentally activated.
- You may switch the active archive directory or create new ones in Settings → Plugin Archiver.

= Common use cases =
- Maintain a “toolbox” of favorite utilities you don’t need to see every day
- Quarantine buggy or compromised plugins to avoid accidental activation
- Test different plugin stacks by pulling sets into and out of the archive
- Share management with others while reducing “oops, I clicked Activate” incidents

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
By default in wp-content/plugins-[name] (e.g., plugins-archive-xxxxxx). You can create and switch archives in Settings → Plugin Archiver.

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

== Screenshots ==
1. Archive action on the Plugins screen
2. Archived Plugins list with bulk actions
3. Settings screen with archive directory controls

== Changelog ==

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
