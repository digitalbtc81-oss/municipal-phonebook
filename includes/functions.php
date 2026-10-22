<?php
/**
 * Helper functions.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin settings.
 *
 * @return array<string, mixed>
 */
function munipb_default_settings() {
	return array(
		'enable_rest'      => 0,
		'track_calls'      => 0,
		'delete_on_remove' => 0,
	);
}

/**
 * Get merged plugin settings.
 *
 * @return array<string, mixed>
 */
function munipb_get_settings() {
	$saved = get_option( 'munipb_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, munipb_default_settings() );
}

/**
 * Sanitize a single phone number.
 *
 * Keeps digits, a single leading plus, spaces, dashes, dots, slashes and parentheses.
 * Anything else (letters, markup, quotes) is stripped. Limited to 32 chars.
 *
 * @param string $phone Raw phone.
 * @return string
 */
function munipb_sanitize_phone( $phone ) {
	$phone = trim( wp_strip_all_tags( (string) $phone ) );
	$plus  = ( 0 === strpos( $phone, '+' ) ) ? '+' : '';
	$phone = preg_replace( '/[^0-9 \-\.\/\(\)]/', '', $phone );
	$phone = trim( preg_replace( '/\s+/', ' ', (string) $phone ) );

	if ( '' === $phone || ! preg_match( '/[0-9]/', $phone ) ) {
		return '';
	}
	return substr( $plus . $phone, 0, 32 );
}

/**
 * Parse a phones textarea into groups.
 *
 * One line per group; numbers inside a line separated by "|" or ",".
 * Example: "2132018733 | 2132018712\n2132018726".
 *
 * @param string|array $raw Raw input or already parsed array.
 * @return array<int, array<int, string>>
 */
function munipb_parse_phone_groups( $raw ) {
	if ( is_array( $raw ) ) {
		$raw = implode(
			"\n",
			array_map(
				static function ( $group ) {
					return is_array( $group ) ? implode( '|', $group ) : (string) $group;
				},
				$raw
			)
		);
	}

	$groups = array();
	$lines  = preg_split( '/\r\n|\r|\n|;/', (string) $raw );

	foreach ( (array) $lines as $line ) {
		$numbers = array();
		foreach ( (array) preg_split( '/[|,]/', $line ) as $number ) {
			$clean = munipb_sanitize_phone( $number );
			if ( '' !== $clean ) {
				$numbers[] = $clean;
			}
		}
		if ( $numbers ) {
			$groups[] = array_slice( $numbers, 0, 10 );
		}
	}

	return array_slice( $groups, 0, 10 );
}

/**
 * Convert phone groups back to a textarea string.
 *
 * @param mixed $groups Stored groups.
 * @return string
 */
function munipb_phone_groups_to_string( $groups ) {
	if ( ! is_array( $groups ) ) {
		return '';
	}
	$lines = array();
	foreach ( $groups as $group ) {
		if ( is_array( $group ) ) {
			$lines[] = implode( ' | ', $group );
		}
	}
	return implode( "\n", $lines );
}

/**
 * Build a tel: URI value from a sanitized phone.
 *
 * @param string $phone Phone.
 * @return string
 */
function munipb_phone_to_tel( $phone ) {
	$plus = ( 0 === strpos( $phone, '+' ) ) ? '+' : '';
	return 'tel:' . $plus . preg_replace( '/\D/', '', $phone );
}

/**
 * Normalize text for accent-insensitive Greek/Latin search.
 *
 * Lowercases, removes combining diacritics (τόνοι, διαλυτικά) and maps final sigma.
 *
 * @param string $text Text.
 * @return string
 */
function munipb_normalize_text( $text ) {
	$text = mb_strtolower( (string) $text, 'UTF-8' );

	if ( class_exists( 'Normalizer' ) ) {
		$text = Normalizer::normalize( $text, Normalizer::FORM_D );
		$text = preg_replace( '/\p{Mn}+/u', '', (string) $text );
	} else {
		$text = strtr(
			$text,
			array(
				'ά' => 'α',
				'έ' => 'ε',
				'ή' => 'η',
				'ί' => 'ι',
				'ϊ' => 'ι',
				'ΐ' => 'ι',
				'ό' => 'ο',
				'ύ' => 'υ',
				'ϋ' => 'υ',
				'ΰ' => 'υ',
				'ώ' => 'ω',
			)
		);
	}

	return str_replace( 'ς', 'σ', (string) $text );
}

/**
 * Delete cached directory HTML/data.
 *
 * @return void
 */
function munipb_flush_cache() {
	$version = (int) get_option( 'munipb_cache_version', 1 );
	update_option( 'munipb_cache_version', $version + 1, false );
}

/**
 * Get the current cache version (used as part of transient keys).
 *
 * @return int
 */
function munipb_cache_version() {
	return (int) get_option( 'munipb_cache_version', 1 );
}

/**
 * Public, display-safe representation of a single entry. Private fields are never included.
 *
 * @param WP_Post $post Entry post.
 * @return array<string, mixed>
 */
function munipb_get_entry_public_data( $post ) {
	return array(
		'id'     => (int) $post->ID,
		'name'   => get_the_title( $post ),
		'role'   => (string) get_post_meta( $post->ID, '_munipb_role', true ),
		'phones' => munipb_parse_phone_groups( get_post_meta( $post->ID, '_munipb_phones', true ) ),
		'email'  => sanitize_email( (string) get_post_meta( $post->ID, '_munipb_email', true ) ),
	);
}

/**
 * Get ordered department terms.
 *
 * @param int $parent_id Parent term id.
 * @return WP_Term[]
 */
function munipb_get_departments( $parent_id = 0 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => Munipb_Post_Types::TAXONOMY,
			'hide_empty' => false,
			'parent'     => (int) $parent_id,
			'meta_key'   => 'munipb_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small, admin-managed taxonomy; result is cached.
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	// Terms without munipb_order meta are excluded by the meta query above; add them at the end.
	$all = get_terms(
		array(
			'taxonomy'   => Munipb_Post_Types::TAXONOMY,
			'hide_empty' => false,
			'parent'     => (int) $parent_id,
			'orderby'    => 'name',
		)
	);
	if ( ! is_wp_error( $all ) ) {
		$ids = wp_list_pluck( $terms, 'term_id' );
		foreach ( $all as $term ) {
			if ( ! in_array( $term->term_id, $ids, true ) ) {
				$terms[] = $term;
			}
		}
	}

	return $terms;
}

/**
 * Get entries attached directly to a department (not its children), ordered.
 *
 * @param int $term_id Term id.
 * @return WP_Post[]
 */
function munipb_get_entries_for_term( $term_id ) {
	return get_posts(
		array(
			'post_type'        => Munipb_Post_Types::POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded; whole department must be listed, result is cached.
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- required for grouping; output is cached.
				array(
					'taxonomy'         => Munipb_Post_Types::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => (int) $term_id,
					'include_children' => false,
				),
			),
		)
	);
}

