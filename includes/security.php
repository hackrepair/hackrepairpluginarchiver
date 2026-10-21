<?php
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Matches the existing includes/ naming.
/**
 * Archive folder naming, registration and web-access protection.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a registry of archive folders, gives new ones unguessable names,
 * writes web-server deny rules into them and checks that they hold.
 */
class HackRepair_Plugin_Archiver_Security {

	const REGISTRY_OPTION   = 'hackrepair-plugin-archiver_archives';
	const OPTIONS_OPTION    = 'hackrepair-plugin-archiver_options';
	const DB_VERSION_OPTION = 'hackrepair-plugin-archiver_db_version';
	const DB_VERSION        = '3.2.0';
	const MARKER_FILE       = 'hackrepair-archive.txt';
	const MARKER_TEXT       = 'hackrepair-archive: this folder belongs to The Hack Repair Guy\'s Plugin Archiver. Web access to it should be denied.';
	const CHECK_TTL         = 43200;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'add_site_health_test' ) );
		add_action( 'admin_post_hackrepair_plugin_archiver_secure', array( __CLASS__, 'handle_secure' ) );
		add_action( 'admin_post_hackrepair_plugin_archiver_recheck', array( __CLASS__, 'handle_recheck' ) );
	}

	/**
	 * Whether this site allows plugin files to be changed (DISALLOW_FILE_MODS).
	 *
	 * @return bool
	 */
	public static function file_mods_allowed() {
		return (bool) wp_is_file_mod_allowed( 'hackrepair_plugin_archiver' );
	}

	// ---------------------------------------------------------------- Names.

	/**
	 * Random lowercase letters and digits.
	 *
	 * @param int $length Number of characters.
	 * @return string
	 */
	public static function random_suffix( $length = 12 ) {
		$suffix = '';
		$length = max( 1, (int) $length );
		while ( strlen( $suffix ) < $length ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- Suffix grows each pass.
			$suffix .= preg_replace( '/[^a-z0-9]/', '', strtolower( wp_generate_password( $length, false, false ) ) );
		}
		return substr( $suffix, 0, $length );
	}

	/**
	 * Short, safe slug for an archive label.
	 *
	 * @param string $label Label typed by a user.
	 * @return string
	 */
	public static function slug( $label ) {
		$slug = sanitize_title( (string) $label );
		$slug = preg_replace( '/^(plugins-)?(archive-)?/', '', $slug );
		return trim( substr( $slug, 0, 30 ), '-' );
	}

	/**
	 * A new, unguessable archive folder name.
	 *
	 * @param string $label Optional label to include.
	 * @return string
	 */
	public static function new_dir_name( $label = '' ) {
		$slug = self::slug( $label );
		if ( '' === $slug ) {
			return 'plugins-archive-' . self::random_suffix( 12 );
		}
		return 'plugins-archive-' . $slug . '-' . self::random_suffix( 8 );
	}

	/**
	 * Whether a string is acceptable as an archive folder name.
	 *
	 * @param mixed $name Candidate.
	 * @return bool
	 */
	public static function is_valid_dir_name( $name ) {
		return is_string( $name )
			&& strlen( $name ) <= 100
			&& false === strpos( $name, '..' )
			&& 1 === preg_match( '/^plugins-[A-Za-z0-9][A-Za-z0-9._-]*$/', $name );
	}

	/**
	 * Whether a folder name has the random form used for new archives.
	 *
	 * @param string $name Folder name.
	 * @return bool
	 */
	public static function is_random_name( $name ) {
		return 1 === preg_match( '/^plugins-archive-(?:[a-z0-9]{12}|[a-z0-9][a-z0-9-]*-[a-z0-9]{8})$/', (string) $name );
	}

	/**
	 * The predictable name earlier versions used by default.
	 *
	 * @return string
	 */
	public static function legacy_default_name() {
		return 'plugins-archive-' . substr( md5( get_bloginfo( 'url' ) ), 0, 6 );
	}

	/**
	 * The display label that earlier versions derived from a folder name.
	 *
	 * @param string $dir Folder name.
	 * @return string
	 */
	public static function derived_label( $dir ) {
		$name = preg_replace( '/^plugins-/i', '', $dir );
		$name = str_replace( '-', ' ', $name );
		return '' === $name ? $dir : ucfirst( $name );
	}

	// ------------------------------------------------------------- Registry.

	/**
	 * The registered archives: folder name => array( label, created, legacy ).
	 *
	 * @return array
	 */
	public static function registry() {
		$registry = get_option( self::REGISTRY_OPTION, array() );
		$clean    = array();
		if ( is_array( $registry ) ) {
			foreach ( $registry as $dir => $info ) {
				if ( self::is_valid_dir_name( $dir ) && is_array( $info ) ) {
					$clean[ $dir ] = array(
						'label'   => isset( $info['label'] ) ? (string) $info['label'] : self::derived_label( $dir ),
						'created' => isset( $info['created'] ) ? (int) $info['created'] : 0,
						'legacy'  => ! empty( $info['legacy'] ),
					);
				}
			}
		}
		return $clean;
	}

	/**
	 * Save the registry.
	 *
	 * @param array $registry Registry.
	 */
	public static function save_registry( array $registry ) {
		update_option( self::REGISTRY_OPTION, $registry, false );
	}

	/**
	 * Whether a folder name is a registered archive.
	 *
	 * @param string $dir Folder name.
	 * @return bool
	 */
	public static function is_registered( $dir ) {
		$registry = self::registry();
		return self::is_valid_dir_name( $dir ) && isset( $registry[ $dir ] );
	}

	/**
	 * Add an archive to the registry.
	 *
	 * @param string $dir    Folder name.
	 * @param string $label  Display label.
	 * @param bool   $legacy Whether the name is a predictable, pre-3.2.0 one.
	 * @return bool
	 */
	public static function register( $dir, $label, $legacy = false ) {
		if ( ! self::is_valid_dir_name( $dir ) ) {
			return false;
		}
		$registry         = self::registry();
		$registry[ $dir ] = array(
			'label'   => '' === (string) $label ? self::derived_label( $dir ) : (string) $label,
			'created' => ! empty( $registry[ $dir ]['created'] ) ? $registry[ $dir ]['created'] : time(),
			'legacy'  => (bool) $legacy,
		);
		self::save_registry( $registry );
		return true;
	}

	/**
	 * Display label of an archive.
	 *
	 * @param string $dir Folder name.
	 * @return string
	 */
	public static function label_for( $dir ) {
		$registry = self::registry();
		return isset( $registry[ $dir ] ) ? $registry[ $dir ]['label'] : self::derived_label( $dir );
	}

	/**
	 * The name of the active archive, validated against the registry.
	 *
	 * @param string $configured Value of the archive_dir option.
	 * @return string
	 */
	public static function active_name( $configured ) {
		$configured = sanitize_file_name( (string) $configured );
		$registry   = self::registry();
		if ( isset( $registry[ $configured ] ) ) {
			return $configured;
		}
		if ( $registry ) {
			$keys = array_keys( $registry );
			return $keys[0];
		}
		return $configured;
	}

	// ------------------------------------------------------------ Migration.

	/**
	 * Whether the contents of a folder look like an archive of plugins.
	 *
	 * @param string $path Absolute folder path.
	 * @return bool
	 */
	public static function looks_like_archive( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}
		$entries = scandir( $path );
		if ( false === $entries ) {
			return false;
		}
		$ours    = array( '.', '..', '.htaccess', 'web.config', 'index.php', self::MARKER_FILE );
		$entries = array_values( array_diff( $entries, $ours ) );
		if ( ! $entries ) {
			return true;
		}
		$checked = 0;
		foreach ( $entries as $entry ) {
			if ( ++$checked > 200 ) {
				break;
			}
			$full = $path . '/' . $entry;
			if ( is_dir( $full ) ) {
				foreach ( (array) glob( $full . '/*.php' ) as $file ) {
					if ( self::has_plugin_header( $file ) ) {
						return true;
					}
				}
			} elseif ( '.php' === substr( $entry, -4 ) && self::has_plugin_header( $full ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a PHP file starts with a plugin header.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function has_plugin_header( $file ) {
		$head = file_get_contents( $file, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, header only.
		return false !== $head && 1 === preg_match( '/^[ \t\/*#@]*Plugin Name:\s*\S/mi', $head );
	}

	/**
	 * One-time registration of archives made by earlier versions, and
	 * creation of an unguessable default archive on new installs.
	 *
	 * @param array $options Plugin options.
	 * @return array Updated options.
	 */
	public static function maybe_migrate( array $options ) {
		if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
			return $options;
		}
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return $options;
		}

		$configured = isset( $options['archive_dir'] ) ? sanitize_file_name( (string) $options['archive_dir'] ) : '';
		$legacy     = self::legacy_default_name();
		$found      = glob( trailingslashit( WP_CONTENT_DIR ) . 'plugins-*', GLOB_ONLYDIR );
		foreach ( (array) $found as $path ) {
			$dir = basename( $path );
			if ( ! self::is_valid_dir_name( $dir ) || sanitize_file_name( $dir ) !== $dir ) {
				continue;
			}
			if ( $dir === $configured || $dir === $legacy || self::looks_like_archive( $path ) ) {
				self::register( $dir, self::derived_label( $dir ), ! self::is_random_name( $dir ) );
			}
		}

		$registry = self::registry();
		if ( '' === $configured || ! isset( $registry[ $configured ] ) ) {
			if ( isset( $registry[ $legacy ] ) ) {
				$options['archive_dir'] = $legacy;
			} elseif ( $registry ) {
				$keys                   = array_keys( $registry );
				$options['archive_dir'] = $keys[0];
			} elseif ( self::file_mods_allowed() ) {
				$name = self::create_archive( '' );
				if ( $name ) {
					$options['archive_dir'] = $name;
				}
			}
			update_option( self::OPTIONS_OPTION, $options );
		}

		if ( self::file_mods_allowed() ) {
			foreach ( array_keys( self::registry() ) as $dir ) {
				if ( is_dir( trailingslashit( WP_CONTENT_DIR ) . $dir ) ) {
					self::protect( self::fs_path( $dir ) );
				}
			}
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		return $options;
	}

	// -------------------------------------------------------------- Creation.

	/**
	 * Make the filesystem object ready (direct access only, no credentials).
	 *
	 * @return bool
	 */
	private static function filesystem() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) || ! is_object( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return ! empty( $wp_filesystem ) && is_object( $wp_filesystem );
	}

	/**
	 * Filesystem-API path of an archive folder.
	 *
	 * @param string $dir Folder name.
	 * @return string
	 */
	public static function fs_path( $dir ) {
		global $wp_filesystem;
		if ( self::filesystem() ) {
			return trailingslashit( $wp_filesystem->wp_content_dir() ) . $dir;
		}
		return trailingslashit( WP_CONTENT_DIR ) . $dir;
	}

	/**
	 * Create and register a new archive with a random folder name.
	 *
	 * @param string $label Label typed by the user (may be empty).
	 * @return string|false Folder name, or false on failure.
	 */
	public static function create_archive( $label = '' ) {
		global $wp_filesystem;
		if ( ! self::file_mods_allowed() || ! self::filesystem() ) {
			return false;
		}
		$name = self::new_dir_name( $label );
		$path = self::fs_path( $name );
		if ( $wp_filesystem->exists( $path ) || ! $wp_filesystem->mkdir( $path, FS_CHMOD_DIR ) ) {
			return false;
		}
		$display = '' === trim( (string) $label ) ? __( 'Archive', 'hackrepair-plugin-archiver' ) : substr( sanitize_text_field( $label ), 0, 40 );
		self::register( $name, $display, false );
		self::protect( $path );
		return $name;
	}

	// ------------------------------------------------------------ Protection.

	/**
	 * The files that keep a folder from being served.
	 *
	 * @return array File name => contents.
	 */
	public static function protection_files() {
		$htaccess  = "# Managed by The Hack Repair Guy's Plugin Archiver. Changes are overwritten.\n";
		$htaccess .= "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
		$htaccess .= "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

		$webconfig  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n";
		$webconfig .= "\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n";

		return array(
			'.htaccess'       => $htaccess,
			'web.config'      => $webconfig,
			'index.php'       => "<?php\n// Silence is golden.\n",
			self::MARKER_FILE => self::MARKER_TEXT . "\n",
		);
	}

	/**
	 * Write (or repair) the protection files in an archive folder.
	 *
	 * @param string $fs_dir Filesystem-API path of the folder.
	 * @return bool Whether every file is in place.
	 */
	public static function protect( $fs_dir ) {
		global $wp_filesystem;
		if ( ! self::filesystem() ) {
			return false;
		}
		$ok = true;
		foreach ( self::protection_files() as $file => $content ) {
			$path    = trailingslashit( $fs_dir ) . $file;
			$current = $wp_filesystem->exists( $path ) ? $wp_filesystem->get_contents( $path ) : false;
			if ( $current === $content ) {
				continue;
			}
			if ( ! $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE ) ) {
				$ok = false;
			}
		}
		delete_transient( 'hrpa_reach_' . md5( basename( $fs_dir ) ) );
		return $ok;
	}

	// ----------------------------------------------------------- Reachability.

	/**
	 * Classify the answer to a request for the marker file.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Response body.
	 * @return string 'exposed' or 'protected'.
	 */
	public static function evaluate_response( $code, $body ) {
		if ( 200 === (int) $code && false !== strpos( (string) $body, 'hackrepair-archive' ) ) {
			return 'exposed';
		}
		return 'protected';
	}

	/**
	 * Whether an archive folder can be read over the web (cached).
	 *
	 * @param string $dir   Folder name.
	 * @param bool   $force Ignore the cached answer.
	 * @return string 'exposed', 'protected' or 'unknown'.
	 */
	public static function reachability( $dir, $force = false ) {
		if ( ! self::is_valid_dir_name( $dir ) ) {
			return 'unknown';
		}
		$key = 'hrpa_reach_' . md5( $dir );
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( false !== $cached ) {
				return (string) $cached;
			}
		}
		if ( ! file_exists( trailingslashit( WP_CONTENT_DIR ) . $dir . '/' . self::MARKER_FILE ) ) {
			return 'unknown';
		}
		$response = wp_remote_get(
			content_url( $dir . '/' . self::MARKER_FILE ),
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
			)
		);
		if ( is_wp_error( $response ) ) {
			$status = 'unknown';
			$ttl    = 300;
		} else {
			$status = self::evaluate_response( wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) );
			$ttl    = self::CHECK_TTL;
		}
		set_transient( $key, $status, $ttl );
		return $status;
	}

	/**
	 * Site Health: register the direct test.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function add_site_health_test( $tests ) {
		$tests['direct']['hackrepair_archive_protection'] = array(
			'label' => __( 'Archived plugins are protected from web access', 'hackrepair-plugin-archiver' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);
		return $tests;
	}

	/**
	 * Site Health: the test itself.
	 *
	 * @return array
	 */
	public static function site_health_test() {
		$result = array(
			'label'       => __( 'Archived plugins are protected from web access', 'hackrepair-plugin-archiver' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'hackrepair-plugin-archiver' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Your archive folders are not served by the web server.', 'hackrepair-plugin-archiver' ) . '</p>',
			'actions'     => '',
			'test'        => 'hackrepair_archive_protection',
		);

		$exposed = array();
		$unknown = 0;
		foreach ( array_keys( self::registry() ) as $dir ) {
			if ( ! is_dir( trailingslashit( WP_CONTENT_DIR ) . $dir ) ) {
				continue;
			}
			$state = self::reachability( $dir );
			if ( 'exposed' === $state ) {
				$exposed[] = $dir;
			} elseif ( 'unknown' === $state ) {
				++$unknown;
			}
		}

		if ( $exposed ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'An archive folder can be read from the web', 'hackrepair-plugin-archiver' );
			$result['description'] = '<p>' . esc_html(
				sprintf(
					/* translators: %s: archive folder names. */
					__( 'Plugin files in %s can be requested over the web. Archived plugins are often old and may contain unsafe files.', 'hackrepair-plugin-archiver' ),
					implode( ', ', $exposed )
				)
			) . '</p><p>' . esc_html( self::server_advice( $exposed[0] ) ) . '</p>';
		} elseif ( $unknown ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Archive folder protection could not be checked', 'hackrepair-plugin-archiver' );
			$result['description'] = '<p>' . esc_html__( 'The site could not request its own archive folder to confirm that it is blocked. Open the Plugin Archiver settings and use Check again.', 'hackrepair-plugin-archiver' ) . '</p>';
		}
		return $result;
	}

	/**
	 * Plain advice for servers that ignore the deny rules.
	 *
	 * @param string $dir Folder name.
	 * @return string
	 */
	public static function server_advice( $dir ) {
		return sprintf(
			/* translators: %s: archive folder name. */
			__( 'Ask your host to deny web access to /wp-content/%1$s (on nginx: location ^~ /wp-content/%1$s { deny all; }),.', 'hackrepair-plugin-archiver' ),
			$dir
		);
	}

	// ------------------------------------------------------- Admin actions.

	/**
	 * Settings screen address with a message code.
	 *
	 * @param string $message Message code.
	 * @return string
	 */
	private static function settings_url( $message ) {
		return add_query_arg( 'hrpa_msg', $message, admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings' ) );
	}

	/**
	 * Admin-post: rename a predictable archive folder to a random one.
	 */
	public static function handle_secure() {
		$dir = isset( $_POST['archive'] ) ? sanitize_file_name( wp_unslash( $_POST['archive'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpa_secure_' . $dir );
		if ( ! self::file_mods_allowed() ) {
			wp_safe_redirect( self::settings_url( 'disabled' ) );
			exit;
		}
		wp_safe_redirect( self::settings_url( self::secure( $dir ) ) );
		exit;
	}

	/**
	 * Rename a registered, predictable archive to a random name.
	 *
	 * @param string $dir Current folder name.
	 * @return string Result code: secured, secure_failed, needs_direct.
	 */
	public static function secure( $dir ) {
		global $wp_filesystem;
		$registry = self::registry();
		if ( ! isset( $registry[ $dir ] ) || ! $registry[ $dir ]['legacy'] ) {
			return 'secure_failed';
		}
		if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) ) {
			return 'needs_direct';
		}
		if ( ! self::filesystem() ) {
			return 'secure_failed';
		}
		$label    = $registry[ $dir ]['label'];
		$default  = 1 === preg_match( '/^archive [a-f0-9]{6}$/i', $label ) || 'archive' === self::slug( $label );
		$new_name = self::new_dir_name( $default ? '' : $label );
		$from     = trailingslashit( WP_CONTENT_DIR ) . $dir;
		$to       = trailingslashit( WP_CONTENT_DIR ) . $new_name;
		if ( ! is_dir( $from ) || file_exists( $to ) || ! $wp_filesystem->move( self::fs_path( $dir ), self::fs_path( $new_name ) ) ) {
			return 'secure_failed';
		}
		$created = $registry[ $dir ]['created'];
		unset( $registry[ $dir ] );
		$registry[ $new_name ] = array(
			'label'   => $default ? __( 'Archive', 'hackrepair-plugin-archiver' ) : $label,
			'created' => $created ? $created : time(),
			'legacy'  => false,
		);
		self::save_registry( $registry );
		$options = get_option( self::OPTIONS_OPTION, array() );
		if ( is_array( $options ) && isset( $options['archive_dir'] ) && $options['archive_dir'] === $dir ) {
			$options['archive_dir'] = $new_name;
			update_option( self::OPTIONS_OPTION, $options );
		}
		self::protect( self::fs_path( $new_name ) );
		delete_transient( 'hrpa_reach_' . md5( $dir ) );
		return 'secured';
	}

	/**
	 * Admin-post: check an archive's web protection again.
	 */
	public static function handle_recheck() {
		$dir = isset( $_POST['archive'] ) ? sanitize_file_name( wp_unslash( $_POST['archive'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpa_recheck_' . $dir );
		if ( self::is_registered( $dir ) ) {
			if ( self::file_mods_allowed() ) {
				self::protect( self::fs_path( $dir ) );
			}
			self::reachability( $dir, true );
		}
		wp_safe_redirect( self::settings_url( 'rechecked' ) );
		exit;
	}

	// ------------------------------------------------------------ Rendering.

	/**
	 * Human text for a reachability state.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function state_text( $state ) {
		switch ( $state ) {
			case 'exposed':
				return __( 'Readable from the web', 'hackrepair-plugin-archiver' );
			case 'protected':
				return __( 'Protected', 'hackrepair-plugin-archiver' );
			default:
				return __( 'Could not be checked from this server', 'hackrepair-plugin-archiver' );
		}
	}

	/**
	 * The archive folders panel for the Settings tab.
	 */
	public static function render_panel() {
		$registry = self::registry();
		echo '<h2>' . esc_html__( 'Archive folders', 'hackrepair-plugin-archiver' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Each archive folder is blocked from the web. Folders created by earlier versions have a predictable name; use Secure this archive to give one a random name.', 'hackrepair-plugin-archiver' ) . '</p>';
		if ( ! $registry ) {
			echo '<p>' . esc_html__( 'No archive folders yet. One is created the first time you archive a plugin.', 'hackrepair-plugin-archiver' ) . '</p>';
			return;
		}
		$allowed = self::file_mods_allowed();
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr>';
		echo '<th>' . esc_html__( 'Archive', 'hackrepair-plugin-archiver' ) . '</th>';
		echo '<th>' . esc_html__( 'Folder', 'hackrepair-plugin-archiver' ) . '</th>';
		echo '<th>' . esc_html__( 'Web access', 'hackrepair-plugin-archiver' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'hackrepair-plugin-archiver' ) . '</th></tr></thead><tbody>';
		foreach ( $registry as $dir => $info ) {
			$exists = is_dir( trailingslashit( WP_CONTENT_DIR ) . $dir );
			echo '<tr><td>' . esc_html( $info['label'] ) . '</td>';
			echo '<td><code>' . esc_html( $dir ) . '</code>' . ( $exists ? '' : ' <em>' . esc_html__( '(not created yet)', 'hackrepair-plugin-archiver' ) . '</em>' ) . '</td>';
			echo '<td>' . esc_html( $exists ? self::state_text( self::reachability( $dir ) ) : '—' ) . '</td><td>';
			if ( $exists ) {
				self::render_button( 'recheck', $dir, __( 'Check again', 'hackrepair-plugin-archiver' ) );
			}
			if ( $exists && $info['legacy'] && $allowed ) {
				self::render_button( 'secure', $dir, __( 'Secure this archive', 'hackrepair-plugin-archiver' ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * One small admin-post form button.
	 *
	 * @param string $action Short action name (recheck or secure).
	 * @param string $dir    Folder name.
	 * @param string $label  Button text.
	 */
	private static function render_button( $action, $dir, $label ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:6px">';
		wp_nonce_field( 'hrpa_' . $action . '_' . $dir );
		echo '<input type="hidden" name="action" value="' . esc_attr( 'hackrepair_plugin_archiver_' . $action ) . '">';
		echo '<input type="hidden" name="archive" value="' . esc_attr( $dir ) . '">';
		echo '<button type="submit" class="button button-small">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Notices for our own screens.
	 *
	 * @param string $active Active archive folder name.
	 */
	public static function render_notices( $active ) {
		$messages = array(
			'secured'       => array( 'success', __( 'The archive folder now has a random name.', 'hackrepair-plugin-archiver' ) ),
			'secure_failed' => array( 'error', __( 'The archive folder could not be renamed. Nothing was changed.', 'hackrepair-plugin-archiver' ) ),
			'needs_direct'  => array( 'error', __( 'Renaming needs direct file access to wp-content, which this server does not allow. Nothing was changed.', 'hackrepair-plugin-archiver' ) ),
			'disabled'      => array( 'warning', __( 'File changes are disabled on this site.', 'hackrepair-plugin-archiver' ) ),
			'rechecked'     => array( 'success', __( 'Web access checked again.', 'hackrepair-plugin-archiver' ) ),
		);
		$code     = isset( $_GET['hrpa_msg'] ) ? sanitize_key( wp_unslash( $_GET['hrpa_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
		}
		if ( ! self::file_mods_allowed() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'File changes are disabled on this site (DISALLOW_FILE_MODS). Archiving, restoring and deleting are turned off; the lists are read-only.', 'hackrepair-plugin-archiver' ) . '</p></div>';
		}
		if ( self::is_registered( $active ) && 'exposed' === self::reachability( $active ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Your archive folder can be read from the web.', 'hackrepair-plugin-archiver' ) . '</strong> ' . esc_html( self::server_advice( $active ) ) . '</p></div>';
		}
		if ( self::is_registered( $active ) && ! self::is_random_name( $active ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'This archive has a predictable folder name. Open the Plugin Archiver settings and choose Secure this archive to give it a random one.', 'hackrepair-plugin-archiver' ) . '</p></div>';
		}
	}
}
