<?php
/**
 * Elementor widget: Municipal Phonebook.
 *
 * Loaded only from the "elementor/widgets/register" hook, i.e. when Elementor is active.
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Municipal Phonebook Elementor widget.
 */
class Munipb_Elementor_Widget extends Widget_Base {

	/**
	 * Widget name (also used by the JS "element_ready" hook).
	 *
	 * @return string
	 */
	public function get_name() {
		return 'municipal-phonebook';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Municipal Phonebook', 'municipal-phonebook' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-call-to-action';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Munipb_Elementor::CATEGORY, 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'phonebook', 'directory', 'contacts', 'phone', 'τηλέφωνα', 'κατάλογος' );
	}

	/**
	 * Script dependencies (enqueued only where the widget is used).
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( Munipb_Frontend::HANDLE );
	}

	/**
	 * Style dependencies.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( Munipb_Frontend::HANDLE );
	}

	/**
	 * Top-level departments for the select control.
	 *
	 * @return array<string, string>
	 */
	private function department_options() {
		$options = array();
		foreach ( munipb_get_directory() as $section ) {
			$options[ $section['slug'] ] = $section['name'];
		}
		return $options;
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->content_controls();
		$this->style_colors();
		$this->style_headings();
		$this->style_table();
		$this->style_links();
		$this->style_search();
	}

	/**
	 * Content tab.
	 *
	 * @return void
	 */
	private function content_controls() {
		$this->start_controls_section( 'munipb_content', array( 'label' => __( 'Phonebook', 'municipal-phonebook' ) ) );

		$this->add_control(
			'departments',
			array(
				'label'       => __( 'Departments', 'municipal-phonebook' ),
				'description' => __( 'Leave empty to show all departments.', 'municipal-phonebook' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->department_options(),
				'default'     => array(),
			)
		);
		$this->add_control(
			'show_search',
			array(
				'label'   => __( 'Show search', 'municipal-phonebook' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->add_control(
			'show_toc',
			array(
				'label'   => __( 'Show categories', 'municipal-phonebook' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->add_control(
			'breakpoint',
			array(
				'label'       => __( 'Switch to cards below', 'municipal-phonebook' ),
				'description' => __( 'Based on the widget width, not the screen.', 'municipal-phonebook' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '720',
				'options'     => array(
					'never'  => __( 'Never', 'municipal-phonebook' ),
					'480'    => '480px',
					'720'    => '720px',
					'960'    => '960px',
					'always' => __( 'Always cards', 'municipal-phonebook' ),
				),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Add a color control writing a CSS custom property or property on selectors.
	 *
	 * @param string $id        Control id.
	 * @param string $label     Label.
	 * @param array  $selectors Selector => CSS map.
	 * @return void
	 */
	private function color( $id, $label, $selectors ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => $selectors,
			)
		);
	}

	/**
	 * Brand colors (CSS variables used everywhere, incl. mobile cards).
	 *
	 * @return void
	 */
	private function style_colors() {
		$this->start_controls_section(
			'munipb_style_colors',
			array(
				'label' => __( 'Colors', 'municipal-phonebook' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$vars = array(
			'primary'      => array( __( 'Primary', 'municipal-phonebook' ), '--munipb-primary' ),
			'primary_dark' => array( __( 'Primary dark', 'municipal-phonebook' ), '--munipb-primary-dark' ),
			'accent'       => array( __( 'Accent', 'municipal-phonebook' ), '--munipb-accent' ),
			'border_color' => array( __( 'Borders', 'municipal-phonebook' ), '--munipb-border' ),
			'soft_bg'      => array( __( 'Soft background', 'municipal-phonebook' ), '--munipb-bg-soft' ),
			'sub_bg'       => array( __( 'Sub-department background', 'municipal-phonebook' ), '--munipb-sub-bg' ),
			'flash'        => array( __( 'Highlight', 'municipal-phonebook' ), '--munipb-flash' ),
			'toolbar'      => array( __( 'Toolbar', 'municipal-phonebook' ), '--munipb-toolbar' ),
		);
		foreach ( $vars as $id => $def ) {
			$this->color( 'color_' . $id, $def[0], array( '{{WRAPPER}} .munipb-directory' => $def[1] . ': {{VALUE}};' ) );
		}
		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'Corner radius', 'municipal-phonebook' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .munipb-directory' => '--munipb-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Section headings.
	 *
	 * @return void
	 */
	private function style_headings() {
		$this->start_controls_section(
			'munipb_style_headings',
			array(
				'label' => __( 'Department headings', 'municipal-phonebook' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'selector' => '{{WRAPPER}} .munipb-section-title',
			)
		);
		$this->color( 'heading_color', __( 'Text color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-section-title' => 'color: {{VALUE}};' ) );
		$this->color( 'heading_bg', __( 'Background', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-section-title' => 'background: {{VALUE}};' ) );
		$this->add_responsive_control(
			'heading_padding',
			array(
				'label'      => __( 'Padding', 'municipal-phonebook' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .munipb-section-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'section_gap',
			array(
				'label'      => __( 'Space between departments', 'municipal-phonebook' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .munipb-section' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Table / cards.
	 *
	 * @return void
	 */
	private function style_table() {
		$this->start_controls_section(
			'munipb_style_table',
			array(
				'label' => __( 'Table & cards', 'municipal-phonebook' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'thead_typography',
				'label'    => __( 'Column headers', 'municipal-phonebook' ),
				'selector' => '{{WRAPPER}} .munipb-table thead th',
			)
		);
		$this->color( 'thead_color', __( 'Column header color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-table thead th' => 'color: {{VALUE}};' ) );
		$this->color( 'thead_bg', __( 'Column header background', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-table thead th' => 'background: {{VALUE}};' ) );
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'cell_typography',
				'label'    => __( 'Rows', 'municipal-phonebook' ),
				'selector' => '{{WRAPPER}} .munipb-table td, {{WRAPPER}} .munipb-table tbody th',
			)
		);
		$this->color( 'cell_color', __( 'Row text color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-table td' => 'color: {{VALUE}};' ) );
		$row_cell = '{{WRAPPER}} .munipb-directory .munipb-table tbody tr.munipb-entry-row';
		$this->color(
			'row_bg',
			__( 'Row / card background', 'municipal-phonebook' ),
			array(
				implode(
					', ',
					array(
						$row_cell . '.munipb-row-odd > td',
						$row_cell . '.munipb-row-even > td',
						$row_cell . '.munipb-row-odd:hover > td',
						$row_cell . '.munipb-row-even:hover > td',
						$row_cell . ':nth-child(odd) > td',
						$row_cell . ':nth-child(even) > td',
					)
				) => 'background-color: {{VALUE}};',
			)
		);
		$this->color( 'row_odd', __( 'Odd row color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory' => '--munipb-row-odd: {{VALUE}};' ) );
		$this->color( 'row_even', __( 'Even row color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory' => '--munipb-row-even: {{VALUE}};' ) );
		$this->color( 'subdept_color', __( 'Sub-department text color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-subdept-row th, {{WRAPPER}} .munipb-subdept-row td' => 'color: {{VALUE}};' ) );
		$this->add_responsive_control(
			'cell_padding',
			array(
				'label'      => __( 'Row spacing (cell padding)', 'municipal-phonebook' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .munipb-table th, {{WRAPPER}} .munipb-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'table_border',
				'label'    => __( 'Table border', 'municipal-phonebook' ),
				'selector' => '{{WRAPPER}} .munipb-table',
			)
		);
		$this->color( 'row_divider', __( 'Row divider color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-table th, {{WRAPPER}} .munipb-table td' => 'border-bottom-color: {{VALUE}};' ) );
		$this->end_controls_section();
	}

	/**
	 * Phone / email links.
	 *
	 * @return void
	 */
	private function style_links() {
		$this->start_controls_section(
			'munipb_style_links',
			array(
				'label' => __( 'Links', 'municipal-phonebook' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'link_typography',
				'selector' => '{{WRAPPER}} .munipb-phone, {{WRAPPER}} .munipb-email',
			)
		);
		$this->color( 'link_color', __( 'Color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-phone, {{WRAPPER}} .munipb-email' => 'color: {{VALUE}};' ) );
		$this->color( 'link_hover', __( 'Hover color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-phone:hover, {{WRAPPER}} .munipb-email:hover, {{WRAPPER}} .munipb-phone:focus, {{WRAPPER}} .munipb-email:focus' => 'color: {{VALUE}};' ) );
		$this->end_controls_section();
	}

	/**
	 * Search box.
	 *
	 * @return void
	 */
	private function style_search() {
		$this->start_controls_section(
			'munipb_style_search',
			array(
				'label'     => __( 'Search', 'municipal-phonebook' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_search' => 'yes' ),
			)
		);
		$input        = '{{WRAPPER}} .munipb-directory .munipb-search input.munipb-search-input';
		$input_states = implode(
			', ',
			array(
				$input,
				$input . ':hover',
				$input . ':focus',
				$input . ':focus-visible',
				$input . ':active',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'search_typography',
				'selector' => $input_states,
			)
		);
		$this->color( 'search_color', __( 'Text color', 'municipal-phonebook' ), array( $input_states => 'color: {{VALUE}};' ) );
		$this->color( 'search_bg', __( 'Background', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory .munipb-search .munipb-search-box' => 'background-color: {{VALUE}};' ) );
		$this->color( 'search_border', __( 'Border color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory .munipb-search .munipb-search-box' => 'border-color: {{VALUE}};' ) );
		$this->color( 'search_border_focus', __( 'Focus border color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory .munipb-search .munipb-search-box:focus-within' => 'border-color: {{VALUE}};' ) );
		$this->color( 'search_icon', __( 'Icon color', 'municipal-phonebook' ), array( '{{WRAPPER}} .munipb-directory .munipb-search .munipb-search-icon' => 'color: {{VALUE}};' ) );
		$this->add_responsive_control(
			'search_radius',
			array(
				'label'      => __( 'Border radius', 'municipal-phonebook' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .munipb-directory .munipb-search .munipb-search-box' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'search_width',
			array(
				'label'      => __( 'Max width', 'municipal-phonebook' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 200,
						'max' => 1200,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .munipb-directory .munipb-search' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render: validate every setting, then use the same renderer as the shortcode.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$settings = is_array( $settings ) ? $settings : array();

		$departments = array();
		if ( ! empty( $settings['departments'] ) && is_array( $settings['departments'] ) ) {
			$departments = array_map( 'strval', array_filter( $settings['departments'], 'is_scalar' ) );
		}

		$args = array(
			'search'      => isset( $settings['show_search'] ) && 'yes' === $settings['show_search'],
			'toc'         => isset( $settings['show_toc'] ) && 'yes' === $settings['show_toc'],
			'departments' => $departments,
			'breakpoint'  => isset( $settings['breakpoint'] ) ? sanitize_key( (string) $settings['breakpoint'] ) : '720',
		);

		echo Munipb_Frontend::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer escapes every value.
	}
}
