<?php
/**
 * Plugin Name: QPH Single Builder for MEC
 * Plugin URI: https://quepasahoy.com.co
 * Description: Usa Elementor para diseñar eventos de MEC. Optimizado sin bugs de memoria.
 * Version: 3.4.0
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

define('QPH_ESB_VERSION', '3.4.0');
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
        $this->log('=== QPH ESB v3.4.0 Init ===');

        if (!class_exists('MEC') && !defined('MEC_VERSION')) {
            $this->log('ERROR: MEC no activo');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>QPH Single Builder:</strong> Requiere Modern Events Calendar.</p></div>';
            });
            return;
        }

        if (!did_action('elementor/loaded')) {
            $this->log('ERROR: Elementor no activo');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>QPH Single Builder:</strong> Requiere Elementor.</p></div>';
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
    // ============================================

    private function setup_elementor() {
        add_action('elementor/init', array($this, 'register_elementor_cpt'));
        add_filter('template_include', array($this, 'elementor_canvas_template'), 99998);
        add_action('init', function() {
            add_post_type_support('mec_esb', 'elementor');
        }, 999);
        add_action('elementor/preview/enqueue_styles', array($this, 'preview_styles'));
        add_action('elementor/editor/after_enqueue_styles', array($this, 'editor_styles'));
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'register_widget_category'));
    }

    public function register_elementor_cpt() {
        $cpt = get_option('elementor_cpt_support', array('page', 'post'));
        if (!is_array($cpt)) $cpt = array('page', 'post');
        if (!in_array('mec_esb', $cpt)) {
            $cpt[] = 'mec_esb';
            update_option('elementor_cpt_support', $cpt);
            $this->log('mec_esb añadido a Elementor CPT support');
        }
    }

    public function elementor_canvas_template($template) {
        if (!is_singular('mec_esb')) return $template;
        if (!class_exists('\Elementor\Plugin')) return $template;

        $document = \Elementor\Plugin::$instance->documents->get(get_the_ID());
        if ($document && $document->is_built_with_elementor()) {
            $canvas = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
            if (file_exists($canvas)) {
                $this->log('Usando canvas de Elementor para mec_esb');
                return $canvas;
            }
        }

        $fallback = QPH_ESB_PATH . 'templates/single-mec_esb.php';
        if (file_exists($fallback)) return $fallback;

        return $template;
    }

    public function preview_styles() {
        if (\Elementor\Plugin::$instance->preview->is_preview_mode() && get_post_type(get_the_ID()) === 'mec_esb') {
            add_filter('body_class', function($c) {
                $c[] = 'mec-single-event';
                $c[] = 'mec-wrap';
                return $c;
            });
        }
    }

    public function editor_styles() {
        if (get_post_type(get_the_ID()) === 'mec_esb') {
            wp_enqueue_style('mec-font-icons');
        }
    }

    // ============================================
    // HOOKS
    // ============================================

    private function setup_hooks() {
    // CPT
    add_action('init', array($this, 'register_post_type'), 5);

    // MENÚ - Múltiples hooks para asegurar que aparezca
    add_action('after_mec_submenu_action', array($this, 'add_submenu'));
    add_action('admin_menu', array($this, 'add_submenu_fallback'), 9999);

    // MEC INTEGRACIÓN
    add_action('mec_esb_content', array($this, 'render_builder_content'), 10, 1);
    add_action('mec-ajax-load-single-page-before', array($this, 'render_builder_modal'), 10, 1);
    add_filter('mec_filter_single_style', array($this, 'filter_single_style'), 1);

    // SETTINGS - Solo JS, sin hook PHP duplicado
    // add_action('mec_single_style_setting_after', array($this, 'add_settings'), 10, 1);
    add_action('admin_footer', array($this, 'inject_settings_ui'), 99);

    // POPUP
    add_action('admin_enqueue_scripts', array($this, 'show_setup_popup'));
    add_action('admin_post_qph_esb_apply_style_direct', array($this, 'handle_direct_apply'));
    add_action('admin_post_qph_esb_skip_setup_direct', array($this, 'handle_direct_skip'));

    // META
    add_action('add_meta_boxes', array($this, 'add_event_metabox'));
    add_action('save_post_mec-events', array($this, 'save_event_meta'), 10, 1);

    // CATEGORÍAS
    add_action('mec_category_add_form_fields', array($this, 'category_add_fields'));
    add_action('mec_category_edit_form_fields', array($this, 'category_edit_fields'), 10, 2);
    add_action('created_mec_category', array($this, 'save_category_meta'));
    add_action('edited_mec_category', array($this, 'save_category_meta'));

    // ELEMENTOR
    add_filter('elementor/utils/get_public_post_types', array($this, 'register_elementor_post_type'));
    add_filter('mec_event_supports', array($this, 'add_elementor_support'));
    add_action('save_post_mec_esb', array($this, 'clear_elementor_cache'));

    // MISC
    add_filter('post_row_actions', array($this, 'remove_view_action'), 10, 2);

    // DIAGNÓSTICO DEL MENÚ
    add_action('admin_menu', array($this, 'diagnose_menu'), 99999);

    $this->log('Hooks registrados OK');
}

public function diagnose_menu() {
    global $submenu;
    $available = array_keys($submenu);
    $this->log('Menús disponibles: ' . implode(', ', $available));

    foreach ($available as $slug) {
        if (stripos($slug, 'mec') !== false || stripos($slug, 'calendar') !== false) {
            $this->log('Menú MEC detectado: ' . $slug);
            foreach ($submenu[$slug] as $item) {
                $this->log('  → ' . (isset($item[0]) ? $item[0] : 'N/A') . ' | ' . (isset($item[2]) ? $item[2] : 'N/A'));
            }
        }
    }
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
                'all_items'     => 'Todos los Templates',
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

    // ============================================
    // MENÚ
    // ============================================

    public function add_submenu() {
    $this->log('add_submenu via after_mec_submenu_action');
    $this->register_submenu();
}

public function add_submenu_fallback() {
    $this->log('add_submenu_fallback via admin_menu');
    $this->register_submenu();
}

private function register_submenu() {
    global $submenu;

    $parent_slug = 'mec-intro';
    $menu_slug   = 'qph-event-builder';

    // Verificar si ya existe
    if (isset($submenu[$parent_slug])) {
        foreach ($submenu[$parent_slug] as $item) {
            if (isset($item[2]) && $item[2] === $menu_slug) {
                $this->log('Submenu QPH ya existe');
                return;
            }
        }
    }

    // Añadir submenu con slug propio
    add_submenu_page(
        $parent_slug,
        __('QPH Event Builder', 'qph-single-builder'),
        __('QPH Event Builder', 'qph-single-builder'),
        'edit_posts',
        $menu_slug,
        array($this, 'render_admin_page')
    );

    $this->log('Submenu QPH añadido correctamente');
}

/**
 * Página de administración del QPH Event Builder
 * Redirige a la lista de posts del CPT mec_esb
 */
