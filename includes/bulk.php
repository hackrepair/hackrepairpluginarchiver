<?php
/**
 * Prevent direct access to this file
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HackRepair_Plugin_Archiver_Bulk_Action {
	private $actions = array();

	public function __construct( $args = '' ) {
	}
	public function register_bulk_action( $args = '' ) {
		$defaults             = array(
			'action_name'  => '',
			'menu_text'    => '',
			'admin_notice' => '',
			'page'         => false,
		);
		$args                 = wp_parse_args( $args, $defaults );
		$func                 = array();
		$func['callback']     = $args['callback'];
		$func['menu_text']    = $args['menu_text'];
		$func['admin_notice'] = $args['admin_notice'];
		$func['page']         = $args['page'];
		if ( $args['action_name'] === '' ) {
			// Convert menu text to action_name 'Mark as sold' => 'mark_as_sold'
			$args['action_name'] = lcfirst( str_replace( ' ', '_', $args['menu_text'] ) );
		}
		$this->actions[ $args['action_name'] ] = $func;
	}
	// Callbacks need to be registered before add_actions
	public function init() {
		if ( is_admin() ) {
			// admin actions/filters
			add_action( 'admin_footer-plugins.php', array( &$this, 'custom_bulk_admin_footer' ) );
			add_action( 'load-plugins.php', array( &$this, 'custom_bulk_action' ) );
			add_action( 'admin_notices', array( &$this, 'custom_bulk_admin_notices' ) );
		}
	}


	/**
	 * Step 1: add the custom Bulk Action to the select menus
	 */
	function custom_bulk_admin_footer() {
		// Only show bulk action UI to users with the correct capability
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		// global $post_type;

		// //Only permit actions with defined post type
		// if($post_type == $this->bulk_action_post_type) {
		?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
						<?php
						foreach ( $this->actions as $action_name => $action ) {
							?>
								jQuery('<option>').val('<?php echo esc_attr( $action_name ); ?>').text('<?php echo esc_js( $action['menu_text'] ); ?>').appendTo("select[name='action']");
								jQuery('<option>').val('<?php echo esc_attr( $action_name ); ?>').text('<?php echo esc_js( $action['menu_text'] ); ?>').appendTo("select[name='action2']");
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
		// Capability check
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'hackrepair-plugin-archiver' ) );
		}
		// get the action
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
		$action        = $wp_list_table->current_action();

		// allow only defined actions
		$allowed_actions = array_keys( $this->actions );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		// security check
		check_admin_referer( 'bulk-plugins' );

		// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
		// if(isset($_REQUEST['post'])) {
		// $post_ids = array_map('intval', $_REQUEST['post']);
		// }
		// Sanitize and validate the incoming checked plugin list
		$raw_checked = isset( $_REQUEST['checked'] ) && is_array( $_REQUEST['checked'] ) ? wp_unslash( $_REQUEST['checked'] ) : array();
		$post_ids    = array();
		foreach ( $raw_checked as $p ) {
			$p = sanitize_text_field( $p );
			if ( preg_match( '/^[a-zA-Z0-9\-_\.\/]+$/', $p ) ) {
				$post_ids[] = $p;
			}
		}

		// this is based on wp-admin/edit.php
		$sendback = remove_query_arg( array( 'exported', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );
		if ( ! $sendback ) {
			$sendback = admin_url( 'plugins.php' );
		}
		$pagenum  = $wp_list_table->get_pagenum();
		$sendback = add_query_arg( 'paged', $pagenum, $sendback );
		// Check if we have anonymous function as a callback (PHP 5.3+)
		if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) {
			// Check that we have anonymous function as a callback
			$anon_fns = array_filter(
				$this->actions[ $action ],
				function ( $el ) {
					return $el instanceof Closure;
				}
			);
			if ( 0 !== count( $anon_fns ) ) {
				// Finally use the callback
				$result = $this->actions[ $action ]['callback']( $post_ids );
			} else {
				$result = call_user_func( $this->actions[ $action ]['callback'], $post_ids );
			}
		} else {
			$result = call_user_func( $this->actions[ $action ]['callback'], $post_ids );
		}
		$sendback = add_query_arg(
			array(
				'success_action' => $action,
				'count'          => HackRepair_Plugin_Archiver::$count,
			),
			$sendback
		);

		$sendback = remove_query_arg( array( 'action', 'paged', 'mode', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );
		wp_safe_redirect( $sendback );
		exit();
		// }
	}


	/**
	 * Step 3: display an admin notice after action
	 */
	function custom_bulk_admin_notices() {
		global $pagenow;

		if ( $pagenow === 'plugins.php' ) {
			$success_action = isset( $_REQUEST['success_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['success_action'] ) ) : '';
			if ( $success_action && isset( $this->actions[ $success_action ] ) ) {
				// Print notice in admin bar
				$message = $this->actions[ $success_action ]['admin_notice'];
				if ( ! empty( $message ) ) {
					$count          = isset( $_REQUEST['count'] ) ? intval( $_REQUEST['count'] ) : 0;
					$nooped_message = sprintf( translate_nooped_plural( $message, $count, 'hackrepair-plugin-archiver' ), $count );
					echo '<div class="updated"><p>' . esc_html( $nooped_message ) . '</p></div>';
				}
			}
		}
	}
}
