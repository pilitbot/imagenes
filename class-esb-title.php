<?php
namespace QPH_MEC_Single_Builder\Widgets;

if (!defined('ABSPATH')) exit;

if (!class_exists('\\QPH_MEC_Single_Builder\\Widgets\\ESB_Base')) {
    require_once __DIR__ . '/class-esb-base.php';
}


class ESB_Title extends ESB_Base {
    public function get_name() { return 'qph-esb-title'; }
    public function get_title() { return __('MEC Event Title', 'elementor-qph-single-builder-mec'); }
    public function get_icon() { return 'eicon-heading'; }
    public function get_categories() { return array('qph_mec_single_builder'); }

    protected function register_controls() {
        $this->start_controls_section('content', array('label' => __('Contenido', 'elementor-qph-single-builder-mec')));
        $this->add_control('tag', array(
            'label' => __('Etiqueta HTML', 'elementor-qph-single-builder-mec'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => array('h1'=>'H1','h2'=>'H2','h3'=>'H3','div'=>'DIV','p'=>'P')
        ));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $tag = !empty($settings['tag']) ? $settings['tag'] : 'h2';
        $title = get_the_title($this->get_event_id());

        if (!$title) {
            $this->render_empty(__('El evento no tiene título.', 'elementor-qph-single-builder-mec'));
            return;
        }

        printf('<%1$s class="qph-mec-event-title">%2$s</%1$s>', esc_attr($tag), esc_html($title));
    }
}
