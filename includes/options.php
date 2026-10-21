<?php
/**
 * Settings > Plugin Archiver: settings registration, field rendering, tabs and validation.
 *
 * @package HackRepair_Plugin_Archiver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings screen.
 */
class HackRepair_Plugin_Archiver_Options {
	/**
	 * Settings fields, grouped by section.
	 *
	 * @var array
	 */
	private static $fields = array();

	/**
	 * Screen tabs.
	 *
	 * @var array
	 */
	private static $tabs = array();

	/**
	 * Option name and settings group.
	 *
	 * @var string
	 */
	private static $id = '';

	/**
	 * Menu title.
	 *
	 * @var string
	 */
	private static $menu_title = '';

	/**
	 * Screen title.
	 *
	 * @var string
	 */
	private static $title = '';

	/**
	 * Screen slug.
	 *
	 * @var string
	 */
	private static $file = '';

	/**
	 * Capability needed for the screen.
	 *
	 * @var string
	 */
	private static $role = 'manage_options';

	/**
	 * Set up the screen.
	 *
	 * @param string       $id           Option prefix.
	 * @param string       $menu_title   Menu title.
	 * @param string       $title        Screen title.
	 * @param array        $fields       Fields.
	 * @param array        $tabs         Tabs.
	 * @param string       $parent_class Unused, kept for compatibility.
	 * @param string|false $file         Screen slug.
	 * @param string|false $role         Capability.
	 */
	public static function init( $id, $menu_title, $title, $fields, $tabs, $parent_class, $file = false, $role = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature kept for compatibility.
		self::$fields     = $fields;
		self::$tabs       = $tabs;
		self::$file       = $file ? $file : __FILE__;
		self::$id         = $id . '_options';
		self::$menu_title = $menu_title;
		self::$title      = $title;
		self::$role       = $role ? $role : self::$role;
		self::build_settings();
		if ( is_admin() && current_user_can( self::$role ) ) {
			add_options_page( self::$title, self::$menu_title, self::$role, self::$file, array( __CLASS__, 'page' ) );
		}
	}

	/**
	 * Register the setting, its section and fields.
	 */
	public static function build_settings() {
		register_setting( self::$id, self::$id, array( 'sanitize_callback' => array( __CLASS__, 'validate' ) ) );
		foreach ( (array) self::$fields as $group_id => $group ) {
			add_settings_section( $group_id, $group['title'], self::callback( $group['callback'] ), self::$file );
			foreach ( (array) $group['options'] as $option_id => $option ) {
				$option['args']['option_id'] = $option_id;
				add_settings_field( $option_id, $option['title'], self::callback( $option['callback'] ), self::$file, $group_id, $option['args'] );
			}
		}
	}

	/**
	 * Resolve a callback given as a method name of this class or a callable.
	 *
	 * @param mixed $callback Callback.
	 * @return callable|string
	 */
	private static function callback( $callback ) {
		if ( ! $callback ) {
			return '';
		}
		return is_array( $callback ) ? $callback : array( __CLASS__, $callback );
	}

	/**
	 * Whether an array has string keys.
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	public static function is_assoc( $arr ) {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Field name attribute.
	 *
	 * @param array $args Field arguments.
	 * @return string
	 */
	private static function name( $args ) {
		return self::$id . '[' . $args['option_id'] . ']';
	}

