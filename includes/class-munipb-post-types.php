<?php
/**
 * Post type, taxonomy and meta registration.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers data structures.
 */
class Munipb_Post_Types {

	const POST_TYPE = 'munipb_entry';
	const TAXONOMY  = 'munipb_department';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 0 );
		add_action( 'init', array( __CLASS__, 'register' ) );

		// Invalidate the cached directory whenever data changes.
		add_action( 'save_post_' . self::POST_TYPE, 'munipb_flush_cache' );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_flush_on_delete' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush_on_delete' ) );
		add_action( 'created_' . self::TAXONOMY, 'munipb_flush_cache' );
		add_action( 'edited_' . self::TAXONOMY, 'munipb_flush_cache' );
		add_action( 'delete_' . self::TAXONOMY, 'munipb_flush_cache' );
		add_action( 'set_object_terms', array( __CLASS__, 'maybe_flush_on_terms' ), 10, 4 );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'municipal-phonebook', false, dirname( plugin_basename( MUNIPB_FILE ) ) . '/languages' );
	}

	/**
	 * Register CPT, taxonomy and meta.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Phonebook', 'municipal-phonebook' ),
					'singular_name'      => __( 'Contact', 'municipal-phonebook' ),
					'menu_name'          => __( 'Phonebook', 'municipal-phonebook' ),
					'all_items'          => __( 'All contacts', 'municipal-phonebook' ),
					'add_new'            => __( 'Add contact', 'municipal-phonebook' ),
					'add_new_item'       => __( 'Add new contact', 'municipal-phonebook' ),
					'edit_item'          => __( 'Edit contact', 'municipal-phonebook' ),
					'new_item'           => __( 'New contact', 'municipal-phonebook' ),
					'search_items'       => __( 'Search contacts', 'municipal-phonebook' ),
					'not_found'          => __( 'No contacts found.', 'municipal-phonebook' ),
					'not_found_in_trash' => __( 'No contacts found in Trash.', 'municipal-phonebook' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-phone',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'page-attributes' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'              => __( 'Departments', 'municipal-phonebook' ),
					'singular_name'     => __( 'Department', 'municipal-phonebook' ),
					'search_items'      => __( 'Search departments', 'municipal-phonebook' ),
					'all_items'         => __( 'All departments', 'municipal-phonebook' ),
					'parent_item'       => __( 'Parent department', 'municipal-phonebook' ),
					'parent_item_colon' => __( 'Parent department:', 'municipal-phonebook' ),
					'edit_item'         => __( 'Edit department', 'municipal-phonebook' ),
					'update_item'       => __( 'Update department', 'municipal-phonebook' ),
					'add_new_item'      => __( 'Add new department', 'municipal-phonebook' ),
					'new_item_name'     => __( 'New department name', 'municipal-phonebook' ),
					'menu_name'         => __( 'Departments', 'municipal-phonebook' ),
				),
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};

		register_post_meta(
			self::POST_TYPE,
			'_munipb_role',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_munipb_email',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_email',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_munipb_phones',
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => 'munipb_parse_phone_groups',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_munipb_custom',
			array(
				'type'              => 'object',
				'single'            => true,
				'sanitize_callback' => 'munipb_sanitize_entry_custom',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_munipb_private_note',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);

		$term_auth = static function () {
			return current_user_can( 'manage_categories' );
		};
		register_term_meta(
			self::TAXONOMY,
			'munipb_inline_children',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => static function ( $value ) {
					$value = (string) $value;
					return ( '0' === $value || '1' === $value ) ? $value : '';
				},
				'auth_callback'     => $term_auth,
				'show_in_rest'      => false,
			)
		);
		register_term_meta(
			self::TAXONOMY,
			'munipb_columns_mode',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => static function ( $value ) {
					return 'custom' === $value ? 'custom' : 'inherit';
				},
				'auth_callback'     => $term_auth,
				'show_in_rest'      => false,
			)
		);
		register_term_meta(
			self::TAXONOMY,
			'munipb_columns',
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => static function ( $value ) {
					$keys = array();
					foreach ( (array) $value as $key ) {
						$key = munipb_sanitize_column_key( $key );
						if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
							$keys[] = $key;
						}
					}
					return $keys;
				},
				'auth_callback'     => $term_auth,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Flush cache when an entry is deleted or trashed.
	 *
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    Post object (deleted_post only).
	 * @return void
	 */
	public static function maybe_flush_on_delete( $post_id, $post = null ) {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( self::POST_TYPE === $type ) {
			munipb_flush_cache();
		}
	}

	/**
	 * Flush cache when department assignment changes.
	 *
	 * @param int    $object_id Object id.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy ids.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function maybe_flush_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( self::TAXONOMY === $taxonomy ) {
			munipb_flush_cache();
		}
	}
}
