<?php
/**
 * Prevent direct access to this file
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ========================================== SETTINGS

class HackRepair_Plugin_Archiver_Options {
	private static $defaults     = array();
	private static $fields       = array();
	private static $tabs         = array();
	private static $id           = '';
	private static $menu_title   = '';
	private static $title        = '';
	private static $description  = '';
	private static $file         = '';
	private static $role         = 'manage_options';
	private static $parent_class = '';

	public static function init( $id, $menu_title, $title, $fields, $tabs, $parent_class, $file = false, $role = false ) {
		self::$fields       = $fields;
		self::$tabs         = $tabs;
		self::$file         = $file ? $file : __FILE__;
		self::$id           = $id . '_options';
		self::$menu_title   = $menu_title;
		self::$title        = $title;
		self::$parent_class = $parent_class;
		self::$role         = $role ? $role : self::$role;
		self::build_settings();
		// Register the options page only for admin users with the required capability.
		if ( is_admin() && current_user_can( self::$role ) ) {
			add_options_page( self::$title, self::$menu_title, self::$role, self::$file, array( 'HackRepair_Plugin_Archiver_Options', 'page' ) );
		}
	}

	// Register our settings. Add the settings section, and settings fields
	public static function build_settings() {
		register_setting( self::$id, self::$id, array( 'HackRepair_Plugin_Archiver_Options', 'validate' ) );
		if ( is_array( self::$fields ) ) {
			foreach ( self::$fields as $group_id => $group ) {
						add_settings_section( $group_id, $group['title'], $group['callback'] ? is_array( $group['callback'] ) ? $group['callback'] : array( 'HackRepair_Plugin_Archiver_Options', $group['callback'] ) : '', self::$file );
				if ( is_array( $group['options'] ) ) {
					foreach ( $group['options'] as $option_id => $option ) {
										$option['args']['option_id'] = $option_id;
										$option['args']['title']     = $option['title'];
										add_settings_field( $option_id, $option['title'], $option['callback'] ? is_array( $option['callback'] ) ? $option['callback'] : array( 'HackRepair_Plugin_Archiver_Options', $option['callback'] ) : '', self::$file, $group_id, $option['args'] );
					}
				}
			}
		}
	}

	// ************************************************************************************************************
	// Utilities
	public static function is_assoc( $arr ) {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	// ************************************************************************************************************

	// Callback functions

	// DROP-DOWN-BOX - Name: select - Argument : values: array()
	public static function select( $args ) {
		$items       = $args['values'];
		$description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>" : '';
		echo "<select id='" . self::$id . "_{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]'>";
		if ( self::is_assoc( $items ) ) {
			foreach ( $items as $key => $item ) {
				$key      = esc_attr( $key );
				$selected = selected( $key, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ], false );
				echo "<option value='{$key}' $selected>" . esc_html( $item ) . '</option>';
			}
		} else {
			foreach ( $items as $item ) {
				$key      = esc_attr( $item );
				$selected = selected( $item, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ], false );
				echo "<option value='{$key}' $selected>$item</option>";
			}
		}
		echo "</select>{$description}";
	}

	// CHECKBOX - Name: checkbox
	public static function checkbox( $args ) {
		if ( ! isset( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) ) {
			HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] = false;
		}
		$checked     = checked( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ], true, false );
		$description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>" : '';
		echo '<input ' . $checked . " id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' type='checkbox' value=\"1\"/>{$description}";
	}

	// TEXTAREA - Name: textarea - Arguments: rows:int=4 cols:int=20
	public static function textarea( $args ) {
		if ( ! $args['rows'] ) {
			$args['rows'] = 4;
		}
		if ( ! $args['cols'] ) {
			$args['cols'] = 20;
		}
		echo "<textarea id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' rows='{$args['rows']}' cols='{$args['cols']}' type='textarea'>" . HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] . '</textarea>';
	}

	// TEXTBOX - Name: text - Arguments: size:int=40
	public static function text( $args ) {
		if ( ! isset( $args['size'] ) ) {
			$args['size'] = 40;
		}
		$description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>" : '';
		echo "<input id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' size='{$args['size']}' type='text' value='" . esc_attr( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) . "' />{$description}";
	}

	// TEXTBOX CUSTOM - Name: text_plugins - Arguments: size:int=40
	public static function text_plugins( $args ) {
		if ( ! isset( $args['size'] ) ) {
			$args['size'] = 40;
		}
		$description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>" : '';
		echo "<input id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' size='{$args['size']}' type='text' value='" . esc_attr( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) . "' />{$description}";
	}

	// NUMBER TEXTBOX - Name: text - Arguments: size:int=40
	public static function number( $args ) {
		$options = '';
		if ( is_array( $args ) ) {
			foreach ( $args as $key => $value ) {
				if ( in_array( $key, array( 'option_id' ) ) ) {
					continue;
				}
				$options .= " {$key}=\"{$value}\"";
			}
		}
		echo "<input id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' type='number' value='" . HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] . "'{$options}/>";
	}

	// PASSWORD-TEXTBOX - Name: password - Arguments: size:int=40
	public static function password( $args ) {
		if ( ! $args['size'] ) {
			$args['size'] = 40;
		}
		echo "<input id='{$args['option_id']}' name='" . self::$id . "[{$args['option_id']}]' size='{$args['size']}' type='password' value='" . HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] . "' />";
	}

	// RADIO-BUTTON - Name: plugin_options[option_set1]
	public static function radio( $args ) {
		$items = $args['values'];
		if ( self::is_assoc( $items ) ) {
			foreach ( $items as $key => $item ) {
				$checked = checked( $key, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ], false );
				echo '<label><input ' . $checked . " value='$key' name='" . self::$id . "[{$args['option_id']}]' type='radio' /> $item</label><br />";
			}
		} else {
			foreach ( $items as $item ) {
				$checked = checked( $item, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ], false );
				echo '<label><input ' . $checked . " value='$item' name='" . self::$id . "[{$args['option_id']}]' type='radio' /> $item</label><br />";
			}
		}
	}
	// checklist - Name: plugin_options[option_set1]
	public static function checklist( $args ) {
		$items = $args['values'];
		if ( self::is_assoc( $items ) ) {
			foreach ( $items as $key => $item ) {
				if ( is_array( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) ) {
					$checked = checked( in_array( $key, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ), true, false );
				} else {
					$checked = checked( true, false, false );
				}
				echo '<label><input ' . $checked . " value='$key' name='" . self::$id . "[{$args['option_id']}][]' type='checkbox' /> $item</label><br />";
			}
		} else {
			foreach ( $items as $item ) {
				if ( is_array( HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ) ) {
					$checked = checked( in_array( $item, HackRepair_Plugin_Archiver::$options[ $args['option_id'] ] ), true, false );
				} else {
					$checked = checked( true, false, false );
				}
				echo '<label><input ' . $checked . " value='$item' name='" . self::$id . "[{$args['option_id']}][]' type='checkbox' /> $item</label><br />";
			}
		}
	}

	public static function tabs( $current = 'settings' ) {
		$result = '';
		if ( count( self::$tabs ) ) {
			$result = "      <h2 class=\"nav-tab-wrapper\">\r\n";
			foreach ( self::$tabs as $tab_key => $tab ) {
				if ( $tab_key === $current ) {
					$tab['class'] .= ' nav-tab-active';
				}
				$result .= "        <a class=\"nav-tab{$tab['class']}\" href=\"{$tab['href']}\">{$tab['title']}</a>\r\n";
			}
			$result .= "      </h2>\r\n";
		}
		return $result;
	}
	public static function settings() {
		?>
		<form action="options.php" method="post">
		<?php settings_fields( self::$id ); ?>
		<?php do_settings_sections( self::$file ); ?>
		<p class="submit">
		<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
		</p>
		</form>
		<?php
	}
	public static function content( $current ) {
		$callback = array( 'HackRepair_Plugin_Archiver_Options', 'settings' );
		if ( isset( self::$tabs[ $current ]['callback'] ) ) {
			if ( is_callable( self::$tabs[ $current ]['callback'] ) ) {
				$callback = self::$tabs[ $current ]['callback'];
			} else {
				$callback = array( 'HackRepair_Plugin_Archiver_Options', self::$tabs[ $current ]['callback'] );
			}
		}
		return $callback;
	}
	// Display the admin options page
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'hackrepair-plugin-archiver' ) );
		}
		?>
	<div class="wrap">
		<div class="icon32" id="icon-page"><br></div>
		<h2><?php echo esc_html( self::$title ); ?></h2>
		<?php
		echo self::$description;
		$default = array_keys( self::$tabs );
		$default = array_shift( $default );
		$current = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : $default;
		HackRepair_Plugin_Archiver_Security::render_notices( HackRepair_Plugin_Archiver::$options['archive_dir'] );
		echo self::tabs( $current );
		call_user_func( self::content( $current ) );
		if ( 'settings' === $current ) {
			HackRepair_Plugin_Archiver_Security::render_panel();
		}
		?>
	</div>
		<?php
	}

	// Validate user data for some/all of your input fields
	public static function validate( $input ) {
		// Ensure only users with the manage_options capability may modify settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $input;
		}

		if ( isset( $input['archive_dir_add'] ) && $input['archive_dir_add'] ) {
			$label = sanitize_text_field( $input['archive_dir_add'] );
			if ( ! current_user_can( 'install_plugins' ) ) {
				$created = false;
			} else {
				$created = HackRepair_Plugin_Archiver_Security::create_archive( $label );
			}
			if ( ! $created ) {
				add_settings_error( 'hackrepair-plugin-archiver_options', 'archive_create_failed', __( 'The new archive could not be created. File changes may be disabled, or the server needs file credentials.', 'hackrepair-plugin-archiver' ), 'error' );
			}
			$input['archive_dir_add'] = '';
			if ( isset( $input['archive_dir'] ) && ! HackRepair_Plugin_Archiver_Security::is_registered( sanitize_file_name( $input['archive_dir'] ) ) ) {
				$input['archive_dir'] = HackRepair_Plugin_Archiver::$options['archive_dir'];
			}
		}
		return $input; // return validated input
	}
}
