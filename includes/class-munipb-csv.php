<?php
/**
 * CSV import / export.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV tools.
 */
class Munipb_CSV {

	const CAP        = 'manage_options';
	const MAX_BYTES  = 2097152; // 2 MB.
	const MAX_ROWS   = 5000;
	const COLUMNS    = array( 'department', 'subdepartment', 'name', 'role', 'phones', 'email', 'order' );
	const NONCE_EXP  = 'munipb_export';
	const NONCE_IMP  = 'munipb_import';
	const NOTICE_KEY = 'munipb_import_notice_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_munipb_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_munipb_import', array( __CLASS__, 'import' ) );
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Munipb_Post_Types::POST_TYPE,
			__( 'Import / Export', 'municipal-phonebook' ),
			__( 'Import / Export', 'municipal-phonebook' ),
			self::CAP,
			'munipb-tools',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Tools page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'municipal-phonebook' ) );
		}
		$notice = get_transient( self::NOTICE_KEY . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( self::NOTICE_KEY . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import / Export', 'municipal-phonebook' ); ?></h1>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Export', 'municipal-phonebook' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="munipb_export">
				<?php wp_nonce_field( self::NONCE_EXP, 'munipb_nonce' ); ?>
				<?php submit_button( __( 'Download CSV', 'municipal-phonebook' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Import', 'municipal-phonebook' ); ?></h2>
			<p>
				<?php esc_html_e( 'UTF-8 CSV, comma or semicolon separated, first row is the header:', 'municipal-phonebook' ); ?>
				<code><?php echo esc_html( implode( ',', self::COLUMNS ) ); ?></code>
			</p>
			<p class="description"><?php esc_html_e( 'Phones: groups separated by ";" and numbers in a group by "|". A row with a sub-department but no name sets that sub-department\'s own phones/email. Missing departments are created.', 'municipal-phonebook' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="munipb_import">
				<?php wp_nonce_field( self::NONCE_IMP, 'munipb_nonce' ); ?>
				<p>
					<label for="munipb_csv"><?php esc_html_e( 'CSV file (max 2 MB)', 'municipal-phonebook' ); ?></label><br>
					<input type="file" id="munipb_csv" name="munipb_csv" accept=".csv,text/csv" required>
				</p>
				<p>
					<label><input type="checkbox" name="munipb_replace" value="1"> <?php esc_html_e( 'Delete all existing contacts before importing', 'municipal-phonebook' ); ?></label>
				</p>
				<?php submit_button( __( 'Import', 'municipal-phonebook' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Protect spreadsheet apps from CSV/formula injection.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return $value;
		}
		$first = $value[0];
		if ( in_array( $first, array( '=', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		if ( in_array( $first, array( '+', '-' ), true ) && ! preg_match( '/^[+\-]?[0-9 ()\-.\/|;]+$/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Stream a CSV export.
	 *
	 * @return void
	 */
	public static function export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'municipal-phonebook' ), 403 );
		}
		check_admin_referer( self::NONCE_EXP, 'munipb_nonce' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=phonebook-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming to php://output.
		fputcsv( $out, self::COLUMNS, ',', '"', '\\' );

		$phones_str = static function ( $groups ) {
			return str_replace( "\n", '; ', munipb_phone_groups_to_string( $groups ) );
		};

		foreach ( munipb_get_directory() as $section ) {
			$order = 0;
			foreach ( $section['entries'] as $e ) {
				fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), array( $section['name'], '', $e['name'], $e['role'], $phones_str( $e['phones'] ), $e['email'], ++$order ) ), ',', '"', '\\' );
			}
			foreach ( $section['children'] as $child ) {
				if ( $child['phones'] || $child['email'] ) {
					fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), array( $section['name'], $child['name'], '', '', $phones_str( $child['phones'] ), $child['email'], '' ) ), ',', '"', '\\' );
				} else {
					fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), array( $section['name'], $child['name'], '', '', '', '', '' ) ), ',', '"', '\\' );
				}
				foreach ( $child['entries'] as $e ) {
					fputcsv( $out, array_map( array( __CLASS__, 'safe_cell' ), array( $section['name'], $child['name'], $e['name'], $e['role'], $phones_str( $e['phones'] ), $e['email'], ++$order ) ), ',', '"', '\\' );
				}
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming to php://output.
		exit;
	}

	/**
	 * Redirect back to the tools page with a notice.
	 *
	 * @param string $type    success|error.
	 * @param string $message Message.
	 * @return void
	 */
	private static function done( $type, $message ) {
		set_transient(
			self::NOTICE_KEY . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Munipb_Post_Types::POST_TYPE . '&page=munipb-tools' ) );
		exit;
	}

	/**
	 * Find or create a department term.
	 *
	 * @param string $name   Name.
	 * @param int    $parent_id Parent id.
	 * @param int    $order  Order for newly created terms.
	 * @return int Term id or 0.
	 */
	private static function term_id( $name, $parent_id, $order ) {
		$existing = get_terms(
			array(
				'taxonomy'   => Munipb_Post_Types::TAXONOMY,
				'name'       => $name,
				'parent'     => $parent_id,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $existing ) && $existing ) {
			return (int) $existing[0];
		}
		$created = wp_insert_term( $name, Munipb_Post_Types::TAXONOMY, array( 'parent' => $parent_id ) );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		update_term_meta( $created['term_id'], 'munipb_order', $order );
		return (int) $created['term_id'];
	}

	/**
	 * Handle a CSV import.
	 *
	 * @return void
	 */
	public static function import() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'municipal-phonebook' ), 403 );
		}
		check_admin_referer( self::NONCE_IMP, 'munipb_nonce' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- $_FILES entries are validated below; the file is never moved or served.
		if ( empty( $_FILES['munipb_csv'] ) || ! isset( $_FILES['munipb_csv']['tmp_name'], $_FILES['munipb_csv']['name'], $_FILES['munipb_csv']['size'], $_FILES['munipb_csv']['error'] ) ) {
			self::done( 'error', __( 'No file uploaded.', 'municipal-phonebook' ) );
		}
		$file = $_FILES['munipb_csv'];
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::done( 'error', __( 'Upload failed.', 'municipal-phonebook' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			self::done( 'error', __( 'File is too large.', 'municipal-phonebook' ) );
		}
		$check = wp_check_filetype( sanitize_file_name( $file['name'] ), array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== $check['ext'] ) {
			self::done( 'error', __( 'Please upload a .csv file.', 'municipal-phonebook' ) );
		}

		$handle = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the uploaded temp file.
		if ( ! $handle ) {
			self::done( 'error', __( 'Could not read the file.', 'municipal-phonebook' ) );
		}

		$first = (string) fgets( $handle );
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delim = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';
		$head  = array_map(
			static function ( $h ) {
				return sanitize_key( trim( $h ) );
			},
			str_getcsv( trim( $first ), $delim, '"', '\\' )
		);
		$map   = array_flip( $head );

		if ( ! isset( $map['department'], $map['name'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			self::done( 'error', __( 'Invalid header row. Required columns: department, name.', 'municipal-phonebook' ) );
		}

		if ( ! empty( $_POST['munipb_replace'] ) ) {
			$old = get_posts(
				array(
					'post_type'      => Munipb_Post_Types::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $old as $old_id ) {
				wp_delete_post( $old_id, true );
			}
		}

		wp_defer_term_counting( true );
		$get      = static function ( $row, $key ) use ( $map ) {
			return ( isset( $map[ $key ], $row[ $map[ $key ] ] ) ) ? trim( (string) $row[ $map[ $key ] ] ) : '';
		};
		$imported = 0;
		$skipped  = 0;
		$line     = 0;
		$term_seq = 0;

		while ( true ) {
			$row = fgetcsv( $handle, 0, $delim, '"', '\\' );
			if ( false === $row ) {
				break;
			}
			++$line;
			if ( $line > self::MAX_ROWS ) {
				break;
			}
			$dept = sanitize_text_field( $get( $row, 'department' ) );
			if ( '' === $dept ) {
				++$skipped;
				continue;
			}
			$dept_id = self::term_id( mb_substr( $dept, 0, 190 ), 0, ++$term_seq );
			$term_id = $dept_id;
			$sub     = sanitize_text_field( $get( $row, 'subdepartment' ) );
			if ( '' !== $sub && $dept_id ) {
				$term_id = self::term_id( mb_substr( $sub, 0, 190 ), $dept_id, ++$term_seq );
			}
			if ( ! $term_id ) {
				++$skipped;
				continue;
			}

			$name   = sanitize_text_field( $get( $row, 'name' ) );
			$phones = munipb_parse_phone_groups( sanitize_textarea_field( $get( $row, 'phones' ) ) );
			$email  = sanitize_email( $get( $row, 'email' ) );
			$email  = is_email( $email ) ? $email : '';

			if ( '' === $name ) {
				// Sub-department info row.
				if ( '' !== $sub ) {
					if ( $phones ) {
						update_term_meta( $term_id, 'munipb_phones', $phones );
					}
					if ( $email ) {
						update_term_meta( $term_id, 'munipb_email', $email );
					}
				}
				continue;
			}

			$order   = $get( $row, 'order' );
			$post_id = wp_insert_post(
				array(
					'post_type'   => Munipb_Post_Types::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => mb_substr( $name, 0, 200 ),
					'menu_order'  => '' !== $order ? absint( $order ) : $line,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				++$skipped;
				continue;
			}
			wp_set_object_terms( $post_id, array( $term_id ), Munipb_Post_Types::TAXONOMY );
			$role = mb_substr( sanitize_text_field( $get( $row, 'role' ) ), 0, 200 );
			if ( '' !== $role ) {
				update_post_meta( $post_id, '_munipb_role', $role );
			}
			if ( $phones ) {
				update_post_meta( $post_id, '_munipb_phones', $phones );
			}
			if ( $email ) {
				update_post_meta( $post_id, '_munipb_email', $email );
			}
			++$imported;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_defer_term_counting( false );
		munipb_flush_cache();

		self::done(
			'success',
			/* translators: 1: imported rows, 2: skipped rows. */
			sprintf( __( 'Import finished: %1$d contacts imported, %2$d rows skipped.', 'municipal-phonebook' ), $imported, $skipped )
		);
	}
}
