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
		$config   = array(
			'minChars'   => 2,
			'maxResults' => 10,
			'trackCalls' => (bool) $settings['track_calls'],
			'i18n'       => array(
				'noResults' => __( 'No results found', 'municipal-phonebook' ),
				'oneResult' => __( '1 result. Use the arrow keys to navigate.', 'municipal-phonebook' ),
				/* translators: %d: number of results. */
				'results'   => __( '%d results. Use the arrow keys to navigate.', 'municipal-phonebook' ),
				'category'  => __( 'Department', 'municipal-phonebook' ),
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
		if ( $post && ( has_shortcode( $post->post_content, self::SHORTCODE ) || has_block( 'municipal-phonebook/directory', $post ) ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueue front-end assets.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
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
	 * @return array{search:bool, toc:bool, slugs:string[], breakpoint:string}
	 */
	public static function normalize_atts( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return array(
			'search'     => self::truthy( isset( $atts['search'] ) ? $atts['search'] : '1' ),
			'toc'        => self::truthy( isset( $atts['toc'] ) ? $atts['toc'] : '1' ),
			'slugs'      => munipb_parse_department_slugs( isset( $atts['departments'] ) ? $atts['departments'] : '' ),
			'breakpoint' => self::sanitize_breakpoint( isset( $atts['breakpoint'] ) ? $atts['breakpoint'] : '720' ),
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

		// Sections without any contact or sub-department are never rendered, so keep them out of the contents.
		$toc_tree = array_values(
			array_filter(
				$tree,
				static function ( $section ) {
					return ! empty( $section['entries'] ) || ! empty( $section['children'] );
				}
			)
		);
		$show_toc = $opts['toc'] && empty( $words ) && count( $toc_tree ) > 1;
		$id       = 'munipb-' . $inst;
		$classes  = 'munipb-directory munipb-bp-' . $opts['breakpoint'];

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $id ); ?>" data-munipb-directory
			data-munipb-instance="<?php echo esc_attr( (string) $inst ); ?>"
		>
			<?php if ( $opts['search'] ) : ?>
				<form class="munipb-search" role="search" method="get" action="<?php echo esc_url( '#' . $id ); ?>">
					<label class="munipb-sr-only" for="<?php echo esc_attr( $id . '-q' ); ?>"><?php esc_html_e( 'Search the phonebook', 'municipal-phonebook' ); ?></label>
					<div class="munipb-search-box">
						<svg class="munipb-search-icon" aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
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

			<?php if ( $words ) : ?>
				<p class="munipb-filter-note">
					<?php
					/* translators: %s: search terms. */
					printf( esc_html__( 'Showing results for: %s', 'municipal-phonebook' ), '<strong>' . esc_html( $query ) . '</strong>' );
					?>
					<a href="<?php echo esc_url( remove_query_arg( 'munipb_q' ) . '#' . $id ); ?>"><?php esc_html_e( 'Show all', 'municipal-phonebook' ); ?></a>
				</p>
			<?php endif; ?>

			<?php if ( $show_toc ) : ?>
				<nav class="munipb-toc" aria-label="<?php esc_attr_e( 'Contents', 'municipal-phonebook' ); ?>">
					<button type="button" class="munipb-toc-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $id . '-toc' ); ?>">
						<?php esc_html_e( 'Contents', 'municipal-phonebook' ); ?>
						<span class="munipb-toc-arrow" aria-hidden="true">&#9662;</span>
					</button>
					<ul class="munipb-toc-list" id="<?php echo esc_attr( $id . '-toc' ); ?>">
						<?php
						foreach ( $toc_tree as $section ) :
							$anchor = self::anchor( $section, $inst );
							?>
							<li><a href="<?php echo esc_url( '#' . $anchor ); ?>" data-munipb-target="<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $section['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<?php echo self::render_all( $tree, $inst, $words ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
		</div>
		<?php
		return (string) ob_get_clean();
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
				$rows .= self::render_entry_row( $entry, $inst );
			}
			foreach ( $section['children'] as $child ) {
				$child_rows = '';
				foreach ( $child['entries'] as $entry ) {
					if ( $words && ! self::matches( $entry, $words, $section['name'] . ' ' . $child['name'] ) ) {
						continue;
					}
					$child_rows .= self::render_entry_row( $entry, $inst );
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
		$out        = '<section class="munipb-section" id="' . esc_attr( $anchor ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
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
		$out  = '<tr class="munipb-subdept-row munipb-depth-' . (int) $child['depth'] . '" id="' . esc_attr( self::anchor( $child, $inst ) ) . '" data-munipb-subdept>';
		$out .= '<th scope="rowgroup" colspan="2" class="munipb-subdept-name">' . esc_html( $child['name'] ) . '</th>';
		$out .= '<td class="munipb-col-phone" data-label="' . esc_attr__( 'Phone', 'municipal-phonebook' ) . '">' . self::render_phones( $child['phones'] ) . '</td>';
		$out .= '<td class="munipb-col-email" data-label="' . esc_attr__( 'Email', 'municipal-phonebook' ) . '">' . self::render_email( $child['email'] ) . '</td>';
		$out .= '</tr>';
		return $out;
	}

	/**
	 * Render a contact row.
	 *
	 * @param array $entry Entry data.
	 * @param int   $inst  Instance.
	 * @return string
	 */
	private static function render_entry_row( $entry, $inst ) {
		$out  = '<tr class="munipb-entry-row" id="' . esc_attr( self::entry_anchor( $entry['id'], $inst ) ) . '" data-munipb-entry>';
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
