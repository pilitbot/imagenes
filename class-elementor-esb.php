<?php
namespace QPH_MEC_Single_Builder\Admin;

if (!defined('ABSPATH')) exit;

final class Elementor_ESB {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'register_mec_submenu'), 99);

        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_categories'));
        add_action('elementor/widgets/register', array($this, 'init_widgets'));

        // Integración opcional con ajustes MEC (si el core expone estos filtros)
        add_filter('mec_single_event_styles', array($this, 'register_single_style_in_mec_settings'));
        add_filter('mec_settings_single_event_styles', array($this, 'register_single_style_in_mec_settings'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('QPH Single Templates', 'elementor-qph-single-builder-mec'),
            'singular_name'      => __('QPH Single Template', 'elementor-qph-single-builder-mec'),
            'add_new'            => __('Add New', 'elementor-qph-single-builder-mec'),
            'add_new_item'       => __('Add New Template', 'elementor-qph-single-builder-mec'),
            'edit_item'          => __('Edit Template', 'elementor-qph-single-builder-mec'),
            'new_item'           => __('New Template', 'elementor-qph-single-builder-mec'),
            'view_item'          => __('View Template', 'elementor-qph-single-builder-mec'),
            'search_items'       => __('Search Templates', 'elementor-qph-single-builder-mec'),
            'not_found'          => __('No templates found', 'elementor-qph-single-builder-mec'),
            'not_found_in_trash' => __('No templates found in Trash', 'elementor-qph-single-builder-mec'),
            'menu_name'          => __('MEC QPH Builder', 'elementor-qph-single-builder-mec'),
        );

        register_post_type('mec_esb', array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'supports'           => array('title', 'editor', 'elementor'),
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => true,
        ));
    }

    /**
     * Agrega el submenú dentro de MEC para que quede integrado en:
     * Modern Events Calendar > ... > Elementor QPH Single Builder for MEC
     */
    public function register_mec_submenu() {
        $capability = 'manage_options';
        $menu_slug = 'edit.php?post_type=mec_esb';

        // Slugs comunes de MEC según variantes.
        $possible_parents = array('MEC', 'webnus_mec', 'modern-events-calendar-lite', 'mec-intro');

        foreach ($possible_parents as $parent) {
            add_submenu_page(
                $parent,
                __('Elementor QPH Single Builder for MEC', 'elementor-qph-single-builder-mec'),
                __('Elementor QPH Single Builder for MEC', 'elementor-qph-single-builder-mec'),
                $capability,
                $menu_slug
            );
        }
    }

    /**
     * Intenta registrar el estilo en "Single Event Style" de MEC.
     * Si MEC no expone el filtro, no rompe nada.
     */
    public function register_single_style_in_mec_settings($styles) {
        if (!is_array($styles)) {
            $styles = array();
        }

        $styles['qph_elementor_builder'] = __('Elementor QPH Single Builder for MEC', 'elementor-qph-single-builder-mec');
        return $styles;
    }

    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category('qph_mec_single_builder', array(
            'title' => __('QPH MEC Single Builder', 'elementor-qph-single-builder-mec'),
            'icon'  => 'fa fa-calendar',
        ));
    }

    /**
     * BLOQUE 2: carga masiva de widgets ESB.
     */
    public function init_widgets($widgets_manager) {
        require_once QPH_ESB_DIR . 'inc/admin/widgets/class-esb-base.php';

        $this->register_widget($widgets_manager, 'class-esb-title.php', '\\QPH_MEC_Single_Builder\\Widgets\\ESB_Title');

        $widget_map = array(
            array('class-esb-category.php', 'ESB_Category'),
            array('class-esb-cancellation.php', 'ESB_Cancellation'),
            array('class-esb-breadcrumbs.php', 'ESB_Breadcrumbs'),
            array('class-esb-booking.php', 'ESB_Booking'),
            array('class-esb-banner.php', 'ESB_Banner'),
            array('class-esb-attendees.php', 'ESB_Attendees'),
            array('class-esb-featured-image.php', 'ESB_Featured_Image'),
            array('class-esb-export.php', 'ESB_Export'),
            array('class-esb-event-gallery.php', 'ESB_Event_Gallery'),
            array('class-esb-date.php', 'ESB_Date'),
            array('class-esb-countdown.php', 'ESB_Countdown'),
            array('class-esb-cost.php', 'ESB_Cost'),
            array('class-esb-location.php', 'ESB_Location'),
            array('class-esb-local-time.php', 'ESB_Local_Time'),
            array('class-esb-label.php', 'ESB_Label'),
            array('class-esb-hourly-schedule.php', 'ESB_Hourly_Schedule'),
            array('class-esb-googlemap.php', 'ESB_GoogleMap'),
            array('class-esb-custom-data.php', 'ESB_Custom_Data'),
            array('class-esb-public-download.php', 'ESB_Public_Download'),
            array('class-esb-organizer.php', 'ESB_Organizer'),
            array('class-esb-nxt-prv.php', 'ESB_Nxt_Prv'),
            array('class-esb-next-pervious.php', 'ESB_Next_Pervious'),
            array('class-esb-more-info.php', 'ESB_More_Info'),
            array('class-esb-faq.php', 'ESB_Faq'),
            array('class-esb-register-button.php', 'ESB_Register_Button'),
            array('class-esb-qr.php', 'ESB_QR'),
            array('class-esb-speaker.php', 'ESB_Speaker'),
            array('class-esb-zoom-event.php', 'ESB_Zoom_Event'),
            array('class-esb-weather.php', 'ESB_Weather'),
            array('class-esb-virtual-event.php', 'ESB_Virtual_Event'),
            array('class-esb-trailer-url.php', 'ESB_Trailer_URL'),
            array('class-esb-tags.php', 'ESB_Tags'),
        );

        foreach ($widget_map as $widget_data) {
            $this->register_widget(
                $widgets_manager,
                $widget_data[0],
                '\\QPH_MEC_Single_Builder\\Widgets\\' . $widget_data[1]
            );
        }
    }

    private function register_widget($widgets_manager, $file_name, $class_name) {
        $full_path = QPH_ESB_DIR . 'inc/admin/widgets/' . $file_name;

        if (file_exists($full_path)) {
            require_once $full_path;
        }

        if (class_exists($class_name)) {
            $widgets_manager->register(new $class_name());
        }
    }
}
