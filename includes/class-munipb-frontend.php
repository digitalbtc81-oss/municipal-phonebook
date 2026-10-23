<?php
/**
 * Front-end rendering: shortcode, block and assets.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end controller.
 */
class Munipb_Frontend {

	const SHORTCODE   = 'municipal_phonebook';
	const HANDLE      = 'munipb-frontend';
	const BREAKPOINTS = array( 'never', '480', '720', '960', 'always' );

	/**
	 * Instance counter for unique ids when used more than once per page.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Whether the no-JS search reveal has already been printed.
	 *
	 * @var bool
	 */
	private static $noscript_css = false;

	/**
	 * Whether custom CSS was already attached to the front-end stylesheet.
	 *
	 * @var bool
	 */
	private static $custom_css_added = false;

	/**
	 * CSS to print in the footer when the stylesheet tag was already sent.
	 *
	 * @var string
	 */
	private static $late_custom_css = '';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_early' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Register (not enqueue) front-end assets.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( self::HANDLE, MUNIPB_URL . 'assets/css/frontend.css', array(), MUNIPB_VERSION );
		wp_register_script(
			self::HANDLE,
			MUNIPB_URL . 'assets/js/frontend.js',
			array(),
			MUNIPB_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$settings = munipb_get_settings();
		$defaults = munipb_default_settings();
		$odd      = munipb_sanitize_hex( isset( $settings['row_odd'] ) ? $settings['row_odd'] : '' );
		$even     = munipb_sanitize_hex( isset( $settings['row_even'] ) ? $settings['row_even'] : '' );
		wp_add_inline_style(
			self::HANDLE,
			'.munipb-directory{--munipb-row-odd:' . ( '' !== $odd ? $odd : $defaults['row_odd'] ) . ';--munipb-row-even:' . ( '' !== $even ? $even : $defaults['row_even'] ) . ';}'
		);
		$config = array(
			'minChars'   => 2,
			'maxResults' => 10,
			'trackCalls' => (bool) $settings['track_calls'],
			'i18n'       => array(
				'noResults' => __( 'No results found', 'municipal-phonebook' ),
				'oneResult' => __( '1 result. Use the arrow keys to navigate.', 'municipal-phonebook' ),
				/* translators: %d: number of results. */
				'results'   => __( '%d results. Use the arrow keys to navigate.', 'municipal-phonebook' ),
				'category'  => __( 'Department', 'municipal-phonebook' ),
				/* translators: %d: number of selected departments. */
				'selected'  => __( '%d selected', 'municipal-phonebook' ),
			),
		);
		wp_add_inline_script( self::HANDLE, 'window.munipbConfig = ' . wp_json_encode( $config ) . ';', 'before' );

		wp_register_script(
			'munipb-block-editor',
			MUNIPB_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			MUNIPB_VERSION,
			true
		);
		wp_set_script_translations( 'munipb-block-editor', 'municipal-phonebook', MUNIPB_DIR . 'languages' );
	}

