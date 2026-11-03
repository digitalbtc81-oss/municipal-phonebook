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
		'row_odd'          => '#ffffff',
		'row_even'         => '#f3f6fc',
		'sub_header'       => '#d3dceb',
		'inline_children'  => 1,
		'columns'          => array(),
		'custom_css'       => '',
	);
}

/**
 * Maximum stored custom CSS, in bytes.
 *
 * @return int
 */
function munipb_custom_css_max_bytes() {
	return 51200;
}

/**
 * Build an inspection result.
 *
 * @param bool   $ok   Whether the CSS may be stored and printed.
 * @param string $css  CSS to keep when valid.
 * @param string $code Error code, or ''.
 * @param int    $line Line where the problem starts, or 0.
 * @return array{ok:bool,css:string,code:string,line:int}
 */
function munipb_css_inspection( $ok, $css, $code, $line ) {
	return array(
		'ok'   => $ok,
		'css'  => $css,
		'code' => $code,
		'line' => $line,
	);
}

/**
 * Move past a CSS escape and count a newline that the escape consumes.
 *
 * @param string $css    CSS.
 * @param int    $length Length.
 * @param int    $i      Index of the backslash. Updated.
 * @param int    $line   Current line. Updated by the caller via return.
 * @return int Line number.
 */
function munipb_css_skip_escape( $css, $length, &$i, $line ) {
	$next = ( $i + 1 < $length ) ? $css[ $i + 1 ] : '';
	if ( "\r" === $next ) {
		$third = ( $i + 2 < $length ) ? $css[ $i + 2 ] : '';
		$i    += ( "\n" === $third ) ? 3 : 2;
		return $line + 1;
	}
	if ( "\n" === $next ) {
		$i += 2;
		return $line + 1;
	}
	$i += ( '' === $next ) ? 1 : 2;
	return $line;
}

/**
 * Count one source line and advance the scanner.
 *
 * @param string $ch   Current byte.
 * @param string $next Following byte.
 * @param int    $line Current line.
 * @param int    $i    Index. Updated.
 * @return int Line number.
 */
function munipb_css_advance_line( $ch, $next, $line, &$i ) {
	if ( "\r" === $ch ) {
		$i += ( "\n" === $next ) ? 2 : 1;
		return $line + 1;
	}
	if ( "\n" === $ch ) {
		++$i;
		return $line + 1;
	}
	++$i;
	return $line;
}

/**
 * Validate custom CSS.
 *
 * Empty CSS is valid. Markup, null bytes, more than 50 KB, unclosed
 * comments or strings, and unbalanced braces are rejected. The child
 * combinator ">" is allowed. Nothing is rewritten: invalid CSS is refused
 * instead of being passed through strip_tags().
 *
 * @param mixed $css Raw CSS.
 * @return array{ok:bool,css:string,code:string,line:int}
 */
