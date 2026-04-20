<?php
/**
 * Plugin Name: QuePasaHoy MEC API
 * Plugin URI: https://quepasahoy.com.co
 * Description: API Modern Events Calendar - Integración con Google Sheets
 * Version: 1.0.1
 * Author: Pilimur
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// =========================================================
// 1. REGISTRO DE RUTAS REST PARA MEC
// =========================================================
add_action( 'rest_api_init', function () {
    // RUTA DE CREACIÓN DE EVENTOS MEC
    register_rest_route( 'gs-mec-import/v1', '/events', [
        'methods'             => 'POST',
        'callback'            => 'mec_create_event_from_sheets',
        'permission_callback' => function($request) {
            $api_key = $request->get_header('GoogleSheets_Autopost');
            return $api_key === 'AOfF TLdJ aOW6 7jZb e6yY 8RPp';
        }
    ]);

    // RUTA DE SINCRONIZACIÓN DE UBICACIONES MEC
    register_rest_route( 'gs-mec-import/v1', '/locations', [
        'methods'             => 'GET',
        'callback'            => 'mec_get_locations',
        'permission_callback' => '__return_true'
    ]);

    // RUTA DE SINCRONIZACIÓN DE CATEGORÍAS MEC
    register_rest_route( 'gs-mec-import/v1', '/categories', [
        'methods'             => 'GET',
        'callback'            => 'mec_get_categories',
        'permission_callback' => '__return_true'
    ]);
});

// =========================================================
// 2. FUNCIÓN DE CREACIÓN DE EVENTOS MEC (COMPATIBLE PHP 8+)
// =========================================================
function mec_create_event_from_sheets( WP_REST_Request $request ) {
    // Verificar si MEC está activo
    if (!defined('MEC_VERSION')) {
        return new WP_Error('mec_not_active', 'Modern Events Calendar no está activo.', ['status' => 500]);
    }
    
    $params = $request->get_json_params();
    
    error_log('MEC DEBUG: ===== INICIANDO CREACIÓN DE EVENTO =====');
    error_log('MEC DEBUG: Hora: ' . date('Y-m-d H:i:s'));
    
    // 1. Datos básicos del evento - CON MANEJO DE NULL
    $title = isset($params['title']) && !is_null($params['title']) 
        ? sanitize_text_field($params['title']) 
        : 'Evento sin título';
    
    $content = isset($params['content']) && !is_null($params['content'])
        ? wp_kses_post($params['content'])
        : '';
    
    $author_id = isset($params['author']) && !is_null($params['author'])
        ? intval($params['author'])
        : 1;
    
    // 2. Procesar fechas para MEC - CON MANEJO DE NULL
    $date_data = isset($params['date']) && !is_null($params['date']) 
        ? $params['date'] 
        : [];
    
    $start_datetime = isset($date_data['start']) && !is_null($date_data['start'])
        ? sanitize_text_field($date_data['start'])
        : date('Y-m-d 19:00:00');
    
    $end_datetime = isset($date_data['end']) && !is_null($date_data['end'])
        ? sanitize_text_field($date_data['end'])
        : date('Y-m-d 21:00:00');
    
    $timezone = isset($date_data['timezone']) && !is_null($date_data['timezone'])
        ? sanitize_text_field($date_data['timezone'])
        : 'America/Bogota';
    
    $allday = isset($date_data['allday']) && !is_null($date_data['allday'])
        ? $date_data['allday']
        : '0';
    
    // Separar fecha y hora de forma segura
    $start_date = date('Y-m-d');
    $start_time = '19:00:00';
    $end_date = date('Y-m-d');
    $end_time = '21:00:00';
    
    if (!empty($start_datetime)) {
        $start_parts = explode(' ', $start_datetime);
        if (count($start_parts) >= 2) {
            $start_date = $start_parts[0];
            $start_time = $start_parts[1];
        }
    }
    
    if (!empty($end_datetime)) {
        $end_parts = explode(' ', $end_datetime);
        if (count($end_parts) >= 2) {
            $end_date = $end_parts[0];
            $end_time = $end_parts[1];
        }
    }
    
    // 3. Ubicación - CON MANEJO DE NULL
    $location_data = isset($params['location']) && !is_null($params['location'])
        ? $params['location']
        : [];
    
    $location_id = isset($location_data['id']) && !is_null($location_data['id'])
        ? intval($location_data['id'])
        : 0;
    
        
    // 4. Categorías - CON MANEJO DE NULL
    $categories = [];
    if (isset($params['categories']) && !is_null($params['categories']) && is_array($params['categories'])) {
        $categories = array_map('intval', $params['categories']);
    }
    
    // 5. Imagen destacada - CON MANEJO DE NULL Y VALIDACIÓN
    $thumbnail_url = '';
    if (isset($params['thumbnail']) && !is_null($params['thumbnail'])) {
        $raw_thumbnail = $params['thumbnail'];
        if (is_string($raw_thumbnail) && !empty(trim($raw_thumbnail))) {
            $thumbnail_url = esc_url_raw(trim($raw_thumbnail));
            error_log('MEC DEBUG IMAGEN: URL procesada - ' . $thumbnail_url);
        }
    }
    
    // Guardar costo (precio)
    if ( ! empty( $params['cost'] ) ) {
    update_post_meta( $post_id, 'mec_cost', sanitize_text_field( $params['cost'] ) );
    } else {
    delete_post_meta( $post_id, 'mec_cost' );
    }

           // 3. Organizador - viene como organizer_name desde Google Sheets
    $organizer_name = isset( $params['organizer_name'] ) && ! is_null( $params['organizer_name'] )
        ? sanitize_text_field( $params['organizer_name'] )
        : '';

    $organizer_id = 0;

    if ( ! empty( $organizer_name ) ) {
        // ¿Ya existe un organizador MEC con ese nombre?
        $existing_organizer = get_page_by_title( $organizer_name, OBJECT, 'mec-organizer' );

        if ( $existing_organizer ) {
            $organizer_id = (int) $existing_organizer->ID;
            error_log( 'MEC DEBUG: Usando organizador existente ID ' . $organizer_id . ' (' . $organizer_name . ')' );
        } else {
            // Crear un nuevo organizador MEC
            $organizer_id = wp_insert_post( [
                'post_title'  => $organizer_name,
                'post_type'   => 'mec-organizer',
                'post_status' => 'publish',
            ] );

            if ( ! is_wp_error( $organizer_id ) ) {
                error_log( 'MEC DEBUG: Organizador creado ID ' . $organizer_id . ' (' . $organizer_name . ')' );
            } else {
                error_log( 'MEC ERROR: Falló creación de organizador: ' . $organizer_id->get_error_message() );
                $organizer_id = 0;
            }
        }
    }

    // Guardar límite de edad (campo personalizado)
    if ( ! empty( $params['age_limit'] ) ) {
    update_post_meta( $post_id, 'qph_age_limit', sanitize_text_field( $params['age_limit'] ) );
    } else {
    delete_post_meta( $post_id, 'qph_age_limit' );
    }
    
    error_log('MEC DEBUG: Resumen de datos:');
    error_log('MEC DEBUG: Título - ' . $title);
    error_log('MEC DEBUG: Categorías - ' . implode(', ', $categories));
    error_log('MEC DEBUG: Ubicación ID - ' . $location_id);
    error_log('MEC DEBUG: Imagen URL - ' . ($thumbnail_url ?: 'VACÍA'));
    
    // Crear post
    $post_data = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'mec-events',
        'post_author'  => $author_id,
        'meta_input'   => [
            'mec_location_id' => $location_id
        ]
    ];
    
    $post_id = wp_insert_post($post_data, true);
    
    if (is_wp_error($post_id)) {
        error_log('MEC ERROR CREANDO POST: ' . $post_id->get_error_message());
        return new WP_Error('post_creation_failed', $post_id->get_error_message(), ['status' => 500]);
    }
    
    error_log('MEC DEBUG: Post creado exitosamente - ID: ' . $post_id);
    
    // ============================================
    // GUARDAR METADATOS DE MEC (ESTRUCTURA ESPECÍFICA)
    // ============================================
    
    $mec_meta = [
        'mec_start_date' => $start_date,
        'mec_start_time' => $start_time,
        'mec_end_date'   => $end_date,
        'mec_end_time'   => $end_time,
        'mec_allday'     => $allday,
        'mec_hide_time'  => '0',
        'mec_hide_end_time' => '0',
        'mec_repeat'     => [
            'status' => '0',
            'type' => '',
            'interval' => NULL,
            'finish' => '',
            'year' => NULL,
            'month' => NULL,
            'day' => NULL,
            'week' => NULL,
            'weekday' => NULL,
            'weekdays' => NULL,
            'days' => '',
            'not_in_days' => ''
        ],
        'mec_repeat_end' => 'never',
        'mec_repeat_end_at_occurrences' => '',
        'mec_repeat_end_at_date' => '',
        'mec_advanced_days' => '',
        'mec_note' => '',
        'mec_cost' => '',
        'mec_featured_image' => '',
        'mec_more_info' => '',
        'mec_more_info_title' => '',
        'mec_more_info_target' => '_self',
        'mec_organizer_id' => '',
        'mec_additional_organizer_ids' => [],
        'mec_location_id' => $location_id,
        'mec_additional_location_ids' => [],
        'mec_dont_show_map' => '0',
        'mec_event_link' => '',
        'mec_event_link_title' => '',
        'mec_event_link_target' => '_self',
        'mec_read_more' => '',
        'mec_read_more_title' => '',
        'mec_public' => '1'
    ];
    
    // Guardar metadatos principales de MEC SIN tocar mec_date
    update_post_meta( $post_id, 'mec_allday', $allday );
    update_post_meta( $post_id, 'mec_start_date', $start_date );
    update_post_meta( $post_id, 'mec_start_time', $start_time );
    update_post_meta( $post_id, 'mec_end_date',   $end_date );
    update_post_meta( $post_id, 'mec_end_time',   $end_time );
    update_post_meta( $post_id, 'mec_location_id', $location_id );

    // Asignar también la taxonomía mec_location si hay ID válido
    if ( $location_id > 0 ) {
        $term = get_term( $location_id, 'mec_location' );
        if ( $term && ! is_wp_error( $term ) ) {
             wp_set_object_terms( $post_id, (int) $location_id, 'mec_location', false );
             error_log( 'MEC DEBUG: Ubicación asignada a taxonomía mec_location -> ID ' . $location_id );
        } else {
            error_log( 'MEC WARNING: No se encontró el término mec_location con ID ' . $location_id );
        }
    }
    
           // Guardar organizador principal del evento
    if ( $organizer_id ) {
        update_post_meta( $post_id, 'mec_organizer_id', $organizer_id );
        // Si quieres limpiar organizadores adicionales
        update_post_meta( $post_id, 'mec_additional_organizer_ids', [] );
        error_log( 'MEC DEBUG: Organizador asignado al evento ' . $post_id . ' → ID ' . $organizer_id );
    }
    
    // Asignar categorías
    if (!empty($categories)) {
        error_log('MEC DEBUG: Asignando categorías: ' . implode(', ', $categories));
        $term_result = wp_set_object_terms($post_id, $categories, 'mec_category', false);
        
        if (is_wp_error($term_result)) {
            error_log('MEC ERROR asignando categorías: ' . $term_result->get_error_message());
        } else {
            error_log('MEC DEBUG: Categorías asignadas correctamente');
        }
    }
    
    // Procesar imagen destacada
    if (!empty($thumbnail_url)) {
        error_log('MEC DEBUG IMAGEN: Procesando imagen: ' . $thumbnail_url);
        
        $attach_id = mec_download_and_attach_image($thumbnail_url, $post_id, $title);
        
        if ($attach_id && !is_wp_error($attach_id)) {
            // Establecer como imagen destacada
            $set_thumbnail = set_post_thumbnail($post_id, $attach_id);
            
            if ($set_thumbnail) {
                error_log('MEC DEBUG IMAGEN: ✅ Imagen establecida como destacada - ID: ' . $attach_id);
                
                // Guardar en metadatos de MEC
                //update_post_meta($post_id, 'mec_thumbnail', wp_get_attachment_url($attach_id));//
                
                // Actualizar metadata de imagen en el array de MEC
                //$mec_meta['mec_featured_image'] = wp_get_attachment_url($attach_id);
                //update_post_meta($post_id, 'mec_date', $mec_meta);//
            } else {
                error_log('MEC ERROR IMAGEN: ❌ No se pudo establecer imagen destacada');
            }
        } else {
            $error_msg = is_wp_error($attach_id) ? $attach_id->get_error_message() : 'Error desconocido';
            error_log('MEC ERROR IMAGEN: ❌ Error descargando imagen: ' . $error_msg);
        }
    }
    
       // 👉 Asignar el mismo autor del evento al adjunto (imagen)
    if ( isset( $attach_id ) && ! is_wp_error( $attach_id ) ) {
        $event_author = get_post_field( 'post_author', $post_id );
        if ( $event_author ) {
             wp_update_post( [
                 'ID'          => $attach_id,
                 'post_author' => (int) $event_author,
             ] );
        }
    }

    // Guardar metadata adicional
    if (isset($params['metadata']) && !is_null($params['metadata']) && is_array($params['metadata'])) {
        foreach ($params['metadata'] as $key => $value) {
            if (!is_null($value)) {
                //update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }
    }
    
    // Generar permalink
    $event_link = get_permalink($post_id);
    
    // Forzar limpieza de caché de MEC
    if (function_exists('mec_flush_cache')) {
        mec_flush_cache($post_id);
    }
    
    error_log('MEC DEBUG: Evento creado exitosamente - ID: ' . $post_id . ' - Link: ' . $event_link);
    
    return [
        'success'  => true,
        'event_id' => $post_id,
        'link'     => $event_link,
        'message'  => 'Evento MEC creado exitosamente'
    ];
}

/**
 * Obtener ubicaciones de MEC (cuando se manejan como TAXONOMÍA mec_location)
 */
