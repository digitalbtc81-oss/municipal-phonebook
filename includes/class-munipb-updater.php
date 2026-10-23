<?php
/**
 * GitHub release updates.
 *
 * Talks only to the public GitHub API over HTTPS, caches the result, and
 * accepts a package URL only when it is the municipal-phonebook.zip asset of
 * this repository's release. WordPress downloads that URL (GitHub may then
 * redirect the file to its release storage). The extracted folder is forced
 * back to municipal-phonebook so an update cannot rename the plugin directory.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Update checks against GitHub Releases.
 */
class Munipb_Updater {

	const API_URL      = 'https://api.github.com/repos/digitalbtc81-oss/municipal-phonebook/releases/latest';
	const REPO_URL     = 'https://github.com/digitalbtc81-oss/municipal-phonebook';
	const SLUG         = 'municipal-phonebook';
	const PACKAGE_NAME = 'municipal-phonebook.zip';
	const TRANSIENT    = 'munipb_github_release';
	const TESTED_WP    = '7.1';
	const SUCCESS_TTL  = 3 * HOUR_IN_SECONDS;
	const FAILURE_TTL  = 1 * HOUR_IN_SECONDS;
	const NOTICE_KEY   = 'munipb_update_notice_';
	const CHECK_ACTION = 'munipb_check_updates';
	const BODY_LIMIT   = 500000;
	const NOTES_LIMIT  = 20000;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Core hook name is the Update URI host. It cannot carry the munipb prefix.
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHookname
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'select_source' ), 10, 4 );

		// Before wp_update_plugins() on this screen (priority 10), so a fresh lookup is used.
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_forget_on_force_check' ), 1 );
		add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'forget' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_post_' . self::CHECK_ACTION, array( __CLASS__, 'handle_check_updates' ) );
		add_action( 'admin_notices', array( __CLASS__, 'check_updates_notice' ) );
	}

	/**
	 * Forget the cached release lookup.
	 *
	 * @return void
	 */
	public static function forget() {
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * Drop the cached lookup when Dashboard → Updates → Check again is opened.
	 *
	 * Core's force-check link has no nonce. The handler only deletes caches, and
	 * only for someone who can update plugins. The plugin-update transient is
	 * cleared too: on this screen WordPress otherwise skips a new plugin check
	 * for one minute, which would keep hiding a release we just uncached.
	 *
	 * @return void
	 */
	public static function maybe_forget_on_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core force-check has no nonce; capability is checked and the action only clears caches.
		if ( empty( $_GET['force-check'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		self::forget();
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Plugins-row link to check GitHub immediately.
	 *
	 * @param string[] $links Existing meta links.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( MUNIPB_FILE ) !== $file || ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}
		$url     = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CHECK_ACTION ), self::CHECK_ACTION );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'municipal-phonebook' ) . '</a>';
		return $links;
	}

	/**
	 * Clear caches, look up the latest release, and return to the Plugins screen.
	 *
	 * @return void
	 */
	public static function handle_check_updates() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'municipal-phonebook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::CHECK_ACTION );

		self::forget();
		delete_site_transient( 'update_plugins' );

		$release = self::refresh_release();
		$state   = 'error';
		$version = '';
		if ( is_array( $release ) && preg_match( '/^\d+\.\d+\.\d+$/', $release['version'] ) ) {
			if ( version_compare( $release['version'], MUNIPB_VERSION, '>' ) ) {
				$state   = 'available';
				$version = $release['version'];
			} else {
				$state = 'current';
			}
		}

		set_transient(
			self::NOTICE_KEY . get_current_user_id(),
			array(
				'state'   => $state,
				'version' => $version,
			),
			MINUTE_IN_SECONDS
		);

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		wp_update_plugins();

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * One-time result of the plugins-row update check.
	 *
	 * @return void
	 */
	public static function check_updates_notice() {
		if ( ! current_user_can( 'update_plugins' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$key    = self::NOTICE_KEY . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['state'] ) ) {
			return;
		}
		delete_transient( $key );

		$state = (string) $notice['state'];
		$class = 'notice-info';
		if ( 'available' === $state ) {
			$version = isset( $notice['version'] ) ? (string) $notice['version'] : '';
			if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
				return;
			}
			$message = sprintf(
				/* translators: %s: version number, such as 1.4.2. */
				__( 'Version %s is available.', 'municipal-phonebook' ),
				$version
			);
			$class = 'notice-warning';
		} elseif ( 'current' === $state ) {
			$message = __( 'You are up to date.', 'municipal-phonebook' );
			$class   = 'notice-success';
		} else {
			$message = __( 'Could not check for updates. Please try again later.', 'municipal-phonebook' );
			$class   = 'notice-error';
		}

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Supply update data for this plugin when a newer GitHub release exists.
	 *
	 * @param array|false|object $update      Existing update data.
	 * @param array              $plugin_data Plugin headers.
	 * @param string             $plugin_file Plugin basename.
	 * @param string[]           $locales     Installed locales. Unused; required by the hook.
	 * @return array|false|object
	 */
	public static function filter_update( $update, $plugin_data, $plugin_file, $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $locales );

		if ( plugin_basename( MUNIPB_FILE ) !== $plugin_file ) {
			return $update;
		}

		$update_uri = isset( $plugin_data['UpdateURI'] ) ? untrailingslashit( (string) $plugin_data['UpdateURI'] ) : '';
		if ( self::REPO_URL !== $update_uri ) {
			return $update;
		}

		$release = self::get_release();
		if ( ! is_array( $release ) ) {
			return $update;
		}

		$current = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : MUNIPB_VERSION;
		if ( version_compare( $release['version'], $current, '<=' ) ) {
			return $update;
		}

		return array(
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => self::REPO_URL,
			'package'      => $release['package'],
			'tested'       => self::TESTED_WP,
			'requires'     => self::version_header( isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '', '6.2' ),
			'requires_php' => self::version_header( isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '', '7.4' ),
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
		);
	}

	/**
	 * "View details" popup: description plus release notes.
	 *
	 * @param false|object|array $result Response so far.
	 * @param string             $action Requested information type.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data       = get_plugin_data( MUNIPB_FILE, false, false );
		$update_uri = isset( $data['UpdateURI'] ) ? untrailingslashit( (string) $data['UpdateURI'] ) : '';
		if ( self::REPO_URL !== $update_uri ) {
			return $result;
		}

		$release = self::get_release();
		if ( ! is_array( $release ) ) {
			return $result;
		}

		$name        = isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : 'Municipal Phonebook';
		$author      = isset( $data['Author'] ) ? wp_strip_all_tags( (string) $data['Author'] ) : '';
		$description = isset( $data['Description'] ) ? wp_strip_all_tags( (string) $data['Description'] ) : '';
		$release_url = self::release_url( $release['version'] );

		$info                = new stdClass();
		$info->name          = $name;
		$info->slug          = self::SLUG;
		$info->version       = $release['version'];
		$info->author        = $author;
		$info->homepage      = esc_url_raw( self::REPO_URL );
		$info->requires      = self::version_header( isset( $data['RequiresWP'] ) ? $data['RequiresWP'] : '', '6.2' );
		$info->tested        = self::TESTED_WP;
		$info->requires_php  = self::version_header( isset( $data['RequiresPHP'] ) ? $data['RequiresPHP'] : '', '7.4' );
		$info->download_link = esc_url_raw( $release['package'] );
		$info->sections      = array(
			'description' => '<p>' . esc_html( $name ) . '</p><p>' . esc_html( $description ) . '</p>',
			'changelog'   => self::notes_to_html( $release['notes'] ) . '<p><a href="' . esc_url( $release_url ) . '">' . esc_html__( 'View the release on GitHub.', 'municipal-phonebook' ) . '</a></p>',
		);
		$info->banners       = array();
		$info->icons         = array();

		if ( '' !== $release['published'] ) {
			$timestamp = strtotime( $release['published'] );
			if ( false !== $timestamp ) {
				$info->last_updated = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		return $info;
	}

	/**
	 * Install the update into wp-content/plugins/municipal-phonebook.
	 *
	 * WordPress names the destination after the only directory inside the zip.
	 * Renaming that directory keeps the folder stable even if the archive root differs.
	 *
	 * @param string      $source        Selected source directory.
	 * @param string      $remote_source Remote source directory (zip root).
	 * @param WP_Upgrader $upgrader      Upgrader instance. Unused; required by the hook.
	 * @param array       $hook_extra    Extra arguments from the upgrader.
	 * @return string|WP_Error
	 */
	public static function select_source( $source, $remote_source, $upgrader, $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $upgrader );

		if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) ) {
			return $source;
		}
		if ( plugin_basename( MUNIPB_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! is_string( $source ) || '' === $source || ! is_string( $remote_source ) || '' === $remote_source || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return new WP_Error(
				'munipb_invalid_package',
				__( 'The update package does not contain Municipal Phonebook.', 'municipal-phonebook' )
			);
		}

		$base = trailingslashit( $remote_source );
		$from = trailingslashit( $source );
		$to   = $base . self::SLUG . '/';

		$base_norm = trailingslashit( wp_normalize_path( $base ) );
		$to_norm   = trailingslashit( wp_normalize_path( $to ) );
		if ( 0 !== strpos( $to_norm, $base_norm ) ) {
			return new WP_Error(
				'munipb_invalid_package',
				__( 'The update package does not contain Municipal Phonebook.', 'municipal-phonebook' )
			);
		}

		$plugin_file = $from . 'municipal-phonebook.php';
		$size        = $wp_filesystem->size( $plugin_file );
		$header      = ( false === $size || $size > 200000 ) ? '' : $wp_filesystem->get_contents( $plugin_file );
		if ( ! is_string( $header ) || ! preg_match( '/Plugin Name:\s*Municipal Phonebook/', $header ) || ! preg_match( '/Text Domain:\s*municipal-phonebook/', $header ) ) {
			return new WP_Error(
				'munipb_invalid_package',
				__( 'The update package does not contain Municipal Phonebook.', 'municipal-phonebook' )
			);
		}

		if ( trailingslashit( wp_normalize_path( $from ) ) === $to_norm ) {
			return $from;
		}

		$wp_filesystem->delete( $to, true );
		if ( ! $wp_filesystem->move( untrailingslashit( $from ), untrailingslashit( $to ), true ) ) {
			return new WP_Error(
				'munipb_folder_rename_failed',
				__( 'The update package could not be installed into the municipal-phonebook folder.', 'municipal-phonebook' )
			);
		}

		return trailingslashit( $to );
	}

	/**
	 * Validate a decoded GitHub release and return the fields we are willing to use.
	 *
	 * @param mixed $payload Decoded JSON.
	 * @return array<string, string>|null
	 */
	public static function parse_release( $payload ) {
		if ( ! is_array( $payload ) ) {
			return null;
		}
		if ( ! empty( $payload['draft'] ) || ! empty( $payload['prerelease'] ) ) {
			return null;
		}

		$api_url = isset( $payload['url'] ) ? (string) $payload['url'] : '';
		if ( ! preg_match( '#^https://api\.github\.com/repos/digitalbtc81-oss/municipal-phonebook/releases/\d+$#', $api_url ) ) {
			return null;
		}

		$tag = isset( $payload['tag_name'] ) ? (string) $payload['tag_name'] : '';
		if ( ! preg_match( '/^v(\d+\.\d+\.\d+)$/', $tag, $matches ) ) {
			return null;
		}
		$version = $matches[1];

		if ( empty( $payload['assets'] ) || ! is_array( $payload['assets'] ) ) {
			return null;
		}

		$package = '';
		$seen    = 0;
		foreach ( $payload['assets'] as $asset ) {
			++$seen;
			if ( $seen > 50 ) {
				break;
			}
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			if ( self::PACKAGE_NAME !== $name ) {
				continue;
			}
			$download = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( ! self::is_trusted_package_url( $download, $tag ) ) {
				return null;
			}
			$package = self::canonical_package_url( $tag );
			break;
		}

		if ( '' === $package ) {
			return null;
		}

		$notes = isset( $payload['body'] ) && is_string( $payload['body'] ) ? $payload['body'] : '';
		$notes = str_replace( "\0", '', $notes );
		$notes = wp_strip_all_tags( $notes );
		$notes = str_replace( array( "\r\n", "\r" ), "\n", $notes );
		if ( mb_strlen( $notes ) > self::NOTES_LIMIT ) {
			$notes = mb_substr( $notes, 0, self::NOTES_LIMIT );
		}
		$notes = trim( $notes );

		$published = isset( $payload['published_at'] ) ? (string) $payload['published_at'] : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $published ) ) {
			$published = '';
		}

		return array(
			'version'   => $version,
			'package'   => $package,
			'notes'     => $notes,
			'published' => $published,
		);
	}

	/**
	 * Whether a URL is the zip asset for a tag of this repository.
	 *
	 * @param mixed  $url Download URL from the API.
	 * @param string $tag Release tag, including the leading "v".
	 * @return bool
	 */
	public static function is_trusted_package_url( $url, $tag ) {
		if ( ! is_string( $url ) || ! is_string( $tag ) || ! preg_match( '/^v\d+\.\d+\.\d+$/', $tag ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		if ( 'https' !== $scheme || 'github.com' !== $host ) {
			return false;
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		$expected = wp_parse_url( self::canonical_package_url( $tag ) );
		if ( ! is_array( $expected ) || empty( $expected['path'] ) || $path !== $expected['path'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Cached release, or null when there is nothing usable.
	 *
	 * Failures are cached too so a rate limit does not trigger a request on every admin page.
	 *
	 * @return array<string, string>|null
	 */
	private static function get_release() {
		$cached = get_site_transient( self::TRANSIENT );
		if ( is_array( $cached ) && empty( $cached['ok'] ) ) {
			return null;
		}

		$parsed = self::release_from_cache( $cached );
		if ( is_array( $parsed ) ) {
			return $parsed;
		}

		return self::refresh_release();
	}

	/**
	 * Fetch the latest release and cache success or failure.
	 *
	 * @return array<string, string>|null
	 */
	private static function refresh_release() {
		$parsed = self::request_release();
		if ( ! is_array( $parsed ) ) {
			set_site_transient(
				self::TRANSIENT,
				array(
					'ok' => false,
				),
				self::FAILURE_TTL
			);
			return null;
		}

		set_site_transient(
			self::TRANSIENT,
			array(
				'ok'        => true,
				'version'   => $parsed['version'],
				'package'   => $parsed['package'],
				'notes'     => $parsed['notes'],
				'published' => $parsed['published'],
			),
			self::SUCCESS_TTL
		);

		return $parsed;
	}

	/**
	 * Read a previously cached successful lookup, rejecting anything unexpected.
	 *
	 * @param mixed $cached Transient value.
	 * @return array<string, string>|null
	 */
	private static function release_from_cache( $cached ) {
		if ( ! is_array( $cached ) || empty( $cached['ok'] ) ) {
			return null;
		}

		$version   = isset( $cached['version'] ) ? (string) $cached['version'] : '';
		$package   = isset( $cached['package'] ) ? (string) $cached['package'] : '';
		$notes     = isset( $cached['notes'] ) ? (string) $cached['notes'] : '';
		$published = isset( $cached['published'] ) ? (string) $cached['published'] : '';

		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) || ! self::is_trusted_package_url( $package, 'v' . $version ) ) {
			return null;
		}
		if ( mb_strlen( $notes ) > self::NOTES_LIMIT ) {
			$notes = mb_substr( $notes, 0, self::NOTES_LIMIT );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $published ) ) {
			$published = '';
		}

		return array(
			'version'   => $version,
			'package'   => self::canonical_package_url( 'v' . $version ),
			'notes'     => $notes,
			'published' => $published,
		);
	}

	/**
	 * Request the latest release. Network and API errors return null.
	 *
	 * @return array<string, string>|null
	 */
	private static function request_release() {
		$url = self::api_url();
		if ( '' === $url ) {
			return null;
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'           => 'MunicipalPhonebook/' . MUNIPB_VERSION . '; ' . self::REPO_URL,
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body || strlen( $body ) > self::BODY_LIMIT ) {
			return null;
		}

		$decoded = json_decode( $body, true );
		return self::parse_release( $decoded );
	}

	/**
	 * Canonical API URL. Returned only when the constant still points at this repo.
	 *
	 * @return string
	 */
	private static function api_url() {
		$parts = wp_parse_url( self::API_URL );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		if ( 'https' !== $scheme || 'api.github.com' !== $host ) {
			return '';
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		if ( '/repos/digitalbtc81-oss/municipal-phonebook/releases/latest' !== $path ) {
			return '';
		}

		return 'https://api.github.com/repos/digitalbtc81-oss/municipal-phonebook/releases/latest';
	}

	/**
	 * Package URL we are willing to hand to WordPress.
	 *
	 * @param string $tag Release tag, including the leading "v".
	 * @return string
	 */
	private static function canonical_package_url( $tag ) {
		return 'https://github.com/digitalbtc81-oss/municipal-phonebook/releases/download/' . $tag . '/' . self::PACKAGE_NAME;
	}

	/**
	 * Human page for one release.
	 *
	 * @param string $version Version without a leading "v".
	 * @return string
	 */
	private static function release_url( $version ) {
		return self::REPO_URL . '/releases/tag/v' . $version;
	}

	/**
	 * Keep a numeric header value, otherwise use the fallback.
	 *
	 * @param mixed  $value    Header value.
	 * @param string $fallback Fallback version.
	 * @return string
	 */
	private static function version_header( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^\d+(\.\d+){0,2}$/', $value ) ) {
			return $value;
		}
		return $fallback;
	}

	/**
	 * Turn plain release notes into escaped HTML.
	 *
	 * @param string $notes Sanitized notes.
	 * @return string
	 */
	private static function notes_to_html( $notes ) {
		$html      = '';
		$list_open = false;

		foreach ( explode( "\n", (string) $notes ) as $line ) {
			$trim = trim( $line );
			if ( preg_match( '/^#{1,6}\s+(.+)$/', $trim, $matches ) ) {
				if ( $list_open ) {
					$html     .= '</ul>';
					$list_open = false;
				}
				$html .= '<h4>' . esc_html( $matches[1] ) . '</h4>';
				continue;
			}
			if ( preg_match( '/^[-*]\s+(.+)$/', $trim, $matches ) ) {
				if ( ! $list_open ) {
					$html     .= '<ul>';
					$list_open = true;
				}
				$html .= '<li>' . esc_html( $matches[1] ) . '</li>';
				continue;
			}
			if ( $list_open ) {
				$html     .= '</ul>';
				$list_open = false;
			}
			if ( '' !== $trim ) {
				$html .= '<p>' . esc_html( $trim ) . '</p>';
			}
		}

		if ( $list_open ) {
			$html .= '</ul>';
		}

		return $html;
	}
}
