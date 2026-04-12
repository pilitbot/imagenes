<?php
/**
 * Plugin Name: QPH Single Builder for MEC
 * Plugin URI: https://quepasahoy.com.co
 * Description: Usa Elementor para diseñar eventos de MEC.
 * Version: 4.0.0
 * Author: QuePasaHoy
 * Author URI: https://quepasahoy.com.co
 * Text Domain: qph-single-builder
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('QPH_ESB_VERSION', '4.0.0');
define('QPH_ESB_PATH', plugin_dir_path(__FILE__));
define('QPH_ESB_URL', plugin_dir_url(__FILE__));

final class QPH_Single_Builder_MEC {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 20);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    // ============================================
    // INIT
    // ============================================

    public function init() {
        $this->log('=== QPH ESB v4.0.0 Init ===');

        if (!class_exists('MEC') && !defined('MEC_VERSION')) {
            $this->log('ERROR: MEC no activo');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>'
                    . '<strong>QPH Single Builder:</strong> '
                    . 'Requiere Modern Events Calendar.</p></div>';
            });
            return;
        }

        if (!did_action('elementor/loaded')) {
            $this->log('ERROR: Elementor no activo');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>'
                    . '<strong>QPH Single Builder:</strong> '
                    . 'Requiere Elementor.</p></div>';
            });
            return;
        }

        $this->log('Dependencias OK');
        $this->setup_hooks();
        $this->setup_elementor();
    }

    private function log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[QPH-ESB] ' . $msg);
        }
    }

    // ============================================
    // ELEMENTOR SETUP
    // Replica exactamente lo que hace el plugin original
    // ============================================

    private function setup_elementor() {

        // Registrar CPT en Elementor
        add_action('elementor/init', array($this, 'register_elementor_cpt'));

        // Template canvas para editar mec_esb con Elementor
        add_filter('template_include', array($this, 'elementor_canvas_template'), 99998);

        // Soporte de Elementor para el CPT
        add_action('init', function() {
            add_post_type_support('mec_esb', 'elementor');
        }, 999);

        // Preview: cargar estilos y scripts de MEC
        // (igual que el plugin original)
        add_action('elementor/preview/enqueue_styles', array($this, 'preview_enqueue'));

        // Editor: cargar estilos adicionales
        add_action('elementor/editor/after_enqueue_styles', array($this, 'editor_styles'));

        // Registrar categoría de widgets
        add_action(
            'elementor/elements/categories_registered',
            array($this, 'register_widget_category')
        );

        // Registrar widgets
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Limpiar caché al guardar template
        add_action('save_post_mec_esb', array($this, 'clear_elementor_cache'));

        // Soporte Elementor para mec-events
        add_filter('mec_event_supports', array($this, 'apply_elementor_support_for_mec_events'));
    }

    /**
     * Replica: apply_elementor_support_for_mec_events()
     * del plugin original
     */
    public function apply_elementor_support_for_mec_events($supports) {
        $supports[] = 'elementor';
        return $supports;
    }

    /**
     * Replica: preview enqueue del plugin original
     * Carga Google Maps y scripts de MEC en el preview
     */
    public function preview_enqueue() {
        if (!class_exists('\Elementor\Plugin')) return;
        if (!is_singular('mec_esb')) return;

        $settings = get_option('mec_options', array());
        $mec_settings = isset($settings['settings']) ? $settings['settings'] : array();

        // Cargar scripts de MEC (igual que el plugin original)
        if (class_exists('MEC_main')) {
            $mainClass = new MEC_main();
            if (method_exists($mainClass, 'load_map_assets')) {
                $mainClass->load_map_assets();
            }
        }

        // Google Maps
        $api_key = isset($mec_settings['google_maps_api_key'])
            ? trim($mec_settings['google_maps_api_key'])
            : '';

        $maps_url = '//maps.googleapis.com/maps/api/js?libraries=places';
        if (!empty($api_key)) {
            $maps_url .= '&key=' . $api_key;
        }

        wp_enqueue_script('googlemap', $maps_url);

        // Scripts adicionales de MEC
        if (wp_script_is('mec-richmarker-script', 'registered')) {
            wp_enqueue_script('mec-richmarker-script');
        }
        if (wp_script_is('mec-flipcount-script', 'registered')) {
            wp_enqueue_script('mec-flipcount-script');
        }

        // Añadir clases al body (igual que filter_function_name del plugin original)
        if (\Elementor\Plugin::$instance->preview->is_preview_mode()
            && get_post_type(get_the_ID()) === 'mec_esb') {
            add_filter('body_class', array($this, 'add_body_classes'));
        }
    }

    /**
     * Replica: filter_function_name() del plugin original
     */
    public function add_body_classes($classes) {
        $classes[] = 'mec-single-event';
        $classes[] = 'mec-wrap';
        return $classes;
    }

    public function editor_styles() {
        if (get_post_type(get_the_ID()) === 'mec_esb') {
            wp_enqueue_style('mec-font-icons');
            $css = QPH_ESB_PATH . 'assets/css/editor.css';
            if (file_exists($css)) {
                wp_enqueue_style(
                    'qph-esb-editor',
                    QPH_ESB_URL . 'assets/css/editor.css',
                    array(),
                    QPH_ESB_VERSION
                );
            }
        }
    }

    public function register_elementor_cpt() {
        $cpt = get_option('elementor_cpt_support', array('page', 'post'));
        if (!is_array($cpt)) $cpt = array('page', 'post');
        if (!in_array('mec_esb', $cpt)) {
            $cpt[] = 'mec_esb';
            update_option('elementor_cpt_support', $cpt);
            $this->log('mec_esb añadido a Elementor CPT');
        }
    }

    public function elementor_canvas_template($template) {
        if (!is_singular('mec_esb')) return $template;
        if (!class_exists('\Elementor\Plugin')) return $template;

        $document = \Elementor\Plugin::$instance->documents->get(get_the_ID());
        if ($document && $document->is_built_with_elementor()) {
            $canvas = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
            if (file_exists($canvas)) return $canvas;
        }

        $fallback = QPH_ESB_PATH . 'templates/single-mec_esb.php';
        if (file_exists($fallback)) return $fallback;

        return $template;
    }

    /**
     * Replica: clear_elementor_cache() del plugin original
     */
    public function clear_elementor_cache($post_id) {
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            $this->log('Caché Elementor limpiada');
        }
    }

    /**
     * Replica: add_elementor_widget_categories() del plugin original
     * Categoría: 'single_builder' (IGUAL que el original)
     */
    public function register_widget_category($elements_manager) {
        $elements_manager->add_category(
            'single_builder',
            array(
                'title' => __('MEC Single Builder', 'qph-single-builder'),
                'icon'  => 'fa fa-plug',
            )
        );
        $this->log('Categoría single_builder registrada');
    }

    /**
     * Replica: init_widgets() del plugin original
     */
    public function register_widgets($widgets_manager) {
        $widgets = array(
            'class-widget-title'     => 'QPH_ESB_Widget_Title',
            'class-widget-date'      => 'QPH_ESB_Widget_Date',
            'class-widget-time'      => 'QPH_ESB_Widget_Time',
            'class-widget-location'  => 'QPH_ESB_Widget_Location',
            'class-widget-cost'      => 'QPH_ESB_Widget_Cost',
            'class-widget-organizer' => 'QPH_ESB_Widget_Organizer',
            'class-widget-content'   => 'QPH_ESB_Widget_Content',
            'class-widget-image'     => 'QPH_ESB_Widget_Image',
            'class-widget-category'  => 'QPH_ESB_Widget_Category',
            'class-widget-map'       => 'QPH_ESB_Widget_Map',
        );

        foreach ($widgets as $file => $class) {
            $path = QPH_ESB_PATH . 'widgets/' . $file . '.php';
            if (file_exists($path)) {
                require_once $path;
                if (class_exists($class)) {
                    $widgets_manager->register(new $class());
                    $this->log('Widget registrado: ' . $class);
                }
            } else {
                $this->log('Widget no encontrado: ' . $path);
            }
        }
    }

    public function register_elementor_post_type($pt) {
        $pt['mec_esb']    = 'QPH Event Builder';
        $pt['mec-events'] = 'Eventos MEC';
        return $pt;
    }

    // ============================================
    // HOOKS PRINCIPALES
    // ============================================

    private function setup_hooks() {

        // CPT
        add_action('init', array($this, 'register_post_type'), 5);

        // MENÚ
        add_action('after_mec_submenu_action', array($this, 'add_submenu'));
        add_action('admin_menu', array($this, 'add_submenu_fallback'), 9999);

        // ============================================
        // MEC INTEGRACIÓN PRINCIPAL
        // El hook mec_esb_content lo ejecuta MEC
        // desde /app/skins/single/builder.php
        // ============================================
        add_action('mec_esb_content', array($this, 'render_builder_content'), 10, 1);
        add_action('mec-ajax-load-single-page-before', array($this, 'render_builder_modal'), 10, 1);
        add_filter('mec_filter_single_style', array($this, 'filter_single_style'), 1);

        // ============================================
        // SETTINGS EN MEC - Solo via JavaScript
        // El hook PHP es como fallback
        // ============================================
        add_action('mec_single_style_setting_after', array($this, 'add_settings_fallback'), 10, 1);
        add_action('admin_footer', array($this, 'inject_settings_ui'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // POPUP
        add_action('admin_enqueue_scripts', array($this, 'show_setup_popup'));
        add_action('admin_post_qph_esb_apply_style_direct', array($this, 'handle_direct_apply'));
        add_action('admin_post_qph_esb_skip_setup_direct', array($this, 'handle_direct_skip'));

        // META EN EVENTOS
        add_action('add_meta_boxes', array($this, 'add_event_metabox'));
        add_action('save_post_mec-events', array($this, 'save_event_meta'), 10, 1);

        // CATEGORÍAS MEC
        add_action('mec_category_add_form_fields', array($this, 'category_add_fields'));
        add_action('mec_category_edit_form_fields', array($this, 'category_edit_fields'), 10, 2);
        add_action('created_mec_category', array($this, 'save_category_meta'));
        add_action('edited_mec_category', array($this, 'save_category_meta'));

        // ELEMENTOR
        add_filter('elementor/utils/get_public_post_types', array($this, 'register_elementor_post_type'));
        
       
        // ESTA ES LA LÍNEA CLAVE
        add_filter('mec_single_style_options', array($this, 'register_builder_style'), 10, 1);

        // MISC
        add_filter('post_row_actions', array($this, 'remove_view_action'), 10, 2);
        add_action('admin_menu', array($this, 'diagnose_menu'), 99999);

        $this->log('Hooks registrados OK');
    }

    // ============================================
    // POST TYPE
    // ============================================

    public function register_post_type() {
        register_post_type('mec_esb', array(
            'labels' => array(
                'name'          => 'QPH Event Builder',
                'singular_name' => 'Event Template',
                'add_new'       => 'Crear Nuevo',
                'add_new_item'  => 'Crear Nuevo Template',
                'edit_item'     => 'Editar Template',
                'all_items'     => 'QPH Event Builder',
                'not_found'     => 'No hay templates',
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'qph_esb'),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'exclude_from_search' => true,
            'show_in_rest'        => true,
        ));

        add_post_type_support('mec_esb', 'elementor');
        $this->log('CPT mec_esb registrado');
    }

    
     /**
 * Registrar 'builder' en los estilos de MEC
 * Esto hace que aparezca en el selector de MEC Settings
 */
public function register_builder_style($styles) {
    $this->log('register_builder_style ejecutado');
    $this->log('Estilos actuales: ' . print_r($styles, true));
    
    if (!isset($styles['builder'])) {
        $styles['builder'] = __('QPH Single Builder (Elementor)', 'qph-single-builder');
    }
    
    return $styles;
}

public function get_builder_style_label($label, $style) {
    if ($style === 'builder') {
        return __('QPH Single Builder (Elementor)', 'qph-single-builder');
    }
    return $label;
}
    // ============================================
    // MENÚ
    // ============================================

    public function add_submenu() {
        $this->log('add_submenu via after_mec_submenu_action');
        $this->register_submenu();
    }

    public function add_submenu_fallback() {
        $this->register_submenu();
    }

    private function register_submenu() {
        global $submenu;

        $parent_slug = 'mec-intro';
        $menu_slug   = 'qph-event-builder';

        if (isset($submenu[$parent_slug])) {
            foreach ($submenu[$parent_slug] as $item) {
                if (isset($item[2]) && $item[2] === $menu_slug) {
                    return;
                }
            }
        }

        add_submenu_page(
            $parent_slug,
            __('QPH Event Builder', 'qph-single-builder'),
            __('QPH Event Builder', 'qph-single-builder'),
            'edit_posts',
            $menu_slug,
            array($this, 'render_admin_page')
        );

        $this->log('Submenu registrado');
    }

    public function render_admin_page() {
        $templates = $this->get_all_templates();
        $mec_opts  = get_option('mec_options', array());
        $active_id = isset($mec_opts['settings']['single_single_default_builder'])
            ? (int) $mec_opts['settings']['single_single_default_builder']
            : 0;
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">QPH Event Builder</h1>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
               class="page-title-action">
                <?php _e('Añadir Nuevo', 'qph-single-builder'); ?>
            </a>
            <hr class="wp-header-end">

            <?php if (empty($templates)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('No hay templates. ', 'qph-single-builder'); ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>">
                            <?php _e('Crea tu primer template', 'qph-single-builder'); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'qph-single-builder'); ?></th>
                            <th><?php _e('Estado', 'qph-single-builder'); ?></th>
                            <th><?php _e('Fecha', 'qph-single-builder'); ?></th>
                            <th><?php _e('Acciones', 'qph-single-builder'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $tpl) : ?>
                            <tr <?php echo ($tpl->ID === $active_id) ? 'style="background:#e8f5e9;"' : ''; ?>>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($tpl->ID)); ?>">
                                            <?php echo esc_html($tpl->post_title); ?>
                                        </a>
                                    </strong>
                                    <?php if ($tpl->ID === $active_id) : ?>
                                        <span style="color:green;font-size:12px;">
                                            ✅ <?php _e('Template activo', 'qph-single-builder'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($tpl->post_status)); ?></td>
                                <td><?php echo esc_html(get_the_date('d/m/Y', $tpl->ID)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($tpl->ID)); ?>"
                                       class="button button-small">
                                        <?php _e('Editar con Elementor', 'qph-single-builder'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:15px;">
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
                       class="button button-primary">
                        + <?php _e('Crear Nuevo Template', 'qph-single-builder'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=MEC-settings')); ?>"
                       class="button" style="margin-left:10px;">
                        ⚙️ <?php _e('MEC Settings', 'qph-single-builder'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function remove_view_action($actions, $post) {
        if ($post->post_type === 'mec_esb') unset($actions['view']);
        return $actions;
    }

    public function diagnose_menu() {
        global $submenu;
        $this->log('Menús: ' . implode(', ', array_keys($submenu)));
        if (isset($submenu['mec-intro'])) {
            foreach ($submenu['mec-intro'] as $item) {
                $this->log('MEC submenu: ' . (isset($item[0]) ? $item[0] : '') . ' → ' . (isset($item[2]) ? $item[2] : ''));
            }
        }
    }

    // ============================================
    // RENDERIZADO SINGLE
    // ============================================

    public function render_builder_content($event) {
        $this->log('render_builder_content - Event ID: ' . $event->ID);

        $style = self::get_event_template_style($event->ID);
        $this->log('Estilo: ' . $style);

        if ($style !== 'builder') {
            $this->log('No es builder');
            return;
        }

        if (!class_exists('\Elementor\Plugin')) {
            $this->log('Elementor no disponible');
            return;
        }

        // En modo editor de Elementor
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()
            || \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            the_content();
            return;
        }

        global $eventt;
        $eventt = $event;

        $template_id = $this->resolve_template_id($event->ID, 'single');
        $this->log('Template ID: ' . $template_id);

        if (!$template_id) {
            echo '<div class="qph-esb-notice"><p>'
                . __('Selecciona un template en MEC Settings.', 'qph-single-builder')
                . ' <a href="' . esc_url(admin_url('post-new.php?post_type=mec_esb')) . '">'
                . __('Crear template', 'qph-single-builder') . '</a></p></div>';
            return;
        }

        // Verificar datos de Elementor
        $edit_mode = get_post_meta($template_id, '_elementor_edit_mode', true);
        $data      = get_post_meta($template_id, '_elementor_data', true);

        $this->log('Edit mode: ' . $edit_mode);
        $this->log('Data length: ' . strlen($data));

        if ($edit_mode !== 'builder') {
            update_post_meta($template_id, '_elementor_edit_mode', 'builder');
        }

        if (empty($data)) {
            echo '<div class="qph-esb-notice"><p>'
                . __('El template está vacío. ', 'qph-single-builder')
                . '<a href="' . esc_url(get_edit_post_link($template_id)) . '">'
                . __('Edítalo con Elementor', 'qph-single-builder')
                . '</a></p></div>';
            return;
        }

        // Cargar assets de Elementor
        \Elementor\Plugin::$instance->frontend->enqueue_styles();
        \Elementor\Plugin::$instance->frontend->enqueue_scripts();

        // Guardar post global original
        global $post;
        $original_post = $post;

        // Cambiar post global al template
        $post = get_post($template_id);
        setup_postdata($post);

        $content = \Elementor\Plugin::$instance->frontend
            ->get_builder_content_for_display($template_id, true);

        $this->log('Content length: ' . strlen($content));

        // Restaurar post global
        $post = $original_post;
        if ($original_post) {
            setup_postdata($original_post);
        } else {
            wp_reset_postdata();
        }

        if (empty($content)) {
            $this->log('ERROR: Contenido vacío');
            echo '<div class="qph-esb-notice"><p>'
                . __('Error renderizando template. ', 'qph-single-builder')
                . '<a href="' . esc_url(get_edit_post_link($template_id)) . '">'
                . __('Verificar template', 'qph-single-builder')
                . '</a></p></div>';
            return;
        }

        echo '<div class="mec-wrap mec-single-builder-wrap qph-esb-wrapper">';
        echo '<div class="row mec-single-event"><div class="wn-single">';
        echo $content;
        echo '</div></div></div>';

        $this->load_template_css($template_id);
        $this->log('✅ Renderizado OK');
    }

    // ============================================
    // RENDERIZADO MODAL
    // ============================================

    public function render_builder_modal($event_id) {
        $this->log('render_builder_modal - Event ID: ' . $event_id);

        if (!class_exists('\Elementor\Plugin')) return;

        $style = self::get_event_template_style($event_id);
        if ($style !== 'builder') return;

        $template_id = $this->resolve_template_id($event_id, 'modal');
        if (!$template_id) return;

        global $post;
        $event_post = get_post($event_id);
        if (!$event_post) return;

        $original_post = $post;
        $post = $event_post;
        setup_postdata($post);

        \Elementor\Plugin::$instance->frontend->enqueue_styles();
        \Elementor\Plugin::$instance->frontend->enqueue_scripts();

        // Cambiar post al template para renderizar
        $post = get_post($template_id);
        setup_postdata($post);

        $content = \Elementor\Plugin::$instance->frontend
            ->get_builder_content_for_display($template_id, true);

        $post = $original_post;
        if ($original_post) {
            setup_postdata($original_post);
        } else {
            wp_reset_postdata();
        }

        if (empty($content)) {
            $this->log('Modal: contenido vacío');
            return;
        }

        echo '<div class="mec-wrap mec-single-builder-wrap clearfix">';
        echo '<div class="row mec-single-event"><div class="wn-single">';
        echo $content;
        echo '</div></div></div>';

        $this->load_template_css($template_id);
        $this->log('✅ Modal renderizado OK');
        die();
    }

    // ============================================
    // HELPERS
    // ============================================

    public static function get_event_template_style($event_id) {
        $opts = get_option('mec_options', array());

        $per_event = isset($opts['settings']['style_per_event'])
            ? $opts['settings']['style_per_event'] : '';

        if ($per_event) {
            $style = get_post_meta($event_id, 'mec_style_per_event', true);
            if (!empty($style) && $style !== 'global') return $style;
        }

        return isset($opts['settings']['single_single_style'])
            ? $opts['settings']['single_single_style']
            : 'default';
    }

    private function resolve_template_id($event_id, $type = 'single') {
        $opts = get_option('mec_options', array());
        $s    = isset($opts['settings']) ? $opts['settings'] : array();

        $mk = ($type === 'modal') ? 'single_modal_design_page' : 'single_design_page';
        $dk = ($type === 'modal') ? 'single_modal_default_builder' : 'single_single_default_builder';

        // 1. Por evento
        $id = (int) get_post_meta($event_id, $mk, true);
        if ($id > 0 && get_post($id)) {
            $this->log("Template por evento: $id");
            return $id;
        }

        // 2. Por categoría
        $cats = wp_get_post_terms($event_id, 'mec_category', array('fields' => 'ids'));
        if (!is_wp_error($cats)) {
            foreach ($cats as $cid) {
                $ct = (int) get_term_meta($cid, $mk, true);
                if ($ct > 0 && get_post($ct)) {
                    $this->log("Template por categoría: $ct");
                    return $ct;
                }
            }
        }

        // 3. Global
        $gid = isset($s[$dk]) ? (int) $s[$dk] : 0;
        if ($gid > 0 && get_post($gid)) {
            $this->log("Template global: $gid");
            return $gid;
        }

        // 4. Fallback modal → single
        if ($type === 'modal') {
            $sid = isset($s['single_single_default_builder'])
                ? (int) $s['single_single_default_builder'] : 0;
            if ($sid > 0 && get_post($sid)) {
                $this->log("Template fallback single: $sid");
                return $sid;
            }
        }

        // 5. Cualquier template
        $any = get_posts(array(
            'post_type'      => 'mec_esb',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ));

        if (!empty($any)) {
            $this->log("Template fallback cualquiera: " . $any[0]);
            return (int) $any[0];
        }

        $this->log('No se encontró ningún template');
        return 0;
    }

    private function load_template_css($tid) {
        if (class_exists('\Elementor\Core\Files\CSS\Post')) {
            $css = new \Elementor\Core\Files\CSS\Post($tid);
            $css->enqueue();
        }
        echo '<style>
            .mec-wrap .elementor-text-editor p{margin:inherit;color:inherit;font-size:inherit;line-height:inherit}
            .mec-container{width:auto!important}
            .qph-esb-wrapper .elementor-section-wrap{width:100%}
            .qph-esb-notice{padding:15px;background:#fff3cd;border-left:4px solid #ffc107;margin:15px 0;}
            .qph-esb-notice a{color:#0073aa;font-weight:bold;}
        </style>';
    }

    private function get_all_templates() {
        return get_posts(array(
            'post_type'      => 'mec_esb',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
    }

    // ============================================
    // FILTROS MEC
    // ============================================

    public function filter_single_style($style) {
        if (is_singular('mec-events')) {
            return self::get_event_template_style(get_the_ID());
        }
        return $style;
    }

    // ============================================
    // MEC SETTINGS - Fallback PHP
    // ============================================

    public function add_settings_fallback($mec) {
        $this->log('add_settings_fallback ejecutado');
        // El JS maneja la inyección principal
        // Este es solo un fallback mínimo
    }

    // ============================================
    // SETTINGS UI VIA JAVASCRIPT
    // ============================================

    public function enqueue_admin_assets($hook) {
        global $post_type;

        $is_mec = $post_type === 'mec_esb'
               || strpos($hook, 'mec') !== false
               || (isset($_GET['page']) && strpos(
                   sanitize_text_field($_GET['page']), 'MEC') !== false)
               || (isset($_GET['page']) && $_GET['page'] === 'qph-event-builder');

        if (!$is_mec) return;

        wp_enqueue_style(
            'qph-esb-admin',
            QPH_ESB_URL . 'assets/css/admin.css',
            array(),
            QPH_ESB_VERSION
        );

        wp_enqueue_script(
            'qph-esb-admin',
            QPH_ESB_URL . 'assets/js/admin.js',
            array('jquery'),
            QPH_ESB_VERSION,
            true
        );
    }

    public function inject_settings_ui() {
    $screen = get_current_screen();
    if (!$screen) return;

    $is_mec = strpos($screen->id, 'mec') !== false
           || strpos($screen->id, 'MEC') !== false
           || (isset($_GET['page']) && strpos(
               sanitize_text_field($_GET['page']), 'MEC') !== false);

    if (!$is_mec) return;

    $opts       = get_option('mec_options', array());
    $settings   = isset($opts['settings']) ? $opts['settings'] : array();
    $current    = isset($settings['single_single_style'])
                  ? $settings['single_single_style'] : '';
    $is_builder = ($current === 'builder');

    $sel_builder = isset($settings['single_single_default_builder'])
        ? $settings['single_single_default_builder'] : '';
    $sel_modal   = isset($settings['single_modal_default_builder'])
        ? $settings['single_modal_default_builder'] : '';
    $sel_event   = isset($settings['custom_event_for_set_settings'])
        ? $settings['custom_event_for_set_settings'] : '';

    $builders = $this->get_all_templates();
    $builders_json = array();
    foreach ($builders as $b) {
        $builders_json[] = array(
            'id'    => $b->ID,
            'title' => esc_html($b->post_title),
        );
    }

    $events = get_posts(array(
        'post_type'      => 'mec-events',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    $events_json = array();
    foreach ($events as $ev) {
        $events_json[] = array(
            'id'    => $ev->ID,
            'title' => esc_html($ev->post_title),
        );
    }

    $create_url = esc_url(admin_url('post-new.php?post_type=mec_esb'));
    ?>
    <script>
    (function($) {

        var builders  = <?php echo wp_json_encode($builders_json); ?>;
        var events    = <?php echo wp_json_encode($events_json); ?>;
        var isBuilder = <?php echo $is_builder ? 'true' : 'false'; ?>;
        var selBuild  = '<?php echo esc_js($sel_builder); ?>';
        var selModal  = '<?php echo esc_js($sel_modal); ?>';
        var selEvent  = '<?php echo esc_js($sel_event); ?>';
        var createUrl = '<?php echo esc_js($create_url); ?>';

        function qphBuildOptions(arr, selected) {
            var html = '';
            $.each(arr, function(i, item) {
                var sel = (String(item.id) === String(selected))
                    ? ' selected="selected"' : '';
                html += '<option value="' + item.id + '"' + sel + '>'
                     + item.title + '</option>';
            });
            return html;
        }

        function qphInjectOptions() {

            // ✅ MEC ya creó el radio button via filtro
            // Solo necesitamos añadir las opciones adicionales
            var $builderRadio = $('#mec_settings_single_style_builder');

            if ($builderRadio.length === 0) {
                // Buscar radio con value="builder"
                $builderRadio = $('input[name="mec[settings][single_single_style]"][value="builder"]');
            }

            if ($builderRadio.length === 0) {
                console.log('[QPH-ESB] Radio builder no encontrado todavía');
                return false;
            }

            console.log('[QPH-ESB] ✅ Radio builder encontrado');

            // Si ya inyectamos las opciones, salir
            if ($('#mec_settings_single_event_single_default_builder_wrap').length > 0) {
                console.log('[QPH-ESB] Opciones ya inyectadas');
                qphBindToggle();
                return true;
            }

            // Construir opciones
            var showStyle   = isBuilder ? '' : 'display:none;';
            var builderOpts = qphBuildOptions(builders, selBuild);
            var modalOpts   = qphBuildOptions(builders, selModal);
            var eventOpts   = qphBuildOptions(events, selEvent);

            var noTpl = builders.length === 0
                ? '<div class="mec-col-12">Please Create New Design '
                  + '<a href="' + createUrl + '" class="taxonomy-add-new">Create new</a></div>'
                : '';

            var singleSel = builders.length > 0
                ? '<label class="mec-col-3" for="mec_settings_single_event_single_default_builder">'
                  + 'Default Builder for Single Event</label>'
                  + '<div class="mec-col-9">'
                  + '<select id="mec_settings_single_event_single_default_builder"'
                  + ' name="mec[settings][single_single_default_builder]">'
                  + builderOpts + '</select></div>'
                : noTpl;

            var modalSel = builders.length > 0
                ? '<label class="mec-col-3" for="mec_settings_single_event_single_modal_default_builder">'
                  + 'Default Builder for Modal View</label>'
                  + '<div class="mec-col-9">'
                  + '<select id="mec_settings_single_event_single_modal_default_builder"'
                  + ' name="mec[settings][single_modal_default_builder]">'
                  + modalOpts + '</select></div>'
                : noTpl;

            var optionsHtml = ''

                // Default Builder for Single
                + '<div class="mec-form-row"'
                + ' id="mec_settings_single_event_single_default_builder_wrap"'
                + ' style="' + showStyle + '">'
                + singleSel
                + '</div>'

                // Default Builder for Modal
                + '<div class="mec-form-row"'
                + ' id="mec_settings_single_event_single_modal_default_builder_wrap"'
                + ' style="' + showStyle + '">'
                + modalSel
                + '</div>'

                // Custom Event For Set Settings
                + '<div class="mec-form-row"'
                + ' id="mec_settings_custom_event_for_set_settings_wrap"'
                + ' style="' + showStyle + '">'
                + '<label class="mec-col-3" for="mec_settings_custom_event_for_set_settings">'
                + 'Custom Event For Set Settings</label>'
                + '<div class="mec-col-9">'
                + '<select id="mec_settings_custom_event_for_set_settings"'
                + ' name="mec[settings][custom_event_for_set_settings]">'
                + eventOpts + '</select>'
                + '<span class="mec-tooltip">'
                + '<div class="box left">'
                + '<h5 class="title">Default Single Event Template on Elementor</h5>'
                + '<div class="content"><p>Choose your event for single builder addon.</p></div>'
                + '</div>'
                + '<i class="dashicons-before dashicons-editor-help"></i>'
                + '</span></div>'
                + '</div>';

            // Insertar después del radio button de builder
            $builderRadio.closest('.mec-form-row').after(optionsHtml);

            console.log('[QPH-ESB] ✅ Opciones adicionales inyectadas');
            qphBindToggle();
            return true;
        }

        function qphBindToggle() {
            $(document).off('change.qphesb');
            $(document).on(
                'change.qphesb',
                'input[name="mec[settings][single_single_style]"]',
                function() {
                    var val = $(this).val();
                    var $w = $(
                        '#mec_settings_single_event_single_default_builder_wrap,'
                        + '#mec_settings_single_event_single_modal_default_builder_wrap,'
                        + '#mec_settings_custom_event_for_set_settings_wrap'
                    );
                    val === 'builder' ? $w.slideDown(300) : $w.slideUp(300);
                }
            );
        }

        // Reintentos
        var attempts = 0;
        function tryInject() {
            attempts++;
            if (qphInjectOptions()) return;
            if (attempts < 30) setTimeout(tryInject, 300);
        }

        $(document).ready(function() {
            console.log('[QPH-ESB] v4.0 iniciado');
            tryInject();

            // Observer para tabs dinámicos
            var observer = new MutationObserver(function() {
                var $radio = $('input[name="mec[settings][single_single_style]"][value="builder"]');
                if ($radio.length > 0 && $('#mec_settings_single_event_single_default_builder_wrap').length === 0) {
                    attempts = 0;
                    tryInject();
                }
            });

            observer.observe(
                document.getElementById('wpbody-content') || document.body,
                { childList: true, subtree: true }
            );

            // Click en tabs
            $(document).on('click', '.mec-settings-menu a, [data-id]', function() {
                setTimeout(function() {
                    attempts = 0;
                    tryInject();
                }, 400);
            });
        });

    })(jQuery);
    </script>
    <?php
     }

    // ============================================
    // POPUP INICIAL
    // ============================================

    public function show_setup_popup() {
        if (get_option('qph_esb_setup_done')) return;

        $screen = get_current_screen();
        if (!$screen) return;

        $is_mec = strpos($screen->id, 'mec') !== false
               || $screen->post_type === 'mec_esb'
               || (isset($_GET['page']) && strpos(
                   sanitize_text_field($_GET['page']), 'MEC') !== false);

        if (!$is_mec) return;

        $builders = $this->get_all_templates();
        ?>
        <div id="qph-esb-setup-popup">
            <div class="qph-esb-overlay"></div>
            <div class="qph-esb-modal">
                <div class="qph-esb-modal-header">
                    <h3>Select Single Event Style</h3>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <div class="qph-esb-modal-body">
                        <input type="hidden" name="action" value="qph_esb_apply_style_direct" />
                        <?php wp_nonce_field('qph_esb_setup', '_wpnonce'); ?>

                        <div class="qph-esb-option">
                            <label>
                                <input type="radio" name="qph_style" value="builder" checked />
                                <strong>Builder</strong>
                            </label>
                        </div>

                        <?php if (!empty($builders)) : ?>
                            <div style="margin:15px 0;">
                                <select name="template_id" style="width:100%;padding:8px;">
                                    <?php foreach ($builders as $b) : ?>
                                        <option value="<?php echo esc_attr($b->ID); ?>">
                                            <?php echo esc_html($b->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="template_id" value="0" />
                            <p style="color:#666;font-size:13px;">
                                <?php _e('No hay templates aún. Puedes crear uno después.', 'qph-single-builder'); ?>
                            </p>
                        <?php endif; ?>

                        <p style="color:#999;font-size:12px;margin-top:15px;">
                            If you are using QPH Single Builder for the first time,
                            simply ignore this pop-up.
                        </p>
                    </div>
                    <div class="qph-esb-modal-footer">
                        <button type="submit" class="button button-primary">Apply</button>
                        <button type="submit" name="action"
                                value="qph_esb_skip_setup_direct" class="button">
                            Skip
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <style>
            #qph-esb-setup-popup{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center}
            .qph-esb-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55)}
            .qph-esb-modal{position:relative;background:#fff;border-radius:10px;width:90%;max-width:460px;box-shadow:0 15px 50px rgba(0,0,0,.25);overflow:hidden}
            .qph-esb-modal-header{background:#0073aa;padding:18px 24px}
            .qph-esb-modal-header h3{margin:0;color:#fff;font-size:17px}
            .qph-esb-modal-body{padding:24px}
            .qph-esb-option{background:#f0f7ff;border:2px solid #0073aa;border-radius:6px;padding:12px 15px;margin-bottom:12px}
            .qph-esb-modal-footer{padding:14px 24px;background:#f7f7f7;border-top:1px solid #e0e0e0;text-align:right;display:flex;gap:10px;justify-content:flex-end}
        </style>
        <?php
    }

    public function handle_direct_apply() {
        check_admin_referer('qph_esb_setup', '_wpnonce');

        $tid  = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $opts = get_option('mec_options', array());

        if (!is_array($opts)) $opts = array();
        if (!isset($opts['settings'])) $opts['settings'] = array();

        $opts['settings']['single_single_style'] = 'builder';
        if ($tid > 0) {
            $opts['settings']['single_single_default_builder'] = $tid;
            $opts['settings']['single_modal_default_builder']  = $tid;
        }

        update_option('mec_options', $opts);
        update_option('qph_esb_setup_done', '1');
        $this->log('Setup aplicado - template: ' . $tid);

        wp_redirect(admin_url('admin.php?page=qph-event-builder'));
        exit;
    }

    public function handle_direct_skip() {
        check_admin_referer('qph_esb_setup', '_wpnonce');
        update_option('qph_esb_setup_done', '1');
        wp_redirect(admin_url('admin.php?page=MEC-settings'));
        exit;
    }

    // ============================================
    // METABOX
    // ============================================

    public function add_event_metabox() {
        add_meta_box(
            'qph_esb_tpl',
            'QPH Template',
            array($this, 'render_event_metabox'),
            'mec-events',
            'side'
        );
    }

    public function render_event_metabox($post) {
        $builders = $this->get_all_templates();
        $s = get_post_meta($post->ID, 'single_design_page', true);
        $m = get_post_meta($post->ID, 'single_modal_design_page', true);
        wp_nonce_field('qph_esb_meta', 'qph_esb_nonce');
        ?>
        <p>
            <label><strong><?php _e('Template Single:', 'qph-single-builder'); ?></strong></label>
            <select name="mec[single_design_page]" style="width:100%;margin-top:4px">
                <option value=""><?php _e('-- Por defecto --', 'qph-single-builder'); ?></option>
                <?php foreach ($builders as $b) : ?>
                    <option value="<?php echo esc_attr($b->ID); ?>"
                        <?php selected($s, $b->ID); ?>>
                        <?php echo esc_html($b->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><strong><?php _e('Template Modal:', 'qph-single-builder'); ?></strong></label>
            <select name="mec[single_modal_design_page]" style="width:100%;margin-top:4px">
                <option value=""><?php _e('-- Por defecto --', 'qph-single-builder'); ?></option>
                <?php foreach ($builders as $b) : ?>
                    <option value="<?php echo esc_attr($b->ID); ?>"
                        <?php selected($m, $b->ID); ?>>
                        <?php echo esc_html($b->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function save_event_meta($pid) {
        if (!isset($_POST['qph_esb_nonce'])
            || !wp_verify_nonce($_POST['qph_esb_nonce'], 'qph_esb_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $mec = isset($_POST['mec']) ? $_POST['mec'] : array();
        if (isset($mec['single_design_page'])) {
            update_post_meta($pid, 'single_design_page',
                sanitize_text_field($mec['single_design_page']));
        }
        if (isset($mec['single_modal_design_page'])) {
            update_post_meta($pid, 'single_modal_design_page',
                sanitize_text_field($mec['single_modal_design_page']));
        }
    }

    // ============================================
    // CATEGORÍAS
    // ============================================

    public function category_add_fields() {
        $bs = $this->get_all_templates();
        if (empty($bs)) return;
        foreach (array(
            'single_design_page'       => 'Template Single (QPH)',
            'single_modal_design_page' => 'Template Modal (QPH)',
        ) as $k => $l) {
            echo '<div class="form-field"><label>' . esc_html($l) . '</label>';
            echo '<select name="' . esc_attr($k) . '">';
            echo '<option value="">' . __('-- Por defecto --', 'qph-single-builder') . '</option>';
            foreach ($bs as $b) {
                echo '<option value="' . esc_attr($b->ID) . '">'
                    . esc_html($b->post_title) . '</option>';
            }
            echo '</select></div>';
        }
    }

    public function category_edit_fields($term) {
        $bs = $this->get_all_templates();
        if (empty($bs)) return;
        foreach (array(
            'single_design_page'       => 'Template Single (QPH)',
            'single_modal_design_page' => 'Template Modal (QPH)',
        ) as $k => $l) {
            $v = get_term_meta($term->term_id, $k, true);
            echo '<tr class="form-field"><th><label>' . esc_html($l) . '</label></th><td>';
            echo '<select name="' . esc_attr($k) . '">';
            echo '<option value="">' . __('-- Por defecto --', 'qph-single-builder') . '</option>';
            foreach ($bs as $b) {
                echo '<option value="' . esc_attr($b->ID) . '"'
                    . selected($v, $b->ID, false) . '>'
                    . esc_html($b->post_title) . '</option>';
            }
            echo '</select></td></tr>';
        }
    }

    public function save_category_meta($tid) {
        foreach (array('single_design_page', 'single_modal_design_page') as $k) {
            if (isset($_POST[$k])) {
                update_term_meta($tid, $k, sanitize_text_field($_POST[$k]));
            }
        }
    }

    // ============================================
    // ACTIVACIÓN
    // ============================================

    public function activate() {
        $this->register_post_type();

        $cpt = get_option('elementor_cpt_support', array('page', 'post'));
        if (!is_array($cpt)) $cpt = array('page', 'post');
        if (!in_array('mec_esb', $cpt)) {
            $cpt[] = 'mec_esb';
            update_option('elementor_cpt_support', $cpt);
        }

        delete_option('qph_esb_setup_done');
        flush_rewrite_rules();
        $this->log('Plugin activado v4.0.0');
    }

    public function deactivate() {
        flush_rewrite_rules();
        $this->log('Plugin desactivado');
    }
}

QPH_Single_Builder_MEC::get_instance();