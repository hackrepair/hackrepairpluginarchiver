<?php
/**
 * Prevent direct access to this file
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name:       The Hack Repair Guy's Plugin Archiver
 * Plugin URI:        https://wordpress.org/plugins/hackrepair-plugin-archiver/
 * Description:       Quickly deactivate and archive a plugin for later use. Archiving a plugin both deactivates and removes the plugin from your visible Plugins list.
 * Version:           3.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Jim Walker, The Hack Repair Guy
 * Author URI:        https://hackrepair.com/hackrepair-plugin-archiver/
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       hackrepair-plugin-archiver
 * Domain Path:       /languages
 *
 * @package HackRepair_Plugin_Archiver
 */


require_once plugin_dir_path( __FILE__ ) . 'includes/security.php';
HackRepair_Plugin_Archiver_Security::init();

add_action( 'init', array( 'HackRepair_Plugin_Archiver', 'init' ) );

class HackRepair_Plugin_Archiver {
	public static $count = 0;
	public static $plugin_dir;
	public static $options = array(
		'archive_dir'     => '',
		'archive_dir_add' => '',
		'deactivate'      => true,
	);
	public static function init() {
		load_plugin_textdomain( 'hackrepair-plugin-archiver', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		self::$plugin_dir = plugin_dir_path( __FILE__ );
		$options          = get_option( 'hackrepair-plugin-archiver_options' );
		self::$options    = wp_parse_args( $options, self::$options );
		self::$options = HackRepair_Plugin_Archiver_Security::maybe_migrate( self::$options );
		if ( '' === self::$options['archive_dir'] ) {
			// Nothing could be created (for example DISALLOW_FILE_MODS); read-only fallback.
			self::$options['archive_dir'] = HackRepair_Plugin_Archiver_Security::legacy_default_name();
		}
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'HackRepair_Plugin_Archiver', 'admin_init' ) );
		}
		if ( class_exists( 'HackRepair_Plugin_Archiver_Bulk_Action' ) && HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
			$bulk_action = new HackRepair_Plugin_Archiver_Bulk_Action();
			$bulk_action->init();
			$bulk_action->register_bulk_action(
				array(
					'action_name'  => 'archive-selected',
					'menu_text'    => __( 'Archive', 'hackrepair-plugin-archiver' ),
					'admin_notice' => _n_noop( 'Plugin archived successfully', '%d plugins archived successfully', 'hackrepair-plugin-archiver' ),
					'callback'     => array( 'HackRepair_Plugin_Archiver', 'bulk_archive' ),
				)
			);
		}
		add_filter( 'plugin_action_links', array( 'HackRepair_Plugin_Archiver', 'action_link' ), 10, 4 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'HackRepair_Plugin_Archiver', 'settings_link' ), 10, 1 );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), array( 'HackRepair_Plugin_Archiver', 'settings_link' ), 10, 1 );
		add_action( 'admin_menu', array( 'HackRepair_Plugin_Archiver', 'menu' ) );
		add_filter( 'custom_menu_order', array( 'HackRepair_Plugin_Archiver', 'menu_order' ) );
		add_action( 'load-plugins_page_hackrepair-plugin-archiver', array( 'HackRepair_Plugin_Archiver', 'archive_actions' ) );
		add_action( 'admin_notices', array( 'HackRepair_Plugin_Archiver', 'admin_notice' ) );
		add_filter( 'views_plugins', array( 'HackRepair_Plugin_Archiver', 'plugin_views' ) );
	}
	/**
	 * Registered archive folders (folder name => label).
	 *
	 * @return array
	 */
	public static function get_archive_dirs() {
		$result = array();
		foreach ( HackRepair_Plugin_Archiver_Security::registry() as $dir => $info ) {
			$result[ $dir ] = $info['label'];
		}
		if ( ! $result ) {
			$active            = sanitize_file_name( self::$options['archive_dir'] );
			$result[ $active ] = HackRepair_Plugin_Archiver_Security::derived_label( $active );
		}
		return $result;
	}

	public static function pointer_filter( $pointers ) {
		if ( ! isset( $pointers['plugins_page_hackrepair-plugin-archiver'] ) ) {
			$pointers['plugins_page_hackrepair-plugin-archiver'] = array();
		}
		$pointers['plugins_page_hackrepair-plugin-archiver']['test3'] = array(
			'callback' => array( 'HackRepair_Plugin_Archiver', 'pointer_content' ),
		);
		return $pointers;
	}
	public static function pointer_content() {
		$content  = '<h3>' . __( 'Pick active plugin archive', 'hackrepair-plugin-archiver' ) . '</h3>';
		$content .= '<p>' . __( 'Choose the active plugin archive directory.', 'hackrepair-plugin-archiver' ) . '</p>';
		HackRepair_Plugin_Archiver_Pointer::print_js(
			'test3',
			'ul.subsubsub',
			array(
				'content'  => $content,
				'position' => array(
					'edge'  => 'top',
					'align' => 'left',
				),
			)
		);
	}
	public static function admin_init() {
		global $pagenow;
		if ( 'plugins.php' === $pagenow ) {
			$dirs = self::get_archive_dirs();
			if ( 1 < count( $dirs ) ) {
				require_once self::$plugin_dir . 'includes/pointers.php';
				add_action( 'admin_enqueue_scripts', array( 'HackRepair_Plugin_Archiver_Pointer', 'enqueue_scripts' ) );
				add_filter( 'hackrepair_plugin_archiver_pointers', array( 'HackRepair_Plugin_Archiver', 'pointer_filter' ) );
			}
		}
		require_once self::$plugin_dir . 'includes/options.php';
		$archive_dirs = self::get_archive_dirs();
		$fields       = array(
			'general' => array(
				'title'    => '',
				'callback' => '',
				'options'  => array(
					'archive_dir'     => array(
						'title'    => __( 'Current Archive', 'hackrepair-plugin-archiver' ),
						'args'     => array(
							'values'      => $archive_dirs,
							'description' => __( 'The archive that new plugins are moved into.', 'hackrepair-plugin-archiver' ),
						),
						'callback' => 'select',
					),
					'archive_dir_add' => array(
						'title'    => __( 'New Archive', 'hackrepair-plugin-archiver' ),
						'args'     => array(
							'description' => __( 'Optional. Type a name to create another archive. Its folder gets a random suffix so it cannot be guessed.', 'hackrepair-plugin-archiver' ),
						),
						'callback' => 'text_plugins',
					),
					'deactivate'      => array(
						'title'    => __( 'Auto-deactivate before archiving', 'hackrepair-plugin-archiver' ),
						'args'     => array(
							'description' => __( 'Deactivate the plugin (if it\'s currently active) before moving it into the archive. This prevents issues caused by moving an active plugin and ensures a safe, clean archive. Recommended; this does not delete the plugin or its data.', 'hackrepair-plugin-archiver' ),
						),
						'callback' => 'checkbox',
					),
				),
			),
		);
		$tabs         = array(
			'settings' => array(
				'title'    => __( 'Settings', 'hackrepair-plugin-archiver' ),
				'href'     => admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings' ),
				'class'    => '',
				'callback' => 'settings',
			),
			'archive'  => array(
				'title'    => __( 'Archived Plugins', 'hackrepair-plugin-archiver' ),
				'href'     => admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings&tab=archive' ),
				'class'    => '',
				'callback' => array( 'HackRepair_Plugin_Archiver', 'archive_tab' ),
			),
			'notes'    => array(
				'title'    => __( 'Author Notes', 'hackrepair-plugin-archiver' ),
				'href'     => admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings&tab=notes' ),
				'class'    => '',
				'callback' => array( 'HackRepair_Plugin_Archiver', 'notes' ),
			),
		);
		HackRepair_Plugin_Archiver_Options::init(
			'hackrepair-plugin-archiver',
			__( 'Plugin Archiver', 'hackrepair-plugin-archiver' ),
			__( 'The Hack Repair Guy\'s Plugin Archiver: Settings', 'hackrepair-plugin-archiver' ),
			$fields,
			$tabs,
			'HackRepair_Plugin_Archiver',
			'hackrepair-plugin-archiver-settings'
		);
	}
	public static function notes() {
		echo '<div style="max-width: 600px; text-align:justify;">';
		include_once self::$plugin_dir . 'includes/notes.php';
		echo '</div>';
	}

	// Render the Archived Plugins list inside the Settings page "Archived Plugins" tab.
	public static function archive_tab() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'WP_Plugins_Archive_List_Table' ) ) {
			require_once self::$plugin_dir . 'includes/list.php';
		}

		$list_table = new WP_Plugins_Archive_List_Table();
		$list_table->prepare_items();

		echo '<div class="hrgpa-archive-tab">';
		echo '<form method="post" action="' . esc_url( admin_url( 'plugins.php?page=hackrepair-plugin-archiver' ) ) . '">';
		wp_nonce_field( 'bulk-plugins' );
		$list_table->views();
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	public static function plugin_views( $views ) {
		// Validate page parameter
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';
		if ( 'hackrepair-plugin-archiver' === $page ) {
			return $views;
		}
		$plugins = self::get_archived_plugins();
		$count   = count( $plugins );
		if ( 0 < $count ) {
			$link              = admin_url( 'plugins.php?page=hackrepair-plugin-archiver' );
			$title             = __( 'Archived', 'hackrepair-plugin-archiver' );
			$view              = '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . ' <span class="count">(' . esc_html( $count ) . ')</span></a>';
			$views['archived'] = $view;
		}
		return $views;
	}
	// Register Plugins submenu and render the Archived Plugins page.
	public static function menu() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		add_plugins_page(
			__( 'Archived Plugins', 'hackrepair-plugin-archiver' ),
			__( 'Archived Plugins', 'hackrepair-plugin-archiver' ),
			'install_plugins',
			'hackrepair-plugin-archiver',
			array( 'HackRepair_Plugin_Archiver', 'render_archive_page' )
		);
	}

	// No-op menu order handler to satisfy the hooked filter.
	public static function menu_order( $custom ) {
		return $custom;
	}

	/**
	 * Tab navigation markup.
	 *
	 * @param array  $tabs    Tabs (title, href, class).
	 * @param string $current Current tab key.
	 * @return string
	 */
	private static function generate_tabs( $tabs, $current = 'archive' ) {
		$result = '';
		if ( count( $tabs ) ) {
			$result = '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $tab_key => $tab ) {
				$class   = 'nav-tab' . ( $tab_key === $current ? ' nav-tab-active' : '' );
				$result .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $tab['href'] ) . '">' . esc_html( $tab['title'] ) . '</a>';
			}
			$result .= '</h2>';
		}
		return $result;
	}

	public static function render_archive_page() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}// Ensure list table class is available.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'WP_Plugins_Archive_List_Table' ) ) {
			require_once self::$plugin_dir . 'includes/list.php';
		}

		// Define tabs for the archived plugins page
		$tabs = array(
			'settings' => array(
				'title' => __( 'Settings', 'hackrepair-plugin-archiver' ),
				'href'  => admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings' ),
				'class' => '',
			),
			'archive'  => array(
				'title' => __( 'Archived Plugins', 'hackrepair-plugin-archiver' ),
				'href'  => admin_url( 'plugins.php?page=hackrepair-plugin-archiver' ),
				'class' => '',
			),
			'notes'    => array(
				'title' => __( 'Author Notes', 'hackrepair-plugin-archiver' ),
				'href'  => admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings&tab=notes' ),
				'class' => '',
			),
		);

		$list_table = new WP_Plugins_Archive_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Archived Plugins', 'hackrepair-plugin-archiver' ); ?></h1>
			<?php HackRepair_Plugin_Archiver_Security::render_notices( self::$options['archive_dir'] ); ?>
			<?php echo self::generate_tabs( $tabs, 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_tabs(). ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'plugins.php?page=hackrepair-plugin-archiver' ) ); ?>">
				<?php wp_nonce_field( 'bulk-plugins' ); ?>
				<?php $list_table->views(); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}


	public static function get_archived_plugins( $plugin_root = '' ) {
			$wp_plugins = array();
		if ( empty( $plugin_root ) ) {
				// Sanitize the archive directory name
				$archive_dir = sanitize_file_name( self::$options['archive_dir'] );
				$plugin_root = WP_CONTENT_DIR . '/' . $archive_dir;
		} else {
					// Validate that plugin_root is within WP_CONTENT_DIR
					$real_plugin_root = realpath( $plugin_root );
					$real_content_dir = realpath( WP_CONTENT_DIR );
			if ( false === $real_plugin_root || false === $real_content_dir || strpos( $real_plugin_root, $real_content_dir ) !== 0 ) {
						// If plugin_root is outside WP_CONTENT_DIR, use default
						$archive_dir = sanitize_file_name( self::$options['archive_dir'] );
						$plugin_root = WP_CONTENT_DIR . '/' . $archive_dir;
			} else {
				$plugin_root = $real_plugin_root;
			}
		}

		// Debug: log chosen archive root
				// Ensure plugin_root is a valid directory
		if ( ! is_dir( $plugin_root ) ) {
				return $wp_plugins;
		}

				// Files in wp-content/plugins directory
				$plugins_dir  = @ opendir( $plugin_root );
				$plugin_files = array();
		if ( $plugins_dir ) {
			while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
					// Skip hidden files
				if ( substr( $file, 0, 1 ) == '.' ) {
							continue;
				}
					// Sanitize file name
					$file = sanitize_file_name( $file );
					// Validate file name
				if ( ! preg_match( '/^[a-zA-Z0-9\-_\.]+$/', $file ) ) {
								continue;
				}
								$full_path = $plugin_root . '/' . $file;
								// Ensure full_path is within plugin_root
								$real_path = realpath( $full_path );
				if ( ! $real_path || strpos( $real_path, $plugin_root ) !== 0 ) {
						continue;
				}
				if ( is_dir( $full_path ) ) {
						$plugins_subdir = @ opendir( $full_path );
					if ( $plugins_subdir ) {
						while ( ( $subfile = readdir( $plugins_subdir ) ) !== false ) {
							// Skip hidden files
							if ( substr( $subfile, 0, 1 ) == '.' ) {
												continue;
							}
							// Sanitize subfile name
							$subfile = sanitize_file_name( $subfile );
							// Validate subfile name
							if ( ! preg_match( '/^[a-zA-Z0-9\-_\.]+$/', $subfile ) ) {
										continue;
							}
								$sub_full_path = $full_path . '/' . $subfile;
								// Ensure sub_full_path is within full_path
								$sub_real_path = realpath( $sub_full_path );
							if ( ! $sub_real_path || strpos( $sub_real_path, $plugin_root ) !== 0 ) {
													continue;
							}
							if ( substr( $subfile, -4 ) == '.php' ) {
														$plugin_files[] = "$file/$subfile";
							}
						}
									closedir( $plugins_subdir );
					}
				} else {
								// Sanitize file name
								$file = sanitize_file_name( $file );
								// Validate file name
					if ( ! preg_match( '/^[a-zA-Z0-9\-_\.]+$/', $file ) ) {
								continue;
					}
					if ( substr( $file, -4 ) == '.php' ) {
									$plugin_files[] = $file;
					}
				}
			}
				closedir( $plugins_dir );
		}

		if ( empty( $plugin_files ) ) {
			return $wp_plugins;
		}

		foreach ( $plugin_files as $plugin_file ) {
				// Allow typical plugin file paths like "dir/file.php" or single-file plugins "file.php"
				$plugin_file = wp_normalize_path( $plugin_file );
			if ( ! preg_match( '/^[A-Za-z0-9._\\/-]+$/', $plugin_file ) || strpos( $plugin_file, '..' ) !== false ) {
				continue;
			}
				$full_plugin_path = "$plugin_root/$plugin_file";
				// Ensure full_plugin_path is within plugin_root
				$real_plugin_path = realpath( $full_plugin_path );
			if ( ! $real_plugin_path || strpos( $real_plugin_path, $plugin_root ) !== 0 ) {
					continue;
			}
			if ( ! is_readable( $full_plugin_path ) ) {
						continue;
			}

				$plugin_data = get_plugin_data( $full_plugin_path, false, false ); // Do not apply markup/translate as it'll be cached.

			if ( empty( $plugin_data['Name'] ) ) {
							continue;
			}

							$wp_plugins[ plugin_basename( $plugin_file ) ] = $plugin_data;
		}
				uasort( $wp_plugins, '_sort_uname_callback' );

				// $cache_plugins[ $plugin_folder ] = $wp_plugins;
				// wp_cache_set('plugins', $cache_plugins, 'plugins');

				return $wp_plugins;
	}
	public static function archive_actions() {
			// Verify nonce for security
			// Check user capabilities
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		self::require_file_mods();

		if ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-plugins' ) ) {
				wp_die( __( 'Security check failed', 'hackrepair-plugin-archiver' ) );
			}

			// Validate action parameter
			$action = sanitize_key( $_REQUEST['action'] );
			if ( ! in_array( $action, array( 'restore-selected', 'remove-selected' ), true ) ) {
				wp_die( __( 'Invalid action', 'hackrepair-plugin-archiver' ) );
			}

			switch ( $action ) {
				case 'restore-selected':
					$result = self::bulk_restore();
					if ( ! $result ) {
						include ABSPATH . 'wp-admin/admin-footer.php';
						die();
					} else {
						wp_safe_redirect( admin_url( 'plugins.php?page=hackrepair-plugin-archiver&success_action=restore-selected&count=' . self::$count ) );
						exit;
					}
					break;
				case 'remove-selected':
					$result = self::bulk_remove();
					if ( ! $result ) {
						include ABSPATH . 'wp-admin/admin-footer.php';
						die();
					} else {
						wp_safe_redirect( admin_url( 'plugins.php?page=hackrepair-plugin-archiver&success_action=remove-selected&count=' . self::$count ) );
						exit;
					}
					break;
			}
		}
	}
	public static function admin_notice() {
		global $pagenow;
		if ( $pagenow === 'plugins.php' ) {
			// Validate success_action parameter
			$success_action = isset( $_REQUEST['success_action'] ) ? sanitize_key( $_REQUEST['success_action'] ) : '';
			if ( 'restore-selected' === $success_action ) {
				// Print notice in admin bar
				$message = _n_noop( 'Plugin restored successfully', '%d plugins restored successfully', 'hackrepair-plugin-archiver' );
				if ( ! empty( $message ) ) {
					// Validate count parameter
					$count          = isset( $_REQUEST['count'] ) ? intval( $_REQUEST['count'] ) : 0;
					$nooped_message = sprintf( translate_nooped_plural( $message, $count, 'hackrepair-plugin-archiver' ), $count );
					echo '<div class="updated"><p>' . esc_html( $nooped_message ) . '</p></div>';
				}
			}
			if ( 'remove-selected' === $success_action ) {
				// Print notice in admin bar
				$message = _n_noop( 'Plugin deleted successfully', '%d plugins deleted successfully', 'hackrepair-plugin-archiver' );
				if ( ! empty( $message ) ) {
					// Validate count parameter
					$count          = isset( $_REQUEST['count'] ) ? intval( $_REQUEST['count'] ) : 0;
					$nooped_message = sprintf( translate_nooped_plural( $message, $count, 'hackrepair-plugin-archiver' ), $count );
					echo '<div class="updated"><p>' . esc_html( $nooped_message ) . '</p></div>';
				}
			}
		}
	}

	// action link filter
	public static function action_link( $actions, $plugin_file, $plugin_data, $context ) {
		$exclude_context = array( 'mustuse', 'dropins' );
		$page            = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		$this_plugin     = plugin_basename( __FILE__ );

		if ( ! in_array( $context, $exclude_context, true ) && ( $plugin_file !== $this_plugin ) && ( 'hackrepair-plugin-archiver' !== $page ) ) {
			if ( current_user_can( 'install_plugins' ) && HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
				$actions['archive'] = '<a href="' . esc_url( wp_nonce_url( 'plugins.php?action=archive-selected&checked%5B0%5D=' . rawurlencode( $plugin_file ), 'bulk-plugins' ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Archive %s', 'hackrepair-plugin-archiver' ), esc_html( $plugin_data['Name'] ) ) ) . '">' . esc_html__( 'Archive', 'hackrepair-plugin-archiver' ) . '</a>';
			}
		} elseif ( $plugin_file === $this_plugin && 'hackrepair-plugin-archiver' !== $page ) {
			if ( current_user_can( 'install_plugins' ) && HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
				$actions['restore-all'] = '<a href="' . esc_url( wp_nonce_url( 'plugins.php?page=hackrepair-plugin-archiver&action=restore-selected&all=true', 'bulk-plugins' ) ) . '" aria-label="' . esc_attr( __( 'Unarchive all archived plugins', 'hackrepair-plugin-archiver' ) ) . '">' . esc_html__( 'Unarchive All', 'hackrepair-plugin-archiver' ) . '</a>';
			}
		}
		return $actions;
	}

		// Add Settings link specifically for this plugin row in Plugins list.
	public static function settings_link( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			$settings_url  = admin_url( 'options-general.php?page=hackrepair-plugin-archiver-settings' );
			$settings_link = '<a href="' . esc_url( $settings_url ) . '" aria-label="' . esc_attr__( 'Plugin Archiver Settings', 'hackrepair-plugin-archiver' ) . '">' . esc_html__( 'Settings', 'hackrepair-plugin-archiver' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

		// bulk actions
	public static function bulk_remove( $checked = false ) {
		// Check user capabilities
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		self::require_file_mods();

		global $wp_filesystem;
		$form_fields = $_REQUEST;
		unset( $form_fields['_wpnonce'] );
		unset( $form_fields['_wp_http_referer'] );
		$url = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds  = request_filesystem_credentials( $url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
		ob_end_clean();
		if ( $creds ) {
			WP_Filesystem( $creds );
			// Sanitize archive directory
			$archive_dir = self::prepare_archive_dir();
			$count = 0;
			// Validate that checked is an array
			$checked_plugins = isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ? $_REQUEST['checked'] : array();
			foreach ( $checked_plugins as $plugin ) {
				// Sanitize plugin name
				$plugin = wp_unslash( $plugin );// Validate plugin name format
				if ( ! preg_match( '/^[a-zA-Z0-9\-_\.\/]+$/', $plugin ) || strpos( $plugin, '..' ) !== false ) {
					continue;
				}
				$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
				// Validate plugin_dir is within archive_dir
				$real_plugin_dir  = realpath( $plugin_dir );
				$real_archive_dir = realpath( $archive_dir );
				if ( ! $real_plugin_dir || false === $real_archive_dir || strpos( $real_plugin_dir, $real_archive_dir ) !== 0 ) {
					continue;
				}
				$wp_filesystem->chmod( $plugin_dir, $wp_filesystem->is_dir( $plugin_dir ) ? FS_CHMOD_DIR : FS_CHMOD_FILE );
				$wp_filesystem->delete( $plugin_dir, true );
				++$count;
			}
			self::$count = $count;
			return true;
		} else {
			require_once ABSPATH . 'wp-admin/admin.php';
			require_once ABSPATH . 'wp-admin/admin-header.php';
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
			// include(ABSPATH . 'wp-admin/admin-footer.php');
			return false;
		}
	}
	public static function bulk_restore( $checked = false ) {
		// Check user capabilities
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		self::require_file_mods();

		global $wp_filesystem;
		$form_fields = $_REQUEST;
		unset( $form_fields['_wpnonce'] );
		unset( $form_fields['_wp_http_referer'] );
		$url = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds  = request_filesystem_credentials( $url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
			ob_end_clean();
		if ( $creds ) {
			WP_Filesystem( $creds );
			// Sanitize archive directory
			$archive_dir = self::prepare_archive_dir();
			$count = 0;
			if ( isset( $_REQUEST['all'] ) && $_REQUEST['all'] ) {
				$plugins  = self::get_archived_plugins();
				$checked  = array_keys( $plugins );
				$redirect = admin_url( 'plugins.php' );
			} else {
				// Validate that checked is an array
				$checked = isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ? $_REQUEST['checked'] : array();
			}
			foreach ( $checked as $plugin ) {
				// Sanitize plugin name
				$plugin = wp_unslash( $plugin );// Validate plugin name format
				if ( ! preg_match( '/^[a-zA-Z0-9\-_\.\/]+$/', $plugin ) || strpos( $plugin, '..' ) !== false ) {
					continue;
				}
				// if ( isset( $plugins[$plugin] ) ) {
					$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
					$target_dir = self::plugin_basename( $plugin, WP_PLUGIN_DIR );
					// Validate paths are within expected directories (target may not exist yet)
					$real_plugin_dir    = realpath( $plugin_dir );
					$norm_wp_plugin_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
					$norm_target_dir    = wp_normalize_path( $target_dir );
					$real_archive_dir   = realpath( $archive_dir );
					// Ensure plugin_dir is within archive_dir
				if ( ! $real_plugin_dir || false === $real_archive_dir || strpos( $real_plugin_dir, $real_archive_dir ) !== 0 ) {
					continue;
				}
					// Ensure target_dir is within WP_PLUGIN_DIR by normalized prefix
				if ( strpos( $norm_target_dir, $norm_wp_plugin_dir ) !== 0 ) {
					continue;
				}
					if ( $wp_filesystem->exists( $target_dir ) ) {
						continue; // Never overwrite a plugin that is already installed.
					}
					$result = $wp_filesystem->move( $plugin_dir, $target_dir );
				if ( $result ) {
					++$count;
				}
				// }
			}
			self::$count = $count;
			return true;
		} else {
			require_once ABSPATH . 'wp-admin/admin.php';
			require_once ABSPATH . 'wp-admin/admin-header.php';
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
			// include(ABSPATH . 'wp-admin/admin-footer.php');
			return false;
		}
	}
	public static function bulk_archive( $checked ) {
		// Check user capabilities
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		self::require_file_mods();

		global $wp_filesystem;
		$plugins = get_plugins();

		// Debug: capture raw incoming selection and plugin count.

		$form_fields = $_REQUEST;
		$url         = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds  = request_filesystem_credentials( $url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
			ob_end_clean();
		if ( $creds ) {
			WP_Filesystem( $creds );
			// Sanitize archive directory
			$archive_dir = self::prepare_archive_dir();
			$count = 0;
			// Validate that checked is an array
			$checked_plugins = is_array( $checked ) ? $checked : ( isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ? wp_unslash( $_REQUEST['checked'] ) : array() );
			foreach ( $checked_plugins as $plugin ) {
				$original_plugin = $plugin; // Keep original for diagnostics
				// Existing behavior: sanitize removes slashes; keep for now to validate root cause via logs.
				$plugin = wp_unslash( $plugin );// Debug: show original vs sanitized and whether keys exist in get_plugins().

				// Validate plugin name format
				if ( ! preg_match( '/^[a-zA-Z0-9\-_\.\/]+$/', $plugin ) || strpos( $plugin, '..' ) !== false ) {
					continue;
				}
				if ( isset( $plugins[ $plugin ] ) ) {
					$target_dir = self::plugin_basename( $plugin, $archive_dir );
					$plugin_dir = self::plugin_basename( $plugin, WP_PLUGIN_DIR );
					// Validate paths are within expected directories without requiring target to exist
					$norm_archive_dir   = trailingslashit( wp_normalize_path( $archive_dir ) );
					$norm_target_dir    = wp_normalize_path( $target_dir );
					$real_plugin_dir    = realpath( $plugin_dir );
					$norm_wp_plugin_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
					// Ensure target_dir resolves under archive_dir
					if ( strpos( $norm_target_dir, $norm_archive_dir ) !== 0 ) {
						continue;
					}
					// Ensure plugin_dir is within WP_PLUGIN_DIR
					if ( ! $real_plugin_dir || strpos( wp_normalize_path( $real_plugin_dir ), $norm_wp_plugin_dir ) !== 0 ) {
						continue;
					}
					if ( $wp_filesystem->exists( $target_dir ) ) {
						continue; // Never overwrite a plugin that is already in the archive.
					}
					if ( self::$options['deactivate'] ) {
						deactivate_plugins( $plugin );
					}
					$result = $wp_filesystem->move( $plugin_dir, $target_dir );
					if ( $result ) {
						++$count;
					}
				}
			}
			self::$count = $count;
		} else {
			unset( $_REQUEST['success_action'] );
			require_once ABSPATH . 'wp-admin/admin.php';
			require_once ABSPATH . 'wp-admin/admin-header.php';
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
			include ABSPATH . 'wp-admin/admin-footer.php';
			die( '' );
		}
	}

	// utility functions

	/**
	 * Make sure the active archive exists, is registered and is protected.
	 * Uses the filesystem object that already has credentials.
	 *
	 * @return string Filesystem-API path of the archive, with trailing slash.
	 */
	private static function prepare_archive_dir() {
		global $wp_filesystem;
		$name = HackRepair_Plugin_Archiver_Security::active_name( self::$options['archive_dir'] );
		if ( ! HackRepair_Plugin_Archiver_Security::is_registered( $name ) ) {
			HackRepair_Plugin_Archiver_Security::register( $name, HackRepair_Plugin_Archiver_Security::derived_label( $name ), ! HackRepair_Plugin_Archiver_Security::is_random_name( $name ) );
		}
		$archive_dir = trailingslashit( $wp_filesystem->wp_content_dir() . $name );
		$wp_filesystem->mkdir( $archive_dir );
		HackRepair_Plugin_Archiver_Security::protect( $archive_dir );
		return $archive_dir;
	}

	/**
	 * Stop when file changes are disabled (DISALLOW_FILE_MODS).
	 */
	private static function require_file_mods() {
		if ( ! HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
			wp_die( esc_html__( 'File changes are disabled on this site (DISALLOW_FILE_MODS), so plugins cannot be archived, restored or deleted here.', 'hackrepair-plugin-archiver' ) );
		}
	}

	private static function plugin_basename( $plugin, $base = '' ) {
		$dir = basename( $plugin );
		if ( $dir === $plugin ) {
			$result = $dir;
		} else {
			$result = dirname( $plugin );
		}
		if ( $base ) {
			$base = trailingslashit( $base );
		}
		$result = $base . $result;
		return $result;
	}

	private static function array_search( $needle, $key, $haystack ) {
		foreach ( $haystack as $main_key => $value ) {
			if ( $needle === $value[ $key ] ) {
				return $main_key;
			}
		}
		return false;
	}
}

// include admin classes - Bulk Action, List Table, Archive List Table
if ( is_admin() ) {
	if ( ! class_exists( 'HackRepair_Plugin_Archiver_Bulk_Action' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/bulk.php';
	}

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once plugin_dir_path( __FILE__ ) . 'includes/list.php';
}
