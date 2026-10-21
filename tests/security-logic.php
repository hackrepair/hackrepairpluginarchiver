<?php
/**
 * Plain-PHP tests for the archive naming and protection logic (no WordPress needed).
 * Run: php tests/security-logic.php
 *
 * @package HackRepair_Plugin_Archiver
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['hrpa_options'] = array();

function sanitize_title( $t ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $t ) ), '-' );
}
function wp_generate_password( $len = 12 ) {
	return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ), 0, $len );
}
function get_option( $k, $d = false ) {
	return $GLOBALS['hrpa_options'][ $k ] ?? $d;
}
function update_option( $k, $v ) {
	$GLOBALS['hrpa_options'][ $k ] = $v;
	return true;
}
function add_filter() {}
function add_action() {}
function sanitize_file_name( $n ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', $n );
}
function __( $t ) {
	return $t;
}

require __DIR__ . '/../includes/security.php';
$S    = 'HackRepair_Plugin_Archiver_Security';
$fail = 0;
function check( $name, $ok ) {
	global $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . ": $name\n";
	if ( ! $ok ) {
		++$fail;
	}
}

$names = array();
for ( $i = 0; $i < 200; $i++ ) {
	$names[] = $S::new_dir_name( '' );
}
check( 'default names look right', 200 === count( array_filter( $names, array( $S, 'is_random_name' ) ) ) );
check( 'default names are valid', 200 === count( array_filter( $names, array( $S, 'is_valid_dir_name' ) ) ) );
check( 'names are unique', 200 === count( array_unique( $names ) ) );
check( 'default name is 12 random chars', 1 === preg_match( '/^plugins-archive-[a-z0-9]{12}$/', $names[0] ) );
$labelled = $S::new_dir_name( 'My Old Stuff!' );
check( 'labelled name has slug and 8 random chars', 1 === preg_match( '/^plugins-archive-my-old-stuff-[a-z0-9]{8}$/', $labelled ) );
check( 'labelled name is random form', $S::is_random_name( $labelled ) );
check( 'long label is shortened', strlen( $S::new_dir_name( str_repeat( 'a', 200 ) ) ) <= 100 );
check( 'label with path chars is neutralised', $S::is_valid_dir_name( $S::new_dir_name( '../../etc' ) ) );

foreach ( array( '../x', 'plugins-../x', 'plugins-a/b', 'plugins-', 'notplugins-x', "plugins-a\0b", '', 'plugins-a b' ) as $bad ) {
	check( 'rejects ' . json_encode( $bad ), ! $S::is_valid_dir_name( $bad ) );
}
check( 'rejects non-string', ! $S::is_valid_dir_name( array( 'plugins-x' ) ) );
check( 'old predictable name is not random', ! $S::is_random_name( 'plugins-archive-a1b2c3' ) );

$files = $S::protection_files();
check( 'htaccess denies 2.4 and 2.2', false !== strpos( $files['.htaccess'], 'Require all denied' ) && false !== strpos( $files['.htaccess'], 'Deny from all' ) );
check( 'web.config denies', false !== strpos( $files['web.config'], '<deny users="*" />' ) );
check( 'index.php is silent', false !== strpos( $files['index.php'], 'Silence' ) );
check( 'marker file present', isset( $files[ $S::MARKER_FILE ] ) && false !== strpos( $files[ $S::MARKER_FILE ], 'hackrepair-archive' ) );

check( '200 + marker = exposed', 'exposed' === $S::evaluate_response( 200, 'hackrepair-archive: x' ) );
check( '200 without marker = protected', 'protected' === $S::evaluate_response( 200, '<html>home page</html>' ) );
check( '403 = protected', 'protected' === $S::evaluate_response( 403, 'Forbidden' ) );
check( '404 = protected', 'protected' === $S::evaluate_response( 404, '' ) );

check( 'derived label', 'Archive a1b2c3' === $S::derived_label( 'plugins-archive-a1b2c3' ) );

check( 'registry ignores junk', array() === $S::registry() );
update_option( $S::REGISTRY_OPTION, array( '../bad' => array( 'label' => 'x' ), 'plugins-ok' => array( 'label' => 'Ok', 'created' => 5, 'legacy' => 1 ), 'plugins-str' => 'x' ) );
$reg = $S::registry();
check( 'registry keeps only valid entries', array( 'plugins-ok' ) === array_keys( $reg ) && true === $reg['plugins-ok']['legacy'] );
check( 'active_name falls back to first registered', 'plugins-ok' === $S::active_name( 'plugins-missing' ) );
check( 'active_name keeps registered', 'plugins-ok' === $S::active_name( 'plugins-ok' ) );
check( 'register keeps created time', $S::register( 'plugins-ok', 'Renamed', false ) && 5 === $S::registry()['plugins-ok']['created'] );
check( 'register refuses bad names', ! $S::register( 'etc', 'x' ) );

exit( $fail ? 1 : 0 );
