<?php
/**
 * Directory columns: schema, inheritance and public values.
 *
 * Built-in columns (name, role, phone, email) can be reordered or hidden.
 * Custom columns keep a stable ASCII slug used by post meta, CSV and REST.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keys reserved for the four built-in columns and the CSV header.
 *
 * @return string[]
 */
function munipb_reserved_column_keys() {
	return array( 'name', 'role', 'phone', 'phones', 'email', 'department', 'subdepartment', 'order' );
}

/**
 * Whether a key is one of the four built-in directory columns.
 *
 * @param string $key Column key.
 * @return bool
 */
function munipb_column_is_builtin( $key ) {
	return in_array( (string) $key, array( 'name', 'role', 'phone', 'email' ), true );
}

/**
 * Column types an administrator can add.
 *
 * @return string[]
 */
function munipb_custom_column_types() {
	return array( 'text', 'phone', 'email', 'url', 'file' );
}

/**
 * The historical directory: name, role, phone, email, all visible.
 *
 * An empty label means "use the translated built-in title".
 *
 * @return array<int, array<string, mixed>>
 */
function munipb_default_columns() {
	$columns = array();
	foreach ( array( 'name', 'role', 'phone', 'email' ) as $key ) {
		$columns[] = array(
			'key'       => $key,
			'label'     => '',
			'type'      => $key,
			'visible'   => 1,
			'link_text' => '',
		);
	}
	return $columns;
}

/**
 * Turn a label, including Greek, into an ASCII slug.
 *
 * @param string $text Label.
 * @return string
 */
function munipb_ascii_slug( $text ) {
	$text = trim( (string) $text );
	if ( function_exists( 'mb_strtolower' ) ) {
		$text = mb_strtolower( $text, 'UTF-8' );
	} else {
		$text = strtolower( $text );
	}
	$map  = array(
		'ά' => 'a',
		'ὰ' => 'a',
		'ἀ' => 'a',
		'ἁ' => 'a',
		'ἄ' => 'a',
		'ἅ' => 'a',
		'ἆ' => 'a',
		'ἇ' => 'a',
		'έ' => 'e',
		'ὲ' => 'e',
		'ἐ' => 'e',
		'ἑ' => 'e',
		'ἔ' => 'e',
		'ἕ' => 'e',
		'ή' => 'i',
		'ὴ' => 'i',
		'ἠ' => 'i',
		'ἡ' => 'i',
		'ἤ' => 'i',
		'ἥ' => 'i',
		'ἦ' => 'i',
		'ἧ' => 'i',
		'ί' => 'i',
		'ὶ' => 'i',
		'ἰ' => 'i',
		'ἱ' => 'i',
		'ἴ' => 'i',
		'ἵ' => 'i',
		'ἶ' => 'i',
		'ἷ' => 'i',
		'ΐ' => 'i',
		'ϊ' => 'i',
		'ό' => 'o',
		'ὸ' => 'o',
		'ὀ' => 'o',
		'ὁ' => 'o',
		'ὄ' => 'o',
		'ὅ' => 'o',
		'ύ' => 'y',
		'ὺ' => 'y',
		'ὐ' => 'y',
		'ὑ' => 'y',
		'ὔ' => 'y',
		'ὕ' => 'y',
		'ὖ' => 'y',
		'ὗ' => 'y',
		'ΰ' => 'y',
		'ϋ' => 'y',
		'ώ' => 'o',
		'ὼ' => 'o',
		'ὠ' => 'o',
		'ὡ' => 'o',
		'ὤ' => 'o',
		'ὥ' => 'o',
		'ὦ' => 'o',
		'ὧ' => 'o',
		'θ' => 'th',
		'χ' => 'ch',
		'ψ' => 'ps',
		'α' => 'a',
		'β' => 'b',
		'γ' => 'g',
		'δ' => 'd',
		'ε' => 'e',
		'ζ' => 'z',
		'η' => 'i',
		'ι' => 'i',
		'κ' => 'k',
		'λ' => 'l',
		'μ' => 'm',
		'ν' => 'n',
		'ξ' => 'x',
		'ο' => 'o',
		'π' => 'p',
		'ρ' => 'r',
		'σ' => 's',
		'ς' => 's',
		'τ' => 't',
		'υ' => 'y',
		'φ' => 'f',
		'ω' => 'o',
	);
	$text = strtr( $text, $map );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '_', $text );
	$text = trim( (string) $text, '_' );
	return substr( (string) $text, 0, 32 );
}

