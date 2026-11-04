<p align="center">
  <img src=".wordpress-org/banner-772x250.png" alt="The Hack Repair Guy's Plugin Archiver" width="772" height="250">
</p>

<h1 align="center">The Hack Repair Guy's Plugin Archiver</h1>

<p align="center">
  <strong>Disable plugins without deleting them. Archive any plugin in one click, restore it whenever you need it.</strong>
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/"><img src="https://img.shields.io/wordpress/plugin/v/hackrepair-plugin-archiver?label=WordPress.org" alt="Version on WordPress.org"></a>
  <a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/"><img src="https://img.shields.io/wordpress/plugin/installs/hackrepair-plugin-archiver" alt="Active installs"></a>
  <a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/"><img src="https://img.shields.io/wordpress/plugin/rating/hackrepair-plugin-archiver" alt="Rating"></a>
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/"><strong>Get it on WordPress.org</strong></a> ·
  <a href="https://hackrepair.com/about/hackrepair-plugin-archiver">Project page</a> ·
  <a href="https://wordpress.org/support/plugin/hackrepair-plugin-archiver/">Support forum</a> ·
  <a href="https://wordpress.org/support/plugin/hackrepair-plugin-archiver/reviews/">Reviews</a>
</p>

Plugin Archiver moves a plugin's files out of `wp-content/plugins` into a separate archive folder. WordPress stops listing it, so it cannot be activated by accident, but nothing is deleted and its settings stay in the database.

**Install and updates come from WordPress.org.** In your dashboard, open **Plugins → Add New** and search for "The Hack Repair Guy's Plugin Archiver", or use the link above. This GitHub repository is the source code and history.

Current release: **3.3.3**. See the [release notes](releases/v3.3.3.md).

## What it does

- **Archive** any plugin from Installed Plugins, one at a time or in bulk
- **Restore**, **delete** or **download as a zip** from Archived Plugins, with the date each plugin was archived
- **Random, web-blocked archive folders**, with a Site Health check
- **Activity log** with filters and CSV export
- Respects `DISALLOW_FILE_MODS`; nothing is overwritten

Full details, screenshots and the changelog are on the [WordPress.org plugin page](https://wordpress.org/plugins/hackrepair-plugin-archiver/).

## Privacy and scope

The plugin sends nothing about your site anywhere and adds nothing to the public side of your site. It is not a backup tool, a malware scanner or a plugin manager.

## More from The Hack Repair Guy

- [Bunker](https://github.com/hackrepair/wp-bunker "WordPress behind a static site")
- [Stickies](https://github.com/hackrepair/thrg-stickies "Pin sticky notes to any page")
- [Site Inspector](https://github.com/hackrepair/thrg-site-inspector "Read-only security checks with report history")
- [Plugin Detective](https://github.com/hackrepair/hackrepair-plugin-detective "Find the plugin that broke your site")
- [Disable Other Plugin Notices](https://github.com/hackrepair/disable-other-plugin-notices "Group other plugins' admin notices into one panel")
- [Group Password Reset](https://github.com/hackrepair/group-password-reset "Bulk reset passwords and send secure reset links")
- [Post Update Email Notifier](https://github.com/tvcnet/hackrepair-post-update-email-notifier "Email your team when published content changes")

## Author and support

**[Jim Walker](https://hackrepair.com/)**, The Hack Repair Guy, author and maintainer. Developer notes: [PROJECT.md](PROJECT.md "Architecture, release process, design decisions and known limits").

[Support forum](https://wordpress.org/support/plugin/hackrepair-plugin-archiver/) · [Report a bug](https://github.com/hackrepair/hackrepairpluginarchiver/issues) · [Security reporting](SECURITY.md) · [Buy Jim a coffee](https://hackrepair.com/donations/buy-jim-a-coffee)

## License

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html). The default archive-folder randomization began as a 2015 contribution by Arūnas Liuiza.

## Changelog

See the **Changelog** section of [`readme.txt`](readme.txt) or the [WordPress.org page](https://wordpress.org/plugins/hackrepair-plugin-archiver/#developers).
