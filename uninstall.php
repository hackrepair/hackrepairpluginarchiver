<?php
/**
 * Removes this plugin's own settings when it is deleted, on every site of a network.
 * Archived plugins and archive folders are left exactly as they are.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the plugin's options and transients for the current site.
 */
function hackrepair_plugin_archiver_uninstall_site() {
	$registry = get_option( 'hackrepair-plugin-archiver_archives', array() );
	if ( is_array( $registry ) ) {
		foreach ( array_keys( $registry ) as $dir ) {
			delete_transient( 'hrpa_reach_' . md5( (string) $dir ) );
		}
	}
	foreach ( array(
		'hackrepair-plugin-archiver_options',
		'hackrepair-plugin-archiver_archives',
		'hackrepair-plugin-archiver_db_version',
		'hackrepair-plugin-archiver_manifest',
		'hackrepair-plugin-archiver_log',
	) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $hackrepair_plugin_archiver_site_id ) {
		switch_to_blog( $hackrepair_plugin_archiver_site_id );
		hackrepair_plugin_archiver_uninstall_site();
		restore_current_blog();
	}
} else {
	hackrepair_plugin_archiver_uninstall_site();
}