/**
 * A column key: a letter, then letters, digits or underscores, at most 32 characters.
 *
 * @param mixed $key Raw key.
 * @return string
 */
function munipb_sanitize_column_key( $key ) {
	$key = strtolower( (string) $key );
	$key = preg_replace( '/[^a-z0-9_]/', '', $key );
	$key = trim( (string) $key, '_' );
	if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,31}$/', (string) $key ) ) {
		return '';
	}
	return (string) $key;
}

/**
 * Plain single-line text, limited in length.
 *
 * @param mixed $text Raw text.
 * @param int   $max  Maximum characters.
 * @return string
 */
function munipb_clean_line( $text, $max ) {
	$text = sanitize_text_field( is_scalar( $text ) ? (string) $text : '' );
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $text, 0, $max );
	}
	return substr( $text, 0, $max );
}

/**
 * Normalize the column list stored in settings.
 *
 * Built-in columns that the request omitted are appended and kept visible,
 * so a partial save cannot drop Full name. An empty list is the historical default.
 * Custom slugs are unique. A second "adeies" becomes "adeies_2".
 *
 * @param mixed $input Raw columns.
 * @return array<int, array<string, mixed>>
 */
function munipb_sanitize_columns( $input ) {
	if ( ! is_array( $input ) || array() === $input ) {
		return munipb_default_columns();
	}

	$columns = array();
	$used    = array();
	$count   = 0;
	foreach ( $input as $row ) {
		if ( $count >= 16 || ! is_array( $row ) ) {
			continue;
		}
		$key = munipb_sanitize_column_key( isset( $row['key'] ) ? $row['key'] : '' );
		if ( '' === $key ) {
			$key = munipb_sanitize_column_key( munipb_ascii_slug( isset( $row['label'] ) ? $row['label'] : '' ) );
		}
		if ( '' === $key || in_array( $key, array( 'phones', 'department', 'subdepartment', 'order' ), true ) ) {
			$key = '';
		}
		if ( '' === $key ) {
			continue;
		}
		$base = $key;
		$n    = 2;
		while ( isset( $used[ $key ] ) ) {
			$key = substr( $base, 0, 28 ) . '_' . $n;
			++$n;
			if ( $n > 50 ) {
				continue 2;
			}
		}

		$builtin = munipb_column_is_builtin( $key );
		$type    = isset( $row['type'] ) ? (string) $row['type'] : 'text';
		if ( $builtin ) {
			$type = $key;
		} elseif ( ! in_array( $type, munipb_custom_column_types(), true ) ) {
			$type = 'text';
		}

		$link = '';
		if ( 'file' === $type ) {
			$link = munipb_clean_line( isset( $row['link_text'] ) ? $row['link_text'] : '', 40 );
		}

		$columns[]    = array(
			'key'       => $key,
			'label'     => munipb_clean_line( isset( $row['label'] ) ? $row['label'] : '', 80 ),
			'type'      => $type,
			'visible'   => empty( $row['visible'] ) ? 0 : 1,
			'link_text' => $link,
		);
		$used[ $key ] = true;
		++$count;
	}

	foreach ( munipb_default_columns() as $builtin ) {
		if ( ! isset( $used[ $builtin['key'] ] ) ) {
			$columns[]               = $builtin;
			$used[ $builtin['key'] ] = true;
		}
	}

	return $columns ? $columns : munipb_default_columns();
}

/**
 * Columns from settings, always a usable list.
 *
 * @return array<int, array<string, mixed>>
 */
function munipb_get_columns() {
	$settings = munipb_get_settings();
	$columns  = isset( $settings['columns'] ) ? $settings['columns'] : array();
	if ( ! is_array( $columns ) || array() === $columns ) {
		return munipb_default_columns();
	}
	return munipb_sanitize_columns( $columns );
}

/**
 * Visible column keys, in settings order.
 *
 * @param array|null $columns Column definitions. Null reads the settings.
 * @return string[]
 */
function munipb_visible_column_keys( $columns = null ) {
	if ( null === $columns ) {
		$columns = munipb_get_columns();
	}
	$keys = array();
	foreach ( (array) $columns as $column ) {
		if ( is_array( $column ) && ! empty( $column['visible'] ) && ! empty( $column['key'] ) ) {
			$keys[] = (string) $column['key'];
		}
	}
	return $keys ? $keys : array( 'name', 'role', 'phone', 'email' );
}

