<?php
/**
 * Extract one changelog section from readme.txt for a GitHub Release.
 *
 * Usage: php .github/scripts/release-notes.php 1.3.0
 *
 * @package MunicipalPhonebook
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$version = isset( $argv[1] ) ? (string) $argv[1] : '';
if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	fwrite( STDERR, "Pass a version like 1.3.0.\n" );
	exit( 1 );
}

$readme_path = dirname( __DIR__, 2 ) . '/readme.txt';
$readme      = file_get_contents( $readme_path );
if ( ! is_string( $readme ) || '' === $readme ) {
	fwrite( STDERR, "Could not read readme.txt.\n" );
	exit( 1 );
}

if ( ! preg_match( '/^== Changelog ==\s*(.*)\z/ms', $readme, $changelog_match ) ) {
	fwrite( STDERR, "readme.txt has no changelog.\n" );
	exit( 1 );
}

$section_pattern = '/^= ' . preg_quote( $version, '/' ) . ' =[ \t]*\r?\n(.*?)(?=\r?\n= \d+\.\d+\.\d+ =|\z)/s';
if ( ! preg_match( $section_pattern, $changelog_match[1], $section_match ) ) {
	fwrite( STDERR, "No changelog entry for {$version}.\n" );
	exit( 1 );
}

$notes = trim( $section_match[1] );
if ( '' === $notes ) {
	fwrite( STDERR, "Changelog entry for {$version} is empty.\n" );
	exit( 1 );
}

echo "Municipal Phonebook {$version}\n\n";
echo $notes;
echo "\n";
