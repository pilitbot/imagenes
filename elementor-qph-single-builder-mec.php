<?php
/**
 * Plugin Name: Elementor QPH Single Builder for MEC
 * Description: Widgets de Elementor para construir el single de eventos de Modern Events Calendar.
 * Version: 1.0.3
 * Author: QPH
 * Text Domain: elementor-qph-single-builder-mec
 */

if (!defined('ABSPATH')) exit;

define('QPH_ESB_VERSION', '1.0.2');
define('QPH_ESB_DIR', plugin_dir_path(__FILE__));
define('QPH_ESB_URL', plugin_dir_url(__FILE__));

final class QPH_ESB_Plugin {

    public function __construct() {
        register_activation_hook(__FILE__, array(__CLASS__, 'activate_plugin'));
        add_action('plugins_loaded', array($this, 'bootstrap'));
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_footer', array($this, 'render_setup_popup'));
        add_action('wp_ajax_qph_esb_apply_single_style', array($this, 'ajax_apply_single_style'));
        add_filter('site_transient_update_plugins', array($this, 'maybe_block_mec_updates'));
        add_filter('auto_update_plugin', array($this, 'maybe_disable_mec_auto_update'), 10, 2);
    }

    public static function activate_plugin() {
        update_option('esb_show_setup_popup', true);

        $mec_options = get_option('mec_options', array());
        if (!is_array($mec_options)) {
            $mec_options = array();
        }

        if (!isset($mec_options['settings']) || !is_array($mec_options['settings'])) {
            $mec_options['settings'] = array();
        }

        $mec_options['settings']['single_single_style'] = 'builder';
        $mec_options['settings']['single_event_single_style'] = 'builder';

        if (empty($mec_options['settings']['single_single_default_builder'])) {
            $builder = get_posts(array(
                'post_type'      => 'mec_esb',
                'posts_per_page' => 1,
                'post_status'    => array('publish', 'private', 'draft', 'pending', 'future'),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            if (!empty($builder) && isset($builder[0]->ID)) {
                $mec_options['settings']['single_single_default_builder'] = (int) $builder[0]->ID;
                $mec_options['settings']['single_modal_default_builder'] = (int) $builder[0]->ID;
                $mec_options['settings']['qph_default_template'] = (int) $builder[0]->ID;
            }
        }

        // Backward compatibility mirrors at root level.
        $mec_options['single_single_style'] = $mec_options['settings']['single_single_style'];
        $mec_options['single_event_single_style'] = $mec_options['settings']['single_event_single_style'];
        if (!empty($mec_options['settings']['single_single_default_builder'])) {
            $mec_options['single_single_default_builder'] = $mec_options['settings']['single_single_default_builder'];
        }
        if (!empty($mec_options['settings']['single_modal_default_builder'])) {
            $mec_options['single_modal_default_builder'] = $mec_options['settings']['single_modal_default_builder'];
        }
        if (!empty($mec_options['settings']['qph_default_template'])) {
            $mec_options['qph_default_template'] = $mec_options['settings']['qph_default_template'];
        }

        update_option('mec_options', $mec_options);
    }

    public function load_textdomain() {
        load_plugin_textdomain('elementor-qph-single-builder-mec', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function bootstrap() {
        if (!$this->has_elementor()) {
            add_action('admin_notices', array($this, 'notice_missing_elementor'));
            return;
        }

        if (!$this->has_mec()) {
            add_action('admin_notices', array($this, 'notice_missing_mec'));
            return;
        }

        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        require_once QPH_ESB_DIR . 'inc/admin/class-elementor-esb.php';
        \QPH_MEC_Single_Builder\Admin\Elementor_ESB::instance();
    }

    public function register_assets() {
        $script_rel = 'assets/js/esb-countdown.js';
        if (!file_exists(QPH_ESB_DIR . $script_rel) && file_exists(QPH_ESB_DIR . 'assets/js/esb-countdown.json')) {
            // Fallback for incorrect extension in some deployments.
            $script_rel = 'assets/js/esb-countdown.json';
        }

        wp_register_script(
            'qph-esb-countdown',
            QPH_ESB_URL . $script_rel,
            array(),
            QPH_ESB_VERSION,
            true
        );
    }

    private function has_elementor() {
        return did_action('elementor/loaded') || class_exists('Elementor\\Plugin');
    }

    private function has_mec() {
        return class_exists('MEC') || defined('MEC_VERSION') || defined('MEC_ABSPATH');
    }

    public function notice_missing_elementor() {
        if (!current_user_can('activate_plugins')) return;

        echo '<div class="notice notice-error"><p>' .
            esc_html__('Elementor QPH Single Builder for MEC requiere Elementor activo.', 'elementor-qph-single-builder-mec') .
            '</p></div>';
    }

    public function notice_missing_mec() {
        if (!current_user_can('activate_plugins')) return;

        echo '<div class="notice notice-error"><p>' .
            esc_html__('Elementor QPH Single Builder for MEC requiere Modern Events Calendar (MEC) activo.', 'elementor-qph-single-builder-mec') .
            '</p></div>';
    }

    private function should_disable_mec_updates() {
        if (defined('QPH_DISABLE_MEC_UPDATES') && QPH_DISABLE_MEC_UPDATES) {
            return true;
        }

        return (bool) get_option('qph_disable_mec_updates', false);
    }

    private function get_mec_plugin_basenames() {
        return array(
            'modern-events-calendar/mec.php',
            'modern-events-calendar-lite/modern-events-calendar-lite.php',
            'modern-events-calendar-pro/mec.php',
        );
    }

    public function maybe_block_mec_updates($transient) {
        if (!$this->should_disable_mec_updates() || !is_object($transient)) {
            return $transient;
        }

        foreach ($this->get_mec_plugin_basenames() as $plugin_file) {
            if (isset($transient->response[$plugin_file])) {
                unset($transient->response[$plugin_file]);
            }
        }

        return $transient;
    }

    public function maybe_disable_mec_auto_update($update, $item) {
        if (!$this->should_disable_mec_updates() || !is_object($item) || !isset($item->plugin)) {
            return $update;
        }

        if (in_array($item->plugin, $this->get_mec_plugin_basenames(), true)) {
            return false;
        }

        return $update;
    }

    private function should_render_setup_popup() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return false;
        }

        if (!get_option('esb_show_setup_popup', false)) {
            return false;
        }

        return $this->has_mec();
    }

    public function render_setup_popup() {
        if (!$this->should_render_setup_popup()) {
            return;
        }

        $nonce = wp_create_nonce('qph_esb_setup_popup');
        ?>
        <div id="qph-esb-setup-modal" style="position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;">
            <div style="background:#fff;max-width:640px;width:92%;padding:32px;border-radius:8px;position:relative;">
                <button type="button" id="qph-esb-close-modal" style="position:absolute;right:12px;top:8px;background:#000;color:#fff;border:0;width:28px;height:28px;border-radius:50%;cursor:pointer;">×</button>
                <h2 style="margin-top:0;"><?php echo esc_html__('Select Single Event Style', 'elementor-qph-single-builder-mec'); ?></h2>
                <div style="display:flex;gap:14px;align-items:center;margin-bottom:16px;">
                    <select id="qph-esb-style" style="min-width:320px;">
                        <option value="builder"><?php echo esc_html__('Builder', 'elementor-qph-single-builder-mec'); ?></option>
                        <option value="default"><?php echo esc_html__('Default', 'elementor-qph-single-builder-mec'); ?></option>
                    </select>
                    <button type="button" class="button button-primary" id="qph-esb-apply-style"><?php echo esc_html__('Apply', 'elementor-qph-single-builder-mec'); ?></button>
                </div>
                <p><?php echo esc_html__('If this is your first time using QPH Single Builder, choose Builder and click Apply.', 'elementor-qph-single-builder-mec'); ?></p>
                <div id="qph-esb-setup-success" style="display:none;font-size:42px;color:#28c7bb;text-align:center;padding:22px 0;"><?php echo esc_html__('Success', 'elementor-qph-single-builder-mec'); ?></div>
            </div>
        </div>
        <script>
            (function($){
                const modal = $('#qph-esb-setup-modal');
                $('#qph-esb-close-modal').on('click', function(){
                    modal.remove();
                });
                $('#qph-esb-apply-style').on('click', function(){
                    $.post(ajaxurl, {
                        action: 'qph_esb_apply_single_style',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        style: $('#qph-esb-style').val()
                    }).done(function(resp){
                        if (resp && resp.success) {
                            $('#qph-esb-setup-success').show();
                            setTimeout(function(){ modal.remove(); location.reload(); }, 900);
                        }
                    });
                });
            })(jQuery);
        </script>
        <?php
    }

    public function ajax_apply_single_style() {
        check_ajax_referer('qph_esb_setup_popup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $style = isset($_POST['style']) ? sanitize_key($_POST['style']) : 'builder';
        $style = in_array($style, array('builder', 'default'), true) ? $style : 'builder';

        $options = get_option('mec_options', array());
        if (!is_array($options)) {
            $options = array();
        }
        if (!isset($options['settings']) || !is_array($options['settings'])) {
            $options['settings'] = array();
        }

        $options['settings']['single_single_style'] = $style;
        $options['settings']['single_event_single_style'] = $style;
        $options['single_single_style'] = $style;
        $options['single_event_single_style'] = $style;

        if ('builder' === $style && empty($options['settings']['single_single_default_builder'])) {
            $builder = get_posts(array(
                'post_type'      => 'mec_esb',
                'posts_per_page' => 1,
                'post_status'    => array('publish', 'private', 'draft'),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            if (!empty($builder) && isset($builder[0]->ID)) {
                $builder_id = (int) $builder[0]->ID;
                $options['settings']['single_single_default_builder'] = $builder_id;
                $options['settings']['single_modal_default_builder'] = $builder_id;
                $options['settings']['qph_default_template'] = $builder_id;
                $options['single_single_default_builder'] = $builder_id;
                $options['single_modal_default_builder'] = $builder_id;
                $options['qph_default_template'] = $builder_id;
            }
        }
       add_action('elementor/widgets/register', function($widgets_manager) {

    foreach (glob(__DIR__ . 'inc/admin/widgets/*.php') as $file) {
        require_once $file;
    }

    // 🔥 Detectar clases automáticamente
    foreach (get_declared_classes() as $class) {

        if (strpos($class, 'QPH_MEC_Single_Builder\\Widgets\\ESB_') === 0) {

            $widgets_manager->register(new $class());
        }
    }

});
        update_option('mec_options', $options);
        update_option('esb_show_setup_popup', false);
        wp_send_json_success(array('style' => $style));
    }
}

new QPH_ESB_Plugin();
