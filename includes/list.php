<?php
/**
 * The Archived Plugins table.
 *
 * Modelled on core's WP_Plugins_List_Table: it shares core's list-table
 * globals ($status, $plugins, $totals, $page, $orderby, $order) and fires
 * core's own plugin-row hooks so other plugins' row links and columns keep
 * working here. Those two patterns are intentional.
 *
 * @package HackRepair_Plugin_Archiver
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Mirrors core's WP_Plugins_List_Table globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Core plugin-row hooks, fired on purpose.
// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore -- Callback names kept from core.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list parameters (search, sort, paging); changes are verified in archive_actions().

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archived Plugins list table.
 */
class HackRepair_Plugin_Archiver_List_Table extends WP_List_Table {

	/**
	 * Folder name of the archive being listed.
	 *
	 * @var string
	 */
	protected $archive = '';


	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		global $status, $page;

		// Only instantiate the list table for users who can manage plugins.
		// This avoids exposing plugin UI to unauthorized users.
		if ( ! HackRepair_Plugin_Archiver_Security::can_view() ) {
			return;
		}

		parent::__construct(
			array(
				'plural' => 'plugins',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : 'plugins',
			)
		);

		$status = 'all';

		if ( isset( $_REQUEST['s'] ) ) {
			$_SERVER['REQUEST_URI'] = add_query_arg( 's', sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) );
		}

		$page = $this->get_pagenum();
	}

	protected function get_table_classes() {
		return array( 'widefat', $this->_args['plural'] );
	}

	public function prepare_items() {
		global $status, $plugins, $totals, $page, $orderby, $order, $s;

		// Only allow users with the appropriate capability to prepare and view plugin archive items.
		if ( ! HackRepair_Plugin_Archiver_Security::can_view() ) {
			$this->items = array();
			return;
		}

		wp_reset_vars( array( 'orderby', 'order', 's' ) );
			$this->process_bulk_action();

		/**
		 * Filter the full array of plugins to list in the Plugins list table.
		 *
		 * @since 3.0.0
		 *
		 * @see get_plugins()
		 *
		 * @param array $plugins An array of plugins to display in the list table.
		 */
		$dir = false;
		// Switching the active archive changes a saved setting, so it needs a valid token.
		if ( isset( $_REQUEST['archive_dir'], $_REQUEST['_hrpa_switch'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_hrpa_switch'] ) ), 'hrpa_switch_archive' ) ) {
			// Sanitize incoming archive_dir parameter before using it
			$archive_dir = sanitize_file_name( wp_unslash( $_REQUEST['archive_dir'] ) );
			// Only archives we know about can be selected.
			if ( HackRepair_Plugin_Archiver_Security::is_registered( $archive_dir ) ) {
				$dir = WP_CONTENT_DIR . '/' . $archive_dir;
				if ( HackRepair_Plugin_Archiver::$options['archive_dir'] !== $archive_dir ) {
					HackRepair_Plugin_Archiver::$options['archive_dir'] = $archive_dir;
					update_option( 'hackrepair-plugin-archiver_options', HackRepair_Plugin_Archiver::$options );
				}
			}
		}
		$this->archive = HackRepair_Plugin_Archiver_Security::active_name( HackRepair_Plugin_Archiver::$options['archive_dir'] );
		$all_plugins   = HackRepair_Plugin_Archiver::get_archived_plugins( $dir );
		$wprocket      = isset( $all_plugins['wp-rocket/wp-rocket.php'] );
		$all_plugins   = apply_filters( 'all_plugins', $all_plugins );
		if ( ! $wprocket ) {
			unset( $all_plugins['wp-rocket/wp-rocket.php'] );
		}
		$plugins = array(
			'all' => $all_plugins,
		);

		$screen = $this->screen;

		if ( $s ) {
			$status            = 'search';
			$plugins['search'] = array_filter( $plugins['all'], array( $this, '_search_callback' ) );
		}

		$totals = array();
		foreach ( $plugins as $type => $list ) {
			$totals[ $type ] = count( $list );
		}

		if ( empty( $plugins[ $status ] ) && ! in_array( $status, array( 'all', 'search' ), true ) ) {
			$status = 'all';
		}

		$this->items = array();
		foreach ( $plugins[ $status ] as $plugin_file => $plugin_data ) {
			$this->items[ $plugin_file ] = self::clean_plugin_data( $plugin_data );
		}
		$total_this_page = $totals[ $status ];

		if ( in_array( $orderby, array( 'archived', 'duration' ), true ) ) {
			$archive = $this->archive;
			$times   = array();
			foreach ( array_keys( $this->items ) as $file ) {
				$times[ $file ] = HackRepair_Plugin_Archiver_Records::archived_at( $archive, $file, $this->item_path( $file ) )[0];
			}
			// Newest archived first means shortest time in the archive first.
			$newest_first = ( 'archived' === $orderby ) === ( 'DESC' === strtoupper( $order ) );
			uksort(
				$this->items,
				function ( $a, $b ) use ( $times, $newest_first ) {
					return $newest_first ? $times[ $b ] - $times[ $a ] : $times[ $a ] - $times[ $b ];
				}
			);
		} elseif ( $orderby ) {
			$orderby = ucfirst( $orderby );
			$order   = strtoupper( $order );

			uasort( $this->items, array( $this, '_order_callback' ) );
		}

		$plugins_per_page = $this->get_items_per_page( str_replace( '-', '_', $screen->id . '_per_page' ), 999 );

		$start = ( $page - 1 ) * $plugins_per_page;

		if ( $total_this_page > $plugins_per_page ) {
			$this->items = array_slice( $this->items, $start, $plugins_per_page );
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_this_page,
				'per_page'    => $plugins_per_page,
			)
		);
	}

	/**
	 * @staticvar string $term
	 * @param array $plugin
	 * @return boolean
	 */
	public function _search_callback( $plugin ) {
		static $term;
		if ( is_null( $term ) ) {
			// Validate and sanitize search term
			$term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		}

		foreach ( $plugin as $value ) {
			// Use wp_strip_all_tags() instead of strip_tags() for sanitizing search text.
			if ( false !== stripos( wp_strip_all_tags( $value ), $term ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @global string $orderby
	 * @global string $order
	 * @param array $plugin_a
	 * @param array $plugin_b
	 * @return int
	 */
	public function _order_callback( $plugin_a, $plugin_b ) {
		global $orderby, $order;

		$a = $plugin_a[ $orderby ];
		$b = $plugin_b[ $orderby ];

		if ( $a === $b ) {
			return 0;
		}

		if ( 'DESC' === $order ) {
			return ( $a < $b ) ? 1 : -1;
		} else {
			return ( $a < $b ) ? -1 : 1;
		}
	}

	public function no_items() {
		global $plugins;
		esc_html_e( 'You do not appear to have any archived plugins', 'hackrepair-plugin-archiver' );
	}

	/**
	 * Make the header fields of an archived plugin safe to print (same rules core uses on the Plugins screen).
	 *
	 * @param array $plugin_data Plugin header data.
	 * @return array
	 */
	private static function clean_plugin_data( $plugin_data ) {
		$in_links        = array(
			'abbr'    => array( 'title' => true ),
			'acronym' => array( 'title' => true ),
			'code'    => true,
			'em'      => true,
			'strong'  => true,
		);
		$with_links      = $in_links;
		$with_links['a'] = array(
			'href'  => true,
			'title' => true,
		);
		foreach ( array( 'Name', 'Author', 'Description', 'Version', 'PluginURI', 'AuthorURI' ) as $field ) {
			$plugin_data[ $field ] = isset( $plugin_data[ $field ] ) ? $plugin_data[ $field ] : '';
		}
		$plugin_data['Name']        = wp_kses( $plugin_data['Name'], $in_links );
		$plugin_data['Author']      = wp_kses( $plugin_data['Author'], $with_links );
		$plugin_data['Description'] = wp_kses( $plugin_data['Description'], $with_links );
		$plugin_data['Version']     = wp_kses( $plugin_data['Version'], $with_links );
		$plugin_data['PluginURI']   = esc_url( $plugin_data['PluginURI'] );
		$plugin_data['AuthorURI']   = esc_url( $plugin_data['AuthorURI'] );
		$plugin_data['Title']       = $plugin_data['Name'];
		$plugin_data['AuthorName']  = $plugin_data['Author'];
		return $plugin_data;
	}

	/**
	 * Whether the current user may change the archive here.
	 *
	 * @return bool
	 */
	private static function can_change() {
		return current_user_can( 'install_plugins' ) && HackRepair_Plugin_Archiver_Security::file_mods_allowed();
	}

	/**
	 * Show the table, then ask before a bulk Delete.
	 */
	public function display() {
		parent::display();
		static $printed = false;
		if ( $printed || ! self::can_change() ) {
			return;
		}
		$printed = true;
		$message = __( 'Delete the selected plugins from the archive? Their files are removed permanently.', 'hackrepair-plugin-archiver' );
		wp_print_inline_script_tag(
			'document.addEventListener("submit",function(e){var f=e.target;if(!f.querySelector||!f.querySelector(\'input[name="checked[]"]\')){return;}'
			. 'var a=f.querySelector(\'select[name="action"]\'),b=f.querySelector(\'select[name="action2"]\');'
			. 'var by=e.submitter&&e.submitter.id==="doaction2"?b:a;'
			. 'if(by&&by.value==="remove-selected"&&!window.confirm(' . wp_json_encode( $message ) . ')){e.preventDefault();}});'
		);
	}

	public function get_columns() {
		global $status;

		$columns = array(
			'cb'          => ! in_array( $status, array( 'mustuse', 'dropins' ), true ) ? '<input type="checkbox" />' : '',
			// Provide text domain for translations in the column headers.
			'name'        => __( 'Plugin', 'hackrepair-plugin-archiver' ),
			'description' => __( 'Description', 'hackrepair-plugin-archiver' ),
			'archived'    => __( 'Archived on', 'hackrepair-plugin-archiver' ),
			'duration'    => __( 'In the archive for', 'hackrepair-plugin-archiver' ),
		);
		// No checkbox column when nothing can be changed: there are no bulk actions to apply.
		if ( ! self::can_change() ) {
			unset( $columns['cb'] );
		}
		return $columns;
	}

	/**
	 * Absolute path of an archived plugin's top-level folder or file.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return string
	 */
	protected function item_path( $plugin_file ) {
		return trailingslashit( WP_CONTENT_DIR ) . $this->archive . '/' . HackRepair_Plugin_Archiver_Download::top_entry( $plugin_file );
	}

	protected function get_sortable_columns() {
		return array(
			'archived' => array( 'archived', true ),
			'duration' => array( 'duration', false ),
		);
	}

	protected function get_views() {
		global $totals, $status;

		$screen       = $this->screen;
		$status_links = array();
		$dirs         = HackRepair_Plugin_Archiver::get_archive_dirs();
		foreach ( $dirs as $dir => $name ) {
			$key   = $dir;
			$count = HackRepair_Plugin_Archiver::archived_count( $dir );
			$link  = '<a href="%s" %s>%s%s</a>';
			$href  = admin_url( 'plugins.php?page=hackrepair-plugin-archiver' );
			if ( HackRepair_Plugin_Archiver::$options['archive_dir'] !== $dir ) {
				$href = add_query_arg(
					array(
						'archive_dir'  => $dir,
						'_hrpa_switch' => wp_create_nonce( 'hrpa_switch_archive' ),
					),
					$href
				);
			}
			$status_links[ $key ] = sprintf(
				$link,
				esc_url( $href ),
				( HackRepair_Plugin_Archiver::$options['archive_dir'] === $dir ) ? ' class="current"' : '',
				esc_html( $name ),
				0 < $count ? ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>' : ''
			);
		}
		return $status_links;
	}

	protected function get_bulk_actions() {
		if ( ! HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
			return array();
		}
		$actions = array(
			'restore-selected' => __( 'Unarchive', 'hackrepair-plugin-archiver' ),
			'remove-selected'  => __( 'Delete', 'hackrepair-plugin-archiver' ),
		);
		return $actions;
	}

	/**
	 * @global string $status
	 * @param string $which
	 * @return null
	 */
	public function bulk_actions( $which = '' ) {
		global $status;

		if ( in_array( $status, array( 'mustuse', 'dropins' ), true ) ) {
			return;
		}

		parent::bulk_actions( $which );
	}

	/**
	 * @global string $status
	 * @param string $which
	 * @return null
	 */
	protected function extra_tablenav( $which ) {
		global $status;
		if ( ! HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
			return;
		}
		echo '<div class="alignleft actions" style="margin-top:1px;">
		    <a class="button action" href="' . esc_url( wp_nonce_url( admin_url( 'plugins.php?page=hackrepair-plugin-archiver&action=restore-selected&all=true' ), 'bulk-plugins' ) ) . '">' . esc_html__( 'Unarchive All', 'hackrepair-plugin-archiver' ) . '</a>
		  </div>';
	}

	public function display_rows() {
		global $status;

		foreach ( $this->items as $plugin_file => $plugin_data ) {
			$this->single_row( array( $plugin_file, $plugin_data ) );
		}
	}

	/**
	 * @global string $status
	 * @global int $page
	 * @global string $s
	 * @global array $totals
	 * @param array $item
	 */
	public function single_row( $item ) {
		global $status, $page, $s, $totals;

		list( $plugin_file, $plugin_data ) = $item;
		$context                           = $status;
		$screen                            = $this->screen;

		// Pre-order.
		$actions = array();
		if ( current_user_can( 'install_plugins' ) && HackRepair_Plugin_Archiver_Security::file_mods_allowed() ) {
			$restore_url        = wp_nonce_url( 'plugins.php?page=hackrepair-plugin-archiver&action=restore-selected&checked%5B0%5D=' . rawurlencode( $plugin_file ) . '&paged=' . intval( $page ) . '&s=' . rawurlencode( $s ), 'bulk-plugins' );
			$remove_url         = wp_nonce_url( 'plugins.php?page=hackrepair-plugin-archiver&action=remove-selected&checked%5B0%5D=' . rawurlencode( $plugin_file ) . '&paged=' . intval( $page ) . '&s=' . rawurlencode( $s ), 'bulk-plugins' );
			$actions['restore'] = '<a href="' . esc_url( $restore_url ) . '" title="' . esc_attr__( 'Unarchive this plugin', 'hackrepair-plugin-archiver' ) . '">' . esc_html__( 'Unarchive', 'hackrepair-plugin-archiver' ) . '</a>';
			/* translators: %s: plugin name. */
			$confirm           = sprintf( __( 'Delete %s from the archive? Its files are removed permanently.', 'hackrepair-plugin-archiver' ), wp_strip_all_tags( $plugin_data['Name'] ) );
			$actions['remove'] = '<a href="' . esc_url( $remove_url ) . '" title="' . esc_attr__( 'Delete this plugin', 'hackrepair-plugin-archiver' ) . '" class="delete" onclick="' . esc_attr( 'return window.confirm(' . wp_json_encode( $confirm ) . ');' ) . '">' . esc_html__( 'Delete', 'hackrepair-plugin-archiver' ) . '</a>';
		}

		if ( HackRepair_Plugin_Archiver_Security::can_view() && class_exists( 'ZipArchive' ) && HackRepair_Plugin_Archiver_Download::is_valid_plugin_file( $plugin_file ) ) {
			$actions['download'] = '<a href="' . esc_url( HackRepair_Plugin_Archiver_Download::url( $this->archive, $plugin_file ) ) . '" title="' . esc_attr__( 'Download this plugin as a zip file', 'hackrepair-plugin-archiver' ) . '">' . esc_html__( 'Download', 'hackrepair-plugin-archiver' ) . '</a>';
		}

		$prefix = $screen->in_admin( 'network' ) ? 'network_admin_' : '';

		/**
		 * Filter the action links displayed for each plugin in the Plugins list table.
		 *
		 * The dynamic portion of the hook name, `$prefix`, refers to the context the
		 * action links are displayed in. The 'network_admin_' prefix is used if the
		 * current screen is the Network plugins list table. The prefix is empty ('')
		 * if the current screen is the site plugins list table.
		 *
		 * The default action links for the Network plugins list table include
		 * 'Network Activate', 'Network Deactivate', 'Edit', and 'Delete'.
		 *
		 * The default action links for the site plugins list table include
		 * 'Activate', 'Deactivate', and 'Edit', for a network site, and
		 * 'Activate', 'Deactivate', 'Edit', and 'Delete' for a single site.
		 *
		 * @since 2.5.0
		 *
		 * @param array  $actions     An array of plugin action links.
		 * @param string $plugin_file Path to the plugin file.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $context     The plugin context. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade',
		 *                            'Must-Use', 'Drop-ins', 'Search'.
		 */
		$actions = apply_filters( $prefix . 'plugin_action_links', array_filter( $actions ), $plugin_file, $plugin_data, $context );

		/**
		 * Filter the list of action links displayed for a specific plugin.
		 *
		 * The first dynamic portion of the hook name, $prefix, refers to the context
		 * the action links are displayed in. The 'network_admin_' prefix is used if the
		 * current screen is the Network plugins list table. The prefix is empty ('')
		 * if the current screen is the site plugins list table.
		 *
		 * The second dynamic portion of the hook name, $plugin_file, refers to the path
		 * to the plugin file, relative to the plugins directory.
		 *
		 * @since 2.7.0
		 *
		 * @param array  $actions     An array of plugin action links.
		 * @param string $plugin_file Path to the plugin file.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $context     The plugin context. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade',
		 *                            'Must-Use', 'Drop-ins', 'Search'.
		 */
		$actions = apply_filters( $prefix . "plugin_action_links_$plugin_file", $actions, $plugin_file, $plugin_data, $context );

		$class       = 'inactive';
		$checkbox_id = 'checkbox_' . md5( $plugin_data['Name'] );
		if ( in_array( $status, array( 'mustuse', 'dropins' ), true ) ) {
			$checkbox = '';
		} else {
			/* translators: %s: plugin name. */
			$select_label = sprintf( __( 'Select %s', 'hackrepair-plugin-archiver' ), wp_strip_all_tags( $plugin_data['Name'] ) );
			$checkbox     = '<label class="screen-reader-text" for="' . esc_attr( $checkbox_id ) . '">' . esc_html( $select_label ) . '</label>'
				. '<input type="checkbox" name="checked[]" value="' . esc_attr( $plugin_file ) . '" id="' . esc_attr( $checkbox_id ) . '" />';
		}
		if ( 'dropins' !== $context ) {
			$description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
			$plugin_name = $plugin_data['Name'];
		}

		$id = sanitize_title( $plugin_name );
		if ( ! empty( $totals['upgrade'] ) && ! empty( $plugin_data['update'] ) ) {
			$class .= ' update';
		}

		$plugin_slug = ( isset( $plugin_data['slug'] ) ) ? $plugin_data['slug'] : '';
		printf(
			"<tr id='%s' class='%s' data-slug='%s'>",
			esc_attr( $id ),
			esc_attr( $class ),
			esc_attr( $plugin_slug )
		);

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$style = '';
			if ( in_array( $column_name, $hidden, true ) ) {
				$style = ' style="display:none;"';
			}

			switch ( $column_name ) {
				case 'cb':
					echo '<th scope="row" class="check-column">' . $checkbox . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
					break;
				case 'name':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is a fixed string; dynamic parts are escaped.
					echo "<td class='plugin-title'$style><strong>" . esc_html( $plugin_name ) . '</strong>';
					echo $this->row_actions( $actions, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core list-table markup of escaped links.
					echo '</td>';
					break;
				case 'archived':
				case 'duration':
					list( $stamp, $exact ) = HackRepair_Plugin_Archiver_Records::archived_at( $this->archive, $plugin_file, $this->item_path( $plugin_file ) );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is a fixed string; dynamic parts are escaped.
					echo "<td class='column-" . esc_attr( $column_name ) . "'$style>";
					if ( ! $stamp ) {
						echo '&mdash;';
					} elseif ( 'archived' === $column_name ) {
						$when = wp_date( get_option( 'date_format' ), $stamp );
						echo esc_html( $exact ? $when : sprintf( /* translators: %s: date. */ __( 'about %s', 'hackrepair-plugin-archiver' ), $when ) );
					} else {
						$span = human_time_diff( $stamp, time() );
						echo esc_html( $exact ? $span : sprintf( /* translators: %s: time span such as "3 weeks". */ __( 'about %s', 'hackrepair-plugin-archiver' ), $span ) );
					}
					echo '</td>';
					break;
				case 'description':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is a fixed string; dynamic parts are escaped.
					echo "<td class='column-description desc'$style>
						<div class='plugin-description'>" . wp_kses_post( $description ) . "</div>
						<div class='" . esc_attr( $class ) . " second plugin-version-author-uri'>";

					$plugin_meta = array();
					if ( ! empty( $plugin_data['Version'] ) ) {
						/* translators: %s: plugin version. */
						$plugin_meta[] = esc_html( sprintf( __( 'Version %s', 'hackrepair-plugin-archiver' ), $plugin_data['Version'] ) );
					}
					if ( ! empty( $plugin_data['Author'] ) ) {
						$author_name = esc_html( $plugin_data['Author'] );
						if ( ! empty( $plugin_data['AuthorURI'] ) ) {
							$author = '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . $author_name . '</a>';
						} else {
							$author = $author_name;
						}
						/* translators: %s: plugin author. */
						$plugin_meta[] = sprintf( esc_html__( 'By %s', 'hackrepair-plugin-archiver' ), $author );
					}

					// Details link using API info, if available
					if ( isset( $plugin_data['slug'] ) && current_user_can( 'install_plugins' ) ) {
						$plugin_meta[] = sprintf(
							'<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
							esc_url(
								network_admin_url(
									'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data['slug'] .
									'&TB_iframe=true&width=600&height=550'
								)
							),
							/* translators: %s: plugin name. */
							esc_attr( sprintf( __( 'More information about %s', 'hackrepair-plugin-archiver' ), $plugin_name ) ),
							esc_attr( $plugin_name ),
							esc_html__( 'View details', 'hackrepair-plugin-archiver' )
						);
					} elseif ( ! empty( $plugin_data['PluginURI'] ) ) {
						$plugin_meta[] = sprintf(
							'<a href="%s">%s</a>',
							esc_url( $plugin_data['PluginURI'] ),
							esc_html__( 'Visit plugin site', 'hackrepair-plugin-archiver' )
						);
					}

					/**
					 * Filter the array of row meta for each plugin in the Plugins list table.
					 *
					 * @since 2.8.0
					 *
					 * @param array  $plugin_meta An array of the plugin's metadata,
					 *                            including the version, author,
					 *                            author URI, and plugin URI.
					 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
					 * @param array  $plugin_data An array of plugin data.
					 * @param string $status      Status of the plugin. Defaults are 'All', 'Active',
					 *                            'Inactive', 'Recently Activated', 'Upgrade', 'Must-Use',
					 *                            'Drop-ins', 'Search'.
					 */
					$plugin_meta = apply_filters( 'plugin_row_meta', $plugin_meta, $plugin_file, $plugin_data, $status );
					echo wp_kses_post( implode( ' | ', $plugin_meta ) );

					echo '</div></td>';
					break;
				default:
					echo '<td class="' . esc_attr( $column_name . ' column-' . $column_name ) . '"' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is a fixed string.

					/**
					 * Fires inside each custom column of the Plugins list table.
					 *
					 * @since 3.1.0
					 *
					 * @param string $column_name Name of the column.
					 * @param string $plugin_file Path to the plugin file.
					 * @param array  $plugin_data An array of plugin data.
					 */
					do_action( 'manage_plugins_custom_column', $column_name, $plugin_file, $plugin_data );
					echo '</td>';
			}
		}

		echo '</tr>';

		/**
		 * Fires after each row in the Plugins list table.
		 *
		 * @since 2.3.0
		 *
		 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $status      Status of the plugin. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade', 'Must-Use',
		 *                            'Drop-ins', 'Search'.
		 */
		do_action( 'after_plugin_row', $plugin_file, $plugin_data, $status );

		/**
		 * Fires after each specific row in the Plugins list table.
		 *
		 * The dynamic portion of the hook name, `$plugin_file`, refers to the path
		 * to the plugin file, relative to the plugins directory.
		 *
		 * @since 2.7.0
		 *
		 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $status      Status of the plugin. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade', 'Must-Use',
		 *                            'Drop-ins', 'Search'.
		 */
		do_action( "after_plugin_row_$plugin_file", $plugin_file, $plugin_data, $status );
	}
}
