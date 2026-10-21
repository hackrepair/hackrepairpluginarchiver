<?php
/**
 * Notes content for the HackRepair Plugin Archiver plugin.
 *
 * Displays descriptive text about the plugin on the settings screen.
 *
 * @package hackrepair-plugin-archiver
 */
// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><strong>What this plugin does</strong></p>
<p>Archive any plugin with one click. Archived plugins are hidden from your Plugins list and safely moved out of the active <code>wp-content/plugins</code> directory into a dedicated archive directory. You can restore (Unarchive) or permanently Delete them later.</p>

<p><strong>Why I built it</strong></p>
<p>In my day‑to‑day malware cleanup work, toggling a broken plugin back on by accident could take a site down. Deactivating wasn't enough—someone would re‑activate it. Archiving removes the temptation.</p>

<p><strong>Highlights</strong></p>
<ul class="ul-disc">
<li>One‑click Archive from the Plugins screen</li>
<li>Bulk Archive / Unarchive / Delete actions</li>
<li>Optional auto‑deactivate before archiving (recommended)</li>
<li>Manage multiple archive directories and switch between them</li>
<li>"Unarchive All" button on the Archived Plugins tab</li>
<li>No data loss—archiving does not delete plugin settings or data</li>
</ul>

<p><strong>Quick start</strong></p>
<ol>
<li>Go to Plugins → Installed Plugins.</li>
<li>Click “Archive” under any plugin you want to set aside.</li>
<li>Manage archived plugins under Plugins → Archived Plugins, or Settings → Plugin Archiver → Archived Plugins.</li>
</ol>

<p><strong>Where are archived plugins stored?</strong><br>
By default in <code>wp-content/plugins-…</code>. You can create and switch archives in Settings → Plugin Archiver.</p>

<p><strong>Notes</strong></p>
<ul class="ul-disc">
<li>Archiving does not uninstall plugins or erase their data.</li>
<li>“Unarchive on plugin deletion” is not supported because multiple archives may exist.</li>
</ul>

<p><strong>Documentation</strong><br>
<a href="https://hackrepair.com/about/hackrepair-plugin-archiver" target="_blank" rel="noopener noreferrer">Usage guide and video</a></p>

<p><strong>Like it?</strong><br>
Please consider a small donation: <a href="https://hackrepair.com/about/hackrepair-plugin-archiver" target="_blank" rel="noopener noreferrer">Donate</a>.</p>

<p><strong>Enjoy!</strong></p>