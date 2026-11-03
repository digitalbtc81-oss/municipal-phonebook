<?php
/**
 * Department matching. Run with: wp eval-file tests/test-department-slugs.php
 *
 * Expects the municipal-phonebook taxonomy to be registered. Creates nothing.
 *
 * @package MunicipalPhonebook
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run with wp eval-file tests/test-department-slugs.php\n" );
	exit( 1 );
}

/**
 * Fail the test.
 *
 * @param string $message Message.
 * @return void
 */
function munipb_test_fail( $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

$mayor = get_term_by( 'name', 'Δήμαρχος', 'munipb_department' );
$child = get_term_by( 'name', 'Τμήμα Πολιτισμού', 'munipb_department' );
if ( ! $mayor || ! $child ) {
	munipb_test_fail( 'Missing demo terms Δήμαρχος / Τμήμα Πολιτισμού' );
}

$slug  = $mayor->slug;
$child_slug = $child->slug;
$key   = munipb_slug_key( $slug );
if ( $key !== strtolower( $slug ) && false === strpos( $slug, '%' ) ) {
	munipb_test_fail( 'ASCII slug key changed: ' . $key );
}
if ( munipb_slug_key( rawurldecode( $slug ) ) !== munipb_slug_key( $slug ) ) {
	munipb_test_fail( 'Decoded slug key does not match encoded slug key' );
}

$assert = static function ( $label, $raw, $expect_active, $expect_id ) {
	$selection = munipb_parse_department_selection( $raw );
	$got_id    = $selection['ids'] ? (int) $selection['ids'][0] : 0;
	if ( (bool) $selection['active'] !== (bool) $expect_active || $got_id !== (int) $expect_id ) {
		munipb_test_fail( $label . ' got ' . wp_json_encode( $selection ) );
	}
};

$cases = static function () use ( $assert, $slug, $child_slug, $mayor, $child ) {
	$assert( 'empty', '', false, 0 );
	$assert( 'empty array', array(), false, 0 );
	$assert( 'encoded slug', $slug, true, (int) $mayor->term_id );
	$assert( 'elementor array', array( $slug ), true, (int) $mayor->term_id );
	$assert( 'greek name', 'Δήμαρχος', true, (int) $mayor->term_id );
	$assert( 'term id', (string) $mayor->term_id, true, (int) $mayor->term_id );
	$assert( 'child slug', $child_slug, true, (int) $child->term_id );
	$assert( 'child id', (string) $child->term_id, true, (int) $child->term_id );
	$assert( 'unknown', 'not-a-real-department', true, 0 );
	$assert( 'uppercase hex', strtoupper( $slug ), true, (int) $mayor->term_id );
};

$cases();

add_filter(
	'sanitize_title',
	static function ( $title ) {
		$map = array(
			'α' => 'a', 'ά' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'έ' => 'e',
			'ζ' => 'z', 'η' => 'i', 'ή' => 'i', 'θ' => 'th', 'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i',
			'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'ό' => 'o',
			'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'ύ' => 'y',
			'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o', 'ώ' => 'o',
		);
		return strtr( mb_strtolower( (string) $title, 'UTF-8' ), $map );
	},
	9
);

if ( 'dimarchos' !== sanitize_title( rawurldecode( $slug ) ) ) {
	munipb_test_fail( 'transliteration filter did not run: ' . sanitize_title( rawurldecode( $slug ) ) );
}
$cases();

$only = munipb_filter_directory( munipb_get_directory(), array( (int) $child->term_id ) );
if ( 1 !== count( $only ) ) {
	munipb_test_fail( 'child filter section count ' . count( $only ) );
}
$kids = $only[0]['children'];
if ( 1 !== count( $kids ) || (int) $kids[0]['id'] !== (int) $child->term_id ) {
	munipb_test_fail( 'child filter did not keep only the selected child' );
}
if ( ! empty( $only[0]['entries'] ) ) {
	munipb_test_fail( 'parent entries were kept when only the child was selected' );
}

$none = munipb_filter_directory( munipb_get_directory(), array() );
if ( array() !== $none ) {
	munipb_test_fail( 'empty id list should show nothing' );
}

$mayor_tree = munipb_filter_directory( munipb_get_directory(), array( (int) $mayor->term_id ) );
if ( 1 !== count( $mayor_tree ) || 'Δήμαρχος' !== $mayor_tree[0]['name'] ) {
	munipb_test_fail( 'mayor filter did not keep one section' );
}

$html = Munipb_Frontend::render(
	array(
		'search'      => '0',
		'toc'         => '0',
		'departments' => array( $slug ),
		'breakpoint'  => 'never',
	)
);
if ( false === strpos( $html, 'Δήμαρχος' ) || false !== strpos( $html, 'Γνωμοδότηση' ) || false !== strpos( $html, 'Αντιδήμαρχοι' ) ) {
	munipb_test_fail( 'render did not limit the directory to the selected department' );
}

echo "OK\n";
