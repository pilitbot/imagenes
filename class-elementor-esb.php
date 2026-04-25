<?php
namespace QPH_MEC_Single_Builder\Admin;

if (!defined('ABSPATH')) exit;

final class Elementor_ESB {
    private static $instance = null;
    private $debug_enabled = null;
    private $widgets_bootstrapped = false;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register_autoloader();
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_init', array($this, 'enforce_builder_style_on_settings_save'));
        add_action('wp', array($this, 'detach_conflicting_single_builder_hooks'), 1);
        add_action('admin_menu', array($this, 'register_mec_submenu'), 99);
        add_action('after_mec_submenu_action', array($this, 'register_mec_submenu'));
        add_action('init', array($this, 'register_shortcodes'));

               // Integración en sección Single Event Style de MEC (sin menú adicional)
        add_action('mec_single_style_setting_after', array($this, 'add_settings'));

        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_categories'));
        add_action('elementor/widgets/register', array($this, 'init_widgets'));
        add_action('elementor/widgets/widgets_registered', array($this, 'init_widgets_legacy'));
        add_filter('elementor_cpt_support', array($this, 'enable_elementor_for_mec_esb'));

        add_filter('mec_single_event_styles', array($this, 'register_single_style_in_mec_settings'));
        add_filter('mec_settings_single_event_styles', array($this, 'register_single_style_in_mec_settings'));
        add_filter('single_template', array($this, 'esb_single_template'), PHP_INT_MAX);
        add_filter('template_include', array($this, 'filter_template_include'), PHP_INT_MAX);
        add_action('template_redirect', array($this, 'force_qph_single_template_render'), 0);
        add_action('mec_esb_content', array($this, 'load_the_builder'), 10, 1);
        add_action('mec-ajax-load-single-page-before', array($this, 'load_the_builder_modal'), 10, 1);
        add_filter('mec_filter_single_style', array($this, 'filter_single_style'), 10, 1);
        add_filter('mec_single_builder_editor_mode', array($this, 'filter_mec_single_builder_editor_mode'));
        add_filter('mec_get_event_id_for_widget', array($this, 'filter_mec_event_id_for_widget'), 10, 2);
        add_action('save_post_mec_esb', array($this, 'sync_default_builder_on_template_save'), 20, 3);
        add_filter('pre_update_option_mec_options', array($this, 'sync_builder_defaults_before_mec_option_update'), 10, 3);
    }

    private function register_autoloader() {
        spl_autoload_register(function ($class) {
            $prefix = 'QPH_MEC_Single_Builder\\Widgets\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $short = substr($class, strlen($prefix));
            $slug = strtolower(str_replace('_', '-', $short));
            $file = QPH_ESB_DIR . 'inc/admin/widgets/class-' . $slug . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }


    private function ensure_base_loaded() {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            $this->debug_log('Elementor Widget_Base class is not available.');
            return false;
        }

        $base_path = QPH_ESB_DIR . 'inc/admin/widgets/class-esb-base.php';
        if (file_exists($base_path) && !class_exists('\\QPH_MEC_Single_Builder\\Widgets\\ESB_Base')) {
            require_once $base_path;
        }

        return class_exists('\\QPH_MEC_Single_Builder\\Widgets\\ESB_Base');
    }

    private function debug_log($message) {
        if ($this->debug_enabled === null) {
            $query_debug_enabled = isset($_GET['qph_esb_debug']) && '1' === (string) $_GET['qph_esb_debug']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $this->debug_enabled = (defined('QPH_ESB_DEBUG') && QPH_ESB_DEBUG) || ((defined('WP_DEBUG') && WP_DEBUG) && $query_debug_enabled);
        }

        if (!$this->debug_enabled) {
            return;
        }

        error_log('[QPH ESB] ' . $message);
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
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'rewrite'            => array('slug' => 'mec_esb'),
            'supports'           => array('title', 'editor', 'elementor'),
            'exclude_from_search'=> true,
            'show_in_rest'       => true,
        ));
    }

    public function register_mec_submenu() {
        static $submenu_registered = false;
        if ($submenu_registered) {
            return;
        }

        $parent_slug = $this->resolve_mec_menu_parent_slug();

        add_submenu_page(
            $parent_slug,
            __('Single Builder', 'elementor-qph-single-builder-mec'),
            __('Single Builder', 'elementor-qph-single-builder-mec'),
            'edit_posts',
            'edit.php?post_type=mec_esb'
        );

        $submenu_registered = true;
    }

    private function resolve_mec_menu_parent_slug() {
        global $menu;

        $candidates = array('mec-intro', 'mec-settings', 'mec', 'MEC', 'webnus', 'modern-events-calendar');
        $available = array();

        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && is_string($item[2])) {
                    $available[] = $item[2];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        // Fallback to legacy MEC slug.
        return 'mec-intro';
    }

    public function detach_conflicting_single_builder_hooks() {
        $hooks = array('single_template', 'template_include', 'mec_filter_single_style');

        foreach ($hooks as $hook_name) {
            if (empty($GLOBALS['wp_filter'][$hook_name])) {
                continue;
            }

            $hook_object = $GLOBALS['wp_filter'][$hook_name];
            if (!is_object($hook_object) || !isset($hook_object->callbacks) || !is_array($hook_object->callbacks)) {
                continue;
            }

            foreach ($hook_object->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback_data) {
                    if (!isset($callback_data['function'])) {
                        continue;
                    }

                    $function = $callback_data['function'];
                    $class_name = '';
                    $method_name = '';
                    $function_name = '';

                    if (is_array($function) && isset($function[0], $function[1])) {
                        $class_name = is_object($function[0]) ? get_class($function[0]) : (string) $function[0];
                        $method_name = (string) $function[1];
                    } elseif (is_string($function)) {
                        $function_name = $function;
                    }

                    $signature = strtolower($class_name . '::' . $method_name . '|' . $function_name);
                    $is_our_callback = (false !== strpos($signature, 'qph_mec_single_builder\\admin\\elementor_esb'));
                    $looks_conflicting = (
                        false !== strpos($signature, 'mec_single_builder') ||
                        false !== strpos($signature, 'mecsinglebuilder') ||
                        (false !== strpos($signature, 'single_builder') && !$is_our_callback)
                    );

                    if (!$looks_conflicting || $is_our_callback) {
                        continue;
                    }

                    remove_filter($hook_name, $function, $priority);
                    $this->debug_log('Detached conflicting callback: ' . $signature . ' from hook: ' . $hook_name);
                }
            }
        }
    }

    public function register_shortcodes() {
        add_shortcode('qph_esb_template_debug', array($this, 'shortcode_template_debug'));
    }

    private function get_mec_settings_normalized() {
        $set = array();
        if (class_exists('\MEC_main')) {
            $set = (new \MEC_main())->get_settings();
        }

        if (isset($set['settings']) && is_array($set['settings'])) {
            // MEC stores most settings under "settings"; merge top-level for compatibility.
            return array_merge($set['settings'], $set);
        }

        return is_array($set) ? $set : array();
    }

    private function get_mec_options() {
        $options = get_option('mec_options', array());
        if (!is_array($options)) {
            $options = array();
        }

        if (!isset($options['settings']) || !is_array($options['settings'])) {
            $options['settings'] = array();
        }

        return $options;
    }

    private function set_builder_style_in_mec_options(array &$options) {
        $options['settings']['single_single_style'] = 'builder';
        $options['settings']['single_event_single_style'] = 'builder';
        $options['settings']['single_event_style'] = 'builder';
        $options['settings']['single_style'] = 'builder';

        // Backward compatibility mirrors at root level.
        $options['single_single_style'] = 'builder';
        $options['single_event_single_style'] = 'builder';
        $options['single_event_style'] = 'builder';
        $options['single_style'] = 'builder';
    }

    private function set_default_builder_in_mec_options(array &$options, $builder_id) {
        $builder_id = (int) $builder_id;
        if (!$builder_id) {
            return;
        }

        $options['settings']['single_single_default_builder'] = $builder_id;
        $options['single_single_default_builder'] = $builder_id;
        $options['settings']['qph_default_template'] = $builder_id;
        $options['qph_default_template'] = $builder_id;

        if (empty($options['settings']['single_modal_default_builder'])) {
            $options['settings']['single_modal_default_builder'] = $builder_id;
            $options['single_modal_default_builder'] = $builder_id;
        }
    }

    public function enforce_builder_style_on_settings_save() {
        if (!is_admin() || empty($_POST['mec']['settings']) || !is_array($_POST['mec']['settings'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $settings = &$_POST['mec']['settings'];

        $has_builder_selection = !empty($settings['single_single_default_builder']) || !empty($settings['single_modal_default_builder']);
        if (!$has_builder_selection) {
            return;
        }

        $settings['single_single_style'] = 'builder';
        $settings['single_event_single_style'] = 'builder';
        $settings['single_event_style'] = 'builder';
        $settings['single_style'] = 'builder';
    }

    public function sync_default_builder_on_template_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$post || 'mec_esb' !== $post->post_type || 'auto-draft' === $post->post_status) {
            return;
        }

        $options = $this->get_mec_options();
        $this->set_builder_style_in_mec_options($options);
        $this->set_default_builder_in_mec_options($options, $post_id);
        update_option('mec_options', $options);
    }

    public function sync_builder_defaults_before_mec_option_update($new_value, $old_value, $option) {
        if (!is_array($new_value)) {
            return $new_value;
        }

        if (!isset($new_value['settings']) || !is_array($new_value['settings'])) {
            $new_value['settings'] = array();
        }

        $old_settings = (is_array($old_value) && isset($old_value['settings']) && is_array($old_value['settings'])) ? $old_value['settings'] : array();

        $single_builder_id = 0;
        if (!empty($new_value['settings']['single_single_default_builder'])) {
            $single_builder_id = (int) $new_value['settings']['single_single_default_builder'];
        } elseif (!empty($new_value['single_single_default_builder'])) {
            $single_builder_id = (int) $new_value['single_single_default_builder'];
        } elseif (!empty($old_settings['single_single_default_builder'])) {
            $single_builder_id = (int) $old_settings['single_single_default_builder'];
        }

        $modal_builder_id = 0;
        if (!empty($new_value['settings']['single_modal_default_builder'])) {
            $modal_builder_id = (int) $new_value['settings']['single_modal_default_builder'];
        } elseif (!empty($new_value['single_modal_default_builder'])) {
            $modal_builder_id = (int) $new_value['single_modal_default_builder'];
        } elseif (!empty($old_settings['single_modal_default_builder'])) {
            $modal_builder_id = (int) $old_settings['single_modal_default_builder'];
        }

        if ($single_builder_id) {
            $new_value['settings']['single_single_default_builder'] = $single_builder_id;
            $new_value['single_single_default_builder'] = $single_builder_id;
            $new_value['settings']['qph_default_template'] = $single_builder_id;
            $new_value['qph_default_template'] = $single_builder_id;
        }

        if ($modal_builder_id) {
            $new_value['settings']['single_modal_default_builder'] = $modal_builder_id;
            $new_value['single_modal_default_builder'] = $modal_builder_id;
        }

        if ($single_builder_id || $modal_builder_id) {
            $this->set_builder_style_in_mec_options($new_value);
        }

        return $new_value;
    }

    public function shortcode_template_debug($atts = array()) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $atts = shortcode_atts(array(
            'builder_id'   => 0,
            'event_id'     => 0,
            'show_render'  => 'yes',
        ), $atts, 'qph_esb_template_debug');

        $settings = $this->get_mec_settings_normalized();

        $builder_id = (int) $atts['builder_id'];
        if (!$builder_id && isset($settings['single_single_default_builder'])) {
            $builder_id = (int) $settings['single_single_default_builder'];
        }

        $event_id = (int) $atts['event_id'];
        if (!$event_id) {
            $current_id = get_the_ID() ? (int) get_the_ID() : 0;
            $current_type = $current_id ? get_post_type($current_id) : '';

            if ($current_id && 'mec_esb' === $current_type && isset($settings['custom_event_for_set_settings'])) {
                $event_id = (int) $settings['custom_event_for_set_settings'];
            } else {
                $event_id = $current_id;
            }
        }

        $messages = array();
        $messages[] = 'Elementor loaded: ' . (did_action('elementor/loaded') ? 'yes' : 'no');
        $messages[] = 'MEC settings available: ' . (!empty($settings) ? 'yes' : 'no');
        $messages[] = 'Builder ID: ' . $builder_id;
        $messages[] = 'Event ID: ' . $event_id;

        $content = '';
        if (!$builder_id) {
            $messages[] = 'ERROR: No builder selected. Use [qph_esb_template_debug builder_id="123"].';
        } elseif (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
            $messages[] = 'ERROR: Elementor is not available.';
        } elseif ($builder_id > 0 && $builder_id === $event_id) {
            $messages[] = 'ERROR: Builder ID and Event ID are the same. Set MEC "Custom Event For Set Settings" or use event_id attribute.';
        } else {
            try {
                if ($event_id > 0) {
                    $GLOBALS['qph_esb_event_id'] = $event_id;
                }
                $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($builder_id, true);
                unset($GLOBALS['qph_esb_event_id']);
                if (empty(trim((string) $content))) {
                    $messages[] = 'WARNING: Builder rendered empty content.';
                } else {
                    $messages[] = 'Builder render: OK';
                }
            } catch (\Throwable $e) {
                unset($GLOBALS['qph_esb_event_id']);
                $messages[] = 'ERROR: ' . $e->getMessage();
                $this->debug_log('Shortcode render error: ' . $e->getMessage());
            }
        }

        ob_start();
        ?>
        <div class="qph-esb-debug" style="padding:12px;border:1px solid #dcdcde;background:#fff;">
            <strong>QPH ESB Debug</strong>
            <ul style="margin:8px 0 0 16px;">
                <?php foreach ($messages as $message): ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ('yes' === strtolower((string) $atts['show_render']) && !empty($content)) : ?>
                <hr />
                <div class="qph-esb-debug-render"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * HTML en Single Event Page > Single Event Style (estilo ESB original).
     */
    public function add_settings($mec) {
        $settings = isset($mec->settings) ? $mec->settings : array();
        $builders = get_posts(array(
            'post_type'      => 'mec_esb',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private', 'draft', 'pending', 'future'),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        ?>
        <div class="mec-form-row qph-builder-row">
            <label class="mec-col-3" for="mec_single_single_default_builder"><?php echo esc_html__('Default Builder for Single Event', 'elementor-qph-single-builder-mec'); ?></label>
            <div class="mec-col-4">
                <select id="mec_single_single_default_builder" name="mec[settings][single_single_default_builder]">
                    <?php if (!empty($builders)) : ?>
                        <?php foreach ($builders as $builder): ?>
                            <option value="<?php echo esc_attr($builder->ID); ?>" <?php selected(isset($settings['single_single_default_builder']) ? (int) $settings['single_single_default_builder'] : 0, (int) $builder->ID); ?>>
                                <?php echo esc_html($builder->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <option value=""><?php echo esc_html__('No builder templates found', 'elementor-qph-single-builder-mec'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="mec-form-row qph-builder-row">
            <label class="mec-col-3" for="mec_single_modal_default_builder"><?php echo esc_html__('Default Builder for Modal View', 'elementor-qph-single-builder-mec'); ?></label>
            <div class="mec-col-4">
                <select id="mec_single_modal_default_builder" name="mec[settings][single_modal_default_builder]">
                    <?php if (!empty($builders)) : ?>
                        <?php foreach ($builders as $builder): ?>
                            <option value="<?php echo esc_attr($builder->ID); ?>" <?php selected(isset($settings['single_modal_default_builder']) ? (int) $settings['single_modal_default_builder'] : 0, (int) $builder->ID); ?>>
                                <?php echo esc_html($builder->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <option value=""><?php echo esc_html__('No builder templates found', 'elementor-qph-single-builder-mec'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <?php
        $events = get_posts(array(
            'post_type'      => 'mec-events',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        $selected_event = isset($settings['custom_event_for_set_settings']) ? (int) $settings['custom_event_for_set_settings'] : 0;
        $event_options = array();
        foreach ($events as $event) {
            $event_options[] = array(
                'id'    => (int) $event->ID,
                'title' => html_entity_decode(get_the_title($event->ID), ENT_QUOTES, 'UTF-8'),
            );
        }
        ?>
        <div class="mec-form-row qph-builder-row" id="qph_settings_custom_event_for_set_settings_wrap">
            <label class="mec-col-3" for="qph_settings_custom_event_for_set_settings"><?php echo esc_html__('Custom Event For Set Settings', 'elementor-qph-single-builder-mec'); ?></label>
            <div class="mec-col-9">
                <select id="qph_settings_custom_event_for_set_settings" name="mec[settings][custom_event_for_set_settings]">
                    <option value=""><?php echo esc_html__('Select Event', 'elementor-qph-single-builder-mec'); ?></option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($selected_event, (int) $event->ID); ?>>
                            <?php echo esc_html(get_the_title($event->ID)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <script type="text/javascript">
            (function () {
                var styleSelect = document.getElementById('mec_settings_single_event_single_style');
                if (!styleSelect) return;

                var builderValue = 'builder';
                var builderLabel = 'Select Builder';
                var hasBuilder = false;

                for (var i = 0; i < styleSelect.options.length; i++) {
                    if (styleSelect.options[i].value === builderValue) {
                        hasBuilder = true;
                        styleSelect.options[i].text = builderLabel;
                        break;
                    }
                }

                if (!hasBuilder) {
                    var option = document.createElement('option');
                    option.value = builderValue;
                    option.text = builderLabel;
                    styleSelect.appendChild(option);
                }

                styleSelect.value = builderValue;

                var rows = document.querySelectorAll('.qph-builder-row');
                var nativeCustomEventRow = document.getElementById('mec_settings_custom_event_for_set_settings_wrap');
                var fallbackCustomEventRow = document.getElementById('qph_settings_custom_event_for_set_settings_wrap');
                var singleBuilder = document.getElementById('mec_single_single_default_builder');
                var modalBuilder = document.getElementById('mec_single_modal_default_builder');
                var nativeCustomEvent = document.getElementById('mec_settings_custom_event_for_set_settings');
                var fallbackCustomEvent = document.getElementById('qph_settings_custom_event_for_set_settings');
                var customEvent = nativeCustomEvent || fallbackCustomEvent;
                var customEventSelected = <?php echo (int) $selected_event; ?>;
                var customEventOptions = <?php echo wp_json_encode($event_options); ?> || [];

                if (nativeCustomEvent && fallbackCustomEvent) {
                    fallbackCustomEvent.removeAttribute('name');
                    if (fallbackCustomEventRow) fallbackCustomEventRow.style.display = 'none';
                }

                function fillCustomEventOptions(select) {
                    if (!select || !Array.isArray(customEventOptions) || !customEventOptions.length) return;

                    var currentValue = String(customEventSelected || select.value || '');
                    select.innerHTML = '';

                    var emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = 'Select Event';
                    select.appendChild(emptyOption);

                    customEventOptions.forEach(function (item) {
                        var option = document.createElement('option');
                        option.value = String(item.id);
                        option.textContent = item.title || ('#' + item.id);
                        if (currentValue && option.value === currentValue) option.selected = true;
                        select.appendChild(option);
                    });
                }

                fillCustomEventOptions(customEvent);

                function toggleRows() {
                    var show = styleSelect.value === builderValue;
                    rows.forEach(function (row) {
                        row.style.display = show ? '' : 'none';
                    });

                    if (nativeCustomEventRow) {
                        nativeCustomEventRow.style.display = show ? '' : 'none';
                    }

                    if (fallbackCustomEventRow && !(nativeCustomEvent && fallbackCustomEvent)) {
                        fallbackCustomEventRow.style.display = show ? '' : 'none';
                    }
                }

                var isSubmitting = false;
                function submitSettingsForm(form) {
                    if (!form || isSubmitting) return;

                    isSubmitting = true;
                    var submitter = form.querySelector('button[type="submit"], input[type="submit"]');

                    if (typeof form.requestSubmit === 'function') {
                        if (submitter) form.requestSubmit(submitter);
                        else form.requestSubmit();
                        return;
                    }

                    if (submitter) {
                        submitter.click();
                        return;
                    }

                    form.submit();
                }

                function autoSubmit(input) {
                    if (!input) return;
                    input.addEventListener('change', function () {
                        submitSettingsForm(input.closest('form'));
                    });
                }

                styleSelect.addEventListener('change', function () {
                    toggleRows();
                    submitSettingsForm(styleSelect.closest('form'));
                });

                autoSubmit(singleBuilder);
                autoSubmit(modalBuilder);
                autoSubmit(customEvent);
                toggleRows();
            })();
        </script>
        <?php
    }

    public function register_single_style_in_mec_settings($styles) {
        if (!is_array($styles)) {
            $styles = array();
        }

        $styles['builder'] = __('Select Builder', 'elementor-qph-single-builder-mec');
        return $styles;
    }

    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category('qph_mec_single_builder', array(
            'title' => __('QPH MEC Single Builder', 'elementor-qph-single-builder-mec'),
            'icon'  => 'fa fa-calendar',
        ));
    }

    public function enable_elementor_for_mec_esb($post_types) {
        if (!is_array($post_types)) {
            $post_types = array();
        }

        if (!in_array('mec_esb', $post_types, true)) {
            $post_types[] = 'mec_esb';
        }

        return $post_types;
    }

    public function init_widgets($widgets_manager = null) {
        if ($this->widgets_bootstrapped) {
            $this->debug_log('init_widgets skipped: widgets already bootstrapped.');
            return;
        }

        if (!is_object($widgets_manager)) {
            $this->debug_log('init_widgets aborted: widgets_manager is not an object.');
            return;
        }

        if (!method_exists($widgets_manager, 'register') && !method_exists($widgets_manager, 'register_widget_type')) {
            $this->debug_log('init_widgets aborted: widgets_manager has no compatible register method.');
            return;
        }

        $this->debug_log('init_widgets called with manager: ' . get_class($widgets_manager));
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
            array('class-esb-lugar-establecimiento.php', 'ESB_Lugar_Establecimiento'),
            array('class-esb-limite-edad.php', 'ESB_Limite_Edad'),
            array('class-esb-zona-barrio.php', 'ESB_Zona_Barrio'),
            array('class-esb-hour.php', 'ESB_Hour'),
            array('class-esb-municipio-ciudad.php', 'ESB_Municipio_Ciudad'),
            array('class-esb-post-content.php', 'ESB_Post_Content'),
        );

        foreach ($widget_map as $widget_data) {
            $this->register_widget(
                $widgets_manager,
                $widget_data[0],
                '\\QPH_MEC_Single_Builder\\Widgets\\' . $widget_data[1]
            );
        }

        $this->widgets_bootstrapped = true;
        $this->debug_log('Widgets bootstrap completed.');
    }

    public function init_widgets_legacy() {
        $this->debug_log('init_widgets_legacy called.');
        if (!did_action('elementor/loaded')) {
            $this->debug_log('Elementor is not loaded yet in legacy hook.');
            return;
        }

        $plugin_instance = \Elementor\Plugin::instance();
        if (!$plugin_instance || !isset($plugin_instance->widgets_manager)) {
            $this->debug_log('Elementor widgets_manager is missing in legacy hook.');
            return;
        }

        $this->init_widgets($plugin_instance->widgets_manager);
    }

    private function register_widget($widgets_manager, $file_name, $class_name) {
        if (!$this->ensure_base_loaded()) {
            $this->debug_log('Base class not loaded. Skipping widget: ' . $class_name);
            return;
        }

        $full_path = QPH_ESB_DIR . 'inc/admin/widgets/' . $file_name;

        if (!file_exists($full_path)) {
            $fallback_paths = glob(QPH_ESB_DIR . 'inc/admin/widgets/class-esb-*.php');
            $fallback_file = '';

            if (is_array($fallback_paths)) {
                $class_parts = explode('\\', $class_name);
                $short_class = strtolower(end($class_parts));
                $expected_slug = str_replace('esb_', '', $short_class);
                $expected_slug = str_replace('_', '-', $expected_slug);

                foreach ($fallback_paths as $candidate) {
                    if (strpos(basename($candidate), $expected_slug) !== false) {
                        $fallback_file = $candidate;
                        break;
                    }
                }
            }

            if ($fallback_file) {
                $full_path = $fallback_file;
                $this->debug_log('Widget file fallback found: ' . $full_path);
            } else {
                $this->debug_log('Widget file not found: ' . $full_path);
            }
        }

        if (file_exists($full_path)) {
            require_once $full_path;
        }

        if (class_exists($class_name)) {
            $widget_instance = new $class_name();

            if (method_exists($widgets_manager, 'register')) {
                $widgets_manager->register($widget_instance);
                $this->debug_log('Registered widget via register(): ' . $class_name);
            } elseif (method_exists($widgets_manager, 'register_widget_type')) {
                $widgets_manager->register_widget_type($widget_instance);
                $this->debug_log('Registered widget via register_widget_type(): ' . $class_name);
            } else {
                $this->debug_log('No compatible register method for widget manager. Widget: ' . $class_name);
            }
        } else {
            $this->debug_log('Widget class not found after require: ' . $class_name);
        }
    }


    public function esb_single_template($single) {
        global $post;
        if ($post && isset($post->post_type) && 'mec_esb' === $post->post_type) {
            $template = QPH_ESB_DIR . 'templates/single-mec_esb.php';
            if (file_exists($template)) {
                return $template;
            }
        }

        if ($post && isset($post->post_type) && 'mec-events' === $post->post_type) {
            $event_id = (int) $post->ID;
            if ($event_id && 'builder' === $this->get_single_event_template_settings($event_id)) {
                $template = QPH_ESB_DIR . 'templates/single-mec-event-builder.php';
                if (file_exists($template)) {
                    return $template;
                }
            }
        }

        return $single;
    }

    public function filter_template_include($original) {
        if (!is_single() || 'mec-events' !== get_post_type()) {
            return $original;
        }

        $event_id = get_the_ID();
        if (!$event_id || !$this->should_use_qph_event_template($event_id)) {
            return $original;
        }

        $template = QPH_ESB_DIR . 'templates/single-mec-event-builder.php';
        if (file_exists($template)) {
            return $template;
        }

        return $original;
    }

    public function force_qph_single_template_render() {
        if (is_admin() || wp_doing_ajax() || is_feed() || is_embed()) {
            return;
        }

        if (!is_singular('mec-events')) {
            return;
        }

        $event_id = get_queried_object_id();
        if (!$event_id || !$this->should_use_qph_event_template($event_id)) {
            return;
        }

        $template = QPH_ESB_DIR . 'templates/single-mec-event-builder.php';
        if (!file_exists($template)) {
            return;
        }

        status_header(200);
        include $template;
        exit;
    }

    private function should_use_qph_event_template($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id) {
            return false;
        }

        $event_builder = (int) get_post_meta($event_id, 'single_design_page', true);
        if ($event_builder > 0) {
            return true;
        }

        $settings = $this->get_mec_settings_normalized();
        $default_builder = isset($settings['single_single_default_builder']) ? (int) $settings['single_single_default_builder'] : 0;
        if ($default_builder > 0) {
            return true;
        }

        $legacy_qph_builder = isset($settings['qph_default_template']) ? (int) $settings['qph_default_template'] : 0;
        return $legacy_qph_builder > 0;
    }

    public function filter_mec_single_builder_editor_mode($is_editor_mode) {
        $preview_mode = isset($_GET['preview_id']) && !empty($_GET['preview_id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $elementor_mode = class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode();
        return $preview_mode || $elementor_mode || $is_editor_mode;
    }

    public function filter_mec_event_id_for_widget($event_id, $is_editor_mode) {
        if (!$is_editor_mode) {
            if (!$event_id && wp_doing_ajax()) {
                return get_the_ID();
            }

            return $event_id;
        }

        $settings = $this->get_mec_settings_normalized();
        $custom_event_id = isset($settings['custom_event_for_set_settings']) ? (int) $settings['custom_event_for_set_settings'] : 0;

        if ($custom_event_id) {
            return $custom_event_id;
        }

        return $event_id;
    }

    public function get_single_event_template_settings($event_id) {
        $set = $this->get_mec_settings_normalized();

        $global = '';
        if (!empty($set['single_single_style'])) {
            $global = (string) $set['single_single_style'];
        } elseif (!empty($set['single_event_single_style'])) {
            $global = (string) $set['single_event_single_style'];
        } elseif (!empty($set['single_event_style'])) {
            $global = (string) $set['single_event_style'];
        } elseif (!empty($set['single_style'])) {
            $global = (string) $set['single_style'];
        }
        $has_default_builder = !empty($set['single_single_default_builder']) && (int) $set['single_single_default_builder'] > 0;

        // If builder is configured globally, force builder as effective style.
        if ('builder' === $global) {
            $this->debug_log('Single style resolved as builder (global style key). Event: ' . (int) $event_id);
            return 'builder';
        }

        // If a default single builder is selected, use builder style as effective fallback
        // even when MEC global style key drifts to "default".
        if ($has_default_builder) {
            $this->debug_log('Single style resolved as builder (default builder present). Event: ' . (int) $event_id . ', Builder: ' . (int) $set['single_single_default_builder']);
            return 'builder';
        }

        $style_per_event = isset($set['style_per_event']) ? $set['style_per_event'] : '';
        $event_style = '';

        if ($style_per_event) {
            $event_style = get_post_meta($event_id, 'mec_style_per_event', true);
            if ('global' === $event_style) {
                $event_style = '';
            }
        }

        $resolved = $event_style ? $event_style : $global;
        $this->debug_log('Single style resolved using event/global fallback. Event: ' . (int) $event_id . ', Resolved: ' . $resolved);
        return $resolved;
    }

    public function filter_single_style($single_style) {
        if ('mec-events' === get_post_type()) {
            $event_id = get_the_ID();
            if ($event_id) {
                $single_style = $this->get_single_event_template_settings($event_id);
            }
        }

        return $single_style;
    }

    public function load_the_builder($event) {
        if (!is_object($event) || !isset($event->ID)) {
            return;
        }

        if ('builder' !== $this->get_single_event_template_settings($event->ID)) {
            return;
        }

        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $post_id = get_post_meta($event->ID, 'single_design_page', true);

        $set = $this->get_mec_settings_normalized();
        if (!$post_id) {
            $post_id = isset($set['single_single_default_builder']) ? $set['single_single_default_builder'] : false;
        }

        if (!$post_id || !get_post($post_id)) {
            echo esc_html__('Please select default builder from MEC single page settings.', 'elementor-qph-single-builder-mec');
            return;
        }

        echo '<div id="mec_skin_single_page_esb_warp">';
        $GLOBALS['qph_esb_event_id'] = (int) $event->ID;
        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        unset($GLOBALS['qph_esb_event_id']);
        echo '</div>';
    }

    public function get_single_builder_template_id($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id) {
            return 0;
        }

        $post_id = (int) get_post_meta($event_id, 'single_design_page', true);
        if (!$post_id) {
            $set = $this->get_mec_settings_normalized();
            $post_id = isset($set['single_single_default_builder']) ? (int) $set['single_single_default_builder'] : 0;
        }

        return ($post_id && get_post($post_id)) ? $post_id : 0;
    }

    public function render_single_event_builder_content($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id || !class_exists('\Elementor\Plugin')) {
            return '';
        }

        $builder_id = $this->get_single_builder_template_id($event_id);
        if (!$builder_id) {
            return '';
        }

        $GLOBALS['qph_esb_event_id'] = $event_id;
        $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($builder_id, true);
        unset($GLOBALS['qph_esb_event_id']);

        return (string) $content;
    }

    public function load_the_builder_modal($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id) {
            return;
        }

        if ('builder' !== $this->get_single_event_template_settings($event_id)) {
            return;
        }

        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $post_id = get_post_meta($event_id, 'single_modal_design_page', true);
        $set = $this->get_mec_settings_normalized();
        if (!$post_id) {
            $post_id = isset($set['single_modal_default_builder']) ? $set['single_modal_default_builder'] : false;
        }

        if (!$post_id || !get_post($post_id)) {
            echo esc_html__('Please select default builder for modal from MEC single page settings.', 'elementor-qph-single-builder-mec');
            return;
        }

        $GLOBALS['qph_esb_event_id'] = $event_id;
        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        unset($GLOBALS['qph_esb_event_id']);
        wp_die();
    }

}