/**
 * Walk a department chain from the top-level ancestor to the department itself.
 *
 * Each item is null when that department inherits, or a list of column keys
 * when it has its own set. The first custom list replaces the global columns.
 * A later null keeps the parent choice, so a child inherits unless it overrides.
 *
 * @param string[]          $global_keys Visible global keys.
 * @param array<int, mixed> $chain       Ancestor choices, root first.
 * @return string[]
 */
function munipb_resolve_column_keys( $global_keys, $chain ) {
	$current = array_values( (array) $global_keys );
	foreach ( (array) $chain as $choice ) {
		if ( is_array( $choice ) ) {
			$current = array_values( $choice );
		}
	}
	return $current;
}

/**
 * Merge column lists. The first list wins order. Later lists append new keys.
 *
 * @param array<int, string[]> $lists Key lists.
 * @return string[]
 */
function munipb_union_column_keys( $lists ) {
	$out = array();
	foreach ( (array) $lists as $list ) {
		foreach ( (array) $list as $key ) {
			$key = (string) $key;
			if ( '' !== $key && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
	}
	return $out;
}

/**
 * Column definitions for a key list, using the catalog for labels and types.
 *
 * @param string[] $keys    Keys in display order.
 * @param array    $catalog Full column catalog.
 * @return array<int, array<string, mixed>>
 */
function munipb_columns_by_keys( $keys, $catalog ) {
	$by_key = array();
	foreach ( (array) $catalog as $column ) {
		if ( is_array( $column ) && ! empty( $column['key'] ) ) {
			$by_key[ $column['key'] ] = $column;
		}
	}
	$out = array();
	foreach ( (array) $keys as $key ) {
		if ( isset( $by_key[ $key ] ) ) {
			$column            = $by_key[ $key ];
			$column['visible'] = 1;
			$out[]             = $column;
		}
	}
	return $out ? $out : munipb_default_columns();
}

/**
 * Public http(s) URL, or an empty string.
 *
 * @param mixed $url Raw URL.
 * @return string
 */
function munipb_sanitize_public_url( $url ) {
	$url = trim( is_scalar( $url ) ? (string) $url : '' );
	if ( function_exists( 'esc_url_raw' ) ) {
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		$url   = is_string( $clean ) ? $clean : '';
	} elseif ( 1 !== preg_match( '#^https?://#i', $url ) || preg_match( '/[\s<>"\']/', $url ) ) {
		return '';
	}
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $url, 0, 300 );
	}
	return substr( $url, 0, 300 );
}

/**
 * Sanitize one custom-column value. File values are positive integers, not yet checked against the media library.
 *
 * @param string $type  Column type.
 * @param mixed  $value Raw value.
 * @return string|int
 */
function munipb_sanitize_column_value( $type, $value ) {
	if ( 'file' === $type ) {
		return absint( $value );
	}
	if ( 'email' === $type ) {
		$email = trim( is_scalar( $value ) ? (string) $value : '' );
		if ( function_exists( 'sanitize_email' ) ) {
			$email = sanitize_email( $email );
		}
		return is_email( $email ) ? $email : '';
	}
	if ( 'url' === $type ) {
		return munipb_sanitize_public_url( $value );
	}
	if ( 'phone' === $type ) {
		return munipb_sanitize_phone( is_scalar( $value ) ? (string) $value : '' );
	}
	return munipb_clean_line( $value, 500 );
}

/**
 * Attachment id when the post is a public attachment. Zero otherwise.
 *
 * Without WordPress the numeric id is returned so unit tests can store it.
 *
 * @param mixed $id Raw id.
 * @return int
 */
function munipb_valid_attachment_id( $id ) {
	$id = absint( $id );
	if ( ! $id || ! function_exists( 'get_post' ) ) {
		return $id;
	}
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
		return 0;
	}
	if ( ! in_array( $post->post_status, array( 'inherit', 'publish' ), true ) ) {
		return 0;
	}
	return $id;
}

/**
 * Public download data for an attachment, or an empty url when it cannot be shown.
 *
 * @param mixed $id Attachment id.
 * @return array{id:int,url:string,name:string}
 */
function munipb_attachment_download( $id ) {
	$empty = array(
		'id'   => 0,
		'url'  => '',
		'name' => '',
	);
	$id    = munipb_valid_attachment_id( $id );
	if ( ! $id || ! function_exists( 'wp_get_attachment_url' ) ) {
		return $empty;
	}
	$url = wp_get_attachment_url( $id );
	if ( ! is_string( $url ) || '' === $url ) {
		return $empty;
	}
	$name = '';
	if ( function_exists( 'get_attached_file' ) ) {
		$file = get_attached_file( $id );
		if ( is_string( $file ) && '' !== $file ) {
			$name = function_exists( 'wp_basename' ) ? wp_basename( $file ) : basename( $file );
			$name = sanitize_file_name( $name );
		}
	}
	return array(
		'id'   => $id,
		'url'  => $url,
		'name' => $name,
	);
}

