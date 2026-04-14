<?php
/**
 * Widget: Event Title
 */

if (!defined('ABSPATH')) {
    exit;
}

class QPH_ESB_Widget_Title extends \Elementor\Widget_Base {

    public function get_name() {
        return 'mec_esb_title';
    }

    public function get_title() {
        return __('Título del Evento', 'mec-single-builder');
    }

    public function get_icon() {
        return 'eicon-post-title';
    }

    public function get_categories() {
        return ['mec_single_builder'];
    }

    protected function register_controls() {
        // Contenido
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Contenido', 'mec-single-builder'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'html_tag',
            [
                'label'   => __('Etiqueta HTML', 'mec-single-builder'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'h1',
                'options' => [
                    'h1'   => 'H1',
                    'h2'   => 'H2',
                    'h3'   => 'H3',
                    'h4'   => 'H4',
                    'h5'   => 'H5',
                    'h6'   => 'H6',
                    'div'  => 'div',
                    'span' => 'span',
                    'p'    => 'p',
                ],
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label'     => __('Alineación', 'mec-single-builder'),
                'type'      => \Elementor\Controls_Manager::CHOOSE,
                'options'   => [
                    'left'   => [
                        'title' => __('Izquierda', 'mec-single-builder'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Centro', 'mec-single-builder'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => __('Derecha', 'mec-single-builder'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .qph-event-title' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Estilos
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Estilo', 'mec-single-builder'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label'     => __('Color', 'mec-single-builder'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .qph-event-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .mec-event-title',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'title_shadow',
                'selector' => '{{WRAPPER}} .mec-event-title',
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label'      => __('Margen', 'mec-single-builder'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .qph-event-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $event_id = $this->get_event_id();

        if (!$event_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p class="qph-esb-placeholder">' . __('Título del Evento', 'mec-single-builder') . '</p>';
            }
            return;
        }

        $title = get_the_title($event_id);
        $tag = $settings['html_tag'];

        printf(
            '<%1$s class="qph-event-title">%2$s</%1$s>',
            esc_attr($tag),
            esc_html($title)
        );
    }

    protected function get_event_id() {
        if (\Elementor\Plugin::$instance->editor->is_edit_mode() || 
            (isset($_GET['preview_id']) && !empty($_GET['preview_id']))) {
            $settings = get_option('mec_options', array());
            $custom_event = isset($settings['settings']['custom_event_for_set_settings'])
                ? (int) $settings['settings']['custom_event_for_set_settings']
                : 0;

            if ($custom_event) {
                return $custom_event;
            }
        }

        global $post, $eventt;

        if (isset($eventt) && is_object($eventt) && isset($eventt->ID)) {
            return $eventt->ID;
        }

        if ($post && $post->post_type === 'mec-events') {
            return $post->ID;
        }

        return 0;
    }
}