function mec_get_locations( WP_REST_Request $request ) {
    // Verificar si MEC está activo
    if ( ! defined( 'MEC_VERSION' ) ) {
        return rest_ensure_response( [] );
    }

    // Obtener términos de la taxonomía mec_location
    $terms = get_terms( array(
        'taxonomy'   => 'mec_location',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => 0, // sin límite
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return rest_ensure_response( [] );
    }

    $formatted_locations = array();

    foreach ( $terms as $term ) {
        $term_id = (int) $term->term_id;

        // Si en el futuro quieres sacar address/lat/lng de term_meta, se añadiría aquí.
        // De momento, solo devolvemos nombre e ID (suficiente para la hoja Ubicaciones_MEC).

        $formatted_locations[] = array(
            'id'        => $term_id,
            'name'      => $term->name,
            'address'   => '',
            'city'      => '',
            'state'     => '',
            'country'   => 'Colombia',
            'latitude'  => '',
            'longitude' => '',
        );
    }

    return rest_ensure_response( $formatted_locations );
}

// =========================================================
// 4. FUNCIÓN PARA OBTENER CATEGORÍAS MEC (COMPATIBLE PHP 8+)
// =========================================================
function mec_get_categories( WP_REST_Request $request ) {
    // Verificar si MEC está activo
    if (!defined('MEC_VERSION')) {
        return rest_ensure_response([]);
    }
    
    // Obtener parámetros de paginación con manejo de null
    $per_page_param = $request->get_param('per_page');
    $page_param = $request->get_param('page');
    
    $per_page = !is_null($per_page_param) ? intval($per_page_param) : 100;
    $page = !is_null($page_param) ? intval($page_param) : 1;
    
    $args = [
        'taxonomy'   => 'mec_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => $per_page,
        'offset'     => ($page - 1) * $per_page
    ];
    
    $terms = get_terms($args);
    
    if (is_wp_error($terms)) {
        return rest_ensure_response([]);
    }
    
    $formatted_categories = array_map(function($term) {
        return [
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => is_string($term->description) ? $term->description : '',
            'count'       => (int) $term->count,
            'parent'      => (int) $term->parent
        ];
    }, $terms);
    
    return rest_ensure_response($formatted_categories);
}

// =========================================================
// 5. FUNCIÓN AUXILIAR PARA DESCARGAR IMÁGENES (WEBP + FALLBACK)
// =========================================================
function mec_download_and_attach_image( $image_url, $post_id, $title ) {
    // 0. Validar URL
    if ( empty( $image_url ) || ! is_string( $image_url ) ) {
        error_log( 'MEC DOWNLOAD ERROR: URL inválida o vacía' );
        return new WP_Error( 'invalid_url', 'URL de imagen inválida o vacía' );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // 1. Descargar archivo temporal
    $tmp_file = download_url( $image_url, 30 );

    if ( is_wp_error( $tmp_file ) ) {
        error_log( 'MEC DOWNLOAD ERROR: ' . $tmp_file->get_error_message() );
        return $tmp_file;
    }

    // 2. Intentar optimizar + convertir a WebP
    $editor = wp_get_image_editor( $tmp_file );

    $final_path = $tmp_file;
    $final_mime = 'image/jpeg';
    $final_name = sanitize_title( $title ) . '-' . date( 'Y' ); // slug-2025

    if ( ! is_wp_error( $editor ) ) {
        // Redimensionar si es muy grande (por ejemplo ancho máximo 1920px)
        $size = $editor->get_size();
        if ( $size && ! empty( $size['width'] ) && $size['width'] > 1920 ) {
            $editor->resize( 1920, null, false );
        }

        // Calidad 80%
        $editor->set_quality( 80 );

        // Intentar guardar como WebP
        $saved = $editor->save( $tmp_file . '.webp' ); // dejamos que WP ponga mime

        if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
            // Éxito WebP
            $final_path = $saved['path'];
            $final_mime = 'image/webp';
            $final_name = $final_name . '.webp';

            // Borrar original
            @unlink( $tmp_file );
        } else {
            // Fallback: usar archivo original tal cual (JPG/PNG)
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime_orig = finfo_file( $finfo, $tmp_file );
            finfo_close( $finfo );

            $ext = 'jpg';
            if ( $mime_orig === 'image/png' ) $ext = 'png';
            if ( $mime_orig === 'image/gif' ) $ext = 'gif';

            $final_path = $tmp_file;
            $final_mime = $mime_orig;
            $final_name = $final_name . '.' . $ext;

            // (Opcional) log del error WebP:
            if ( is_wp_error( $saved ) ) {
                update_post_meta( $post_id, '_debug_webp_error', $saved->get_error_message() );
            }
        }
    } else {
        // Si el editor falla, dejamos el archivo original en JPG
        $final_path = $tmp_file;
        $final_mime = 'image/jpeg';
        $final_name = $final_name . '.jpg';
    }

    // 3. Preparar array de archivo para media_handle_sideload
    $file_array = array(
        'name'     => $final_name,
        'tmp_name' => $final_path,
        'type'     => $final_mime,
    );

    // 4. Crear adjunto en la biblioteca de medios
    $attach_id = media_handle_sideload( $file_array, $post_id, $title );
    
    // 👉 Poner ALT automáticamente usando el título del evento
    if ( ! is_wp_error( $attach_id ) ) {
    update_post_meta(
        $attach_id,
        '_wp_attachment_image_alt',
        sanitize_text_field( $title )
       );
    }

    // Limpiar archivo temporal
    if ( file_exists( $final_path ) ) {
        @unlink( $final_path );
    }

    if ( is_wp_error( $attach_id ) ) {
        error_log( 'MEC DOWNLOAD ERROR: ' . $attach_id->get_error_message() );
        return $attach_id;
    }

    // 5. Generar metadatos (tamaños, etc.)
    wp_update_attachment_metadata(
        $attach_id,
        wp_generate_attachment_metadata( $attach_id, get_attached_file( $attach_id ) )
    );

    error_log( 'MEC DOWNLOAD SUCCESS: Imagen adjuntada ID ' . $attach_id );
    return $attach_id;
}

// =========================================================
// 6. FUNCIÓN DE DIAGNÓSTICO
// =========================================================
add_action('rest_api_init', function () {
    register_rest_route('gs-mec-import/v1', '/diagnostic', [
        'methods' => 'GET',
        'callback' => function() {
            $mec_active = defined('MEC_VERSION');
            
            return [
                'plugin' => 'QuePasaHoy MEC API',
                'version' => '1.0.1',
                'mec_active' => $mec_active,
                'mec_version' => $mec_active ? MEC_VERSION : 'No activo',
                'php_version' => phpversion(),
                'endpoints' => [
                    'events' => rest_url('gs-mec-import/v1/events'),
                    'locations' => rest_url('gs-mec-import/v1/locations'),
                    'categories' => rest_url('gs-mec-import/v1/categories')
                ]
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});

// =========================================================
// 7. MANEJO DE VERSIONES DE PHP
// =========================================================

// Función auxiliar para manejar trim de forma segura en PHP 8+
if (!function_exists('safe_trim')) {
    function safe_trim($string) {
        if (is_null($string)) {
            return '';
        }
        return trim($string);
    }
}

// Función auxiliar para manejar empty de forma segura en PHP 8+
if (!function_exists('safe_empty')) {
    function safe_empty($value) {
        if (is_null($value)) {
            return true;
        }
        return empty($value);
    }
}

// =========================================================
// 8. ACTIVACIÓN DEL PLUGIN
// =========================================================

register_activation_hook(__FILE__, function() {
    // Verificar dependencias
    if (!defined('MEC_VERSION')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Este plugin requiere Modern Events Calendar. Por favor, instala y activa MEC primero.');
    }
    
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});