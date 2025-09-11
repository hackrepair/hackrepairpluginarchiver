# The Hack Repair Guy's Plugin Archiver

Archive any plugin with one click. Archived plugins are hidden from your Plugins list and safely moved out of `wp-content/plugins` into a dedicated archive directory for easy restore later.

## Why use it?
- Keep a toolbox of utilities out of sight until needed
- Quarantine buggy/compromised plugins to avoid accidental activation
- Test plugin stacks by archiving and restoring sets
- Reduce “oops, I clicked Activate” incidents when sharing admin access

## Features
- One‑click Archive link on the Plugins screen
- Bulk Archive / Unarchive / Delete actions
- Optional auto‑deactivate before archiving (recommended)
- Manage multiple archive directories and switch between them
- “Unarchive All” button on the Archived Plugins tab
- Non‑destructive: archiving does not remove a plugin’s settings/data

## Quick start
1. Go to Plugins → Installed Plugins and click “Archive” under any plugin.
2. Manage archived plugins at Plugins → Archived Plugins, or via Settings → Plugin Archiver → Archived Plugins.
3. Use “Unarchive” to restore to `wp-content/plugins`, or “Delete” to remove from the archive.

## Where are archived plugins stored?
By default in `wp-content/plugins-[name]` (e.g., `plugins-archive-xxxxxx`). You can create and switch archives in Settings → Plugin Archiver.

## Video
https://www.youtube.com/watch?v=i_Gn22bWLVs

## Learn more, or need help?
Guide and documentation: https://hackrepair.com/about/hackrepair-plugin-archiver

—
Built by Jim Walker, The Hack Repair Guy — https://HackRepair.com
