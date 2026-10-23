<?php
/**
 * Custom CSS validator checks. No WordPress load.
 *
 * @package MunicipalPhonebook
 */

define( 'ABSPATH', __DIR__ );

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

/**
 * Assert one inspection result.
 *
 * @param mixed  $css      Raw CSS.
 * @param bool   $ok       Expected validity.
 * @param string $code     Expected error code.
 * @param int    $line     Expected line.
 * @param string $message  Label.
 * @return void
 */
function munipb_test_css( $css, $ok, $code, $line, $message ) {
	$result = munipb_inspect_custom_css( $css );
	munipb_test_assert( $ok === $result['ok'], $message . ' ok' );
	munipb_test_assert( $code === $result['code'], $message . ' code ' . $result['code'] );
	munipb_test_assert( $line === $result['line'], $message . ' line ' . $result['line'] );
	if ( $ok ) {
		munipb_test_assert( (string) $css === $result['css'] || ( '' === trim( (string) $css ) && '' === $result['css'] ), $message . ' stored css' );
	}
}

munipb_test_css( '', true, '', 0, 'empty' );
munipb_test_css( "  \n", true, '', 0, 'blank' );
munipb_test_css( '.a{color:red}', true, '', 0, 'simple' );
munipb_test_css( '.a > .b { color: red; }', true, '', 0, 'child combinator' );
munipb_test_css( ".a{\n  content: \"{}\";\n}", true, '', 0, 'braces in string' );
munipb_test_css( ".a{ /* } */ color: red; }", true, '', 0, 'brace in comment' );
munipb_test_css( "@media (min-width: 10px) {\n  .a { color: red; }\n}", true, '', 0, 'nested' );
munipb_test_css( '::selection{color:red}', true, '', 0, 'selection' );
munipb_test_css( ".a{content:\"foo\\\nbar\"}", true, '', 0, 'escaped newline' );

munipb_test_css( ".a{\ncolor:red", false, 'unbalanced', 1, 'missing close' );
munipb_test_css( ".a{color:red}\n}", false, 'unbalanced', 2, 'extra close' );
munipb_test_css( "/* comment\n.a{color:red}", false, 'unclosed_comment', 1, 'open comment' );
munipb_test_css( ".a{content:\"foo}", false, 'unclosed_string', 1, 'open string' );
munipb_test_css( ".a{content:\"foo\nbar\"}", false, 'unclosed_string', 1, 'raw newline in string' );
munipb_test_css( '.a{color:red} </style>', false, 'markup', 0, 'style close' );
munipb_test_css( ".a{color:red} <\n/style>", false, 'markup', 0, 'split style close' );
munipb_test_css( '.a{content:"<"}', false, 'markup', 0, 'angle bracket' );
munipb_test_css( "a\0b", false, 'invalid', 0, 'null' );
munipb_test_css( str_repeat( 'a', 51200 ), true, '', 0, 'max length' );
munipb_test_css( str_repeat( 'a', 51201 ), false, 'too_long', 0, 'over length' );
munipb_test_assert( false === munipb_inspect_custom_css( array( 'x' ) )['ok'], 'non string' );

$scope = '.elementor-element-abc .munipb-directory';
munipb_test_assert(
	'.elementor-element-abc .munipb-directory{color:red}' === munipb_scope_custom_css( 'selector{color:red}', $scope ),
	'scope keyword'
);
munipb_test_assert(
	'::selection{color:red} .elementor-element-abc .munipb-directory{color:blue}' === munipb_scope_custom_css( '::selection{color:red} selector{color:blue}', $scope ),
	'selection stays'
);
munipb_test_assert(
	$scope . '{.a{color:red}}' === munipb_scope_custom_css( '.a{color:red}', $scope ),
	'wrap when selector is absent'
);
munipb_test_assert( '' === munipb_scope_custom_css( '.a{color:red} <script>', $scope ), 'invalid widget css dropped' );
munipb_test_assert( '' === munipb_scope_custom_css( 'selector{color:red}', '.evil .munipb-directory' ), 'unsafe scope dropped' );
munipb_test_assert( '' === munipb_prepare_custom_css( '</style>' ), 'prepare rejects markup' );
munipb_test_assert( '.a > .b{color:red}' === munipb_prepare_custom_css( '.a > .b{color:red}' ), 'prepare keeps combinator' );

fwrite( STDOUT, "OK\n" );
