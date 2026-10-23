<?php
/**
 * Admin UI for directory columns and inline sub-departments.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings repeater, department column picker and contact fields.
 */
class Munipb_Columns_Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Assets for the settings screen, the department editor and the contact editor.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$settings = 'munipb_entry_page_munipb-settings' === $screen->id;
		$term     = Munipb_Post_Types::TAXONOMY === $screen->taxonomy;
		$entry    = Munipb_Post_Types::POST_TYPE === $screen->post_type && 'post' === $screen->base;
		if ( ! $settings && ! $term && ! $entry ) {
			return;
		}

		wp_enqueue_style( 'munipb-admin', MUNIPB_URL . 'assets/css/admin.css', array(), MUNIPB_VERSION );
		if ( $entry ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'munipb-admin', MUNIPB_URL . 'assets/js/admin.js', array(), MUNIPB_VERSION, true );
		wp_localize_script(
			'munipb-admin',
			'munipbAdmin',
			array(
				'chooseFile'  => __( 'Choose file', 'municipal-phonebook' ),
				'frameTitle'  => __( 'Choose a file', 'municipal-phonebook' ),
				'frameButton' => __( 'Use this file', 'municipal-phonebook' ),
				'noFile'      => __( 'No file selected', 'municipal-phonebook' ),
			)
		);
	}

	/**
	 * Settings rows: inline sub-departments, sub-header color and the column repeater.
	 *
	 * @param array $settings Saved settings.
	 * @return void
	 */
	public static function render_settings( $settings ) {
		$columns = munipb_get_columns();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Child departments', 'municipal-phonebook' ); ?></th>
			<td>
				<input type="hidden" name="munipb_settings[inline_children]" value="0">
				<label>
					<input type="checkbox" name="munipb_settings[inline_children]" value="1" <?php checked( ! empty( $settings['inline_children'] ) ); ?>>
					<?php esc_html_e( 'Show child departments inside the parent table', 'municipal-phonebook' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'A child department becomes a row across the parent table. Turn this off to keep the previous row, with that department\'s own phones and email. Each department can override this.', 'municipal-phonebook' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="munipb-sub-header"><?php esc_html_e( 'Sub-header color', 'municipal-phonebook' ); ?></label></th>
			<td>
				<input type="text" class="munipb-color-picker" id="munipb-sub-header" name="munipb_settings[sub_header]" value="<?php echo esc_attr( (string) $settings['sub_header'] ); ?>" data-default-color="#d3dceb">
				<p class="description"><?php esc_html_e( 'Background of a child department row when child departments are shown inside the parent table.', 'municipal-phonebook' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Columns', 'municipal-phonebook' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'Drag to reorder. Built-in columns can be hidden. A custom column keeps its slug, which CSV import uses.', 'municipal-phonebook' ); ?></p>
				<table class="widefat munipb-columns" id="munipb-columns">
					<thead>
						<tr>
							<th class="munipb-col-move"><?php esc_html_e( 'Order', 'municipal-phonebook' ); ?></th>
							<th><?php esc_html_e( 'Label', 'municipal-phonebook' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'municipal-phonebook' ); ?></th>
							<th><?php esc_html_e( 'Type', 'municipal-phonebook' ); ?></th>
							<th><?php esc_html_e( 'Link text', 'municipal-phonebook' ); ?></th>
							<th><?php esc_html_e( 'Visible', 'municipal-phonebook' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'municipal-phonebook' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $columns as $index => $column ) {
							self::settings_row( $index, $column );
						}
						?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="munipb-add-column"><?php esc_html_e( 'Add column', 'municipal-phonebook' ); ?></button></p>
				<template id="munipb-column-template">
					<?php
					self::settings_row(
						'__i__',
						array(
							'key'       => '',
							'label'     => '',
							'type'      => 'text',
							'visible'   => 1,
							'link_text' => '',
						)
					);
					?>
				</template>
			</td>
		</tr>
		<?php
	}

	/**
	 * One settings repeater row.
	 *
	 * @param int|string $index  Row index.
	 * @param array      $column Column definition.
	 * @return void
	 */
	private static function settings_row( $index, $column ) {
		$key     = isset( $column['key'] ) ? (string) $column['key'] : '';
		$builtin = munipb_column_is_builtin( $key );
		$name    = 'munipb_settings[columns][' . $index . ']';
		?>
		<tr class="munipb-column-row" draggable="true" data-builtin="<?php echo esc_attr( $builtin ? '1' : '0' ); ?>">
			<td class="munipb-col-move">
				<button type="button" class="button-link munipb-drag" aria-label="<?php esc_attr_e( 'Drag to reorder', 'municipal-phonebook' ); ?>">&#9776;</button>
				<button type="button" class="button-link munipb-up" aria-label="<?php esc_attr_e( 'Move up', 'municipal-phonebook' ); ?>">&#8593;</button>
				<button type="button" class="button-link munipb-down" aria-label="<?php esc_attr_e( 'Move down', 'municipal-phonebook' ); ?>">&#8595;</button>
			</td>
			<td><input type="text" class="regular-text munipb-column-label" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( (string) $column['label'] ); ?>" maxlength="80" placeholder="<?php echo esc_attr( $builtin ? munipb_column_label( $column ) : '' ); ?>"></td>
			<td>
				<input type="text" class="munipb-column-key" name="<?php echo esc_attr( $name . '[key]' ); ?>" value="<?php echo esc_attr( $key ); ?>" maxlength="32" pattern="[A-Za-z][A-Za-z0-9_]{0,31}" 
				<?php
				if ( $builtin ) :
					?>
					readonly="readonly"<?php endif; ?>>
			</td>
			<td>
				<?php if ( $builtin ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( self::type_label( $key ) ); ?>
				<?php else : ?>
					<select class="munipb-column-type" name="<?php echo esc_attr( $name . '[type]' ); ?>">
						<?php foreach ( munipb_custom_column_types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type, $column['type'] ); ?>><?php echo esc_html( self::type_label( $type ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
			<td>
				<input type="text" class="munipb-column-link" name="<?php echo esc_attr( $name . '[link_text]' ); ?>" value="<?php echo esc_attr( (string) $column['link_text'] ); ?>" maxlength="40" placeholder="<?php esc_attr_e( 'Download', 'municipal-phonebook' ); ?>" 
				<?php
				if ( $builtin || 'file' !== $column['type'] ) :
					?>
					hidden<?php endif; ?>>
			</td>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $name . '[visible]' ); ?>" value="0">
				<input type="checkbox" name="<?php echo esc_attr( $name . '[visible]' ); ?>" value="1" <?php checked( ! empty( $column['visible'] ) ); ?>>
			</td>
			<td>
				<?php if ( ! $builtin ) : ?>
					<button type="button" class="button-link-delete munipb-remove-column"><?php esc_html_e( 'Remove', 'municipal-phonebook' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Translated name of a column type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	private static function type_label( $type ) {
		switch ( $type ) {
			case 'name':
				return __( 'Full name', 'municipal-phonebook' );
			case 'role':
				return __( 'Role', 'municipal-phonebook' );
			case 'phone':
				return __( 'Phone', 'municipal-phonebook' );
			case 'email':
				return __( 'Email', 'municipal-phonebook' );
			case 'text':
				return __( 'Text', 'municipal-phonebook' );
			case 'url':
				return __( 'Link', 'municipal-phonebook' );
			case 'file':
				return __( 'File', 'municipal-phonebook' );
			default:
				return (string) $type;
		}
	}

	/**
	 * Department fields shared by the add and edit screens.
	 *
	 * @param int $term_id Term id, or 0 when adding.
	 * @return void
	 */
	public static function render_term_fields( $term_id ) {
		$term_id = (int) $term_id;
		$inline  = $term_id ? (string) get_term_meta( $term_id, 'munipb_inline_children', true ) : '';
		$mode    = $term_id ? (string) get_term_meta( $term_id, 'munipb_columns_mode', true ) : '';
		$chosen  = $term_id ? get_term_meta( $term_id, 'munipb_columns', true ) : array();
		if ( 'custom' !== $mode ) {
			$mode = 'inherit';
		}
		if ( ! is_array( $chosen ) ) {
			$chosen = array();
		}
		$catalog = munipb_get_columns();
		$ordered = array();
		$by_key  = array();
		foreach ( $catalog as $column ) {
			$by_key[ $column['key'] ] = $column;
		}
		foreach ( $chosen as $key ) {
			$key = munipb_sanitize_column_key( $key );
			if ( isset( $by_key[ $key ] ) ) {
				$ordered[] = $by_key[ $key ];
				unset( $by_key[ $key ] );
			}
		}
		foreach ( $by_key as $column ) {
			$ordered[] = $column;
		}
		?>
		<div class="munipb-term-columns">
			<p>
				<label for="munipb_inline_children"><?php esc_html_e( 'Child departments', 'municipal-phonebook' ); ?></label><br>
				<select name="munipb_inline_children" id="munipb_inline_children">
					<option value="" <?php selected( $inline, '' ); ?>><?php esc_html_e( 'Use the global setting', 'municipal-phonebook' ); ?></option>
					<option value="1" <?php selected( $inline, '1' ); ?>><?php esc_html_e( 'Inside the parent table', 'municipal-phonebook' ); ?></option>
					<option value="0" <?php selected( $inline, '0' ); ?>><?php esc_html_e( 'Previous sub-department row', 'municipal-phonebook' ); ?></option>
				</select>
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Columns', 'municipal-phonebook' ); ?></legend>
				<p>
					<label><input type="radio" name="munipb_columns_mode" value="inherit" <?php checked( 'inherit', $mode ); ?>> <?php esc_html_e( 'Same as the parent department, or the global columns', 'municipal-phonebook' ); ?></label><br>
					<label><input type="radio" name="munipb_columns_mode" value="custom" <?php checked( 'custom', $mode ); ?>> <?php esc_html_e( 'Choose columns for this department', 'municipal-phonebook' ); ?></label>
				</p>
				<ul class="munipb-column-picker">
					<?php foreach ( $ordered as $column ) : ?>
						<li draggable="true">
							<button type="button" class="button-link munipb-drag" aria-label="<?php esc_attr_e( 'Drag to reorder', 'municipal-phonebook' ); ?>">&#9776;</button>
							<button type="button" class="button-link munipb-up" aria-label="<?php esc_attr_e( 'Move up', 'municipal-phonebook' ); ?>">&#8593;</button>
							<button type="button" class="button-link munipb-down" aria-label="<?php esc_attr_e( 'Move down', 'municipal-phonebook' ); ?>">&#8595;</button>
							<label>
								<input type="checkbox" name="munipb_column_keys[]" value="<?php echo esc_attr( $column['key'] ); ?>" <?php checked( in_array( $column['key'], $chosen, true ) || ( 'inherit' === $mode && ! empty( $column['visible'] ) ) ); ?>>
								<?php echo esc_html( munipb_column_label( $column ) ); ?>
								<code><?php echo esc_html( $column['key'] ); ?></code>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="description"><?php esc_html_e( 'Unchecked columns are hidden. A child department inherits this list unless it has its own.', 'municipal-phonebook' ); ?></p>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Save the department column choice. The caller has already checked the nonce and capability.
	 *
	 * @param int $term_id Term id.
	 * @return void
	 */
	public static function save_term( $term_id ) {
		if ( ! isset( $_POST['munipb_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['munipb_term_nonce'] ) ), Munipb_Admin::NONCE_TERM ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$inline = isset( $_POST['munipb_inline_children'] ) ? sanitize_text_field( wp_unslash( $_POST['munipb_inline_children'] ) ) : '';
		if ( '0' === $inline || '1' === $inline ) {
			update_term_meta( $term_id, 'munipb_inline_children', $inline );
		} else {
			delete_term_meta( $term_id, 'munipb_inline_children' );
		}

		$mode = isset( $_POST['munipb_columns_mode'] ) ? sanitize_key( wp_unslash( $_POST['munipb_columns_mode'] ) ) : 'inherit';
		if ( 'custom' !== $mode ) {
			update_term_meta( $term_id, 'munipb_columns_mode', 'inherit' );
			delete_term_meta( $term_id, 'munipb_columns' );
			return;
		}

		$allowed = array();
		foreach ( munipb_get_columns() as $column ) {
			$allowed[ $column['key'] ] = true;
		}
		$keys = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key is passed through munipb_sanitize_column_key().
		$raw = isset( $_POST['munipb_column_keys'] ) ? wp_unslash( $_POST['munipb_column_keys'] ) : array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $key ) {
				$key = munipb_sanitize_column_key( $key );
				if ( '' !== $key && isset( $allowed[ $key ] ) && ! in_array( $key, $keys, true ) ) {
					$keys[] = $key;
				}
			}
		}
		update_term_meta( $term_id, 'munipb_columns_mode', 'custom' );
		update_term_meta( $term_id, 'munipb_columns', $keys );
	}

	/**
	 * Custom column inputs on the contact screen.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function render_entry_fields( $post_id ) {
		$stored = munipb_read_entry_custom( $post_id );
		$custom = array();
		foreach ( munipb_get_columns() as $column ) {
			if ( ! munipb_column_is_builtin( $column['key'] ) ) {
				$custom[] = $column;
			}
		}
		if ( ! $custom ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Custom columns', 'municipal-phonebook' ) . '</h3>';
		echo '<table class="form-table" role="presentation">';
		foreach ( $custom as $column ) {
			$key   = $column['key'];
			$value = isset( $stored[ $key ] ) ? $stored[ $key ] : '';
			echo '<tr><th scope="row"><label for="' . esc_attr( 'munipb-custom-' . $key ) . '">' . esc_html( munipb_column_label( $column ) ) . '</label></th><td>';
			self::render_entry_input( $column, $value );
			echo '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * One custom field.
	 *
	 * @param array      $column Column definition.
	 * @param string|int $value  Stored value.
	 * @return void
	 */
	private static function render_entry_input( $column, $value ) {
		$key  = $column['key'];
		$id   = 'munipb-custom-' . $key;
		$name = 'munipb_custom[' . $key . ']';
		$type = $column['type'];
		if ( 'file' === $type ) {
			$file       = munipb_attachment_download( $value );
			$seen       = $file['id'] ? $file['id'] : '';
			$name_label = $file['name'] ? $file['name'] : __( 'No file selected', 'municipal-phonebook' );
			echo '<div class="munipb-file-field" data-munipb-file>';
			echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $seen ) . '">';
			echo '<span class="munipb-file-name">' . esc_html( $name_label ) . '</span> ';
			echo '<button type="button" class="button munipb-file-choose">' . esc_html__( 'Choose file', 'municipal-phonebook' ) . '</button> ';
			echo '<button type="button" class="button munipb-file-clear">' . esc_html__( 'Remove file', 'municipal-phonebook' ) . '</button>';
			echo '</div>';
			return;
		}
		$input = 'email' === $type ? 'email' : ( 'url' === $type ? 'url' : 'text' );
		echo '<input type="' . esc_attr( $input ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" maxlength="500">';
	}

	/**
	 * Save custom column values. The caller has already checked the nonce and capability.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function save_entry( $post_id ) {
		if ( ! isset( $_POST['munipb_entry_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['munipb_entry_nonce'] ) ), Munipb_Admin::NONCE_ENTRY ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- munipb_sanitize_entry_custom() sanitizes every value.
		$raw = isset( $_POST['munipb_custom'] ) ? wp_unslash( $_POST['munipb_custom'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$clean = array();
		foreach ( $raw as $key => $value ) {
			$clean[ munipb_sanitize_column_key( $key ) ] = $value;
		}
		$stored = munipb_sanitize_entry_custom( $clean );
		if ( $stored ) {
			update_post_meta( $post_id, '_munipb_custom', $stored );
		} else {
			delete_post_meta( $post_id, '_munipb_custom' );
		}
	}
}
