<?php
/**
 * Download an archived plugin as a zip file.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a zip of one archived plugin and sends it to an administrator.
 */
class HackRepair_Plugin_Archiver_Download {

	const DEFAULT_MAX_BYTES = 209715200; // 200 MB.

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_hackrepair_plugin_archiver_download', array( __CLASS__, 'handle' ) );
	}

	// ------------------------------------------------------- Pure helpers.

	/**
	 * Whether a plugin file name is acceptable (relative, no traversal).
	 *
	 * @param mixed $plugin_file Candidate.
	 * @return bool
	 */
	public static function is_valid_plugin_file( $plugin_file ) {
		return is_string( $plugin_file )
			&& '' !== $plugin_file
			&& strlen( $plugin_file ) <= 200
			&& 1 === preg_match( '/^[a-zA-Z0-9\-_\.\/]+$/', $plugin_file )
			&& false === strpos( $plugin_file, '..' )
			&& '/' !== $plugin_file[0]
			&& '.' !== $plugin_file[0];
	}

	/**
	 * The top-level item inside the archive for a plugin file.
	 *
	 * @param string $plugin_file Plugin file such as folder/main.php or main.php.
	 * @return string Folder name, or the file name for a single-file plugin.
	 */
	public static function top_entry( $plugin_file ) {
		$parts = explode( '/', $plugin_file );
		return $parts[0];
	}

	/**
	 * The single folder name used inside the zip.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return string
	 */
	public static function zip_folder( $plugin_file ) {
		$top = self::top_entry( $plugin_file );
		if ( false === strpos( $plugin_file, '/' ) ) {
			$top = preg_replace( '/\.php$/i', '', $top );
		}
		return $top;
	}

	/**
	 * Whether a total size is within the cap.
	 *
	 * @param int $bytes Total bytes.
	 * @param int $cap   Cap in bytes.
	 * @return bool
	 */
	public static function within_cap( $bytes, $cap ) {
		return (int) $bytes <= (int) $cap;
	}

	/**
	 * Whether a real path sits inside a real root.
	 *
	 * @param string $path Real path.
	 * @param string $root Real root path.
	 * @return bool
	 */
	public static function is_inside( $path, $root ) {
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );
		$path = str_replace( '\\', '/', $path );
		return 0 === strpos( $path, $root . '/' );
	}

	/**
	 * Address of the download link for one archived plugin.
	 *
	 * @param string $archive     Archive folder name.
	 * @param string $plugin_file Plugin file.
	 * @return string
	 */
	public static function url( $archive, $plugin_file ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'hackrepair_plugin_archiver_download',
					'archive' => $archive,
					'plugin'  => $plugin_file,
				),
				admin_url( 'admin-post.php' )
			),
			'hrpa_download_' . $archive . '|' . $plugin_file
		);
	}

	// ------------------------------------------------------------ Handling.

	/**
	 * Admin-post: build and send the zip.
	 */
	public static function handle() {
		if ( ! HackRepair_Plugin_Archiver_Security::can_view() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified below.
		$archive     = isset( $_GET['archive'] ) ? sanitize_file_name( wp_unslash( $_GET['archive'] ) ) : '';
		$plugin_file = isset( $_GET['plugin'] ) ? wp_unslash( $_GET['plugin'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! is_string( $plugin_file ) || ! self::is_valid_plugin_file( $plugin_file ) || ! HackRepair_Plugin_Archiver_Security::is_registered( $archive ) ) {
			wp_die( esc_html__( 'That plugin could not be found in the archive.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 404 ) );
		}
		check_admin_referer( 'hrpa_download_' . $archive . '|' . $plugin_file );
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'This server does not have the PHP zip extension, so a zip file cannot be made.', 'hackrepair-plugin-archiver' ) );
		}

		$root = realpath( trailingslashit( WP_CONTENT_DIR ) . $archive );
		$item = false === $root ? false : realpath( $root . '/' . self::top_entry( $plugin_file ) );
		if ( false === $root || false === $item || ! self::is_inside( $item, $root ) || is_link( $root . '/' . self::top_entry( $plugin_file ) ) ) {
			wp_die( esc_html__( 'That plugin could not be found in the archive.', 'hackrepair-plugin-archiver' ), '', array( 'response' => 404 ) );
		}

		$cap = (int) apply_filters( 'hackrepair_plugin_archiver_download_max_bytes', self::DEFAULT_MAX_BYTES );
		$tmp = wp_tempnam( 'hrpa-' . self::zip_folder( $plugin_file ) );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'A temporary file could not be created.', 'hackrepair-plugin-archiver' ) );
		}

		$error = self::build( $item, $root, self::zip_folder( $plugin_file ), $tmp, $cap );
		if ( '' !== $error ) {
			wp_delete_file( $tmp );
			wp_die( esc_html( $error ) );
		}

		$name = HackRepair_Plugin_Archiver_Records::manifest();
		$key  = HackRepair_Plugin_Archiver_Records::key( $archive, $plugin_file );
		HackRepair_Plugin_Archiver_Records::log( 'download', $archive, $plugin_file, isset( $name[ $key ] ) ? $name[ $key ]['name'] : '' );

		$size = filesize( $tmp );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( self::zip_folder( $plugin_file ) ) . '.zip"' );
		header( 'Content-Length: ' . (int) $size );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a zip we just built.
		wp_delete_file( $tmp );
		exit;
	}

	/**
	 * Build the zip.
	 *
	 * @param string $item   Real path of the archived plugin folder or file.
	 * @param string $root   Real path of the archive folder.
	 * @param string $folder Single top-level folder name inside the zip.
	 * @param string $tmp    Temporary zip path.
	 * @param int    $cap    Size cap in bytes.
	 * @return string Empty on success, otherwise an error message.
	 */
	public static function build( $item, $root, $folder, $tmp, $cap ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return __( 'The zip file could not be created.', 'hackrepair-plugin-archiver' );
		}
		$total = 0;
		if ( is_file( $item ) ) {
			$total += (int) filesize( $item );
			if ( ! self::within_cap( $total, $cap ) ) {
				$zip->close();
				return self::too_big();
			}
			$zip->addFile( $item, $folder . '/' . basename( $item ) );
		} else {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $item, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $path => $info ) {
				if ( $info->isLink() ) {
					continue; // Never follow symbolic links.
				}
				$real = realpath( $path );
				if ( false === $real || ! self::is_inside( $real, $root ) ) {
					continue;
				}
				$relative = ltrim( substr( str_replace( '\\', '/', $real ), strlen( str_replace( '\\', '/', $item ) ) ), '/' );
				if ( $info->isDir() ) {
					$zip->addEmptyDir( $folder . '/' . $relative );
				} elseif ( $info->isFile() ) {
					$total += (int) $info->getSize();
					if ( ! self::within_cap( $total, $cap ) ) {
						$zip->close();
						return self::too_big();
					}
					$zip->addFile( $real, $folder . '/' . $relative );
				}
			}
		}
		if ( ! $zip->close() ) {
			return __( 'The zip file could not be written.', 'hackrepair-plugin-archiver' );
		}
		return '';
	}

	/**
	 * Message for an oversized plugin.
	 *
	 * @return string
	 */
	private static function too_big() {
		return __( 'This plugin is too large to download as a zip from the dashboard. Copy it from the archive folder with SFTP instead.', 'hackrepair-plugin-archiver' );
	}
}
