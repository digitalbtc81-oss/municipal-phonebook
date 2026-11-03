<?php
/**
 * Elementor integration loader. Only does anything when Elementor fires its hooks,
 * so the plugin never fatals when Elementor is missing.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor integration.
 */
class Munipb_Elementor {

	const CATEGORY = 'municipal-phonebook';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_filter( 'elementor/document/load/data', array( __CLASS__, 'normalize_department_settings' ) );
	}

	/**
	 * Show saved department slugs as term ids in the editor.
	 *
	 * The control's option values are term ids. A widget saved with percent-encoded
	 * slugs still filters correctly, and this rewrite lets the select show the
	 * department name instead of the slug. Unmatched tokens are left as typed so
	 * an unknown selection is not turned into "show everything".
	 *
	 * @param mixed $data Elements data.
	 * @return mixed
	 */
	public static function normalize_department_settings( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$widget = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
			if ( 'municipal-phonebook' === $widget && isset( $element['settings'] ) && is_array( $element['settings'] ) && isset( $element['settings']['departments'] ) ) {
				$element['settings']['departments'] = self::department_setting_ids( $element['settings']['departments'] );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::normalize_department_settings( $element['elements'] );
			}
			$data[ $index ] = $element;
		}
		return $data;
	}

	/**
	 * Replace matched department tokens with their term ids.
	 *
	 * @param mixed $raw Saved control value.
	 * @return string[]
	 */
	private static function department_setting_ids( $raw ) {
		$tokens = munipb_department_tokens( $raw );
		if ( ! $tokens ) {
			return array();
		}
		$index = munipb_department_index();
		$out   = array();
		foreach ( $tokens as $token ) {
			$id    = munipb_match_department_token( $token, $index );
			$out[] = $id > 0 ? (string) $id : $token;
		}
		return $out;
	}

	/**
	 * Register our widget category.
	 *
	 * @param object $elements_manager Elementor elements manager.
	 * @return void
	 */
	public static function register_category( $elements_manager ) {
		if ( is_object( $elements_manager ) && method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category(
				self::CATEGORY,
				array(
					'title' => __( 'Municipal Phonebook', 'municipal-phonebook' ),
					'icon'  => 'eicon-call-to-action',
				)
			);
		}
	}

	/**
	 * Register the widget.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		require_once MUNIPB_DIR . 'includes/class-munipb-elementor-widget.php';
		$widgets_manager->register( new Munipb_Elementor_Widget() );
	}
}
