<?php
/**
 * Column schema, inheritance and value checks. No WordPress load.
 *
 * @package MunicipalPhonebook
 */

define( 'ABSPATH', __DIR__ );

/**
 * Stand-in for the WordPress text sanitizer.
 *
 * @param string $text Raw text.
 * @return string
 */
function sanitize_text_field( $text ) {
	$text = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) );
	return $text;
}

/**
 * Stand-in for esc_url_raw(). Only http(s) URLs survive.
 *
 * @param string $url       Raw URL.
 * @param array  $protocols Unused. Present so the signature matches WordPress.
 * @return string
 */
function esc_url_raw( $url, $protocols = array() ) {
	unset( $protocols );
	$url = trim( (string) $url );
	if ( 1 !== preg_match( '#^https?://#i', $url ) || preg_match( '/[\s<>"\']/', $url ) ) {
		return '';
	}
	return $url;
}

/**
 * Stand-in for is_email().
 *
 * @param string $email Email.
 * @return string|false
 */
function is_email( $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

/**
 * Stand-in for absint().
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

require dirname( __DIR__ ) . '/includes/functions.php';

/**
 * Fail the process when an expectation is missed.
 *
 * @param bool   $ok      Result.
 * @param string $message Failure text.
 * @return void
 */
function munipb_test_assert( $ok, $message ) {
	if ( ! $ok ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" );
		exit( 1 );
	}
}

$defaults = munipb_default_columns();
munipb_test_assert( array( 'name', 'role', 'phone', 'email' ) === array_column( $defaults, 'key' ), 'default column order' );
munipb_test_assert( 'adeies' === munipb_ascii_slug( 'Άδειες' ), 'greek slug adeies got ' . munipb_ascii_slug( 'Άδειες' ) );
munipb_test_assert( 'tmima' === munipb_ascii_slug( 'Τμήμα' ), 'greek slug tmima got ' . munipb_ascii_slug( 'Τμήμα' ) );
munipb_test_assert( '' === munipb_sanitize_column_key( '1bad' ), 'slug must start with a letter' );
munipb_test_assert( 'adeies' === munipb_sanitize_column_key( 'Adeies' ), 'slug is lowercase' );

$saved = munipb_sanitize_columns(
	array(
		array(
			'key'       => 'phone',
			'label'     => '',
			'type'      => 'phone',
			'visible'   => '1',
			'link_text' => 'ignored',
		),
		array(
			'key'       => 'name',
			'label'     => '<b>Ονοματεπώνυμο</b>',
			'type'      => 'text',
			'visible'   => 1,
			'link_text' => '',
		),
		array(
			'key'       => '',
			'label'     => 'Άδειες',
			'type'      => 'file',
			'visible'   => 1,
			'link_text' => 'Λήψη',
		),
		array(
			'key'       => 'adeies',
			'label'     => 'Second',
			'type'      => 'file',
			'visible'   => 0,
			'link_text' => '',
		),
		array(
			'key'     => 'site',
			'label'   => 'Site',
			'type'    => 'url',
			'visible' => 1,
		),
	)
);
$keys = array_column( $saved, 'key' );
munipb_test_assert( 'phone' === $keys[0] && 'name' === $keys[1], 'submitted order is kept' );
munipb_test_assert( in_array( 'role', $keys, true ) && in_array( 'email', $keys, true ), 'missing built-in columns are restored' );
munipb_test_assert( 'name' === $saved[1]['type'], 'built-in type cannot be changed' );
munipb_test_assert( 'Ονοματεπώνυμο' === $saved[1]['label'], 'label tags are removed' );
munipb_test_assert( 'adeies' === $keys[2], 'greek label becomes adeies' );
munipb_test_assert( 'Λήψη' === $saved[2]['link_text'], 'file link text is kept' );
munipb_test_assert( '' === $saved[0]['link_text'], 'link text is only for files' );
munipb_test_assert( 'adeies_2' === $keys[3], 'duplicate slug gets a suffix' );
munipb_test_assert( 0 === $saved[3]['visible'], 'visibility is stored' );
munipb_test_assert( array() !== munipb_sanitize_columns( array() ), 'empty input falls back to the defaults' );

$global = array( 'name', 'phone', 'role', 'email' );
$child  = munipb_resolve_column_keys( $global, array( null, null ) );
munipb_test_assert( $global === $child, 'inherit from top to bottom uses the global columns' );
$parent = array( 'name', 'phone', 'role' );
$child  = munipb_resolve_column_keys( $global, array( $parent, null ) );
munipb_test_assert( $parent === $child, 'a child inherits its parent columns' );
$own = array( 'name', 'phone', 'adeies' );
$child = munipb_resolve_column_keys( $global, array( $parent, $own ) );
munipb_test_assert( $own === $child, 'a child override replaces the parent columns' );

$union = munipb_union_column_keys(
	array(
		array( 'name', 'phone', 'role' ),
		array( 'name', 'phone', 'adeies' ),
		array( 'phone', 'site' ),
	)
);
munipb_test_assert( array( 'name', 'phone', 'role', 'adeies', 'site' ) === $union, 'union keeps the parent order and appends new keys' );

$text = munipb_custom_search_text(
	array(
		'note'   => 'πρωτόκολλο',
		'adeies' => '12',
		'site'   => 'https://example.com',
	),
	array(
		array( 'key' => 'note', 'type' => 'text' ),
		array( 'key' => 'adeies', 'type' => 'file' ),
		array( 'key' => 'site', 'type' => 'url' ),
	)
);
munipb_test_assert( 'πρωτόκολλο' === $text, 'search text includes text columns only' );

munipb_test_assert( 'person@example.com' === munipb_sanitize_column_value( 'email', 'person@example.com' ), 'email kept' );
munipb_test_assert( '' === munipb_sanitize_column_value( 'email', 'not-an-email' ), 'bad email dropped' );
munipb_test_assert( 'https://example.com/a' === munipb_sanitize_column_value( 'url', 'https://example.com/a' ), 'https url kept' );
munipb_test_assert( '' === munipb_sanitize_column_value( 'url', 'javascript:alert(1)' ), 'javascript url dropped' );
munipb_test_assert( 15 === munipb_sanitize_column_value( 'file', '15' ), 'file value is an id' );
munipb_test_assert( 0 === munipb_sanitize_column_value( 'file', 'nope' ), 'file value must be numeric' );
munipb_test_assert( 'hello' === munipb_sanitize_column_value( 'text', " <b>hello</b> " ), 'text is plain' );

fwrite( STDOUT, "OK\n" );