/**
 * Custom values that are printed in the row, except files. Used so search matches the visible cells.
 *
 * @param array $custom  Values keyed by column slug.
 * @param array $columns Column definitions.
 * @return string
 */
function munipb_custom_visible_text( $custom, $columns ) {
	$types = array();
	foreach ( (array) $columns as $column ) {
		if ( is_array( $column ) && ! empty( $column['key'] ) ) {
			$types[ $column['key'] ] = isset( $column['type'] ) ? (string) $column['type'] : '';
		}
	}
	$parts = array();
	foreach ( (array) $custom as $key => $value ) {
		if ( ! isset( $types[ $key ] ) || 'file' === $types[ $key ] || ! is_scalar( $value ) ) {
			continue;
		}
		$parts[] = (string) $value;
	}
	return trim( implode( ' ', $parts ) );
}

/**
 * Text custom-column values joined for search. Files, phones, emails and URLs are not included.
 *
 * @param array $custom  Values keyed by column slug.
 * @param array $columns Column definitions that decide the type.
 * @return string
 */
function munipb_custom_search_text( $custom, $columns ) {
	$types = array();
	foreach ( (array) $columns as $column ) {
		if ( is_array( $column ) && ! empty( $column['key'] ) ) {
			$types[ $column['key'] ] = isset( $column['type'] ) ? (string) $column['type'] : '';
		}
	}
	$parts = array();
	foreach ( (array) $custom as $key => $value ) {
		if ( isset( $types[ $key ] ) && 'text' === $types[ $key ] && is_scalar( $value ) && '' !== (string) $value ) {
			$parts[] = (string) $value;
		}
	}
	return trim( implode( ' ', $parts ) );
}

/**
 * Translated label. An empty stored label uses the built-in title.
 *
 * @param array $column Column definition.
 * @return string
 */
function munipb_column_label( $column ) {
	$label = isset( $column['label'] ) ? (string) $column['label'] : '';
	if ( '' !== $label ) {
		return $label;
	}
	switch ( isset( $column['key'] ) ? (string) $column['key'] : '' ) {
		case 'name':
			return __( 'Full name', 'municipal-phonebook' );
		case 'role':
			return __( 'Role', 'municipal-phonebook' );
		case 'phone':
			return __( 'Phone', 'municipal-phonebook' );
		case 'email':
			return __( 'Email', 'municipal-phonebook' );
		default:
			return isset( $column['key'] ) ? (string) $column['key'] : '';
	}
}

/**
 * Link text for a file column. Empty means the translated word "Download".
 *
 * @param array $column Column definition.
 * @return string
 */
function munipb_column_link_text( $column ) {
	$text = isset( $column['link_text'] ) ? (string) $column['link_text'] : '';
	if ( '' !== $text ) {
		return $text;
	}
	return __( 'Download', 'municipal-phonebook' );
}

/**
 * Read stored custom values. Does not check that the attachment still exists.
 *
 * @param int $post_id Entry id.
 * @return array<string, string|int>
 */
