<?php
/**
 * Removes this plugin's own settings when it is deleted.
 * Archived plugins and archive folders are left exactly as they are.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$hackrepair_plugin_archiver_registry = get_option( 'hackrepair-plugin-archiver_archives', array() );
if ( is_array( $hackrepair_plugin_archiver_registry ) ) {
	foreach ( array_keys( $hackrepair_plugin_archiver_registry ) as $hackrepair_plugin_archiver_dir ) {
		delete_transient( 'hrpa_reach_' . md5( (string) $hackrepair_plugin_archiver_dir ) );
	}
}

foreach ( array(
	'hackrepair-plugin-archiver_options',
	'hackrepair-plugin-archiver_archives',
	'hackrepair-plugin-archiver_db_version',
	'hackrepair-plugin-archiver_manifest',
	'hackrepair-plugin-archiver_log',
) as $hackrepair_plugin_archiver_option ) {
	delete_option( $hackrepair_plugin_archiver_option );
}
