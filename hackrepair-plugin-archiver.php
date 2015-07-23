<?php
/*
Plugin Name: The Hack Repair Guys Plugin Archiver
Plugin URI: http://wordpress.org/extend/plugins/hackrepair-plugin-archiver/
Description: Quickly deactivate and archive a plugin for later review. Archiving a plugin both deactivates and removes the plugin from your visible Plugins list.
Author: Jim Walker
Version: 0.1.0
Author URI: http://hackrepair.com/hackrepair-plugin-archiver/
*/


add_action('plugins_loaded', array( 'HackRepair_Plugin_Archiver', 'init' ) );
class HackRepair_Plugin_Archiver {
	public static $count = 0;
	public static $options = array(
		'archive_dir' => 'plugins-archive',
		'deactivate'  => true,
	);
	public static function init() {
		$options = get_option( 'hackrepair-plugin-archiver_options' );
		self::$options = wp_parse_args( $options, self::$options );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'HackRepair_Plugin_Archiver', 'admin_init'  ) );
		}
		$bulk_action = new HackRepair_Plugin_Archiver_Bulk_Action();
		$bulk_action->init();
		$bulk_action->register_bulk_action( array(
			'action_name'  => 'archive-selected',
			'menu_text'    => __( 'Archive', 'hackrepair-plugin-archiver' ),
			'admin_notice' => _n_noop( 'Plugin archived sucessfully', '%d plugins archived sucessfully', 'hackrepair-plugin-archiver' ),
			'callback'     => array( 'HackRepair_Plugin_Archiver', 'bulk_archive' ),
		) );
		add_filter( 'plugin_action_links', 			array( 'HackRepair_Plugin_Archiver', 'action_link' ), 10, 4 );
		add_action( 'admin_menu',          			array( 'HackRepair_Plugin_Archiver', 'menu' ) );
		add_filter( 'custom_menu_order',   			array( 'HackRepair_Plugin_Archiver', 'menu_order' ) );
		add_action( 'load-plugins_page_hackrepair-plugin-archiver', 	array( 'HackRepair_Plugin_Archiver', 'archive_actions' ) );
		add_action( 'admin_notices',          		array( 'HackRepair_Plugin_Archiver', 'admin_notice' ) );
		add_filter( 'views_plugins', 				array( 'HackRepair_Plugin_Archiver', 'plugin_views' ) );
	}
	public static function admin_init() {
		require_once ( 'includes/options.php' );
		$fields =   array(
			"general" => array(
				'title' => '',
				'callback' => '',
				'options' => array(
					'archive_dir' => array(
						'title'=>__('Archive directory','hackrepair-plugin-archiver'),
						'args' => array (
							'description' => __( 'Name of the directory to store archived plugins in. Relative to <code>WP_CONTENT_DIR</code>.', 'hackrepair-plugin-archiver' ),
						),
						'callback' => 'text',
					),
					'deactivate' => array(
						'title'=>__('Deactivate before archiving','hackrepair-plugin-archiver'),
						'args' => array (
							'description' => __( 'Should the plugin be automatically deactivated before moving it to the archive?', 'hackrepair-plugin-archiver' ),
						),
						'callback' => 'checkbox',
					),
				),
			),
		);
		HackRepair_Plugin_Archiver_Options::init(
		'hackrepair-plugin-archiver',
		__( 'Plugin Archiver',          'hackrepair-plugin-archiver' ),
		__( 'Plugin Archiver Settings', 'hackrepair-plugin-archiver' ),
		$fields,
		'hackrepair-plugin-archiver'
		);
	}

	public static function plugin_views( $views ){
		$plugins = self::get_archived_plugins();
		$count = sizeof( $plugins );
		if ( 0 < $count ) {
			$link = admin_url( 'plugins.php?page=hackrepair-plugin-archiver' );
			$title = __( 'Archived', 'hackrepair-plugin-archiver' );
			$view = "<a href=\"{$link}\">{$title} <span class=\"count\">({$count})</span></a>";
		    $views['archived'] = $view;
		}
	    return $views;
	}

	public static function get_archived_plugins($plugin_root = '') {	
        $wp_plugins = array ();
        if ( empty($plugin_root) ) {
                $plugin_root = WP_CONTENT_DIR.'/'.HackRepair_Plugin_Archiver::$options['archive_dir'];	        	
        }
	
        // Files in wp-content/plugins directory
        $plugins_dir = @ opendir( $plugin_root);
        $plugin_files = array();
        if ( $plugins_dir ) {
                while (($file = readdir( $plugins_dir ) ) !== false ) {
                        if ( substr($file, 0, 1) == '.' )
                                continue;
                        if ( is_dir( $plugin_root.'/'.$file ) ) {
                                $plugins_subdir = @ opendir( $plugin_root.'/'.$file );
                                if ( $plugins_subdir ) {
                                        while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
                                                if ( substr($subfile, 0, 1) == '.' )
                                                        continue;
                                                if ( substr($subfile, -4) == '.php' )
                                                        $plugin_files[] = "$file/$subfile";
                                        }
                                        closedir( $plugins_subdir );
                                }
                        } else {
                                if ( substr($file, -4) == '.php' )
                                        $plugin_files[] = $file;
                        }
                }
                closedir( $plugins_dir );
        }
        if ( empty($plugin_files) )
                return $wp_plugins;

        foreach ( $plugin_files as $plugin_file ) {
                if ( !is_readable( "$plugin_root/$plugin_file" ) )
                        continue;

                $plugin_data = get_plugin_data( "$plugin_root/$plugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

                if ( empty ( $plugin_data['Name'] ) )
                        continue;

                $wp_plugins[plugin_basename( $plugin_file )] = $plugin_data;
        }
        uasort( $wp_plugins, '_sort_uname_callback' );

        // $cache_plugins[ $plugin_folder ] = $wp_plugins;
        // wp_cache_set('plugins', $cache_plugins, 'plugins');

        return $wp_plugins;
	}

	public static function archive_actions() {
        if ( isset($_REQUEST['action']) ) {
 		   	switch ( $_REQUEST['action'] ) {
 		   		case 'restore-selected':
 		   		    $result = HackRepair_Plugin_Archiver::bulk_restore();
 		   			if ( !$result ) {
						include(ABSPATH . 'wp-admin/admin-footer.php');
						die();
 		   			} else {
 		   				wp_redirect( admin_url( 'plugins.php?page=hackrepair-plugin-archiver&success_action=restore-selected&count='.self::$count ) );
 		   			}
 		   			break;
 		   		case 'remove-selected':
 		   		    $result = HackRepair_Plugin_Archiver::bulk_remove();
 		   			if ( !$result ) {
						include(ABSPATH . 'wp-admin/admin-footer.php');
						die();
 		   			} else {
 		   				wp_redirect( admin_url( 'plugins.php?page=hackrepair-plugin-archiver&success_action=remove-selected&count='.self::$count ) );
 		   			}
 		   			break;
 		   	}
        }
    }	
	public static function admin_notice() {
		global $pagenow;
		if( $pagenow == 'plugins.php' ) {
			if (isset($_REQUEST['success_action']) && 'restore-selected' == $_REQUEST['success_action'] ) {
				//Print notice in admin bar
				$message = _n_noop( 'Plugin restored sucessfully', '%d plugins restored sucessfully', 'hackrepair-plugin-archiver' );
				if(!empty($message)) {
					$nooped_message = sprintf( translate_nooped_plural( $message, $_REQUEST['count'], 'hackrepair-plugin-archiver' ), $_REQUEST['count'] );
					echo "<div class=\"updated\"><p>{$nooped_message}</p></div>";
				}
			}
			if (isset($_REQUEST['success_action']) && 'remove-selected' == $_REQUEST['success_action'] ) {
				//Print notice in admin bar
				$message = _n_noop( 'Plugin deleted sucessfully', '%d plugins deleted sucessfully', 'hackrepair-plugin-archiver' );
				if(!empty($message)) {
					$nooped_message = sprintf( translate_nooped_plural( $message, $_REQUEST['count'], 'hackrepair-plugin-archiver' ), $_REQUEST['count'] );
					echo "<div class=\"updated\"><p>{$nooped_message}</p></div>";
				}
			}
		}
	}

	public static function menu_order ( $menu_ord ) {
	    global $submenu;

	    $key = self::array_search( 'hackrepair-plugin-archiver', 2, $submenu['plugins.php'] );
	    if (false !== $key ) {
	    	$temp = $submenu['plugins.php'][$key];
	    	unset($submenu['plugins.php'][$key]);
	    	$submenu['plugins.php'][9.9] = $temp;
	    	ksort($submenu['plugins.php']);
	    }
	    return $menu_ord;
	}
	public static function menu() {
		$a = add_plugins_page( 
			__( 'Plugin Archive', 'hackrepair-plugin-archiver' ), 
			__( 'Archived Plugins',        'hackrepair-plugin-archiver' ),
			'install_plugins', 
			'hackrepair-plugin-archiver', 
			array( 'HackRepair_Plugin_Archiver', 'archive_page' )
		);
		// var_dump($a);
	}
	public static function archive_page() {
		global $title;
		add_screen_option( 'per_page', array( 'default' => 3 ) );
		$wp_list_table = new WP_Plugins_Archive_List_Table();
		$pagenum = $wp_list_table->get_pagenum();
		// $action = $_REQUEST['action'];//$wp_list_table->current_action();
		// switch ($action) {
		// 	case 'restore-selected' : 
		// 	  $result = self::bulk_restore();
		// 	  if ($result) {
		// 	  	echo 'aaaaaaaaa';
		// 	  	wp_redirect( admin_url('plugins.php?page=hackrepair-plugin-archiver&success_action=restore-selected') );
		// 	  } else {
		// 	  	return $result;
		// 	  }
		// 	break;
		// 	default :
		// 	break;
		// }
		if (self::$count) {
			var_dump(self::$count);
		}
		$wp_list_table->prepare_items();
		echo '<div class="wrap">';
		echo '<h2>'.esc_html( $title ) .'</h2>';
		//$wp_list_table->views();
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="hackrepair-plugin-archiver" />';
		$wp_list_table->search_box( __( 'Search Archived Plugins', 'hackrepair-plugin-archiver' ), 'plugin' ); 
		echo '</form>';
		echo '<form method="post" id="bulk-action-form">';
		echo '<input type="hidden" name="page" value="hackrepair-plugin-archiver" />';
		$wp_list_table->display();
		echo '</form>';		
		echo '</div>';
	}
	public static function action_link($actions, $plugin_file, $plugin_data, $context) {
		$exclude_context = array( 'mustuse', 'dropins' );
		$page = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';
		if ( !in_array( $context, $exclude_context) && ( 'hackrepair-plugin-archiver/hackrepair-plugin-archiver.php' !== $plugin_file ) && ( 'hackrepair-plugin-archiver' !== $page) ) {
			$actions['archive'] = '<a href="' . wp_nonce_url( 'plugins.php?action=archive-selected&amp;checked%5B0%5D=' . $plugin_file, 'bulk-plugins' ) . '" aria-label="' . esc_attr( sprintf( __( 'Archive %s', 'hackrepair-plugin-archiver' ), $plugin_data['Name'] ) ) . '">' . __( 'Archive', 'hackrepair-plugin-archiver' ) . '</a>';
		} else {
			$actions['restore-all'] = '<a href="' . wp_nonce_url( 'plugins.php?page=hackrepair-plugin-archiver&amp;action=restore-selected&amp;all=true', 'bulk-plugins' ) . '" aria-label="' . esc_attr( __( 'Unarchive all archived plugins', 'hackrepair-plugin-archiver' ) ) . '">' . __( 'Unarchive All', 'hackrepair-plugin-archiver' ) . '</a>';			
		}
		return $actions;
	}
	public static function bulk_remove($checked=false) {
		global $wp_filesystem;
		$form_fields = $_REQUEST;
		unset($form_fields['_wpnonce']);
		unset($form_fields['_wp_http_referer']);
		$url = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds = request_filesystem_credentials($url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
    	ob_end_clean();
		if ( $creds ) {
			WP_Filesystem($creds);
			$archive_dir = trailingslashit( $wp_filesystem->wp_content_dir() . self::$options['archive_dir']);
			$wp_filesystem->mkdir( $archive_dir );
			$count = 0;
			foreach ( $_REQUEST['checked'] as $plugin ) {
				//if ( isset( $plugins[$plugin] ) ) {
					$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
					$result = $wp_filesystem->delete( $plugin_dir );
					if ( $result) {
						$count++;
					}
				//}
			}
			self::$count = $count;
			return true;
		} else {
			require_once( ABSPATH . 'wp-admin/admin.php' );
			require_once( ABSPATH . 'wp-admin/admin-header.php');
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
//			include(ABSPATH . 'wp-admin/admin-footer.php');
			return false;
		}
	}
	public static function bulk_restore( $checked=false ) {
		global $wp_filesystem;
		$form_fields = $_REQUEST;
		unset($form_fields['_wpnonce']);
		unset($form_fields['_wp_http_referer']);
		$url = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds = request_filesystem_credentials($url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
    	ob_end_clean();
		if ( $creds ) {
			WP_Filesystem($creds);
			$archive_dir = trailingslashit( $wp_filesystem->wp_content_dir() . self::$options['archive_dir']);
			$wp_filesystem->mkdir( $archive_dir );
			$count = 0;
			if ( isset( $_REQUEST['all'] ) &&  $_REQUEST['all'] ) {
				$plugins = self::get_archived_plugins();
				$checked = array_keys($plugins);
				$redirect = admin_url('plugins.php');
			} else {
				$checked = $_REQUEST['checked'];
			}
			foreach ( $checked as $plugin ) {
				//if ( isset( $plugins[$plugin] ) ) {
					$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
					$target_dir = self::plugin_basename( $plugin, WP_PLUGIN_DIR );
					$result = $wp_filesystem->move( $plugin_dir, $target_dir );
					if ( $result) {
						$count++;
					}
				//}
			}
			self::$count = $count;
			return true;
		} else {
			require_once( ABSPATH . 'wp-admin/admin.php' );
			require_once( ABSPATH . 'wp-admin/admin-header.php');
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
//			include(ABSPATH . 'wp-admin/admin-footer.php');
			return false;
		}
	}
	public static function bulk_archive($checked) {
		global $wp_filesystem;
		$plugins = get_plugins();
		$form_fields = $_REQUEST;
		$url = add_query_arg( $form_fields, admin_url( 'plugins.php' ) );
		ob_start();
		$creds = request_filesystem_credentials($url, get_filesystem_method(), false, false );
		$output = ob_get_contents();
    	ob_end_clean();
		if ( $creds ) {
			WP_Filesystem($creds);
			$archive_dir = trailingslashit( $wp_filesystem->wp_content_dir() . self::$options['archive_dir']);
			$wp_filesystem->mkdir( $archive_dir );
			$count = 0;
			foreach ( $_REQUEST['checked'] as $plugin ) {
				if ( isset( $plugins[$plugin] ) ) {
					$target_dir = self::plugin_basename( $plugin, $archive_dir );
					$plugin_dir = self::plugin_basename( $plugin, WP_PLUGIN_DIR );
					if ( self::$options['deactivate'] ) {
		 				deactivate_plugins( $plugin );
					}
					$result = $wp_filesystem->move( $plugin_dir, $target_dir );
					if ( $result) {
						$count++;
					}
				}
			}
			self::$count = $count;
		} else {
			unset( $_REQUEST['success_action'] );
			require_once( ABSPATH . 'wp-admin/admin.php' );
			require_once( ABSPATH . 'wp-admin/admin-header.php');
			echo '<div class="wrap">';
			echo $output;
			echo '</div>';
			include(ABSPATH . 'wp-admin/admin-footer.php');
			die('');
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

	private static function array_search($needle,$key, $haystack) {
	    foreach($haystack as $main_key=>$value) {
	        if( $needle === $value[$key] ) {
	            return $main_key;
	        }
	    }
	    return false;
	}

}


if (!class_exists('HackRepair_Plugin_Archiver_Bulk_Action')) {
 
	class HackRepair_Plugin_Archiver_Bulk_Action {
		private $actions = array();
		
		public function __construct($args='') {
		}
		public function register_bulk_action($args='') {
			$defaults = array (
				'action_name' => '',
				'menu_text' => '',
				'admin_notice' => '',
				'page' => false,
			);
			$args = wp_parse_args( $args, $defaults);
			$func = array();
			$func["callback"] = $args['callback'];
			$func["menu_text"] = $args['menu_text'];
			$func["admin_notice"] = $args['admin_notice'];
			$func["page"] = $args['page'];
			if ($args['action_name'] === '') {
				//Convert menu text to action_name 'Mark as sold' => 'mark_as_sold'
				$args['action_name'] = lcfirst(str_replace(' ', '_', $args['menu_text']));
			}
			$this->actions[$args['action_name']] = $func;
		}
		//Callbacks need to be registered before add_actions
		public function init() {
			if(is_admin()) {
				// admin actions/filters
				add_action('admin_footer-plugins.php', array(&$this, 'custom_bulk_admin_footer'));
				add_action('load-plugins.php',         array(&$this, 'custom_bulk_action'));
				add_action('admin_notices',            array(&$this, 'custom_bulk_admin_notices'));
			}
		}
		
		
		/**
		 * Step 1: add the custom Bulk Action to the select menus
		 */
		function custom_bulk_admin_footer() {
			// global $post_type;
			
			// //Only permit actions with defined post type
			// if($post_type == $this->bulk_action_post_type) {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
							<?php
							foreach ($this->actions as $action_name => $action) { ?>
								jQuery('<option>').val('<?php echo $action_name ?>').text('<?php echo $action["menu_text"] ?>').appendTo("select[name='action']");
								jQuery('<option>').val('<?php echo $action_name ?>').text('<?php echo $action["menu_text"] ?>').appendTo("select[name='action2']");
							<?php } ?>
						});
					</script>
				<?php
			// }
		}
		
		
		/**
		 * Step 2: handle the custom Bulk Action
		 * 
		 * Based on the post http://wordpress.stackexchange.com/questions/29822/custom-bulk-action
		 */
		function custom_bulk_action() {
			// get the action
			$wp_list_table = _get_list_table('WP_Plugins_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
			$action = $wp_list_table->current_action();
			
			// allow only defined actions
			$allowed_actions = array_keys($this->actions);
			if(!in_array($action, $allowed_actions)) return;
			
			// security check
			check_admin_referer('bulk-plugins');

			// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
			// if(isset($_REQUEST['post'])) {
			// 	$post_ids = array_map('intval', $_REQUEST['post']);
			// }
			$post_ids = $_REQUEST['checked'];

			// this is based on wp-admin/edit.php
			$sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
			if ( ! $sendback ) {
				$sendback = admin_url( "plugins.php" );
			}
			$pagenum = $wp_list_table->get_pagenum();
			$sendback = add_query_arg( 'paged', $pagenum, $sendback );
			if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
				//check that we have anonymous function as a callback
				$anon_fns = array_filter( $this->actions[$action], function( $el) { return $el instanceof Closure; });
				if( count($anon_fns) != 0) {
					//Finally use the callback
					$result = $this->actions[$action]['callback']($post_ids);
				}
				else {
					$result = call_user_func($this->actions[$action]['callback'], $post_ids);
				}
			}
			else {
				$result = call_user_func($this->actions[$action]['callback'], $post_ids);
			}
			$sendback = add_query_arg( array('success_action' => $action, 'count' => HackRepair_Plugin_Archiver::$count), $sendback );
			
			$sendback = remove_query_arg( array('action', 'paged', 'mode', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
			wp_redirect($sendback);
			exit();
		// }
		}
		
		
		/**
		 * Step 3: display an admin notice after action
		 */
		function custom_bulk_admin_notices() {
			global $pagenow;
			
			if( $pagenow == 'plugins.php' ) {
				if (isset($_REQUEST['success_action']) && isset($this->actions[$_REQUEST['success_action']])) {
					//Print notice in admin bar
					$message = $this->actions[$_REQUEST['success_action']]['admin_notice'];
					if(!empty($message)) {
						$nooped_message = sprintf( translate_nooped_plural( $message, $_REQUEST['count'], 'hackrepair-plugin-archiver' ), $_REQUEST['count'] );
						echo "<div class=\"updated\"><p>{$nooped_message}</p></div>";
					}
				}
			}
		}
	}
}

if ( !class_exists('WP_List_Table') ) {
	require_once( ABSPATH. 'wp-admin/includes/class-wp-list-table.php');
}
class WP_Plugins_Archive_List_Table extends WP_List_Table {

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

		parent::__construct( array(
			'plural' => 'plugins',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : 'plugins',
		) );

		$status = 'all';

		if ( isset($_REQUEST['s']) )
			$_SERVER['REQUEST_URI'] = add_query_arg('s', wp_unslash($_REQUEST['s']) );

		$page = $this->get_pagenum();
	}

	protected function get_table_classes() {
		return array( 'widefat', $this->_args['plural'] );
	}

	// public function ajax_user_can() {
	// 	return false current_user_can('activate_plugins');
	// }

	public function prepare_items() {
		global $status, $plugins, $totals, $page, $orderby, $order, $s;

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
		$all_plugins = HackRepair_Plugin_Archiver::get_archived_plugins();
		$all_plugins = apply_filters( 'all_plugins', $all_plugins );
		$plugins = array(
			'all' => $all_plugins,
		);

		$screen = $this->screen;

		// set_transient( 'plugin_slugs', array_keys( $plugins['all'] ), DAY_IN_SECONDS );

		// foreach ( (array) $plugins['all'] as $plugin_file => $plugin_data ) {
		// 	// Extra info if known. array_merge() ensures $plugin_data has precedence if keys collide.
		// 	if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
		// 		$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
		// 		// Make sure that $plugins['upgrade'] also receives the extra info since it is used on ?plugin_status=upgrade
		// 		if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
		// 			$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
		// 		}

		// 	} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
		// 		$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
		// 		// Make sure that $plugins['upgrade'] also receives the extra info since it is used on ?plugin_status=upgrade
		// 		if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
		// 			$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
		// 		}
		// 	}

		// 	// Filter into individual sections
		// 	if ( is_multisite() && ! $screen->in_admin( 'network' ) && is_network_only_plugin( $plugin_file ) && ! is_plugin_active( $plugin_file ) ) {
		// 		// On the non-network screen, filter out network-only plugins as long as they're not individually activated
		// 		unset( $plugins['all'][ $plugin_file ] );
		// 	} elseif ( ! $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) {
		// 		// On the non-network screen, filter out network activated plugins
		// 		unset( $plugins['all'][ $plugin_file ] );
		// 	} elseif ( ( ! $screen->in_admin( 'network' ) && is_plugin_active( $plugin_file ) )
		// 		|| ( $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) ) {
		// 		// On the non-network screen, populate the active list with plugins that are individually activated
		// 		// On the network-admin screen, populate the active list with plugins that are network activated
		// 		$plugins['active'][ $plugin_file ] = $plugin_data;
		// 	} else {
		// 		if ( ! $screen->in_admin( 'network' ) && isset( $recently_activated[ $plugin_file ] ) ) {
		// 			// On the non-network screen, populate the recently activated list with plugins that have been recently activated
		// 			$plugins['recently_activated'][ $plugin_file ] = $plugin_data;
		// 		}
		// 		// Populate the inactive list with plugins that aren't activated
		// 		$plugins['inactive'][ $plugin_file ] = $plugin_data;
		// 	}
		// }


		if ( $s ) {
			$status = 'search';
			$plugins['search'] = array_filter( $plugins['all'], array( $this, '_search_callback' ) );
		}

		$totals = array();
		foreach ( $plugins as $type => $list )
			$totals[ $type ] = count( $list );

		if ( empty( $plugins[ $status ] ) && !in_array( $status, array( 'all', 'search' ) ) )
			$status = 'all';

		$this->items = array();
		foreach ( $plugins[ $status ] as $plugin_file => $plugin_data ) {
			// Translate, Don't Apply Markup, Sanitize HTML
			$this->items[$plugin_file] = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, false, true );
		}
		$total_this_page = $totals[ $status ];

		if ( $orderby ) {
			$orderby = ucfirst( $orderby );
			$order = strtoupper( $order );

			uasort( $this->items, array( $this, '_order_callback' ) );
		}

		$plugins_per_page = $this->get_items_per_page( str_replace( '-', '_', $screen->id . '_per_page' ), 999 );

		$start = ( $page - 1 ) * $plugins_per_page;

		if ( $total_this_page > $plugins_per_page )
			$this->items = array_slice( $this->items, $start, $plugins_per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_this_page,
			'per_page' => $plugins_per_page,
		) );
	}

	/**
	 * @staticvar string $term
	 * @param array $plugin
	 * @return boolean
	 */
	public function _search_callback( $plugin ) {
		static $term;
		if ( is_null( $term ) )
			$term = wp_unslash( $_REQUEST['s'] );

		foreach ( $plugin as $value ) {
			if ( false !== stripos( strip_tags( $value ), $term ) ) {
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

		$a = $plugin_a[$orderby];
		$b = $plugin_b[$orderby];

		if ( $a == $b )
			return 0;

		if ( 'DESC' == $order )
			return ( $a < $b ) ? 1 : -1;
		else
			return ( $a < $b ) ? -1 : 1;
	}

	public function no_items() {
		global $plugins;
		_e( 'You do not appear to have any archived plugins', 'hackrepair-plugin-archiver' );
	}

	public function get_columns() {
		global $status;

		return array(
			'cb'          => !in_array( $status, array( 'mustuse', 'dropins' ) ) ? '<input type="checkbox" />' : '',
			'name'        => __( 'Plugin' ),
			'description' => __( 'Description' ),
		);
	}

	protected function get_sortable_columns() {
		return array();
	}

	protected function get_views() {
		global $totals, $status;

		$status_links = array();
		$all_plugins = get_plugins();
		$plugins = array(
			'all' => $all_plugins,
		);

		$screen = $this->screen;

		// set_transient( 'plugin_slugs', array_keys( $plugins['all'] ), DAY_IN_SECONDS );

		foreach ( (array) $plugins['all'] as $plugin_file => $plugin_data ) {
			// Extra info if known. array_merge() ensures $plugin_data has precedence if keys collide.
			if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
				$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
				// Make sure that $plugins['upgrade'] also receives the extra info since it is used on ?plugin_status=upgrade
				if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
					$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->response[ $plugin_file ], $plugin_data );
				}

			} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
				$plugins['all'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
				// Make sure that $plugins['upgrade'] also receives the extra info since it is used on ?plugin_status=upgrade
				if ( isset( $plugins['upgrade'][ $plugin_file ] ) ) {
					$plugins['upgrade'][ $plugin_file ] = $plugin_data = array_merge( (array) $plugin_info->no_update[ $plugin_file ], $plugin_data );
				}
			}

			// Filter into individual sections
			if ( is_multisite() && ! $screen->in_admin( 'network' ) && is_network_only_plugin( $plugin_file ) && ! is_plugin_active( $plugin_file ) ) {
				// On the non-network screen, filter out network-only plugins as long as they're not individually activated
				unset( $plugins['all'][ $plugin_file ] );
			} elseif ( ! $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) {
				// On the non-network screen, filter out network activated plugins
				unset( $plugins['all'][ $plugin_file ] );
			} elseif ( ( ! $screen->in_admin( 'network' ) && is_plugin_active( $plugin_file ) )
				|| ( $screen->in_admin( 'network' ) && is_plugin_active_for_network( $plugin_file ) ) ) {
				// On the non-network screen, populate the active list with plugins that are individually activated
				// On the network-admin screen, populate the active list with plugins that are network activated
				$plugins['active'][ $plugin_file ] = $plugin_data;
			} else {
				if ( ! $screen->in_admin( 'network' ) && isset( $recently_activated[ $plugin_file ] ) ) {
					// On the non-network screen, populate the recently activated list with plugins that have been recently activated
					$plugins['recently_activated'][ $plugin_file ] = $plugin_data;
				}
				// Populate the inactive list with plugins that aren't activated
				$plugins['inactive'][ $plugin_file ] = $plugin_data;
			}
		}

		$totals = array();
		foreach ( $plugins as $type => $list )
			$totals[ $type ] = count( $list );


		foreach ( $totals as $type => $count ) {
			if ( !$count )
				continue;

			switch ( $type ) {
				case 'all':
					$text = _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'plugins' );
					break;
				case 'active':
					$text = _n( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', $count );
					break;
				case 'recently_activated':
					$text = _n( 'Recently Active <span class="count">(%s)</span>', 'Recently Active <span class="count">(%s)</span>', $count );
					break;
				case 'inactive':
					$text = _n( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', $count );
					break;
				case 'mustuse':
					$text = _n( 'Must-Use <span class="count">(%s)</span>', 'Must-Use <span class="count">(%s)</span>', $count );
					break;
				case 'dropins':
					$text = _n( 'Drop-ins <span class="count">(%s)</span>', 'Drop-ins <span class="count">(%s)</span>', $count );
					break;
				case 'upgrade':
					$text = _n( 'Update Available <span class="count">(%s)</span>', 'Update Available <span class="count">(%s)</span>', $count );
					break;
			}

			if ( 'search' != $type ) {
				$status_links[$type] = sprintf( "<a href='%s' %s>%s</a>",
					add_query_arg('plugin_status', $type, 'plugins.php'),
					( $type == $status ) ? ' class="current"' : '',
					sprintf( $text, number_format_i18n( $count ) )
					);
			}
		}

		return $status_links;
	}

	protected function get_bulk_actions() {
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

		if ( in_array( $status, array( 'mustuse', 'dropins' ) ) )
			return;

		parent::bulk_actions( $which );
	}

	/**
	 * @global string $status
	 * @param string $which
	 * @return null
	 */
	protected function extra_tablenav( $which ) {
		global $status;
		echo '<div class="alignleft actions" style="margin-top:1px;"><a class="button action" href="'.admin_url( 'plugins.php?page=hackrepair-plugin-archiver&amp;action=restore-selected&amp;all=true').'">'.__( 'Unarchive All', 'hackrepair-plugin-archiver' ).'</a></div>';

		if ( ! in_array($status, array('recently_activated', 'mustuse', 'dropins') ) )
			return;

		echo '<div class="alignleft actions">';

		if ( ! $this->screen->in_admin( 'network' ) && 'recently_activated' == $status )
			submit_button( __( 'Clear List' ), 'button', 'clear-recent-list', false );
		elseif ( 'top' == $which && 'mustuse' == $status )
			echo '<p>' . sprintf( __( 'Files in the <code>%s</code> directory are executed automatically.' ), str_replace( ABSPATH, '/', WPMU_PLUGIN_DIR ) ) . '</p>';
		elseif ( 'top' == $which && 'dropins' == $status )
			echo '<p>' . sprintf( __( 'Drop-ins are advanced plugins in the <code>%s</code> directory that replace WordPress functionality when present.' ), str_replace( ABSPATH, '', WP_CONTENT_DIR ) ) . '</p>';

		echo '</div>';
	}

	public function current_action() {
		if ( isset($_POST['clear-recent-list']) )
			return 'clear-recent-list';

		return parent::current_action();
	}

	public function display_rows() {
		global $status;

		// if ( is_multisite() && ! $this->screen->in_admin( 'network' ) && in_array( $status, array( 'mustuse', 'dropins' ) ) )
		// 	return;

		foreach ( $this->items as $plugin_file => $plugin_data )
			$this->single_row( array( $plugin_file, $plugin_data ) );
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
		$context = $status;
		$screen = $this->screen;

		// Pre-order.
		$actions = array(
			'restore' => '<a href="' . wp_nonce_url('plugins.php?page=hackrepair-plugin-archiver&amp;action=restore-selected&amp;checked%5B0%5D=' . $plugin_file . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins') . '" title="' . esc_attr__('Unarchive this plugin','hackrepair-plugin-archiver') . '">' . __('Unarchive', 'hackrepair-plugin-archiver') . '</a>',
			'remove' => '<a href="' . wp_nonce_url('plugins.php?page=hackrepair-plugin-archiver&amp;action=remove-selected&amp;checked%5B0%5D=' . $plugin_file . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins') . '" title="' . esc_attr__('Delete this plugin','hackrepair-plugin-archiver') . '" class="delete">' . __('Delete', 'hackrepair-plugin-archiver') . '</a>',
		);



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

		$class = 'inactive';
		$checkbox_id =  "checkbox_" . md5($plugin_data['Name']);
		if ( in_array( $status, array( 'mustuse', 'dropins' ) ) ) {
			$checkbox = '';
		} else {
			$checkbox = "<label class='screen-reader-text' for='" . $checkbox_id . "' >" . sprintf( __( 'Select %s' ), $plugin_data['Name'] ) . "</label>"
				. "<input type='checkbox' name='checked[]' value='" . esc_attr( $plugin_file ) . "' id='" . $checkbox_id . "' />";
		}
		if ( 'dropins' != $context ) {
			$description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
			$plugin_name = $plugin_data['Name'];
		}

		$id = sanitize_title( $plugin_name );
		if ( ! empty( $totals['upgrade'] ) && ! empty( $plugin_data['update'] ) )
			$class .= ' update';

		$plugin_slug = ( isset( $plugin_data['slug'] ) ) ? $plugin_data['slug'] : '';
		printf( "<tr id='%s' class='%s' data-slug='%s'>",
			$id,
			$class,
			$plugin_slug
		);

		list( $columns, $hidden ) = $this->get_column_info();
		// var_dump($this->get_column_info());
		// $columns = $this->get_columns();

		foreach ( $columns as $column_name => $column_display_name ) {
			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			switch ( $column_name ) {
				case 'cb':
					echo "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'name':
					echo "<td class='plugin-title'$style><strong>$plugin_name</strong>";
					echo $this->row_actions( $actions, true );
					echo "</td>";
					break;
				case 'description':
					echo "<td class='column-description desc'$style>
						<div class='plugin-description'>$description</div>
						<div class='$class second plugin-version-author-uri'>";

					$plugin_meta = array();
					if ( !empty( $plugin_data['Version'] ) )
						$plugin_meta[] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
					if ( !empty( $plugin_data['Author'] ) ) {
						$author = $plugin_data['Author'];
						if ( !empty( $plugin_data['AuthorURI'] ) )
							$author = '<a href="' . $plugin_data['AuthorURI'] . '">' . $plugin_data['Author'] . '</a>';
						$plugin_meta[] = sprintf( __( 'By %s' ), $author );
					}

					// Details link using API info, if available
					if ( isset( $plugin_data['slug'] ) && current_user_can( 'install_plugins' ) ) {
						$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
							esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data['slug'] .
								'&TB_iframe=true&width=600&height=550' ) ),
							esc_attr( sprintf( __( 'More information about %s' ), $plugin_name ) ),
							esc_attr( $plugin_name ),
							__( 'View details' )
						);
					} elseif ( ! empty( $plugin_data['PluginURI'] ) ) {
						$plugin_meta[] = sprintf( '<a href="%s">%s</a>',
							esc_url( $plugin_data['PluginURI'] ),
							__( 'Visit plugin site' )
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
					echo implode( ' | ', $plugin_meta );

					echo "</div></td>";
					break;
				default:
					echo "<td class='$column_name column-$column_name'$style>";

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
					echo "</td>";
			}
		}

		echo "</tr>";

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