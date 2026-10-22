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