	/**
	 * Field description below the control (simple markup allowed).
	 *
	 * @param array $args Field arguments.
	 */
	private static function description( $args ) {
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Drop-down. Arguments: values (list, or value => label).
	 *
	 * @param array $args Field arguments.
	 */
	public static function select( $args ) {
		$items   = (array) $args['values'];
		$current = isset( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) ? (string) HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] : '';
		if ( ! self::is_assoc( $items ) ) {
			$items = array_combine( $items, $items );
		}
		echo '<select id="' . esc_attr( self::$id . '_' . $args['option_id'] ) . '" name="' . esc_attr( self::name( $args ) ) . '">';
		foreach ( $items as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( (string) $key, $current, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		self::description( $args );
	}

	/**
	 * Checkbox.
	 *
	 * @param array $args Field arguments.
	 */
	public static function checkbox( $args ) {
		$value = ! empty( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] );
		echo '<input type="checkbox" value="1" id="' . esc_attr( $args['option_id'] ) . '" name="' . esc_attr( self::name( $args ) ) . '"' . checked( $value, true, false ) . '>';
		self::description( $args );
	}

	/**
	 * Text box for the label of a new archive.
	 *
	 * @param array $args Field arguments.
	 */
	public static function text_plugins( $args ) {
		$size  = isset( $args['size'] ) ? (int) $args['size'] : 40;
		$value = isset( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) ? HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] : '';
		echo '<input type="text" id="' . esc_attr( $args['option_id'] ) . '" name="' . esc_attr( self::name( $args ) ) . '" size="' . esc_attr( $size ) . '" value="' . esc_attr( $value ) . '">';
		self::description( $args );
	}

	/**
	 * Tab navigation.
	 *
	 * @param string $current Current tab key.
	 */
	public static function tabs( $current = 'settings' ) {
		if ( ! self::$tabs ) {
			return;
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( self::$tabs as $tab_key => $tab ) {
			$class = 'nav-tab' . ( $tab_key === $current ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $tab['href'] ) . '">' . esc_html( $tab['title'] ) . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * The Settings tab.
	 */
	public static function settings() {
		echo '<form action="options.php" method="post">';
		settings_fields( self::$id );
		do_settings_sections( self::$file );
		submit_button();
		echo '</form>';
	}

	/**
	 * Callback that renders a tab.
	 *
	 * @param string $current Tab key.
	 * @return callable
	 */
	public static function content( $current ) {
		if ( isset( self::$tabs[ $current ]['callback'] ) ) {
			$callback = self::$tabs[ $current ]['callback'];
			return is_callable( $callback ) ? $callback : array( __CLASS__, $callback );
		}
		return array( __CLASS__, 'settings' );
	}

	/**
	 * The settings screen.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		$keys    = array_keys( self::$tabs );
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
		if ( ! in_array( $current, $keys, true ) ) {
			$current = (string) reset( $keys );
		}
		echo '<div class="wrap"><h1>' . esc_html( self::$title ) . '</h1>';
		settings_errors( self::$id );
		HackRepair_Plugin_Archiver_Security::render_notices( HackRepair_Plugin_Archiver::$options['archive_dir'] );
		self::tabs( $current );
		call_user_func( self::content( $current ) );
		if ( 'settings' === $current ) {
			HackRepair_Plugin_Archiver_Security::render_panel();
		}
		echo '</div>';
	}

	/**
	 * Clean the submitted settings. Only known keys are kept.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function validate( $input ) {
		$saved = get_option( self::$id, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $input ) ) {
			return $saved;
		}

		$output = array(
			'archive_dir'     => isset( $saved['archive_dir'] ) ? (string) $saved['archive_dir'] : '',
			'archive_dir_add' => '',
			// An unchecked box is not submitted, so absence means off.
			'deactivate'      => ! empty( $input['deactivate'] ),
		);

		if ( isset( $input['archive_dir'] ) ) {
			$dir = sanitize_file_name( wp_unslash( $input['archive_dir'] ) );
			if ( HackRepair_Plugin_Archiver_Security::is_registered( $dir ) ) {
				$output['archive_dir'] = $dir;
			}
		}

		if ( ! empty( $input['archive_dir_add'] ) ) {
			$label   = sanitize_text_field( wp_unslash( $input['archive_dir_add'] ) );
			$created = current_user_can( 'install_plugins' ) ? HackRepair_Plugin_Archiver_Security::create_archive( $label ) : false;
			if ( ! $created ) {
				add_settings_error( self::$id, 'archive_create_failed', __( 'The new archive could not be created. File changes may be disabled, or the server needs file credentials.', 'hackrepair-plugin-archiver' ), 'error' );
			}
		}
		return $output;
	}
}
