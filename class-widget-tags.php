<?php
if (!defined('ABSPATH')) exit;

class QPH_ESB_Widget_Tags extends \Elementor\Widget_Base {

    public function get_name() { return 'qph_esb_tags'; }
    public function get_title() { return __('Event Tags', 'qph-single-builder'); }
    public function get_icon() { return 'eicon-tags'; }
    public function get_categories() { return ['single_builder']; }

    protected function register_controls() {
        $this->start_controls_section('content', ['label' => __('Content', 'qph-single-builder'), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
        $this->add_control('separator', ['label' => __('Separator', 'qph-single-builder'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => ', ']);
        $this->add_control('show_icon', ['label' => __('Show Icon', 'qph-single-builder'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->end_controls_section();

        $this->start_controls_section('style', ['label' => __('Style', 'qph-single-builder'), 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('color', ['label' => __('Color', 'qph-single-builder'), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .qph-tags' => 'color: {{VALUE}}']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'typography', 'selector' => '{{WRAPPER}} .qph-tags']);
        $this->end_controls_section();
    }

    protected function render() {
        $id = $this->get_event_id();
        if (!$id) return;
        $tags = get_the_terms($id, 'mec_tag');
        if (!$tags || is_wp_error($tags)) return;
        $s = $this->get_settings_for_display();
        $links = array();
        foreach ($tags as $tag) {
            $links[] = '<a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) . '</a>';
        }
        echo '<div class="qph-tags">';
        if ($s['show_icon'] === 'yes') echo '<i class="fas fa-tag"></i> ';
        echo implode(esc_html($s['separator']), $links);
        echo '</div>';
    }

    protected function get_event_id() {
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $opts = get_option('mec_options', array());
            $id = isset($opts['settings']['custom_event_for_set_settings']) ? (int)$opts['settings']['custom_event_for_set_settings'] : 0;
            if ($id) return $id;
        }
        global $post, $eventt;
        if (isset($eventt) && isset($eventt->ID)) return $eventt->ID;
        if ($post && $post->post_type === 'mec-events') return $post->ID;
        return 0;
    }
}