/**
 * Build the full, public directory tree (cached), optionally filtered to some top-level departments.
 *
 * Only ONE transient holds the whole tree; filtering happens in memory, so arbitrary
 * filter combinations sent by visitors can never create extra cache rows.
 *
 * @param string[] $only_slugs Optional list of top-level department slugs to include.
 * @return array<int, array<string, mixed>>
 */
function munipb_get_directory( $only_slugs = array() ) {
	static $runtime = array();

	$key = 'munipb_dir_' . munipb_cache_version() . '_' . md5( get_locale() );

	if ( ! isset( $runtime[ $key ] ) ) {
		$tree = get_transient( $key );
		if ( ! is_array( $tree ) ) {
			$tree = array();
			foreach ( munipb_get_departments( 0 ) as $term ) {
				$section = array(
					'id'       => (int) $term->term_id,
					'slug'     => $term->slug,
					'name'     => $term->name,
					'entries'  => array_map( 'munipb_get_entry_public_data', munipb_get_entries_for_term( $term->term_id ) ),
					'children' => array(),
				);
				munipb_collect_children( $term->term_id, $section['children'], 1 );
				$tree[] = $section;
			}
			set_transient( $key, $tree, DAY_IN_SECONDS );
		}
		$runtime = array( $key => $tree );
	}

	$tree = $runtime[ $key ];
	if ( ! $only_slugs ) {
		return $tree;
	}

	return array_values(
		array_filter(
			$tree,
			static function ( $section ) use ( $only_slugs ) {
				return in_array( $section['slug'], $only_slugs, true );
			}
		)
	);
}

