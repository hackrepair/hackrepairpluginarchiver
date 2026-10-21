<?php
/**
 * Plain-PHP tests for the log, record and zip-download helpers (no WordPress needed).
 * Run: php tests/records-download-logic.php
 *
 * @package HackRepair_Plugin_Archiver
 */

define( 'ABSPATH', __DIR__ . '/' );
function __( $t ) {
	return $t;
}
function sanitize_text_field( $t ) {
	return trim( strip_tags( (string) $t ) );
}
function add_action() {}
function add_filter() {}

require __DIR__ . '/../includes/records.php';
require __DIR__ . '/../includes/download.php';

$R    = 'HackRepair_Plugin_Archiver_Records';
$D    = 'HackRepair_Plugin_Archiver_Download';
$fail = 0;
function check( $name, $ok ) {
	global $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . ": $name\n";
	if ( ! $ok ) {
		++$fail;
	}
}

// CSV safety.
foreach ( array( '=1+1', '+cmd', '-2', '@SUM(A1)', "\tx", "\rx" ) as $v ) {
	check( 'csv neutralises ' . json_encode( $v ), "'" === $R::csv_cell( $v )[0] );
}
check( 'csv leaves normal text', 'Akismet' === $R::csv_cell( 'Akismet' ) );
check( 'csv leaves empty', '' === $R::csv_cell( '' ) );

// Log helpers.
$entries = array();
for ( $i = 0; $i < 600; $i++ ) {
	$entries[] = array( 'a' => ( 0 === $i % 2 ) ? 'archive' : 'restore', 't' => $i );
}
check( 'cap keeps newest 500', 500 === count( $R::cap_entries( $entries, 500 ) ) && 0 === $R::cap_entries( $entries, 500 )[0]['t'] );
check( 'filter by action', 300 === count( $R::filter_entries( $entries, 'archive' ) ) );
check( 'empty filter returns all', 600 === count( $R::filter_entries( $entries, '' ) ) );
check( 'manifest key', 'plugins-x|a/b.php' === $R::key( 'plugins-x', 'a/b.php' ) );

// Archive screens and header lookup (3.3.1).
foreach ( array( 'hackrepair-plugin-archiver', 'hackrepair-plugin-archiver-settings' ) as $page ) {
	check( 'own page ' . $page, $R::is_own_page( $page ) );
}
foreach ( array( '', 'plugins', 'other-plugin', array( 'x' ), null ) as $page ) {
	check( 'not own page ' . json_encode( $page ), ! $R::is_own_page( $page ) );
}
$hdr = sys_get_temp_dir() . '/hrpa-hdr-' . bin2hex( random_bytes( 4 ) );
mkdir( $hdr . '/demo', 0777, true );
file_put_contents( $hdr . '/demo/demo.php', "<?php\n/**\n * Plugin Name: wp Hosting Performance Check\n * Version: 2.18.8\n */\n" );
$info = $R::describe( $hdr, 'demo/demo.php' );
check( 'describe reads name', 'wp Hosting Performance Check' === $info['name'] );
check( 'describe reads version', '2.18.8' === $info['version'] );
$info = $R::describe( $hdr, 'demo/missing.php' );
check( 'describe missing file is blank', '' === $info['name'] && '' === $info['version'] );
unlink( $hdr . '/demo/demo.php' );
rmdir( $hdr . '/demo' );
rmdir( $hdr );

// Success notice visibility (3.3.2).
check( 'success shown when some handled', $R::success_visible( 2, 1 ) );
check( 'success shown when nothing skipped', $R::success_visible( 0, 0 ) );
check( 'success hidden when all skipped', ! $R::success_visible( 0, 1 ) );

// Download helpers.
foreach ( array( 'akismet/akismet.php', 'hello.php', 'my-plugin_2/inc/a.b.php' ) as $ok ) {
	check( 'valid plugin file ' . $ok, $D::is_valid_plugin_file( $ok ) );
}
foreach ( array( '../etc/passwd', 'a/../../b.php', '/abs/x.php', '.hidden/x.php', '', 'a b/c.php', "a\0b", array( 'x' ), str_repeat( 'a', 300 ) ) as $bad ) {
	check( 'rejects ' . json_encode( $bad ), ! $D::is_valid_plugin_file( $bad ) );
}
check( 'top entry folder', 'akismet' === $D::top_entry( 'akismet/akismet.php' ) );
check( 'top entry single file', 'hello.php' === $D::top_entry( 'hello.php' ) );
check( 'zip folder single file drops .php', 'hello' === $D::zip_folder( 'hello.php' ) );
check( 'zip folder for folder plugin', 'akismet' === $D::zip_folder( 'akismet/akismet.php' ) );
check( 'cap boundary ok', $D::within_cap( 100, 100 ) );
check( 'cap exceeded', ! $D::within_cap( 101, 100 ) );
check( 'inside root', $D::is_inside( '/a/b/c/x', '/a/b/c' ) );
check( 'root itself is not inside', ! $D::is_inside( '/a/b/c', '/a/b/c' ) );
check( 'sibling prefix is not inside', ! $D::is_inside( '/a/b/cc/x', '/a/b/c' ) );

// Zip build against a real temporary tree.
if ( class_exists( 'ZipArchive' ) ) {
	$base = sys_get_temp_dir() . '/hrpa-test-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base . '/archive/demo/inc', 0777, true );
	file_put_contents( $base . '/archive/demo/demo.php', "<?php\n/* Plugin Name: Demo */\n" );
	file_put_contents( $base . '/archive/demo/inc/a.php', '<?php // a' );
	file_put_contents( $base . '/secret.txt', 'outside' );
	@symlink( $base . '/secret.txt', $base . '/archive/demo/link.txt' ); // phpcs:ignore
	file_put_contents( $base . '/archive/single.php', "<?php\n/* Plugin Name: Single */\n" );
	$root = realpath( $base . '/archive' );

	$tmp = $base . '/out.zip';
	$err = $D::build( realpath( $base . '/archive/demo' ), $root, 'demo', $tmp, 1000000 );
	$zip = new ZipArchive();
	$zip->open( $tmp );
	$names = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$names[] = $zip->getNameIndex( $i );
	}
	$zip->close();
	check( 'build succeeds', '' === $err );
	check( 'zip has one top-level folder', 0 === count( array_filter( $names, function ( $n ) { return 0 !== strpos( $n, 'demo/' ); } ) ) );
	check( 'zip has plugin files', in_array( 'demo/demo.php', $names, true ) && in_array( 'demo/inc/a.php', $names, true ) );
	check( 'symlink is not followed', ! in_array( 'demo/link.txt', $names, true ) );

	$err = $D::build( realpath( $base . '/archive/demo' ), $root, 'demo', $base . '/small.zip', 5 );
	check( 'size cap refuses big plugin', '' !== $err );

	$err = $D::build( realpath( $base . '/archive/single.php' ), $root, 'single', $base . '/single.zip', 1000000 );
	$zip->open( $base . '/single.zip' );
	check( 'single-file plugin zipped in folder', '' === $err && false !== $zip->locateName( 'single/single.php' ) );
	$zip->close();

	// Tidy up the test tree.
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		( $f->isLink() || $f->isFile() ) ? unlink( $f->getPathname() ) : rmdir( $f->getPathname() );
	}
	rmdir( $base );
} else {
	echo "SKIP: ZipArchive not installed\n";
}

exit( $fail ? 1 : 0 );