function munipb_inspect_custom_css( $css ) {
	if ( ! is_string( $css ) ) {
		return munipb_css_inspection( false, '', 'invalid', 0 );
	}
	if ( '' === trim( $css ) ) {
		return munipb_css_inspection( true, '', '', 0 );
	}
	if ( strlen( $css ) > munipb_custom_css_max_bytes() ) {
		return munipb_css_inspection( false, '', 'too_long', 0 );
	}
	if ( false !== strpos( $css, "\0" ) ) {
		return munipb_css_inspection( false, '', 'invalid', 0 );
	}
	$stripped = strip_tags( $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- reject CSS that strip_tags() would alter; do not rewrite it.
	if ( false !== strpos( $css, '<' ) || preg_match( '/<\s*\/\s*style/i', $css ) || $stripped !== $css ) {
		return munipb_css_inspection( false, '', 'markup', 0 );
	}

	$length       = strlen( $css );
	$line         = 1;
	$i            = 0;
	$mode         = 'normal';
	$quote        = '';
	$string_line  = 1;
	$comment_line = 1;
	$opens        = array();

	while ( $i < $length ) {
		$ch   = $css[ $i ];
		$next = ( $i + 1 < $length ) ? $css[ $i + 1 ] : '';

		if ( 'comment' === $mode ) {
			if ( '*' === $ch && '/' === $next ) {
				$mode = 'normal';
				$i   += 2;
				continue;
			}
			$line = munipb_css_advance_line( $ch, $next, $line, $i );
			continue;
		}

		if ( 'string' === $mode ) {
			if ( '\\' === $ch ) {
				$line = munipb_css_skip_escape( $css, $length, $i, $line );
				continue;
			}
			if ( $ch === $quote ) {
				$mode = 'normal';
				++$i;
				continue;
			}
			if ( "\n" === $ch || "\r" === $ch ) {
				return munipb_css_inspection( false, '', 'unclosed_string', $string_line );
			}
			++$i;
			continue;
		}

		if ( '\\' === $ch ) {
			$line = munipb_css_skip_escape( $css, $length, $i, $line );
			continue;
		}
		if ( '/' === $ch && '*' === $next ) {
			$mode         = 'comment';
			$comment_line = $line;
			$i           += 2;
			continue;
		}
		if ( '"' === $ch || "'" === $ch ) {
			$mode        = 'string';
			$quote       = $ch;
			$string_line = $line;
			++$i;
			continue;
		}
		if ( '{' === $ch ) {
			$opens[] = $line;
			++$i;
			continue;
		}
		if ( '}' === $ch ) {
			if ( array() === $opens ) {
				return munipb_css_inspection( false, '', 'unbalanced', $line );
			}
			array_pop( $opens );
			++$i;
			continue;
		}
		$line = munipb_css_advance_line( $ch, $next, $line, $i );
	}

	if ( 'comment' === $mode ) {
		return munipb_css_inspection( false, '', 'unclosed_comment', $comment_line );
	}
	if ( 'string' === $mode ) {
		return munipb_css_inspection( false, '', 'unclosed_string', $string_line );
	}
	if ( array() !== $opens ) {
		return munipb_css_inspection( false, '', 'unbalanced', (int) $opens[0] );
	}
	return munipb_css_inspection( true, $css, '', 0 );
}

/**
 * CSS that is safe to store and print, or '' when it is empty or invalid.
 *
 * @param mixed $css Raw CSS.
 * @return string
 */
function munipb_prepare_custom_css( $css ) {
	$result = munipb_inspect_custom_css( is_scalar( $css ) ? (string) $css : '' );
	return $result['ok'] ? $result['css'] : '';
}

/**
 * Replace the selector keyword with one widget directory wrapper.
 *
 * CSS without that keyword is nested under the wrapper. Invalid CSS and an
 * unexpected wrapper are discarded.
 *
 * @param mixed  $css   Raw CSS.
 * @param string $scope Widget scope, ".elementor-element-ID .munipb-directory".
 * @return string
 */
function munipb_scope_custom_css( $css, $scope ) {
	$prepared = munipb_prepare_custom_css( $css );
	$scope    = (string) $scope;
	if ( '' === $prepared || 1 !== preg_match( '/^\.elementor-element-[A-Za-z0-9_-]+ \.munipb-directory$/', $scope ) ) {
		return '';
	}
	if ( false === strpos( $prepared, 'selector' ) ) {
		return $scope . '{' . $prepared . '}';
	}
	return str_replace( 'selector', $scope, $prepared );
}

/**
 * Greek (and other translated) explanation for a rejected settings value.
 *
 * @param string $code Error code from munipb_inspect_custom_css().
 * @param int    $line Line number, when the code has one.
 * @return string
 */
function munipb_custom_css_error_message( $code, $line ) {
	$line = (int) $line;
	switch ( $code ) {
		case 'too_long':
			return __( 'The custom CSS is too long. The limit is 50 KB. The previous CSS was kept.', 'municipal-phonebook' );
		case 'markup':
			return __( 'The custom CSS cannot contain HTML or a style tag. The previous CSS was kept.', 'municipal-phonebook' );
		case 'unclosed_comment':
			return sprintf(
				/* translators: %d: line number. */
				__( 'The custom CSS has an unclosed comment on line %d. The previous CSS was kept.', 'municipal-phonebook' ),
				$line
			);
		case 'unclosed_string':
			return sprintf(
				/* translators: %d: line number. */
				__( 'The custom CSS has an unclosed quote on line %d. The previous CSS was kept.', 'municipal-phonebook' ),
				$line
			);
		case 'unbalanced':
			return sprintf(
				/* translators: %d: line number. */
				__( 'The custom CSS has unbalanced braces on line %d. The previous CSS was kept.', 'municipal-phonebook' ),
				$line
			);
		default:
			return __( 'The custom CSS is not valid. The previous CSS was kept.', 'municipal-phonebook' );
	}
}

/**
 * Record one settings error for a rejected stylesheet.
 *
 * The Settings API may sanitize the option twice. The notice is added once.
 *
 * @param string $code Error code.
 * @param int    $line Line number.
 * @return void
 */
function munipb_flag_custom_css_settings_error( $code, $line ) {
	static $sent = false;
	if ( $sent ) {
		return;
	}
	$sent = true;
	add_settings_error(
		'munipb_settings',
		'munipb_custom_css',
		munipb_custom_css_error_message( $code, $line ),
		'error'
	);
}

/**
 * Sanitize a hex color. Empty or invalid values become an empty string.
 *
 * @param mixed $color Raw color.
 * @return string #rgb or #rrggbb, or ''.
 */
function munipb_sanitize_hex( $color ) {
	$color = sanitize_hex_color( is_scalar( $color ) ? (string) $color : '' );
	return is_string( $color ) ? $color : '';
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
 * Sanitize the settings option.
 *
 * Booleans come from the submitted array (an unchecked box is 0). Row colors
 * and custom CSS that are absent — a save that only touches another key — stay
 * as stored. An empty or invalid color that was submitted falls back to the
 * default. Invalid custom CSS keeps the previous stylesheet.
 *
 * @param mixed $input Raw input.
 * @return array<string, mixed>
 */
function munipb_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = munipb_default_settings();
	$existing = get_option( 'munipb_settings', array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	$out = array();
	foreach ( array( 'enable_rest', 'track_calls', 'delete_on_remove' ) as $key ) {
		$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}
	foreach ( array( 'row_odd', 'row_even' ) as $key ) {
		if ( ! array_key_exists( $key, $input ) ) {
			$kept        = munipb_sanitize_hex( isset( $existing[ $key ] ) ? $existing[ $key ] : '' );
			$out[ $key ] = '' !== $kept ? $kept : $defaults[ $key ];
			continue;
		}
		$color       = munipb_sanitize_hex( $input[ $key ] );
		$out[ $key ] = '' !== $color ? $color : $defaults[ $key ];
	}
	if ( ! array_key_exists( 'sub_header', $input ) ) {
		$kept              = munipb_sanitize_hex( isset( $existing['sub_header'] ) ? $existing['sub_header'] : '' );
		$out['sub_header'] = '' !== $kept ? $kept : $defaults['sub_header'];
	} else {
		$color             = munipb_sanitize_hex( $input['sub_header'] );
		$out['sub_header'] = '' !== $color ? $color : $defaults['sub_header'];
	}
	if ( ! array_key_exists( 'inline_children', $input ) ) {
		$out['inline_children'] = array_key_exists( 'inline_children', $existing ) ? ( empty( $existing['inline_children'] ) ? 0 : 1 ) : 1;
	} else {
		$out['inline_children'] = empty( $input['inline_children'] ) ? 0 : 1;
	}
	if ( ! array_key_exists( 'columns', $input ) ) {
		$kept           = ( isset( $existing['columns'] ) && is_array( $existing['columns'] ) ) ? $existing['columns'] : array();
		$out['columns'] = $kept ? munipb_sanitize_columns( $kept ) : array();
	} else {
		$out['columns'] = munipb_sanitize_columns( $input['columns'] );
	}
	if ( ! array_key_exists( 'custom_css', $input ) ) {
		$out['custom_css'] = munipb_prepare_custom_css( isset( $existing['custom_css'] ) ? $existing['custom_css'] : '' );
	} else {
		$checked = munipb_inspect_custom_css( is_scalar( $input['custom_css'] ) ? (string) $input['custom_css'] : '' );
		if ( $checked['ok'] ) {
			$out['custom_css'] = $checked['css'];
		} else {
			$out['custom_css'] = munipb_prepare_custom_css( isset( $existing['custom_css'] ) ? $existing['custom_css'] : '' );
			munipb_flag_custom_css_settings_error( $checked['code'], (int) $checked['line'] );
		}
	}
	munipb_flush_cache();
	return $out;
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
		'custom' => munipb_read_entry_custom( $post->ID ),
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
					'inline'   => munipb_term_inline_children( $term->term_id ),
					'columns'  => munipb_columns_for_term( $term->term_id ),
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
 * Compare key for a department slug.
 *
 * Both sides are decoded, lowercased, and percent-encoded again. This matches
 * a stored Greek slug (%ce%b4…) whether the caller passed that slug, the same
 * slug with uppercase hex, or the Greek text. It does not run sanitize_title,
 * so a transliteration plugin cannot turn the slug into a different string.
 *
 * @param mixed $value Raw slug or label.
 * @return string
 */
function munipb_slug_key( $value ) {
	$value = trim( is_scalar( $value ) ? (string) $value : '' );
	if ( '' === $value ) {
		return '';
	}
	$decoded = rawurldecode( $value );
	if ( function_exists( 'mb_strtolower' ) ) {
		$decoded = mb_strtolower( $decoded, 'UTF-8' );
	} else {
		$decoded = strtolower( $decoded );
	}
	return strtolower( rawurlencode( $decoded ) );
}

/**
 * Index every department, including children, by id and by normalized slug.
 *
 * @return array{by_id:array<int, array{id:int, slug:string}>, by_key:array<string, int>}
 */
function munipb_department_index() {
	$by_id  = array();
	$by_key = array();

	$add = static function ( $id, $slug ) use ( &$by_id, &$by_key ) {
		$id   = (int) $id;
		$slug = (string) $slug;
		if ( $id < 1 || isset( $by_id[ $id ] ) ) {
			return;
		}
		$by_id[ $id ] = array(
			'id'   => $id,
			'slug' => $slug,
		);
		$key          = munipb_slug_key( $slug );
		if ( '' !== $key && ! isset( $by_key[ $key ] ) ) {
			$by_key[ $key ] = $id;
		}
		$plain = strtolower( $slug );
		if ( '' !== $plain && ! isset( $by_key[ $plain ] ) ) {
			$by_key[ $plain ] = $id;
		}
	};

	foreach ( munipb_get_directory() as $section ) {
		$add( $section['id'], isset( $section['slug'] ) ? $section['slug'] : '' );
		if ( empty( $section['children'] ) || ! is_array( $section['children'] ) ) {
			continue;
		}
		foreach ( $section['children'] as $child ) {
			$add( isset( $child['id'] ) ? $child['id'] : 0, isset( $child['slug'] ) ? $child['slug'] : '' );
		}
	}

	return array(
		'by_id'  => $by_id,
		'by_key' => $by_key,
	);
}

/**
 * Split a departments attribute into at most 100 tokens.
 *
 * @param string|array $raw Raw list.
 * @return string[]
 */
function munipb_department_tokens( $raw ) {
	if ( is_array( $raw ) ) {
		$parts = array();
		foreach ( $raw as $item ) {
			if ( is_scalar( $item ) ) {
				$parts[] = (string) $item;
			}
		}
		$raw = implode( ',', $parts );
	}
	$raw    = substr( trim( (string) $raw ), 0, 2000 );
	$tokens = array();
	if ( '' === $raw ) {
		return $tokens;
	}
	foreach ( array_slice( explode( ',', $raw ), 0, 100 ) as $token ) {
		$token = trim( $token );
		if ( '' !== $token ) {
			$tokens[] = $token;
		}
	}
	return $tokens;
}

/**
 * Resolve one token to a department id.
 *
 * A slug match wins over a numeric term id, so a slug that happens to be
 * digits is kept. Term ids are accepted for the Elementor control.
 *
 * @param string $token Token.
 * @param array  $index Department index from munipb_department_index().
 * @return int
 */
function munipb_match_department_token( $token, $index ) {
	$key = munipb_slug_key( $token );
	if ( '' !== $key && isset( $index['by_key'][ $key ] ) ) {
		return (int) $index['by_key'][ $key ];
	}
	$plain = strtolower( trim( (string) $token ) );
	if ( '' !== $plain && isset( $index['by_key'][ $plain ] ) ) {
		return (int) $index['by_key'][ $plain ];
	}
	if ( 1 === preg_match( '/^[0-9]+$/', trim( (string) $token ) ) ) {
		$id = (int) $token;
		if ( $id > 0 && isset( $index['by_id'][ $id ] ) ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Resolve a departments attribute.
 *
 * `active` is false only when the caller asked for every department. A
 * non-empty request that matches nothing stays active with an empty id list,
 * so the directory does not fall back to showing everything.
 *
 * @param string|array $raw Slugs, Greek names, or term ids.
 * @return array{active:bool, ids:int[], slugs:string[]}
 */
function munipb_parse_department_selection( $raw ) {
	$tokens = munipb_department_tokens( $raw );
	if ( ! $tokens ) {
		return array(
			'active' => false,
			'ids'    => array(),
			'slugs'  => array(),
		);
	}

	$index = munipb_department_index();
	$ids   = array();
	$slugs = array();
	foreach ( $tokens as $token ) {
		$id = munipb_match_department_token( $token, $index );
		if ( $id < 1 || isset( $ids[ $id ] ) ) {
			continue;
		}
		$ids[ $id ] = true;
		$slugs[]    = $index['by_id'][ $id ]['slug'];
	}

	return array(
		'active' => true,
		'ids'    => array_map( 'intval', array_keys( $ids ) ),
		'slugs'  => $slugs,
	);
}

/**
 * Parse and validate a comma separated list of departments.
 *
 * Each token may be a stored slug, the department name, or a term id. Unknown
 * tokens are dropped. Child departments are included. The returned slugs are
 * the values stored on the terms, not a fresh sanitize_title() pass.
 *
 * @param string|array $raw Raw list.
 * @return string[]
 */
function munipb_parse_department_slugs( $raw ) {
	$selection = munipb_parse_department_selection( $raw );
	return $selection['slugs'];
}

/**
 * Departments the Elementor control can offer.
 *
 * The option value is the term id. Saved widgets that still store a slug keep
 * working because munipb_parse_department_selection() accepts both.
 *
 * @return array<int, array{id:int, slug:string, label:string}>
 */
function munipb_department_choices() {
	$choices = array();
	foreach ( munipb_get_directory() as $section ) {
		$section_id   = (int) $section['id'];
		$section_name = isset( $section['name'] ) ? (string) $section['name'] : '';
		$choices[]    = array(
			'id'    => $section_id,
			'slug'  => isset( $section['slug'] ) ? (string) $section['slug'] : '',
			'label' => $section_name,
		);
		if ( empty( $section['children'] ) || ! is_array( $section['children'] ) ) {
			continue;
		}
		$names = array( $section_id => $section_name );
		foreach ( $section['children'] as $child ) {
			$child_id           = isset( $child['id'] ) ? (int) $child['id'] : 0;
			$names[ $child_id ] = isset( $child['name'] ) ? (string) $child['name'] : '';
		}
		foreach ( $section['children'] as $child ) {
			$child_id = isset( $child['id'] ) ? (int) $child['id'] : 0;
			if ( $child_id < 1 ) {
				continue;
			}
			$parent_id = isset( $child['parent'] ) ? (int) $child['parent'] : $section_id;
			$parent    = isset( $names[ $parent_id ] ) ? $names[ $parent_id ] : $section_name;
			$choices[] = array(
				'id'    => $child_id,
				'slug'  => isset( $child['slug'] ) ? (string) $child['slug'] : '',
				'label' => $parent . ' — ' . $names[ $child_id ],
			);
		}
	}
	return $choices;
}

/**
 * Keep only the selected departments.
 *
 * A selected top-level department keeps its whole table. A selected child
 * keeps that child (and its descendants) inside the parent table, which is
 * how an inline sub-department is shown. An empty id list shows nothing.
 *
 * @param array $tree Full directory tree.
 * @param int[] $ids  Term ids.
 * @return array<int, array<string, mixed>>
 */
function munipb_filter_directory( $tree, $ids ) {
	$wanted = array();
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$wanted[ $id ] = true;
		}
	}
	if ( ! $wanted ) {
		return array();
	}

	$out = array();
	foreach ( (array) $tree as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$section_id = isset( $section['id'] ) ? (int) $section['id'] : 0;
		if ( isset( $wanted[ $section_id ] ) ) {
			$out[] = $section;
			continue;
		}
		$children = ( isset( $section['children'] ) && is_array( $section['children'] ) ) ? $section['children'] : array();
		$parents  = array( $section_id => 0 );
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$child_id             = isset( $child['id'] ) ? (int) $child['id'] : 0;
			$parents[ $child_id ] = isset( $child['parent'] ) ? (int) $child['parent'] : $section_id;
		}
		$keep = array();
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$child_id = isset( $child['id'] ) ? (int) $child['id'] : 0;
			$direct   = munipb_department_selected( $child_id, $wanted, $parents, $section_id );
			$context  = munipb_department_ancestor_selected( $child_id, $wanted, $parents );
			if ( ! $direct && ! $context ) {
				continue;
			}
			if ( ! $direct ) {
				$child['entries'] = array();
			}
			$keep[] = $child;
		}
		if ( $keep ) {
			$section['entries']  = array();
			$section['children'] = $keep;
			$out[]               = $section;
		}
	}
	return $out;
}

/**
 * Whether this department, or one of its ancestors below the section, is selected.
 *
 * @param int   $id         Term id.
 * @param array $wanted     Selected ids.
 * @param array $parents    Child id => parent id.
 * @param int   $section_id Top-level id, which is not itself a match here.
 * @return bool
 */
function munipb_department_selected( $id, $wanted, $parents, $section_id ) {
	$guard   = 0;
	$current = (int) $id;
	while ( $current > 0 && $guard < 8 ) {
		if ( isset( $wanted[ $current ] ) ) {
			return true;
		}
		if ( $current === (int) $section_id ) {
			return false;
		}
		$current = isset( $parents[ $current ] ) ? (int) $parents[ $current ] : 0;
		++$guard;
	}
	return false;
}

/**
 * Whether this department is an ancestor of a selected department.
 *
 * The row stays so an inline child is still shown under its parent band.
 * The caller clears the ancestor's own entries.
 *
 * @param int   $id      Term id.
 * @param array $wanted  Selected ids.
 * @param array $parents Child id => parent id.
 * @return bool
 */
function munipb_department_ancestor_selected( $id, $wanted, $parents ) {
	$id = (int) $id;
	foreach ( array_keys( $wanted ) as $wanted_id ) {
		$guard   = 0;
		$current = isset( $parents[ (int) $wanted_id ] ) ? (int) $parents[ (int) $wanted_id ] : 0;
		while ( $current > 0 && $guard < 8 ) {
			if ( $current === $id ) {
				return true;
			}
			$current = isset( $parents[ $current ] ) ? (int) $parents[ $current ] : 0;
			++$guard;
		}
	}
	return false;
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
			'parent'  => (int) $parent_id,
			'depth'   => $depth,
			'phones'  => munipb_parse_phone_groups( get_term_meta( $child->term_id, 'munipb_phones', true ) ),
			'email'   => sanitize_email( (string) get_term_meta( $child->term_id, 'munipb_email', true ) ),
			'inline'  => munipb_term_inline_children( $child->term_id ),
			'columns' => munipb_columns_for_term( $child->term_id ),
			'entries' => array_map( 'munipb_get_entry_public_data', munipb_get_entries_for_term( $child->term_id ) ),
		);
		munipb_collect_children( $child->term_id, $out, $depth + 1 );
	}
}

require_once __DIR__ . '/columns.php';