/**
 * Parse and validate a comma separated list of department slugs.
 *
 * Unknown slugs are dropped, so the result only ever contains real top-level slugs.
 *
 * @param string|array $raw Raw list.
 * @return string[]
 */
function munipb_parse_department_slugs( $raw ) {
	if ( is_array( $raw ) ) {
		$raw = implode( ',', array_map( 'strval', $raw ) );
	}
	$raw = substr( (string) $raw, 0, 2000 );
	if ( '' === trim( $raw ) ) {
		return array();
	}

	$known = wp_list_pluck( munipb_get_directory(), 'slug' );
	$out   = array();
	foreach ( array_slice( explode( ',', $raw ), 0, 100 ) as $slug ) {
		$slug = sanitize_title( rawurldecode( trim( $slug ) ) );
		if ( '' !== $slug && in_array( $slug, $known, true ) && ! in_array( $slug, $out, true ) ) {
			$out[] = $slug;
		}
	}
	return $out;
}

/**
 * Accent-insensitive match of normalized words against a text (with phone digit matching).
 *
 * @param string[] $words    Normalized words.
 * @param string   $haystack Raw text.
 * @param string   $digits   Digits-only phone string.
 * @return bool
 */
function munipb_words_match( $words, $haystack, $digits = '' ) {
	$haystack = munipb_normalize_text( $haystack );
	foreach ( $words as $word ) {
		if ( false !== strpos( $haystack, $word ) ) {
			continue;
		}
		$word_digits = preg_replace( '/\D/', '', $word );
		if ( strlen( $word_digits ) >= 3 && '' !== $digits && false !== strpos( $digits, $word_digits ) ) {
			continue;
		}
		return false;
	}
	return true;
}

/**
 * Flatten phone groups to a display string.
 *
 * @param array $groups Groups.
 * @return string
 */
function munipb_phones_text( $groups ) {
	$lines = array();
	foreach ( (array) $groups as $group ) {
		$lines[] = implode( ' | ', (array) $group );
	}
	return implode( ' ', $lines );
}

/**
 * Lightweight fixed-window rate limiter.
 *
 * Uses the persistent object cache when available (no DB writes at all); otherwise one
 * short-lived transient per client/bucket, and nothing is written once the limit is reached.
 *
 * @param string $bucket Bucket name.
 * @param int    $limit  Requests per minute.
 * @return bool True when the request is allowed.
 */
function munipb_rate_limit( $bucket, $limit ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip  = (string) apply_filters( 'munipb_client_ip', $ip );
	$key = 'munipb_rl_' . substr( md5( $bucket . '|' . $ip . '|' . wp_salt( 'nonce' ) ), 0, 20 ) . '_' . (int) floor( time() / MINUTE_IN_SECONDS );

	if ( wp_using_ext_object_cache() ) {
		wp_cache_add( $key, 0, 'munipb', MINUTE_IN_SECONDS * 2 );
		$count = (int) wp_cache_incr( $key, 1, 'munipb' );
		return $count <= $limit;
	}

	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS * 2 );
	return true;
}

/**
 * Recursively collect child departments (flattened, with depth) for a section.
 *
 * @param int   $parent_id Parent term id.
 * @param array $out       Output list (by reference).
 * @param int   $depth     Current depth.
 * @return void
 */
function munipb_collect_children( $parent_id, &$out, $depth ) {
	if ( $depth > 5 ) {
		return;
	}
	foreach ( munipb_get_departments( $parent_id ) as $child ) {
		$out[] = array(
			'id'      => (int) $child->term_id,
			'slug'    => $child->slug,
			'name'    => $child->name,
			'depth'   => $depth,
			'phones'  => munipb_parse_phone_groups( get_term_meta( $child->term_id, 'munipb_phones', true ) ),
			'email'   => sanitize_email( (string) get_term_meta( $child->term_id, 'munipb_email', true ) ),
			'entries' => array_map( 'munipb_get_entry_public_data', munipb_get_entries_for_term( $child->term_id ) ),
		);
		munipb_collect_children( $child->term_id, $out, $depth + 1 );
	}
}