function munipb_read_entry_custom( $post_id ) {
	if ( ! function_exists( 'get_post_meta' ) ) {
		return array();
	}
	$stored = get_post_meta( $post_id, '_munipb_custom', true );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Sanitize custom values against the column catalog and, for files, the media library.
 *
 * @param mixed      $raw     Submitted values keyed by slug.
 * @param array|null $columns Catalog. Null uses the saved columns.
 * @return array<string, string|int>
 */
function munipb_sanitize_entry_custom( $raw, $columns = null ) {
	// register_meta passes the meta key as the second argument.
	if ( ! is_array( $columns ) ) {
		$columns = munipb_get_columns();
	}
	$raw = is_array( $raw ) ? $raw : array();
	$out = array();
	foreach ( (array) $columns as $column ) {
		if ( ! is_array( $column ) || empty( $column['key'] ) || munipb_column_is_builtin( $column['key'] ) ) {
			continue;
		}
		if ( ! array_key_exists( $column['key'], $raw ) ) {
			continue;
		}
		$value = munipb_sanitize_column_value( $column['type'], $raw[ $column['key'] ] );
		if ( 'file' === $column['type'] ) {
			$value = munipb_valid_attachment_id( $value );
		}
		if ( '' === $value || 0 === $value ) {
			continue;
		}
		$out[ $column['key'] ] = $value;
	}
	return $out;
}

/**
 * Whether this department shows its children as a full-width sub-header.
 *
 * Term meta "1" or "0" wins. Anything else uses the global setting.
 *
 * @param int $term_id Term id.
 * @return bool
 */
function munipb_term_inline_children( $term_id ) {
	if ( function_exists( 'get_term_meta' ) ) {
		$stored = get_term_meta( (int) $term_id, 'munipb_inline_children', true );
		if ( '0' === (string) $stored || '1' === (string) $stored ) {
			return '1' === (string) $stored;
		}
	}
	$settings = munipb_get_settings();
	return ! empty( $settings['inline_children'] );
}

/**
 * Column definitions for one department: its override, otherwise its parent's, otherwise the global visible columns.
 *
 * @param int $term_id Term id.
 * @return array<int, array<string, mixed>>
 */
function munipb_columns_for_term( $term_id ) {
	$catalog = munipb_get_columns();
	$global  = munipb_visible_column_keys( $catalog );
	if ( ! function_exists( 'get_term' ) ) {
		return munipb_columns_by_keys( $global, $catalog );
	}

	$chain   = array();
	$current = (int) $term_id;
	$guard   = 0;
	while ( $current > 0 && $guard < 6 ) {
		$mode = (string) get_term_meta( $current, 'munipb_columns_mode', true );
		$keys = get_term_meta( $current, 'munipb_columns', true );
		if ( 'custom' === $mode && is_array( $keys ) ) {
			$clean = array();
			foreach ( $keys as $key ) {
				$key = munipb_sanitize_column_key( $key );
				if ( '' !== $key ) {
					$clean[] = $key;
				}
			}
			array_unshift( $chain, $clean );
		} else {
			array_unshift( $chain, null );
		}
		$term = get_term( $current, Munipb_Post_Types::TAXONOMY );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			break;
		}
		$current = (int) $term->parent;
		++$guard;
	}

	return munipb_columns_by_keys( munipb_resolve_column_keys( $global, $chain ), $catalog );
}

/**
 * Keys configured for a directory node.
 *
 * @param array $node Section or child.
 * @return string[]
 */
function munipb_node_column_keys( $node ) {
	$keys = array();
	if ( ! empty( $node['columns'] ) && is_array( $node['columns'] ) ) {
		foreach ( $node['columns'] as $column ) {
			if ( is_array( $column ) && ! empty( $column['key'] ) ) {
				$keys[] = (string) $column['key'];
			}
		}
	}
	return $keys ? $keys : array( 'name', 'role', 'phone', 'email' );
}

/**
 * True when the columns are still name, role, phone, email in that order.
 *
 * @param array $columns Column definitions.
 * @return bool
 */
function munipb_is_legacy_column_set( $columns ) {
	$keys = array();
	foreach ( (array) $columns as $column ) {
		if ( is_array( $column ) && ! empty( $column['key'] ) ) {
			$keys[] = (string) $column['key'];
		}
	}
	return array( 'name', 'role', 'phone', 'email' ) === $keys;
}

/**
 * Public column payload for the REST response. Files expose a URL, not the attachment id.
 *
 * @param array $entry   Public entry.
 * @param array $columns Columns visible for that entry.
 * @return array<string, array<string, string>>
 */
function munipb_public_column_values( $entry, $columns ) {
	$custom = ( isset( $entry['custom'] ) && is_array( $entry['custom'] ) ) ? $entry['custom'] : array();
	$out    = array();
	foreach ( (array) $columns as $column ) {
		if ( ! is_array( $column ) || empty( $column['key'] ) || munipb_column_is_builtin( $column['key'] ) ) {
			continue;
		}
		if ( ! array_key_exists( $column['key'], $custom ) ) {
			continue;
		}
		$item = array(
			'type'  => (string) $column['type'],
			'label' => munipb_column_label( $column ),
		);
		if ( 'file' === $column['type'] ) {
			$file = munipb_attachment_download( $custom[ $column['key'] ] );
			if ( '' === $file['url'] ) {
				continue;
			}
			$item['url'] = $file['url'];
		} else {
			$item['value'] = (string) $custom[ $column['key'] ];
		}
		$out[ $column['key'] ] = $item;
	}
	return $out;
}
