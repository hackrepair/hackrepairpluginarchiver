<?php
/**
 * Companion tools in this plugin's native WordPress information dialog.
 *
 * @package HackRepair_Plugin_Archiver
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the companion tab without replacing WordPress.org metadata.
 *
 * @param mixed  $result Existing plugin information.
 * @param string $action API action.
 * @param object $args   API arguments.
 * @return mixed
 */
function hackrepair_plugin_archiver_companion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || 'hackrepair-plugin-archiver' !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
		return $result;
	}
	$plugin_file = dirname( __DIR__ ) . '/hackrepair-plugin-archiver.php';
	$stylesheet  = dirname( __DIR__ ) . '/assets/css/plugin-details.css';
	wp_enqueue_style( 'hackrepair-plugin-archiver-details', plugins_url( 'assets/css/plugin-details.css', $plugin_file ), array(), (string) filemtime( $stylesheet ) );
	$result->sections['other_THRG_plugins'] = '<div class="hackrepair_plugin_archiver-toolkit"><p class="hackrepair_plugin_archiver-toolkit-intro">More tools from The Hack Repair Guy</p><p>Five focused ways to look after your WordPress site. Use only what you need.</p><div class="hackrepair_plugin_archiver-toolkit-card"><h3>Group Password Reset</h3><p>Reset passwords by role, choose who to exclude, and optionally send secure reset links.</p><p><a href="https://github.com/hackrepair/group-password-reset" target="_blank" rel="noopener noreferrer">Explore Group Password Reset &rarr;</a></p></div><div class="hackrepair_plugin_archiver-toolkit-card"><h3>Disable Other Plugin Notices</h3><p>Group plugin and theme notices into a collapsible panel while keeping WordPress core notices visible.</p><p><a href="https://github.com/hackrepair/disable-other-plugin-notices" target="_blank" rel="noopener noreferrer">Explore Disable Other Plugin Notices &rarr;</a></p></div><div class="hackrepair_plugin_archiver-toolkit-card"><h3>Plugin Detective</h3><p>Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.</p><p><a href="https://github.com/hackrepair/hackrepair-plugin-detective" target="_blank" rel="noopener noreferrer">Explore Plugin Detective &rarr;</a></p></div><div class="hackrepair_plugin_archiver-toolkit-card"><h3>Site Inspector</h3><p>Review security-related observations, keep inspection reports, and see what changed.</p><p><a href="https://github.com/hackrepair/thrg-site-inspector" target="_blank" rel="noopener noreferrer">Explore Site Inspector &rarr;</a></p></div><div class="hackrepair_plugin_archiver-toolkit-card"><h3>Post Update Email Notifier</h3><p>Notify selected team members when published content changes, with configurable update emails.</p><p><a href="https://github.com/tvcnet/hackrepair-post-update-email-notifier" target="_blank" rel="noopener noreferrer">Explore Post Update Email Notifier &rarr;</a></p></div><p class="hackrepair_plugin_archiver-toolkit-note">Each plugin works independently. Links open in a new tab.</p></div>';
	return $result;
}
// WordPress.org information arrives after plugins_api; handle its result too.
add_filter( 'plugins_api', 'hackrepair_plugin_archiver_companion_plugin_info', 100, 3 );
add_filter( 'plugins_api_result', 'hackrepair_plugin_archiver_companion_plugin_info', 100, 3 );