	/**
	 * Register the dynamic block (server-side rendered).
	 *
	 * @return void
	 */
	public static function register_block() {
		register_block_type(
			MUNIPB_DIR . 'blocks/directory',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Enqueue in <head> when the current singular post contains the shortcode (avoids late CSS).
	 *
	 * @return void
	 */
	public static function maybe_enqueue_early() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( $post && self::post_has_directory( $post ) ) {
			self::enqueue();
		}
	}

	/**
	 * Whether a post renders the directory.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function post_has_directory( $post ) {
		if ( has_shortcode( $post->post_content, self::SHORTCODE ) || has_block( 'municipal-phonebook/directory', $post ) ) {
			return true;
		}
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		return is_string( $data ) && 1 === preg_match( '/"widgetType"\s*:\s*"municipal-phonebook"/', $data );
	}

	/**
	 * Enqueue front-end assets.
	 *
	 * @return void
	 */
	public static function enqueue() {
		self::add_custom_css();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Attach the saved stylesheet to the front-end handle.
	 *
	 * Called only from enqueue(), which runs when a directory is rendered.
	 * The value is validated again so a tampered option cannot print markup.
	 *
	 * @return void
	 */
	private static function add_custom_css() {
		if ( self::$custom_css_added ) {
			return;
		}
		self::$custom_css_added = true;

		$settings = munipb_get_settings();
		$css      = munipb_prepare_custom_css( isset( $settings['custom_css'] ) ? $settings['custom_css'] : '' );
		if ( '' === $css ) {
			return;
		}

		wp_add_inline_style( self::HANDLE, $css );
		if ( wp_style_is( self::HANDLE, 'done' ) ) {
			self::$late_custom_css = $css;
			add_action( 'wp_footer', array( __CLASS__, 'print_late_custom_css' ), 20 );
		}
	}

	/**
	 * Print custom CSS when the stylesheet tag was already sent in the head.
	 *
	 * @return void
	 */
	public static function print_late_custom_css() {
		$css                   = self::$late_custom_css;
		self::$late_custom_css = '';
		if ( '' === $css ) {
			return;
		}
		echo '<style id="munipb-custom-css">';
		echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- munipb_prepare_custom_css() rejected markup.
		echo "</style>\n";
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		return self::render(
			array(
				'search'      => ! empty( $attributes['showSearch'] ) ? '1' : '0',
				'toc'         => ! empty( $attributes['showToc'] ) ? '1' : '0',
				'departments' => isset( $attributes['departments'] ) ? (string) $attributes['departments'] : '',
				'breakpoint'  => isset( $attributes['breakpoint'] ) ? (string) $attributes['breakpoint'] : '720',
				'row_odd'     => isset( $attributes['rowOdd'] ) ? (string) $attributes['rowOdd'] : '',
				'row_even'    => isset( $attributes['rowEven'] ) ? (string) $attributes['rowEven'] : '',
			)
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'search'      => '1',
				'toc'         => '1',
				'departments' => '',
				'breakpoint'  => '720',
				'row_odd'     => '',
				'row_even'    => '',
			),
			$atts,
			self::SHORTCODE
		);
		return self::render( $atts );
	}

	/**
	 * Interpret a yes/no shortcode value.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on', 'ναι' ), true );
	}

	/**
	 * Validate the card breakpoint.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_breakpoint( $value ) {
		$value = (string) $value;
		return in_array( $value, self::BREAKPOINTS, true ) ? $value : '720';
	}

	/**
	 * Normalize attributes from any source (shortcode, block, Elementor, REST).
	 *
	 * @param array $atts Raw attributes.
	 * @return array{search:bool, toc:bool, slugs:string[], breakpoint:string, row_odd:string, row_even:string}
	 */
	public static function normalize_atts( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return array(
			'search'     => self::truthy( isset( $atts['search'] ) ? $atts['search'] : '1' ),
			'toc'        => self::truthy( isset( $atts['toc'] ) ? $atts['toc'] : '1' ),
			'slugs'      => munipb_parse_department_slugs( isset( $atts['departments'] ) ? $atts['departments'] : '' ),
			'breakpoint' => self::sanitize_breakpoint( isset( $atts['breakpoint'] ) ? $atts['breakpoint'] : '720' ),
			'row_odd'    => munipb_sanitize_hex( isset( $atts['row_odd'] ) ? $atts['row_odd'] : '' ),
			'row_even'   => munipb_sanitize_hex( isset( $atts['row_even'] ) ? $atts['row_even'] : '' ),
		);
	}

	/**
	 * Anchor id for a department.
	 *
	 * @param array $dept Department data.
	 * @param int   $inst Instance number.
	 * @return string
	 */
	public static function anchor( $dept, $inst ) {
		$slug = ( false === strpos( $dept['slug'], '%' ) ) ? sanitize_html_class( $dept['slug'] ) : '';
		$base = 'munipb-' . ( '' !== $slug ? $slug : 'dept-' . (int) $dept['id'] );
		return ( $inst > 1 ) ? $base . '-' . $inst : $base;
	}

	/**
	 * Anchor id for a contact row.
	 *
	 * @param int $entry_id Entry id.
	 * @param int $inst     Instance.
	 * @return string
	 */
	public static function entry_anchor( $entry_id, $inst ) {
		return 'munipb-e' . (int) $entry_id . ( $inst > 1 ? '-' . (int) $inst : '' );
	}

	/**
	 * Does an entry match the (server-side, no-JS fallback) query?
	 *
	 * @param array  $entry   Entry data.
	 * @param array  $words   Normalized query words.
	 * @param string $context Department names for context.
	 * @return bool
	 */
	private static function matches( $entry, $words, $context ) {
		$phones = munipb_phones_text( isset( $entry['phones'] ) ? $entry['phones'] : array() );
		$text   = implode( ' ', array( $entry['name'], isset( $entry['role'] ) ? $entry['role'] : '', $entry['email'], $context, $phones ) );
		return munipb_words_match( $words, $text, preg_replace( '/\D/', '', $phones ) );
	}

	/**
	 * Render the directory.
	 *
	 * @param array $raw_atts Raw attributes (shortcode/block/Elementor).
	 * @return string
	 */
	public static function render( $raw_atts ) {
		self::enqueue();
		++self::$instance;
		$inst = self::$instance;
		$opts = self::normalize_atts( $raw_atts );
		$tree = munipb_get_directory( $opts['slugs'] );

		// Optional no-JS fallback: ?munipb_q=... filters server side. Read-only, public data, so no nonce needed.
		$query = '';
		if ( isset( $_GET['munipb_q'] ) && is_string( $_GET['munipb_q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query = mb_substr( sanitize_text_field( wp_unslash( $_GET['munipb_q'] ) ), 0, 100 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$words = array();
		if ( mb_strlen( $query ) >= 2 ) {
			$words = array_values( array_filter( preg_split( '/\s+/u', munipb_normalize_text( $query ) ) ) );
		}

		// Sections without any contact or sub-department are never rendered, so keep them out of the menu.
		$toc_tree  = array_values(
			array_filter(
				$tree,
				static function ( $section ) {
					return ! empty( $section['entries'] ) || ! empty( $section['children'] );
				}
			)
		);
		$show_cats = $opts['toc'] && count( $toc_tree ) > 0;
		$show_bar  = $opts['search'] || $show_cats;
		$id        = 'munipb-' . $inst;
		$classes   = 'munipb-directory munipb-bp-' . $opts['breakpoint'];
		$row_vars  = array();
		if ( '' !== $opts['row_odd'] ) {
			$row_vars[] = '--munipb-row-odd:' . $opts['row_odd'];
		}
		if ( '' !== $opts['row_even'] ) {
			$row_vars[] = '--munipb-row-even:' . $opts['row_even'];
		}

		ob_start();
		if ( ! self::$noscript_css ) {
			self::$noscript_css = true;
			echo '<noscript><style>.munipb-directory .munipb-search{display:flex !important;flex:1 1 12rem;min-width:0;margin:0}.munipb-directory .munipb-search-toggle{display:none !important}</style></noscript>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static no-JS fallback.
		}
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $id ); ?>" data-munipb-directory
			data-munipb-instance="<?php echo esc_attr( (string) $inst ); ?>"
			<?php if ( $words ) : ?>
				data-munipb-server-query="1"
			<?php endif; ?>
			<?php if ( $row_vars ) : ?>
				style="<?php echo esc_attr( implode( ';', $row_vars ) ); ?>"
			<?php endif; ?>
		>
			<?php if ( $show_bar ) : ?>
				<div class="munipb-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Phonebook tools', 'municipal-phonebook' ); ?>">
					<?php if ( $show_cats ) : ?>
						<div class="munipb-cats">
							<button type="button" class="munipb-tool munipb-pill munipb-cats-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $id . '-cats' ); ?>">
								<span class="munipb-tool-label"><?php esc_html_e( 'Categories', 'municipal-phonebook' ); ?></span>
								<span class="munipb-count" data-munipb-count hidden>
									<span class="munipb-count-num" aria-hidden="true"></span>
									<span class="munipb-sr-only" data-munipb-count-text></span>
								</span>
								<?php echo self::icon_chevron(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							</button>
							<div class="munipb-cats-panel" id="<?php echo esc_attr( $id . '-cats' ); ?>" hidden>
								<ul class="munipb-cats-list" role="group" aria-label="<?php esc_attr_e( 'Filter by department', 'municipal-phonebook' ); ?>">
									<?php
									foreach ( $toc_tree as $section ) {
										echo self::render_cat_item( $id . '-c-' . (int) $section['id'], $section['id'], $section['name'], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
										foreach ( $section['children'] as $child ) {
											echo self::render_cat_item( $id . '-c-' . (int) $child['id'], $child['id'], $child['name'], (int) $child['depth'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
										}
									}
									?>
								</ul>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $opts['search'] ) : ?>
						<form class="munipb-search<?php echo esc_attr( '' !== $query ? ' is-open' : '' ); ?>" id="<?php echo esc_attr( $id . '-search' ); ?>" role="search" method="get" action="<?php echo esc_url( '#' . $id ); ?>">
							<label class="munipb-sr-only" for="<?php echo esc_attr( $id . '-q' ); ?>"><?php esc_html_e( 'Search the phonebook', 'municipal-phonebook' ); ?></label>
							<div class="munipb-search-box">
								<?php echo self::icon_search(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<input type="search" class="munipb-search-input" id="<?php echo esc_attr( $id . '-q' ); ?>" name="munipb_q" value="<?php echo esc_attr( $query ); ?>" maxlength="100"
									placeholder="<?php esc_attr_e( 'Search name, role, department, phone…', 'municipal-phonebook' ); ?>"
									role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="<?php echo esc_attr( $id . '-results' ); ?>" autocomplete="off">
								<button type="button" class="munipb-search-clear" hidden aria-label="<?php esc_attr_e( 'Clear search', 'municipal-phonebook' ); ?>">&times;</button>
								<button type="submit" class="munipb-search-submit"><?php esc_html_e( 'Search', 'municipal-phonebook' ); ?></button>
							</div>
							<div class="munipb-search-results" id="<?php echo esc_attr( $id . '-results' ); ?>" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'municipal-phonebook' ); ?>" hidden></div>
							<div class="munipb-sr-only munipb-search-status" role="status" aria-live="polite"></div>
						</form>
					<?php endif; ?>
					<div class="munipb-toolbar-end">
						<?php if ( $opts['search'] ) : ?>
							<button type="button" class="munipb-tool munipb-pill munipb-search-toggle" aria-expanded="<?php echo esc_attr( '' !== $query ? 'true' : 'false' ); ?>" aria-controls="<?php echo esc_attr( $id . '-search' ); ?>">
								<span class="munipb-tool-label"><?php esc_html_e( 'Search', 'municipal-phonebook' ); ?></span>
								<?php echo self::icon_search(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							</button>
						<?php endif; ?>
						<button type="button" class="munipb-tool munipb-clear">
							<span class="munipb-tool-label"><?php esc_html_e( 'Clear', 'municipal-phonebook' ); ?></span>
							<?php echo self::icon_clear(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $words ) : ?>
				<p class="munipb-filter-note">
					<?php
					/* translators: %s: search terms. */
					printf( esc_html__( 'Showing results for: %s', 'municipal-phonebook' ), '<strong>' . esc_html( $query ) . '</strong>' );
					?>
					<a href="<?php echo esc_url( remove_query_arg( 'munipb_q' ) . '#' . $id ); ?>"><?php esc_html_e( 'Show all', 'municipal-phonebook' ); ?></a>
				</p>
			<?php endif; ?>

			<?php echo self::render_all( $tree, $inst, $words ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
			<p class="munipb-none" data-munipb-none hidden><?php esc_html_e( 'No results found', 'municipal-phonebook' ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Chevron shown on the categories button. Static markup.
	 *
	 * @return string
	 */
	private static function icon_chevron() {
		return '<svg class="munipb-tool-icon munipb-chevron" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"></path></svg>';
	}

	/**
	 * Magnifier. Static markup. The search field and the search button share it.
	 *
	 * @return string
	 */
	private static function icon_search() {
		return '<svg class="munipb-tool-icon munipb-search-icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"></circle><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 21l-4.35-4.35"></path></svg>';
	}

	/**
	 * Reset icon for the clear control. Static markup.
	 *
	 * @return string
	 */
	private static function icon_clear() {
		return '<svg class="munipb-tool-icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-2.64-6.36"></path><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 3v6h-6"></path></svg>';
	}

	/**
	 * One department checkbox in the categories menu.
	 *
	 * @param string $input_id Input id.
	 * @param int    $dept_id  Term id.
	 * @param string $name     Label.
	 * @param int    $depth    Indent depth (0 = top-level department).
	 * @return string Escaped HTML.
	 */
	private static function render_cat_item( $input_id, $dept_id, $name, $depth ) {
		$dept_id = (int) $dept_id;
		$out     = '<li class="munipb-cats-item" style="' . esc_attr( '--munipb-depth:' . (int) $depth ) . '">';
		$out    .= '<label class="munipb-cats-label" for="' . esc_attr( $input_id ) . '">';
		$out    .= '<input type="checkbox" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( (string) $dept_id ) . '" data-munipb-filter="' . esc_attr( (string) $dept_id ) . '">';
		$out    .= '<span class="munipb-cats-name">' . esc_html( $name ) . '</span>';
		$out    .= '</label></li>';
		return $out;
	}

	/**
	 * Render every section, optionally filtered by search words.
	 *
	 * @param array $tree  Tree.
	 * @param int   $inst  Instance.
	 * @param array $words Normalized words.
	 * @return string Escaped HTML.
	 */
	private static function render_all( $tree, $inst, $words ) {
		$out = '';
		foreach ( $tree as $section ) {
			$rows = '';
			foreach ( $section['entries'] as $entry ) {
				if ( $words && ! self::matches( $entry, $words, $section['name'] ) ) {
					continue;
				}
				$rows .= self::render_entry_row( $entry, $inst, $section['id'] );
			}
			foreach ( $section['children'] as $child ) {
				$child_rows = '';
				foreach ( $child['entries'] as $entry ) {
					if ( $words && ! self::matches( $entry, $words, $section['name'] . ' ' . $child['name'] ) ) {
						continue;
					}
					$child_rows .= self::render_entry_row( $entry, $inst, $child['id'] );
				}
				$child_matches = $words ? self::matches( $child, $words, $section['name'] ) : true;
				if ( '' === $child_rows && ! $child_matches ) {
					continue;
				}
				$rows .= self::render_subdept_row( $child, $inst ) . $child_rows;
			}
			if ( '' !== $rows ) {
				$out .= self::wrap_section( $section, $inst, $rows );
			}
		}
		if ( '' === $out ) {
			$out = '<p class="munipb-empty">' . esc_html__( 'No contacts found.', 'municipal-phonebook' ) . '</p>';
		}
		return '<div class="munipb-content">' . $out . '</div>';
	}

	/**
	 * Wrap rows into a section with heading and table.
	 *
	 * @param array  $section   Section.
	 * @param int    $inst      Instance.
	 * @param string $rows      Escaped rows HTML.
	 * @return string
	 */
	private static function wrap_section( $section, $inst, $rows ) {
		$anchor     = self::anchor( $section, $inst );
		$heading_id = $anchor . '-title';
		$out        = '<section class="munipb-section" id="' . esc_attr( $anchor ) . '" data-munipb-section="' . esc_attr( (string) (int) $section['id'] ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$out       .= '<h2 class="munipb-section-title" id="' . esc_attr( $heading_id ) . '">' . esc_html( $section['name'] ) . '</h2>';
		$out       .= '<table class="munipb-table"><thead><tr>';
		$out       .= '<th scope="col" class="munipb-col-name">' . esc_html__( 'Full name', 'municipal-phonebook' ) . '</th>';
		$out       .= '<th scope="col" class="munipb-col-role">' . esc_html__( 'Role', 'municipal-phonebook' ) . '</th>';
		$out       .= '<th scope="col" class="munipb-col-phone">' . esc_html__( 'Phone', 'municipal-phonebook' ) . '</th>';
		$out       .= '<th scope="col" class="munipb-col-email">' . esc_html__( 'Email', 'municipal-phonebook' ) . '</th>';
		$out       .= '</tr></thead><tbody>' . $rows . '</tbody></table></section>';
		return $out;
	}

	/**
	 * Render a sub-department header row.
	 *
	 * @param array $child Sub-department data.
	 * @param int   $inst  Instance.
	 * @return string
	 */
	private static function render_subdept_row( $child, $inst ) {
		$out  = '<tr class="munipb-subdept-row munipb-depth-' . (int) $child['depth'] . '" id="' . esc_attr( self::anchor( $child, $inst ) ) . '" data-munipb-subdept data-munipb-dept="' . esc_attr( (string) (int) $child['id'] ) . '">';
		$out .= '<th scope="rowgroup" colspan="2" class="munipb-subdept-name">' . esc_html( $child['name'] ) . '</th>';
		$out .= '<td class="munipb-col-phone" data-label="' . esc_attr__( 'Phone', 'municipal-phonebook' ) . '">' . self::render_phones( $child['phones'] ) . '</td>';
		$out .= '<td class="munipb-col-email" data-label="' . esc_attr__( 'Email', 'municipal-phonebook' ) . '">' . self::render_email( $child['email'] ) . '</td>';
		$out .= '</tr>';
		return $out;
	}

	/**
	 * Render a contact row.
	 *
	 * @param array $entry   Entry data.
	 * @param int   $inst    Instance.
	 * @param int   $dept_id Department the row belongs to (section id or sub-department id).
	 * @return string
	 */
	private static function render_entry_row( $entry, $inst, $dept_id ) {
		$out  = '<tr class="munipb-entry-row" id="' . esc_attr( self::entry_anchor( $entry['id'], $inst ) ) . '" data-munipb-entry data-munipb-dept="' . esc_attr( (string) (int) $dept_id ) . '">';
		$out .= '<td class="munipb-col-name">' . esc_html( $entry['name'] ) . '</td>';
		$out .= '<td class="munipb-col-role" data-label="' . esc_attr__( 'Role', 'municipal-phonebook' ) . '">' . esc_html( $entry['role'] ) . '</td>';
		$out .= '<td class="munipb-col-phone" data-label="' . esc_attr__( 'Phone', 'municipal-phonebook' ) . '">' . self::render_phones( $entry['phones'] ) . '</td>';
		$out .= '<td class="munipb-col-email" data-label="' . esc_attr__( 'Email', 'municipal-phonebook' ) . '">' . self::render_email( $entry['email'] ) . '</td>';
		$out .= '</tr>';
		return $out;
	}

	/**
	 * Render phone groups as tel: links.
	 *
	 * @param array $groups Phone groups.
	 * @return string
	 */
	private static function render_phones( $groups ) {
		$lines = array();
		foreach ( (array) $groups as $group ) {
			$links = array();
			foreach ( (array) $group as $phone ) {
				$links[] = '<a class="munipb-phone" href="' . esc_url( munipb_phone_to_tel( $phone ), array( 'tel' ) ) . '">' . esc_html( $phone ) . '</a>';
			}
			$lines[] = '<span class="munipb-phone-group">' . implode( '<span class="munipb-sep" aria-hidden="true"> | </span>', $links ) . '</span>';
		}
		return implode( '', $lines );
	}

	/**
	 * Render an email link.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function render_email( $email ) {
		if ( ! is_email( $email ) ) {
			return '';
		}
		return '<a class="munipb-email" href="' . esc_attr( 'mailto:' . antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>';
	}
}
