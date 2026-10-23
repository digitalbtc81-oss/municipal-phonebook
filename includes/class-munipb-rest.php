<?php
/**
 * Optional public, read-only REST endpoint (disabled by default).
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class Munipb_REST {

	const NAMESPACE_V1 = 'municipal-phonebook/v1';
	const RATE_LIMIT   = 60; // Requests per IP per minute.

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_entries' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'search'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $value ) {
							return sanitize_text_field( (string) $value );
						},
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && mb_strlen( $value ) <= 100;
						},
					),
					'department' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $value ) {
							// Do not pass sanitize_title() directly: REST passes the request as 2nd arg (fallback).
							return sanitize_title( (string) $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Permission: endpoint must be enabled in settings, plus a simple per-IP rate limit.
	 * Logged-in editors bypass the rate limit.
	 *
	 * @return true|WP_Error
	 */
	public static function permission() {
		$settings = munipb_get_settings();
		if ( empty( $settings['enable_rest'] ) && ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'munipb_rest_disabled', __( 'This endpoint is disabled.', 'municipal-phonebook' ), array( 'status' => 404 ) );
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		static $allowed = null;
		if ( null === $allowed ) {
			$allowed = munipb_rate_limit( 'entries', self::RATE_LIMIT );
		}
		if ( ! $allowed ) {
			return new WP_Error( 'munipb_rate_limited', __( 'Too many requests. Please try again later.', 'municipal-phonebook' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * Return public fields only (name, role, phones, email, department). Internal notes are never exposed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_entries( $request ) {
		$dept  = (string) $request->get_param( 'department' );
		$words = array_filter( preg_split( '/\s+/u', munipb_normalize_text( (string) $request->get_param( 'search' ) ) ) );
		$tree  = munipb_get_directory( $dept ? array( $dept ) : array() );
		$out   = array();

		$push = static function ( $entry, $department, $sub, $columns ) use ( &$out, $words ) {
			$columns = is_array( $columns ) ? $columns : array();
			$custom  = ( isset( $entry['custom'] ) && is_array( $entry['custom'] ) ) ? $entry['custom'] : array();
			if ( $words ) {
				$phones = array();
				foreach ( $entry['phones'] as $g ) {
					$phones = array_merge( $phones, $g );
				}
				$hay = munipb_normalize_text( implode( ' ', array( $entry['name'], $entry['role'], $entry['email'], $department, $sub, implode( ' ', $phones ), munipb_custom_search_text( $custom, $columns ) ) ) );
				foreach ( $words as $w ) {
					if ( false === strpos( $hay, $w ) ) {
						return;
					}
				}
			}
			$row    = array(
				'name'          => $entry['name'],
				'role'          => $entry['role'],
				'phones'        => $entry['phones'],
				'email'         => $entry['email'],
				'department'    => $department,
				'subdepartment' => $sub,
			);
			$public = munipb_public_column_values( $entry, $columns );
			if ( $public ) {
				$row['columns'] = $public;
			}
			$out[] = $row;
		};

		foreach ( $tree as $section ) {
			$section_columns = isset( $section['columns'] ) ? $section['columns'] : array();
			foreach ( $section['entries'] as $e ) {
				$push( $e, $section['name'], '', $section_columns );
			}
			foreach ( $section['children'] as $child ) {
				$child_columns = isset( $child['columns'] ) ? $child['columns'] : $section_columns;
				foreach ( $child['entries'] as $e ) {
					$push( $e, $section['name'], $child['name'], $child_columns );
				}
			}
		}

		$response = rest_ensure_response( array_slice( $out, 0, 1000 ) );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}
}