public function render_admin_page() {
    // Obtener acción actual
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    switch ($action) {
        case 'new':
            // Redirigir a crear nuevo template
            wp_redirect(admin_url('post-new.php?post_type=mec_esb'));
            exit;

        default:
            // Mostrar lista de templates
            $templates = $this->get_all_templates();
            $mec_options = get_option('mec_options', array());
            $current_template = isset($mec_options['settings']['single_single_default_builder'])
                ? $mec_options['settings']['single_single_default_builder']
                : 0;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">
                    QPH Event Builder
                </h1>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
                   class="page-title-action">
                    Añadir Nuevo
                </a>
                <hr class="wp-header-end">

                <?php if (isset($_GET['qph_applied'])) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p>✅ Template aplicado correctamente.</p>
                    </div>
                <?php endif; ?>

                <?php if (empty($templates)) : ?>
                    <div class="notice notice-warning">
                        <p>
                            No hay templates creados aún.
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>">
                                Crea tu primer template
                            </a>
                        </p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $tpl) : ?>
                                <tr <?php echo ($tpl->ID == $current_template) ? 'style="background:#e8f5e9;"' : ''; ?>>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($tpl->ID)); ?>">
                                                <?php echo esc_html($tpl->post_title); ?>
                                            </a>
                                        </strong>
                                        <?php if ($tpl->ID == $current_template) : ?>
                                            <span class="dashicons dashicons-yes" style="color:green;" title="Template activo"></span>
                                            <em style="color:green;font-size:12px;">Template activo</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($tpl->post_status)); ?></td>
                                    <td><?php echo esc_html(get_the_date('d/m/Y', $tpl->ID)); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($tpl->ID)); ?>"
                                           class="button button-small">
                                            Editar con Elementor
                                        </a>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $tpl->ID . '&action=trash')); ?>"
                                           class="button button-small"
                                           style="color:red;"
                                           onclick="return confirm('¿Eliminar este template?')">
                                            Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top:15px;">
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
                           class="button button-primary">
                            + Crear Nuevo Template
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=MEC-settings')); ?>"
                           class="button"
                           style="margin-left:10px;">
                            ⚙️ Ir a MEC Settings
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <?php
            break;
    }
}

    // ============================================
    // RENDERIZADO SINGLE
    // ============================================

    public function render_builder_content($event) {
    $this->log('render_builder_content - Event ID: ' . $event->ID);

    $style = self::get_event_template_style($event->ID);
    $this->log('Estilo: ' . $style);

    if ($style !== 'builder') return;
    if (!class_exists('\Elementor\Plugin')) return;

    global $eventt;
    $eventt = $event;

    $template_id = $this->resolve_template_id($event->ID, 'single');
    $this->log('Template ID: ' . $template_id);

    if (!$template_id) {
        echo '<div class="qph-esb-notice">Selecciona un template en MEC Settings.</div>';
        return;
    }

    // ============================================
    // SOLUCIÓN: Configurar el post global
    // correctamente antes de llamar a Elementor
    // ============================================
    global $post, $wp_query;

    // Guardar estado original
    $original_post     = $post;
    $original_id       = get_the_ID();
    $original_query    = $wp_query;

    // Configurar el post del TEMPLATE para Elementor
    $template_post = get_post($template_id);
    if (!$template_post) {
        $this->log('ERROR: Template post no encontrado');
        return;
    }

    // Cambiar el post global al template
    $post = $template_post;
    setup_postdata($post);

    // Forzar que Elementor cargue los assets
    \Elementor\Plugin::$instance->frontend->enqueue_styles();
    \Elementor\Plugin::$instance->frontend->enqueue_scripts();

    // Renderizar con el post global correcto
    $content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display(
        $template_id,
        true
    );

    $this->log('Content length (método 1): ' . strlen($content));

    // Si sigue vacío, intentar con el documento de Elementor
    if (empty($content)) {
        $this->log('Intentando via documento Elementor...');

        $document = \Elementor\Plugin::$instance->documents->get_doc_or_auto_save($template_id);

        if ($document) {
            $this->log('Documento encontrado: ' . get_class($document));
            $content = $document->get_content();
            $this->log('Content length (método 2): ' . strlen($content));
        }
    }

    // Si sigue vacío, intentar renderizar manualmente
    if (empty($content)) {
        $this->log('Intentando renderizado manual...');

        $elementor_data = get_post_meta($template_id, '_elementor_data', true);
        $data = json_decode($elementor_data, true);

        if (!empty($data)) {
            ob_start();
            \Elementor\Plugin::$instance->frontend->add_content_filter();
            echo \Elementor\Plugin::$instance->frontend->get_builder_content($template_id);
            \Elementor\Plugin::$instance->frontend->remove_content_filter();
            $content = ob_get_clean();
            $this->log('Content length (método 3): ' . strlen($content));
        }
    }

    // Restaurar el post original del EVENTO
    $post = $original_post;

    if ($original_post) {
        setup_postdata($original_post);
    } else {
        wp_reset_postdata();
    }

    // Verificar contenido final
    if (empty($content)) {
        $this->log('ERROR FINAL: Todos los métodos devolvieron vacío');
        echo '<div class="qph-esb-notice">'
            . '<p>Error renderizando template. '
            . '<a href="' . esc_url(get_edit_post_link($template_id)) . '">Verificar template</a>'
            . '</p></div>';
        return;
    }

    // Output final
    echo '<div class="mec-wrap mec-single-builder-wrap qph-esb-wrapper">';
    echo '<div class="row mec-single-event"><div class="wn-single">';
    echo $content;
    echo '</div></div></div>';

    $this->load_template_css($template_id);
    $this->log('✅ Renderizado exitoso - Length: ' . strlen($content));
    }
    // ============================================
    // RENDERIZADO MODAL AJAX
    // ============================================

    public function render_builder_modal($event_id) {
        $this->log('render_builder_modal - Event ID: ' . $event_id);

        if (!class_exists('\Elementor\Plugin')) return;

        $style = self::get_event_template_style($event_id);
        if ($style !== 'builder') return;

        $template_id = $this->resolve_template_id($event_id, 'modal');
        if (!$template_id || !get_post($template_id)) return;

        global $post;
        $event_post = get_post($event_id);
        if (!$event_post) return;

        $post = $event_post;
        setup_postdata($post);

        echo '<div class="mec-wrap mec-single-builder-wrap clearfix">';
        echo '<div class="row mec-single-event"><div class="wn-single">';
        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id, true);
        echo '</div></div></div>';

        $this->load_template_css($template_id);
        wp_reset_postdata();

        $this->log('Modal renderizado OK');
        die();
    }

    // ============================================
    // HELPERS
    // ============================================

    public static function get_event_template_style($event_id) {
        $opts = get_option('mec_options', array());

        // Verificar si MEC tiene habilitado el estilo por evento
        $per_event = isset($opts['settings']['style_per_event']) ? $opts['settings']['style_per_event'] : '';
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
        $s = isset($opts['settings']) ? $opts['settings'] : array();

        $mk = ($type === 'modal') ? 'single_modal_design_page' : 'single_design_page';
        $dk = ($type === 'modal') ? 'single_modal_default_builder' : 'single_single_default_builder';

        // 1. Template específico del evento
        $id = (int) get_post_meta($event_id, $mk, true);
        if ($id > 0 && get_post($id)) {
            $this->log("Template por evento: $id");
            return $id;
        }

        // 2. Template por categoría
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

        // 3. Template global
        $gid = isset($s[$dk]) ? (int) $s[$dk] : 0;
        if ($gid > 0 && get_post($gid)) {
            $this->log("Template global: $gid");
            return $gid;
        }

        // 4. Fallback modal → single
        if ($type === 'modal') {
            $sid = isset($s['single_single_default_builder']) ? (int) $s['single_single_default_builder'] : 0;
            if ($sid > 0 && get_post($sid)) {
                $this->log("Template fallback single para modal: $sid");
                return $sid;
            }
        }

        // 5. Cualquier template disponible
        $any = get_posts(array(
            'post_type'      => 'mec_esb',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ));
        if (!empty($any)) {
            $this->log("Template fallback (cualquiera): " . $any[0]);
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
            $new_style = self::get_event_template_style(get_the_ID());
            $this->log("filter_single_style: $style → $new_style");
            return $new_style;
        }
        return $style;
    }

    public function add_elementor_support($supports) {
        $supports[] = 'elementor';
        return $supports;
    }

    public function register_elementor_post_type($pt) {
        $pt['mec_esb'] = 'QPH Event Builder';
        $pt['mec-events'] = 'Eventos MEC';
        return $pt;
    }

    public function clear_elementor_cache($pid) {
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::instance()->files_manager->clear_cache();
        }
    }

    // ============================================
    // MEC SETTINGS - HOOK NATIVO
    // ============================================

    public function add_settings($mec) {
        $this->log('add_settings ejecutado via mec_single_style_setting_after');

        $settings = isset($mec->settings) ? $mec->settings : array();
        $current = isset($settings['single_single_style']) ? $settings['single_single_style'] : '';
        $builders = $this->get_all_templates();
        $is_builder = ($current === 'builder');

        $events = get_posts(array(
            'post_type'      => 'mec-events',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $v_selected = isset($settings['custom_event_for_set_settings'])
            ? (int) $settings['custom_event_for_set_settings']
            : 0;

        $selected_builder = isset($settings['single_single_default_builder'])
            ? $settings['single_single_default_builder']
            : '';

        $selected_modal = isset($settings['single_modal_default_builder'])
            ? $settings['single_modal_default_builder']
            : '';
        ?>

        <div class="mec-form-row">
            <label class="mec-col-3" for="mec_settings_single_style_builder">
                <input type="radio"
                       name="mec[settings][single_single_style]"
                       id="mec_settings_single_style_builder"
                       value="builder"
                       <?php echo $is_builder ? 'checked="checked"' : ''; ?> />
                <?php _e('QPH Single Builder (Elementor)', 'qph-single-builder'); ?>
            </label>
        </div>

        <div class="mec-form-row"
             id="mec_settings_single_event_single_default_builder_wrap"
             style="<?php echo $is_builder ? '' : 'display:none;'; ?>">
            <?php if (empty($builders)) : ?>
                <div class="mec-col-12">
                    <?php _e('Please Create New Design for Single Event Page', 'qph-single-builder'); ?>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
                       class="taxonomy-add-new">
                        <?php _e('Create new', 'qph-single-builder'); ?>
                    </a>
                </div>
            <?php else : ?>
                <label class="mec-col-3"
                       for="mec_settings_single_event_single_default_builder">
                    <?php _e('Default Builder for Single Event', 'qph-single-builder'); ?>
                </label>
                <div class="mec-col-9">
                    <select id="mec_settings_single_event_single_default_builder"
                            name="mec[settings][single_single_default_builder]">
                        <?php foreach ($builders as $b) : ?>
                            <option value="<?php echo esc_attr($b->ID); ?>"
                                <?php selected($selected_builder, $b->ID); ?>>
                                <?php echo esc_html($b->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="mec-form-row"
             id="mec_settings_single_event_single_modal_default_builder_wrap"
             style="<?php echo $is_builder ? '' : 'display:none;'; ?>">
            <?php if (empty($builders)) : ?>
                <div class="mec-col-12">
                    <?php _e('Please Create New Design for Single Event Page', 'qph-single-builder'); ?>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mec_esb')); ?>"
                       class="taxonomy-add-new">
                        <?php _e('Create new', 'qph-single-builder'); ?>
                    </a>
                </div>
            <?php else : ?>
                <label class="mec-col-3"
                       for="mec_settings_single_event_single_modal_default_builder">
                    <?php _e('Default Builder for Modal View', 'qph-single-builder'); ?>
                </label>
                <div class="mec-col-9">
                    <select id="mec_settings_single_event_single_modal_default_builder"
                            name="mec[settings][single_modal_default_builder]">
                        <?php foreach ($builders as $b) : ?>
                            <option value="<?php echo esc_attr($b->ID); ?>"
                                <?php selected($selected_modal, $b->ID); ?>>
                                <?php echo esc_html($b->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="mec-form-row"
             id="mec_settings_custom_event_for_set_settings_wrap"
             style="<?php echo $is_builder ? '' : 'display:none;'; ?>">
            <label class="mec-col-3"
                   for="mec_settings_custom_event_for_set_settings">
                <?php _e('Custom Event For Set Settings', 'qph-single-builder'); ?>
            </label>
            <div class="mec-col-9">
                <select id="mec_settings_custom_event_for_set_settings"
                        name="mec[settings][custom_event_for_set_settings]">
                    <?php foreach ($events as $ev) : ?>
                        <option value="<?php echo esc_attr($ev->ID); ?>"
                            <?php selected($v_selected, $ev->ID); ?>>
                            <?php echo esc_html($ev->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="mec-tooltip">
                    <div class="box left">
                        <h5 class="title">
                            <?php _e('Default Single Event Template on Elementor', 'qph-single-builder'); ?>
                        </h5>
                        <div class="content">
                            <p><?php _e('Choose your event for single builder addon.', 'qph-single-builder'); ?></p>
                        </div>
                    </div>
                    <i title="" class="dashicons-before dashicons-editor-help"></i>
                </span>
            </div>
        </div>

        <?php
    }

    // ============================================
    // INYECCIÓN JS EN SETTINGS
    // Esta función inyecta el radio button via JS
    // si el hook nativo no funcionó
    // ============================================

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
    jQuery(document).ready(function($) {

        var builders  = <?php echo wp_json_encode($builders_json); ?>;
        var events    = <?php echo wp_json_encode($events_json); ?>;
        var isBuilder = <?php echo $is_builder ? 'true' : 'false'; ?>;
        var selBuild  = '<?php echo esc_js($sel_builder); ?>';
        var selModal  = '<?php echo esc_js($sel_modal); ?>';
        var selEvent  = '<?php echo esc_js($sel_event); ?>';
        var createUrl = '<?php echo esc_js($create_url); ?>';

        console.log('[QPH-ESB] v3.5 iniciado');

        // ================================================
        // PASO 1: Eliminar HTML del hook PHP (fuera de lugar)
        // ================================================
        $('#mec_settings_single_style_builder').closest('.mec-form-row').remove();
        $('#mec_settings_single_event_single_default_builder_wrap').remove();
        $('#mec_settings_single_event_single_modal_default_builder_wrap').remove();
        $('#mec_settings_custom_event_for_set_settings_wrap').remove();
        $('#qph-esb-radio-row').remove();

        console.log('[QPH-ESB] HTML anterior limpiado');

        // ================================================
        // PASO 2: Verificar que existe el contenedor de MEC
        // El plugin original usa: #mec_settings_single_event_style
        // ================================================
        var $mecStyleSection = $('#mec_settings_single_event_style');

        console.log('[QPH-ESB] #mec_settings_single_event_style encontrado: ' 
            + $mecStyleSection.length);

        // Si no existe ese ID, buscar por los radio buttons
        if ($mecStyleSection.length === 0) {
            var $radios = $('input[name="mec[settings][single_single_style]"]');
            console.log('[QPH-ESB] Radios encontrados: ' + $radios.length);

            if ($radios.length === 0) {
                console.log('[QPH-ESB] ERROR: No se encontró la sección de estilos');
                return;
            }

            // Usar el contenedor del último radio como referencia
            $mecStyleSection = $radios.last().closest('.mec-form-row');
            console.log('[QPH-ESB] Usando último radio como referencia');
        }

        // ================================================
        // PASO 3: Construir el HTML
        // ================================================
        function buildOptions(arr, selected) {
            var html = '';
            $.each(arr, function(i, item) {
                var sel = (String(item.id) === String(selected))
                    ? ' selected="selected"' : '';
                html += '<option value="' + item.id + '"' + sel + '>'
                     + item.title + '</option>';
            });
            return html;
        }

        var showStyle   = isBuilder ? '' : 'display:none;';
        var builderOpts = buildOptions(builders, selBuild);
        var modalOpts   = buildOptions(builders, selModal);
        var eventOpts   = buildOptions(events, selEvent);

        var noTplHtml = '<div class="mec-col-12">'
            + 'Please Create New Design for Single Event Page '
            + '<a href="' + createUrl + '" class="taxonomy-add-new">Create new</a>'
            + '</div>';

        var singleSelectHtml = builders.length > 0
            ? '<label class="mec-col-3"'
              +   ' for="mec_settings_single_event_single_default_builder">'
              +   'Default Builder for Single Event'
              + '</label>'
              + '<div class="mec-col-9">'
              +   '<select'
              +     ' id="mec_settings_single_event_single_default_builder"'
              +     ' name="mec[settings][single_single_default_builder]">'
              +     builderOpts
              +   '</select>'
              + '</div>'
            : noTplHtml;

        var modalSelectHtml = builders.length > 0
            ? '<label class="mec-col-3"'
              +   ' for="mec_settings_single_event_single_modal_default_builder">'
              +   'Default Builder for Modal View'
              + '</label>'
              + '<div class="mec-col-9">'
              +   '<select'
              +     ' id="mec_settings_single_event_single_modal_default_builder"'
              +     ' name="mec[settings][single_modal_default_builder]">'
              +     modalOpts
              +   '</select>'
              + '</div>'
            : noTplHtml;

        // ================================================
        // PASO 4: Insertar usando el mismo método
        // que usa el plugin original
        // ================================================

        // HTML del radio button
        var radioHtml = '<div class="mec-form-row" id="qph-esb-radio-row">'
            + '<label class="mec-col-3" for="mec_settings_single_style_builder">'
            +   '<input type="radio"'
            +     ' name="mec[settings][single_single_style]"'
            +     ' id="mec_settings_single_style_builder"'
            +     ' value="builder"'
            +     (isBuilder ? ' checked="checked"' : '')
            +   ' />'
            +   ' QPH Single Builder (Elementor)'
            + '</label>'
            + '</div>';

        // HTML de las opciones adicionales
        var optionsHtml = ''

            + '<div class="mec-form-row"'
            +     ' id="mec_settings_single_event_single_default_builder_wrap"'
            +     ' style="' + showStyle + '">'
            +   singleSelectHtml
            + '</div>'

            + '<div class="mec-form-row"'
            +     ' id="mec_settings_single_event_single_modal_default_builder_wrap"'
            +     ' style="' + showStyle + '">'
            +   modalSelectHtml
            + '</div>'

            + '<div class="mec-form-row"'
            +     ' id="mec_settings_custom_event_for_set_settings_wrap"'
            +     ' style="' + showStyle + '">'
            +   '<label class="mec-col-3"'
            +     ' for="mec_settings_custom_event_for_set_settings">'
            +     'Custom Event For Set Settings'
            +   '</label>'
            +   '<div class="mec-col-9">'
            +     '<select'
            +       ' id="mec_settings_custom_event_for_set_settings"'
            +       ' name="mec[settings][custom_event_for_set_settings]">'
            +       eventOpts
            +     '</select>'
            +     '<span class="mec-tooltip">'
            +       '<div class="box left">'
            +         '<h5 class="title">'
            +           'Default Single Event Template on Elementor'
            +         '</h5>'
            +         '<div class="content">'
            +           '<p>Choose your event for single builder addon.</p>'
            +         '</div>'
            +       '</div>'
            +       '<i title="" class="dashicons-before dashicons-editor-help"></i>'
            +     '</span>'
            +   '</div>'
            + '</div>';

        // ================================================
        // INSERTAR - Igual que el plugin original
        // Método 1: Usar #mec_settings_single_event_style
        // ================================================
        if ($('#mec_settings_single_event_style').length > 0) {
            // ✅ EXACTAMENTE como lo hace el plugin original
            $('#mec_settings_single_event_style').after(radioHtml + optionsHtml);
            console.log('[QPH-ESB] Insertado via #mec_settings_single_event_style');

        } else {
            // Fallback: insertar después del último radio
            var $lastRadioRow = $('input[name="mec[settings][single_single_style]"]')
                .last()
                .closest('.mec-form-row');
            $lastRadioRow.after(radioHtml + optionsHtml);
            console.log('[QPH-ESB] Insertado via fallback (último radio)');
        }

        // ================================================
        // PASO 5: Toggle de visibilidad
        // ================================================
        function toggleBuilderOptions() {
            var val = $(
                'input[name="mec[settings][single_single_style]"]:checked'
            ).val();

            console.log('[QPH-ESB] Estilo seleccionado: ' + val);

            var $wraps = $(
                '#mec_settings_single_event_single_default_builder_wrap,'
                + '#mec_settings_single_event_single_modal_default_builder_wrap,'
                + '#mec_settings_custom_event_for_set_settings_wrap'
            );

            if (val === 'builder') {
                $wraps.slideDown(300);
            } else {
                $wraps.slideUp(300);
            }
        }

        $(document).off(
            'change.qphesb',
            'input[name="mec[settings][single_single_style]"]'
        );
        $(document).on(
            'change.qphesb',
            'input[name="mec[settings][single_single_style]"]',
            toggleBuilderOptions
        );

        // Ejecutar al cargar
        toggleBuilderOptions();

        console.log('[QPH-ESB] Setup completado ✅');
    });
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
               || (isset($_GET['page']) && strpos(sanitize_text_field($_GET['page']), 'MEC') !== false);

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
                                <label style="display:block;margin-bottom:5px;font-weight:600;">
                                    <?php _e('Apply Template:', 'qph-single-builder'); ?>
                                </label>
                                <select name="template_id" style="width:100%;padding:8px;border-radius:4px;border:1px solid #ddd;">
                                    <?php foreach ($builders as $b) : ?>
                                        <option value="<?php echo esc_attr($b->ID); ?>">
                                            <?php echo esc_html($b->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="template_id" value="0" />
                            <p style="color:#666;font-size:13px;margin:10px 0;">
                                <?php _e('No hay templates aún. Puedes crear uno después en MEC → QPH Event Builder.', 'qph-single-builder'); ?>
                            </p>
                        <?php endif; ?>

                        <p style="color:#999;font-size:12px;margin-top:15px;line-height:1.5;">
                            If you are using the QPH Single Builder for MEC for the first time,
                            simply ignore this pop-up. However, if you have used it before,
                            you can select the template you previously created in this section
                            for the single event style.
                        </p>
                    </div>
                    <div class="qph-esb-modal-footer">
                        <button type="submit" class="button button-primary">Apply</button>
                        <button type="submit"
                                formaction="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                name="action"
                                value="qph_esb_skip_setup_direct"
                                class="button">
                            Skip
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <style>
        #qph-esb-setup-popup{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;}
        .qph-esb-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);}
        .qph-esb-modal{position:relative;background:#fff;border-radius:10px;width:90%;max-width:460px;box-shadow:0 15px 50px rgba(0,0,0,.25);overflow:hidden;}
        .qph-esb-modal-header{background:#0073aa;padding:18px 24px;}
        .qph-esb-modal-header h3{margin:0;color:#fff;font-size:17px;}
        .qph-esb-modal-body{padding:24px;}
        .qph-esb-option{background:#f0f7ff;border:2px solid #0073aa;border-radius:6px;padding:12px 15px;margin-bottom:12px;}
        .qph-esb-modal-footer{padding:14px 24px;background:#f7f7f7;border-top:1px solid #e0e0e0;text-align:right;display:flex;gap:10px;justify-content:flex-end;}
        </style>
        <?php
    }

    public function handle_direct_apply() {
        check_admin_referer('qph_esb_setup', '_wpnonce');

        $tid = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        $opts = get_option('mec_options', array());
        if (!is_array($opts)) $opts = array();
        if (!isset($opts['settings'])) $opts['settings'] = array();

        $opts['settings']['single_single_style'] = 'builder';
        if ($tid > 0) {
            $opts['settings']['single_single_default_builder'] = $tid;
            $opts['settings']['single_modal_default_builder'] = $tid;
        }

        update_option('mec_options', $opts);
        update_option('qph_esb_setup_done', '1');

        $this->log('Setup aplicado - builder + template: ' . $tid);

        wp_redirect(admin_url('admin.php?page=MEC-settings&qph_applied=1'));
        exit;
    }

    public function handle_direct_skip() {
        check_admin_referer('qph_esb_setup', '_wpnonce');
        update_option('qph_esb_setup_done', '1');
        $this->log('Setup omitido');
        wp_redirect(admin_url('admin.php?page=MEC-settings'));
        exit;
    }

    // ============================================
    // METABOX EN EVENTOS
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
                    <option value="<?php echo esc_attr($b->ID); ?>" <?php selected($s, $b->ID); ?>>
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
                    <option value="<?php echo esc_attr($b->ID); ?>" <?php selected($m, $b->ID); ?>>
                        <?php echo esc_html($b->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function save_event_meta($pid) {
        if (!isset($_POST['qph_esb_nonce']) || !wp_verify_nonce($_POST['qph_esb_nonce'], 'qph_esb_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $mec = isset($_POST['mec']) ? $_POST['mec'] : array();
        if (isset($mec['single_design_page'])) {
            update_post_meta($pid, 'single_design_page', sanitize_text_field($mec['single_design_page']));
        }
        if (isset($mec['single_modal_design_page'])) {
            update_post_meta($pid, 'single_modal_design_page', sanitize_text_field($mec['single_modal_design_page']));
        }
    }

    // ============================================
    // CATEGORÍAS
    // ============================================

    public function category_add_fields() {
        $bs = $this->get_all_templates();
        if (empty($bs)) return;
        foreach (array('single_design_page' => 'Template Single (QPH)', 'single_modal_design_page' => 'Template Modal (QPH)') as $k => $l) {
            echo '<div class="form-field"><label>' . esc_html($l) . '</label>';
            echo '<select name="' . esc_attr($k) . '"><option value="">-- Por defecto --</option>';
            foreach ($bs as $b) {
                echo '<option value="' . esc_attr($b->ID) . '">' . esc_html($b->post_title) . '</option>';
            }
            echo '</select></div>';
        }
    }

    public function category_edit_fields($term) {
        $bs = $this->get_all_templates();
        if (empty($bs)) return;
        foreach (array('single_design_page' => 'Template Single (QPH)', 'single_modal_design_page' => 'Template Modal (QPH)') as $k => $l) {
            $v = get_term_meta($term->term_id, $k, true);
            echo '<tr class="form-field"><th><label>' . esc_html($l) . '</label></th><td>';
            echo '<select name="' . esc_attr($k) . '"><option value="">-- Por defecto --</option>';
            foreach ($bs as $b) {
                echo '<option value="' . esc_attr($b->ID) . '"' . selected($v, $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
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
    // WIDGETS ELEMENTOR
    // ============================================

    public function register_widgets($wm) {
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
                    $wm->register(new $class());
                    $this->log("Widget registrado: $class");
                }
            } else {
                $this->log("Widget no encontrado: $path");
            }
        }
    }

    public function register_widget_category($em) {
        $em->add_category('qph_single_builder', array(
            'title' => 'QPH Event Builder',
            'icon'  => 'fa fa-calendar',
        ));
    }
     
     
     
    // ============================================
    // ACTIVACIÓN / DESACTIVACIÓN
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
        $this->log('Plugin activado');
    }

    public function deactivate() {
        flush_rewrite_rules();
        $this->log('Plugin desactivado');
    }
}

QPH_Single_Builder_MEC::get_instance();