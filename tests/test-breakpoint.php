<?php
/**
 * Card breakpoint sanitizing. No WordPress load.
 *
 * @package MunicipalPhonebook
 */

define( 'ABSPATH', __DIR__ );

/**
 * Stand-in for absint().
 *
 * @param mixed $value Raw value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

require dirname( __DIR__ ) . '/includes/class-munipb-frontend.php';

$checks = array(
	array( '720', '720' ),
	array( 'never', 'never' ),
	array( 'always', 'always' ),
	array( '480', '480' ),
	array( '960', '960' ),
	array( '1100', '1100' ),
	array( '500', '500' ),
	array( '0', 'never' ),
	array( '2400', '2400' ),
	array( '2401', '2400' ),
	array( '99999', '2400' ),
	array( 'foo', '720' ),
	array( '720px', '720' ),
	array( '', '720' ),
	array( ' custom ', '720' ),
	array( ' 500 ', '500' ),
);

foreach ( $checks as $check ) {
	$got = Munipb_Frontend::sanitize_breakpoint( $check[0] );
	if ( $got !== $check[1] ) {
		fwrite( STDERR, "sanitize_breakpoint(" . var_export( $check[0], true ) . ") = {$got}, expected {$check[1]}\n" );
		exit( 1 );
	}
}

echo "OK\n";
