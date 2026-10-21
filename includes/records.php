<?php
/**
 * Record of archived plugins (dates) and the activity log.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remembers when each plugin was archived, and keeps a capped activity log.
 */
class HackRepair_Plugin_Archiver_Records {

	const MANIFEST_OPTION = 'hackrepair-plugin-archiver_manifest';
	const LOG_OPTION      = 'hackrepair-plugin-archiver_log';
	const LOG_MAX         = 500;
	const MANIFEST_MAX    = 1000;
	const PER_PAGE        = 25;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_hackrepair_plugin_archiver_log_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_hackrepair_plugin_archiver_log_clear', array( __CLASS__, 'handle_clear' ) );
	}

	/**
	 * Log action codes and their labels.
	 *
	 * @return array
	 */
	public static function actions() {
		return array(
			'archive'   => __( 'Archived', 'hackrepair-plugin-archiver' ),
			'restore'   => __( 'Restored', 'hackrepair-plugin-archiver' ),
			'delete'    => __( 'Deleted', 'hackrepair-plugin-archiver' ),
			'download'  => __( 'Downloaded', 'hackrepair-plugin-archiver' ),
			'secure'    => __( 'Archive renamed', 'hackrepair-plugin-archiver' ),
			'log_clear' => __( 'Log cleared', 'hackrepair-plugin-archiver' ),
		);
	}

	// ------------------------------------------------------- Pure helpers.

	/**
	 * Manifest key for one archived plugin.
	 *
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file relative to the archive.
	 * @return string
	 */
	public static function key( $archive, $plugin_file ) {
		return $archive . '|' . $plugin_file;
	}

	/**
	 * Keep the newest entries only.
	 *
	 * @param array $entries Newest first.
	 * @param int   $max     Maximum entries.
	 * @return array
	 */
	public static function cap_entries( array $entries, $max ) {
		return array_slice( array_values( $entries ), 0, max( 1, (int) $max ) );
	}

	/**
	 * Entries for one action (or all of them).
	 *
	 * @param array  $entries Log entries.
	 * @param string $action  Action code or empty string.
	 * @return array
	 */
	public static function filter_entries( array $entries, $action ) {
		if ( '' === $action ) {
			return array_values( $entries );
		}
		$out = array();
		foreach ( $entries as $entry ) {
			if ( isset( $entry['a'] ) && $entry['a'] === $action ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Make a value safe to open in a spreadsheet.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	// ------------------------------------------------------------- Manifest.

	/**
	 * The stored manifest (entries that are well formed).
	 *
	 * @return array
	 */
	public static function manifest() {
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		if ( ! is_array( $manifest ) ) {
			return array();
		}
		$clean = array();
		foreach ( $manifest as $key => $entry ) {
			if ( is_string( $key ) && is_array( $entry ) && ! empty( $entry['archived'] ) ) {
				$clean[ $key ] = array(
					'name'     => isset( $entry['name'] ) ? (string) $entry['name'] : '',
					'version'  => isset( $entry['version'] ) ? (string) $entry['version'] : '',
					'archived' => (int) $entry['archived'],
					'user'     => isset( $entry['user'] ) ? (string) $entry['user'] : '',
				);
			}
		}
		return $clean;
	}

	/**
	 * Save the manifest, keeping it to a sensible size.
	 *
	 * @param array $manifest Manifest.
	 */
	private static function save_manifest( array $manifest ) {
		if ( count( $manifest ) > self::MANIFEST_MAX ) {
			uasort(
				$manifest,
				function ( $a, $b ) {
					return $b['archived'] - $a['archived'];
				}
			);
			$manifest = array_slice( $manifest, 0, self::MANIFEST_MAX, true );
		}
		update_option( self::MANIFEST_OPTION, $manifest, false );
	}

	/**
	 * Login name of the current user.
	 *
	 * @return string
	 */
	private static function current_login() {
		$user = wp_get_current_user();
		return ( $user && $user->exists() ) ? (string) $user->user_login : '';
	}

	/**
	 * Remember that a plugin was archived now.
	 *
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file.
	 * @param string $name        Plugin name.
	 * @param string $version     Plugin version.
	 */
	public static function record_archive( $archive, $plugin_file, $name, $version ) {
		$manifest = self::manifest();

		$manifest[ self::key( $archive, $plugin_file ) ] = array(
			'name'     => sanitize_text_field( $name ),
			'version'  => sanitize_text_field( $version ),
			'archived' => time(),
			'user'     => self::current_login(),
		);
		self::save_manifest( $manifest );
		self::log( 'archive', $archive, $plugin_file, $name, $version );
	}

	/**
	 * Whether a screen slug belongs to Plugin Archiver (its list page or its settings page).
	 *
	 * @param string $page The page query value.
	 * @return bool
	 */
	public static function is_own_page( $page ) {
		return is_string( $page ) && 0 === strpos( $page, 'hackrepair-plugin-archiver' );
	}

	/**
	 * Read a plugin's name and version from its main file header.
	 *
	 * @param string $archive_dir Absolute path of the archive folder.
	 * @param string $plugin_file Plugin file such as folder/main.php.
	 * @return array array( name, version ); empty strings when unreadable.
	 */
	public static function describe( $archive_dir, $plugin_file ) {
		$out  = array(
			'name'    => '',
			'version' => '',
		);
		$path = rtrim( (string) $archive_dir, '/\\' ) . '/' . $plugin_file;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return $out;
		}
		$head = (string) file_get_contents( $path, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local header, not a URL.
		if ( 1 === preg_match( '/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $head, $m ) ) {
			$out['name'] = sanitize_text_field( trim( $m[1] ) );
		}
		if ( 1 === preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $head, $m ) ) {
			$out['version'] = sanitize_text_field( trim( $m[1] ) );
		}
		return $out;
	}

	/**
	 * Forget an archived plugin that has been restored or deleted.
	 *
	 * @param string $action      restore or delete.
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file.
	 * @param array  $described   Optional name and version read from the plugin file, used when no record exists.
	 */
	public static function record_leave( $action, $archive, $plugin_file, $described = array() ) {
		$manifest = self::manifest();
		$key      = self::key( $archive, $plugin_file );
		// Plugins archived before the record existed have no saved name; use the one read from the file.
		$name    = isset( $described['name'] ) ? (string) $described['name'] : '';
		$version = isset( $described['version'] ) ? (string) $described['version'] : '';
		if ( isset( $manifest[ $key ] ) ) {
			$name    = $manifest[ $key ]['name'];
			$version = $manifest[ $key ]['version'];
		}
		if ( isset( $manifest[ $key ] ) ) {
			unset( $manifest[ $key ] );
			self::save_manifest( $manifest );
		}
		self::log( $action, $archive, $plugin_file, $name, $version );
	}

	/**
	 * When a plugin went into the archive.
	 *
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file.
	 * @param string $path        Absolute path of the archived item, for an estimate.
	 * @return array array( timestamp, exact ) ; timestamp 0 when unknown.
	 */
	public static function archived_at( $archive, $plugin_file, $path = '' ) {
		$manifest = self::manifest();
		$key      = self::key( $archive, $plugin_file );
		if ( isset( $manifest[ $key ] ) ) {
			return array( $manifest[ $key ]['archived'], true );
		}
		$mtime = ( '' !== $path && file_exists( $path ) ) ? (int) filemtime( $path ) : 0;
		return array( $mtime, false );
	}

	// ------------------------------------------------------------------ Log.

	/**
	 * Stored log entries, newest first.
	 *
	 * @return array
	 */
	public static function entries() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : array();
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $action      Action code.
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file (may be empty).
	 * @param string $name        Plugin name (may be empty).
	 * @param string $detail      Extra detail, such as a version.
	 */
	public static function log( $action, $archive, $plugin_file = '', $name = '', $detail = '' ) {
		$entries = self::entries();
		array_unshift(
			$entries,
			array(
				't' => time(),
				'u' => self::current_login(),
				'a' => $action,
				'd' => (string) $archive,
				'p' => (string) $plugin_file,
				'n' => sanitize_text_field( (string) $name ),
				'x' => sanitize_text_field( (string) $detail ),
			)
		);
		update_option( self::LOG_OPTION, self::cap_entries( $entries, self::LOG_MAX ), false );
	}

	/**
	 * Screen address of the log tab.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	private static function log_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'log' ), $args ), admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings' ) );
	}

	/**
	 * Render the Log tab.
	 */
	public static function render_log_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$actions = self::actions();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $actions[ $filter ] ) ) {
			$filter = '';
		}
		$entries = self::filter_entries( self::entries(), $filter );
		$total   = count( $entries );
		$pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged   = min( $paged, $pages );
		$rows    = array_slice( $entries, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

		echo '<h2>' . esc_html__( 'Activity log', 'hackrepair-plugin-archiver' ) . '</h2>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %d: maximum number of log entries. */
				__( 'The newest %d actions are kept on this site. Nothing is sent anywhere.', 'hackrepair-plugin-archiver' ),
				self::LOG_MAX
			)
		) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'options-general.php' ) ) . '" style="margin:1em 0">';
		echo '<input type="hidden" name="page" value="hackrepair-plugin-archiver-settings"><input type="hidden" name="tab" value="log">';
		echo '<select name="filter"><option value="">' . esc_html__( 'All actions', 'hackrepair-plugin-archiver' ) . '</option>';
		foreach ( $actions as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $filter, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <button type="submit" class="button">' . esc_html__( 'Filter', 'hackrepair-plugin-archiver' ) . '</button></form>';

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nothing has been logged yet.', 'hackrepair-plugin-archiver' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			foreach ( array( __( 'When', 'hackrepair-plugin-archiver' ), __( 'Who', 'hackrepair-plugin-archiver' ), __( 'Action', 'hackrepair-plugin-archiver' ), __( 'Plugin', 'hackrepair-plugin-archiver' ), __( 'Archive', 'hackrepair-plugin-archiver' ) ) as $heading ) {
				echo '<th>' . esc_html( $heading ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $entry ) {
				$label = isset( $actions[ $entry['a'] ] ) ? $actions[ $entry['a'] ] : $entry['a'];
				$plug  = '' !== $entry['n'] ? $entry['n'] : $entry['p'];
				if ( '' !== $entry['x'] && 'archive' === $entry['a'] ) {
					$plug .= ' ' . $entry['x'];
				}
				echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['t'] ) ) . '</td>';
				echo '<td>' . esc_html( $entry['u'] ) . '</td><td>' . esc_html( $label ) . '</td>';
				echo '<td>' . esc_html( $plug ) . '</td><td><code>' . esc_html( $entry['d'] ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%', self::log_url( '' !== $filter ? array( 'filter' => $filter ) : array() ) ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				echo '</div></div>';
			}
		}

		echo '<div style="margin-top:1.5em">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
		wp_nonce_field( 'hrpa_log_export' );
		echo '<input type="hidden" name="action" value="hackrepair_plugin_archiver_log_export"><button type="submit" class="button">' . esc_html__( 'Download as CSV', 'hackrepair-plugin-archiver' ) . '</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Clear the whole activity log?', 'hackrepair-plugin-archiver' ) ) ) . ');">';
		wp_nonce_field( 'hrpa_log_clear' );
		echo '<input type="hidden" name="action" value="hackrepair_plugin_archiver_log_clear"><button type="submit" class="button">' . esc_html__( 'Clear log', 'hackrepair-plugin-archiver' ) . '</button></form></div>';
	}

	/**
	 * Admin-post: download the log as CSV.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpa_log_export' );
		$actions = self::actions();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="plugin-archiver-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to the browser.
		fputcsv( $out, array( 'time_utc', 'user', 'action', 'plugin', 'plugin_file', 'detail', 'archive' ), ',', '"', '\\' );
		foreach ( self::entries() as $entry ) {
			fputcsv(
				$out,
				array(
					gmdate( 'Y-m-d H:i:s', (int) $entry['t'] ),
					self::csv_cell( $entry['u'] ),
					self::csv_cell( isset( $actions[ $entry['a'] ] ) ? $actions[ $entry['a'] ] : $entry['a'] ),
					self::csv_cell( $entry['n'] ),
					self::csv_cell( $entry['p'] ),
					self::csv_cell( $entry['x'] ),
					self::csv_cell( $entry['d'] ),
				),
				',',
				'"',
				'\\'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming CSV to the browser.
		exit;
	}

	/**
	 * Admin-post: clear the log (the clearing itself is logged).
	 */
	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpa_log_clear' );
		update_option( self::LOG_OPTION, array(), false );
		self::log( 'log_clear', '' );
		wp_safe_redirect( self::log_url() );
		exit;
	}
}
