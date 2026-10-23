<?php
/**
 * Admin UI: meta boxes, term fields, list columns, settings.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class Munipb_Admin {

	const NONCE_ENTRY = 'munipb_save_entry';
	const NONCE_TERM  = 'munipb_save_term';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$tax = Munipb_Post_Types::TAXONOMY;
		$cpt = Munipb_Post_Types::POST_TYPE;

		add_action( 'add_meta_boxes_' . $cpt, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . $cpt, array( __CLASS__, 'save_entry' ), 5, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );

		add_action( $tax . '_add_form_fields', array( __CLASS__, 'term_add_fields' ) );
		add_action( $tax . '_edit_form_fields', array( __CLASS__, 'term_edit_fields' ) );
		add_action( 'created_' . $tax, array( __CLASS__, 'save_term' ) );
		add_action( 'edited_' . $tax, array( __CLASS__, 'save_term' ) );
		add_filter( 'manage_edit-' . $tax . '_columns', array( __CLASS__, 'term_columns' ) );
		add_filter( 'manage_' . $tax . '_custom_column', array( __CLASS__, 'term_column_content' ), 10, 3 );

		add_filter( 'manage_' . $cpt . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . $cpt . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'department_filter' ) );

		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MUNIPB_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( Munipb_Post_Types::POST_TYPE === $post->post_type ) {
			return __( 'Full name or office name', 'municipal-phonebook' );
		}
		return $text;
	}

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'munipb_details', __( 'Contact details', 'municipal-phonebook' ), array( __CLASS__, 'render_meta_box' ), Munipb_Post_Types::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Meta box output.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ENTRY, 'munipb_entry_nonce' );
		$role   = (string) get_post_meta( $post->ID, '_munipb_role', true );
		$email  = (string) get_post_meta( $post->ID, '_munipb_email', true );
		$phones = munipb_phone_groups_to_string( get_post_meta( $post->ID, '_munipb_phones', true ) );
		$note   = (string) get_post_meta( $post->ID, '_munipb_private_note', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="munipb_role"><?php esc_html_e( 'Role / title', 'municipal-phonebook' ); ?></label></th>
				<td><input type="text" class="regular-text" id="munipb_role" name="munipb_role" value="<?php echo esc_attr( $role ); ?>" maxlength="200"></td>
			</tr>
			<tr>
				<th scope="row"><label for="munipb_phones"><?php esc_html_e( 'Phones', 'municipal-phonebook' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="3" id="munipb_phones" name="munipb_phones" aria-describedby="munipb_phones_help"><?php echo esc_textarea( $phones ); ?></textarea>
					<p class="description" id="munipb_phones_help"><?php esc_html_e( 'One line per group. Separate numbers on the same line with | (e.g. 2132018733 | 2132018712).', 'municipal-phonebook' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="munipb_email"><?php esc_html_e( 'Email', 'municipal-phonebook' ); ?></label></th>
				<td><input type="email" class="regular-text" id="munipb_email" name="munipb_email" value="<?php echo esc_attr( $email ); ?>" maxlength="190"></td>
			</tr>
			<tr>
				<th scope="row"><label for="munipb_private_note"><?php esc_html_e( 'Internal note', 'municipal-phonebook' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="2" id="munipb_private_note" name="munipb_private_note" aria-describedby="munipb_note_help"><?php echo esc_textarea( $note ); ?></textarea>
					<p class="description" id="munipb_note_help"><?php esc_html_e( 'Private. Never shown on the website, in search or in the REST API.', 'municipal-phonebook' ); ?></p>
				</td>
			</tr>
		</table>
		<?php Munipb_Columns_Admin::render_entry_fields( $post->ID ); ?>
		<p class="description"><?php esc_html_e( 'Choose the department in the "Departments" box and set the display order in "Page Attributes → Order".', 'municipal-phonebook' ); ?></p>
		<?php
	}

	/**
	 * Save entry meta.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_entry( $post_id, $post ) {
		if ( ! isset( $_POST['munipb_entry_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['munipb_entry_nonce'] ) ), self::NONCE_ENTRY ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( Munipb_Post_Types::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$role   = isset( $_POST['munipb_role'] ) ? sanitize_text_field( wp_unslash( $_POST['munipb_role'] ) ) : '';
		$email  = isset( $_POST['munipb_email'] ) ? sanitize_email( wp_unslash( $_POST['munipb_email'] ) ) : '';
		$phones = isset( $_POST['munipb_phones'] ) ? munipb_parse_phone_groups( sanitize_textarea_field( wp_unslash( $_POST['munipb_phones'] ) ) ) : array();
		$note   = isset( $_POST['munipb_private_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['munipb_private_note'] ) ) : '';

		self::update_or_delete( $post_id, '_munipb_role', mb_substr( $role, 0, 200 ) );
		self::update_or_delete( $post_id, '_munipb_email', is_email( $email ) ? $email : '' );
		self::update_or_delete( $post_id, '_munipb_phones', $phones );
		self::update_or_delete( $post_id, '_munipb_private_note', $note );
		Munipb_Columns_Admin::save_entry( $post_id );
	}

	/**
	 * Update meta or delete it when empty.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	private static function update_or_delete( $post_id, $key, $value ) {
		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Common term fields.
	 *
	 * @param string $phones Phones string.
	 * @param string $email  Email.
	 * @param int    $order  Order.
	 * @return array<string, string>
	 */
	private static function term_field_html( $phones, $email, $order ) {
		ob_start();
		?>
		<input type="number" name="munipb_order" id="munipb_order" value="<?php echo esc_attr( (string) $order ); ?>" step="1" min="0" max="9999">
		<?php
		$order_html = (string) ob_get_clean();
		ob_start();
		?>
		<textarea name="munipb_phones" id="munipb_phones" rows="2" class="large-text code"><?php echo esc_textarea( $phones ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Optional, shown on the sub-department row. One line per group, numbers separated by |.', 'municipal-phonebook' ); ?></p>
		<?php
		$phones_html = (string) ob_get_clean();
		ob_start();
		?>
		<input type="email" name="munipb_email" id="munipb_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text">
		<?php
		$email_html = (string) ob_get_clean();
		return array(
			'order'  => $order_html,
			'phones' => $phones_html,
			'email'  => $email_html,
		);
	}

	/**
	 * Add-term form fields.
	 *
	 * @return void
	 */
	public static function term_add_fields() {
		wp_nonce_field( self::NONCE_TERM, 'munipb_term_nonce' );
		$f = self::term_field_html( '', '', 0 );
		?>
		<div class="form-field">
			<label for="munipb_order"><?php esc_html_e( 'Display order', 'municipal-phonebook' ); ?></label>
			<?php echo $f['order']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?>
		</div>
		<div class="form-field">
			<label for="munipb_phones"><?php esc_html_e( 'Department phones', 'municipal-phonebook' ); ?></label>
			<?php echo $f['phones']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?>
		</div>
		<div class="form-field">
			<label for="munipb_email"><?php esc_html_e( 'Department email', 'municipal-phonebook' ); ?></label>
			<?php echo $f['email']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?>
		</div>
		<div class="form-field">
			<?php Munipb_Columns_Admin::render_term_fields( 0 ); ?>
		</div>
		<?php
	}

	/**
	 * Edit-term form fields.
	 *
	 * @param WP_Term $term Term.
	 * @return void
	 */
	public static function term_edit_fields( $term ) {
		$f = self::term_field_html(
			munipb_phone_groups_to_string( get_term_meta( $term->term_id, 'munipb_phones', true ) ),
			(string) get_term_meta( $term->term_id, 'munipb_email', true ),
			(int) get_term_meta( $term->term_id, 'munipb_order', true )
		);
		?>
		<tr class="form-field">
			<th scope="row"><label for="munipb_order"><?php esc_html_e( 'Display order', 'municipal-phonebook' ); ?></label></th>
			<td><?php wp_nonce_field( self::NONCE_TERM, 'munipb_term_nonce' ); ?><?php echo $f['order']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="munipb_phones"><?php esc_html_e( 'Department phones', 'municipal-phonebook' ); ?></label></th>
			<td><?php echo $f['phones']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="munipb_email"><?php esc_html_e( 'Department email', 'municipal-phonebook' ); ?></label></th>
			<td><?php echo $f['email']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in term_field_html(). ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Columns', 'municipal-phonebook' ); ?></th>
			<td><?php Munipb_Columns_Admin::render_term_fields( $term->term_id ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Save term meta (only when our nonce is present, so Quick Edit never wipes values).
	 *
	 * @param int $term_id Term id.
	 * @return void
	 */
	public static function save_term( $term_id ) {
		if ( ! isset( $_POST['munipb_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['munipb_term_nonce'] ) ), self::NONCE_TERM ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$order  = isset( $_POST['munipb_order'] ) ? absint( wp_unslash( $_POST['munipb_order'] ) ) : 0;
		$phones = isset( $_POST['munipb_phones'] ) ? munipb_parse_phone_groups( sanitize_textarea_field( wp_unslash( $_POST['munipb_phones'] ) ) ) : array();
		$email  = isset( $_POST['munipb_email'] ) ? sanitize_email( wp_unslash( $_POST['munipb_email'] ) ) : '';

		update_term_meta( $term_id, 'munipb_order', min( $order, 9999 ) );
		update_term_meta( $term_id, 'munipb_phones', $phones );
		update_term_meta( $term_id, 'munipb_email', is_email( $email ) ? $email : '' );
		Munipb_Columns_Admin::save_term( $term_id );
		munipb_flush_cache();
	}

	/**
	 * Term list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function term_columns( $columns ) {
		$columns['munipb_order'] = __( 'Order', 'municipal-phonebook' );
		return $columns;
	}

	/**
	 * Term column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term id.
	 * @return string
	 */
	public static function term_column_content( $content, $column, $term_id ) {
		if ( 'munipb_order' === $column ) {
			return esc_html( (string) (int) get_term_meta( $term_id, 'munipb_order', true ) );
		}
		return $content;
	}

	/**
	 * Post list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['munipb_role']  = __( 'Role', 'municipal-phonebook' );
				$new['munipb_phone'] = __( 'Phone', 'municipal-phonebook' );
				$new['munipb_email'] = __( 'Email', 'municipal-phonebook' );
				$new['munipb_order'] = __( 'Order', 'municipal-phonebook' );
			}
		}
		unset( $new['date'] );
		return $new;
	}

	/**
	 * Post list column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post id.
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'munipb_role':
				echo esc_html( (string) get_post_meta( $post_id, '_munipb_role', true ) );
				break;
			case 'munipb_phone':
				echo esc_html( str_replace( "\n", ' / ', munipb_phone_groups_to_string( get_post_meta( $post_id, '_munipb_phones', true ) ) ) );
				break;
			case 'munipb_email':
				echo esc_html( (string) get_post_meta( $post_id, '_munipb_email', true ) );
				break;
			case 'munipb_order':
				echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
				break;
		}
	}

	/**
	 * Department dropdown filter on the list screen.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public static function department_filter( $post_type ) {
		if ( Munipb_Post_Types::POST_TYPE !== $post_type ) {
			return;
		}
		$selected = isset( $_GET[ Munipb_Post_Types::TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ Munipb_Post_Types::TAXONOMY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		wp_dropdown_categories(
			array(
				'taxonomy'        => Munipb_Post_Types::TAXONOMY,
				'name'            => Munipb_Post_Types::TAXONOMY,
				'value_field'     => 'slug',
				'selected'        => $selected,
				'hierarchical'    => true,
				'hide_empty'      => false,
				'show_option_all' => __( 'All departments', 'municipal-phonebook' ),
			)
		);
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Munipb_Post_Types::POST_TYPE,
			__( 'Phonebook settings', 'municipal-phonebook' ),
			__( 'Settings', 'municipal-phonebook' ),
			'manage_options',
			'munipb-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Settings API registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'munipb_settings_group',
			'munipb_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'munipb_sanitize_settings',
				'default'           => munipb_default_settings(),
			)
		);
	}

	/**
	 * Color picker assets, only on this settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_settings( $hook ) {
		if ( 'munipb_entry_page_munipb-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery( function ( $ ) { $( ".munipb-color-picker" ).wpColorPicker(); } );'
		);

		// WordPress 7.1 leaves CSS lint off unless the caller turns it on.
		// Gutters are copied only when lint is already enabled, so pass both.
		$editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/css',
				'codemirror' => array(
					'lint'    => true,
					'gutters' => array( 'CodeMirror-lint-markers', 'CodeMirror-linenumbers' ),
				),
			)
		);
		if ( false === $editor ) {
			return;
		}
		wp_add_inline_script(
			'code-editor',
			'jQuery( function () {
				var textarea = document.getElementById( "munipb-custom-css" );
				if ( ! textarea || ! window.wp || ! wp.codeEditor ) { return; }
				var notice = document.getElementById( "munipb-css-lint-notice" );
				var button = document.getElementById( "submit" );
				var form = textarea.form;
				var blocked = false;
				wp.codeEditor.initialize( textarea, {
					onChangeLintingErrors: function ( errors ) {
						blocked = !!( errors && errors.length );
						if ( notice ) { notice.hidden = ! blocked; }
						if ( button ) { button.disabled = blocked; }
					}
				} );
				if ( form ) {
					form.addEventListener( "submit", function ( event ) {
						if ( blocked ) { event.preventDefault(); }
					} );
				}
			} );'
		);
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'municipal-phonebook' ) );
		}
		$s      = munipb_get_settings();
		$fields = array(
			'enable_rest'      => __( 'REST API', 'municipal-phonebook' ) . '|' . __( 'Enable the public read-only REST endpoint (/wp-json/municipal-phonebook/v1/entries). Off by default.', 'municipal-phonebook' ),
			'track_calls'      => __( 'Analytics', 'municipal-phonebook' ) . '|' . __( 'Send a "click_to_call" event to Google Analytics (gtag) when a phone link is clicked.', 'municipal-phonebook' ),
			'delete_on_remove' => __( 'Uninstall', 'municipal-phonebook' ) . '|' . __( 'Delete all phonebook data (contacts and departments) when the plugin is deleted.', 'municipal-phonebook' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Phonebook settings', 'municipal-phonebook' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'munipb_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<?php
					foreach ( $fields as $key => $text ) :
						list( $title, $label ) = explode( '|', $text, 2 );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $title ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( 'munipb_settings[' . $key . ']' ); ?>" value="1" <?php checked( 1, (int) $s[ $key ] ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="munipb-row-odd"><?php esc_html_e( 'Odd row color', 'municipal-phonebook' ); ?></label></th>
						<td>
							<input type="text" class="munipb-color-picker" id="munipb-row-odd" name="munipb_settings[row_odd]" value="<?php echo esc_attr( (string) $s['row_odd'] ); ?>" data-default-color="#ffffff">
							<p class="description"><?php esc_html_e( 'Background of the 1st, 3rd, 5th… visible contact row in each department.', 'municipal-phonebook' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="munipb-row-even"><?php esc_html_e( 'Even row color', 'municipal-phonebook' ); ?></label></th>
						<td>
							<input type="text" class="munipb-color-picker" id="munipb-row-even" name="munipb_settings[row_even]" value="<?php echo esc_attr( (string) $s['row_even'] ); ?>" data-default-color="#f3f6fc">
							<p class="description"><?php esc_html_e( 'Background of the 2nd, 4th, 6th… visible contact row. Stripes start again after each sub-department row.', 'municipal-phonebook' ); ?></p>
						</td>
					</tr>
					<?php Munipb_Columns_Admin::render_settings( $s ); ?>
				</table>
				<h2><?php esc_html_e( 'Custom CSS', 'municipal-phonebook' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This CSS is added only on pages that show the directory. Invalid CSS is rejected and the previous value is kept.', 'municipal-phonebook' ); ?></p>
				<textarea id="munipb-custom-css" name="munipb_settings[custom_css]" rows="12" cols="60" class="large-text code"><?php echo esc_textarea( (string) $s['custom_css'] ); ?></textarea>
				<div id="munipb-css-lint-notice" class="notice notice-error inline" hidden>
					<p><?php esc_html_e( 'The CSS has errors. Fix them before saving.', 'municipal-phonebook' ); ?></p>
				</div>
				<?php submit_button(); ?>
			</form>
			<h2><?php esc_html_e( 'Usage', 'municipal-phonebook' ); ?></h2>
			<p><?php esc_html_e( 'Shortcode (works in the Elementor "Shortcode" widget and in WoodMart/HTML blocks):', 'municipal-phonebook' ); ?></p>
			<p><code>[municipal_phonebook]</code> &nbsp; <code>[municipal_phonebook search="0" toc="0" departments="slug-1,slug-2" breakpoint="720" row_odd="#ffffff" row_even="#f3f6fc"]</code></p>
			<p><?php esc_html_e( 'Elementor: add the "Municipal Phonebook" widget from the "Municipal Phonebook" category.', 'municipal-phonebook' ); ?></p>
			<p><?php esc_html_e( 'Block editor: add the "Municipal Phonebook" block.', 'municipal-phonebook' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Plugin list action links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'edit.php?post_type=' . Munipb_Post_Types::POST_TYPE . '&page=munipb-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'municipal-phonebook' ) . '</a>' );
		return $links;
	}
}
