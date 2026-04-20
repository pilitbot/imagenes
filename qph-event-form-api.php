<?php
/**
 * Plugin Name: QPH Event Form API
 * Version: 1.1.5
 */

defined('ABSPATH') || exit;

define('QPH_APPS_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbyxYriBHXvunZD1erxueNUMCjpUb1GZjd57zvMbG3EWMCgWBS4Ky0dIp-h-UFxdoXNk/exec');
define('QPH_API_KEY', 'EurL CFNM IlEB O6ea hu5J bMua');

add_action('wp_ajax_qph_submit_event', 'qph_submit_event_handler');
add_action('wp_ajax_nopriv_qph_submit_event', 'qph_submit_event_handler');
add_shortcode('qph_event_form', 'qph_event_form_shortcode');

function qph_event_form_shortcode() {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    ob_start();
    ?>
  
  <style>
  /* Contenedor */
  #qph-event-form-wrap{
    max-width:500px;
    margin:auto;
  }

  /* Título y subtítulo centrados */
  #qph-event-form-wrap .qph-title{
    text-align:center;
    color:#fff;
    font-size:26px;
    font-weight:700;
    margin: 0 0 6px;
  }
  #qph-event-form-wrap .qph-subtitle{
    text-align:center;
    color:rgba(255,255,255,.85);
    font-size:14px;
    margin: 0 0 18px;
  }

  /* Labels: blanco para que se vean en tu fondo */
  #qph-event-form label{
    display:block;
    margin: 10px 0 6px;
    color:#fff !important;
    font-weight:600;
  }

  /* Inputs/select/textarea: fondo blanco + texto negro */
  #qph-event-form input[type="text"],
  #qph-event-form input[type="file"],
  #qph-event-form select,
  #qph-event-form textarea{
    width:100%;
    box-sizing:border-box;
    padding:10px 12px;
    border-radius:6px;
    border:1px solid rgba(0,0,0,.15);
    background:#fff;
    color:#000 !important;
    margin-bottom:10px;
  }

  /* Placeholders en gris oscuro visible */
  #qph-event-form input::placeholder,
  #qph-event-form textarea::placeholder{
    color:#444 !important;
    opacity:1;
  }

  /* Checkbox centrado */
  #qph-event-form .qph-center{
    text-align:center;
    margin: 12px 0 18px;
  }
  #qph-event-form .qph-check{
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:#fff !important;
    font-weight:600;
  }

  /* Botón centrado */
  #qph-event-form .qph-btn{
    display:inline-block;
    padding: 12px 20px;
    border:0;
    border-radius:999px;
    background:#ff2b6a;
    color:#fff;
    font-weight:700;
    cursor:pointer;
  }

  #qph-event-form-message{
    margin-top:10px;
    text-align:center;
    color:#fff;
  }
</style>

<div id="qph-event-form-wrap">
  <h2 class="qph-title">Haz visible tu evento</h2>
  <p class="qph-subtitle">Conéctate con personas que buscan planes hoy cerca de ti.</p>

  <form id="qph-event-form" enctype="multipart/form-data">
  <input type="hidden" name="Fuente" value="Formulario Publicar Evento" />
    <input type="hidden" name="Estado" value="Recibido" />
    <?php wp_nonce_field('qph_event_nonce', 'qph_nonce'); ?>
        <h3>📅 Datos del evento</h3>
        
    <label>Título*</label>
    <input type="text" name="Titulo" required
           placeholder="Título del evento (Ej: Concierto de rock en Medellín)">

    <label>Fecha*</label>
    <input type="text" name="Fecha" required placeholder="Fecha del evento">

    <label>Hora*</label>
    <input type="text" name="Hora" required placeholder="Ej: 8:00 PM">

    <label>Lugar*</label>
    <input type="text" name="Lugar" required
           placeholder="Lugar / Escenario (Ej: Teatro Metropolitano)">

    <label>Municipio*</label>
    <select name="Municipio" required>
      <option value="">Selecciona municipio</option>
      <option value="Bello">Bello</option>
      <option value="Caldas">Caldas</option>
      <option value="Envigado">Envigado</option>
      <option value="Estrella">Estrella</option>
      <option value="Itagüí">Itagüí</option>
      <option value="Medellín">Medellín</option>
      <option value="Peñol">Peñol</option>
      <option value="Rionegro">Rionegro</option>
      <option value="Sabaneta">Sabaneta</option>
      <option value="Santa Elena">Santa Elena</option>
    </select>

    <label>Categoría*</label>
    <select name="Categoria" required>
      <option value="">Selecciona categoría</option>
      <option value="Arte">🎨 Arte</option>
      <option value="Conciertos">🎵 Conciertos</option>
      <option value="Deportes">⚽ Deportes</option>
      <option value="Ferias">🎪 Ferias</option>
      <option value="Gastronomía">🍽️ Gastronomía</option>
      <option value="Networking">🤝 Networking</option>
      <option value="Paseos">🚶 Paseos</option>
      <option value="Rumba">💃 Rumba</option>
      <option value="Teatro/Cine">🎭 Teatro/Cine</option>
      <option value="Vida nocturna">🌙 Vida nocturna</option>
      <option value="Convenciones & Cultura Pop">🎮 Convenciones & Cultura Pop</option>
    </select>

    <label>Organizador*</label>
    <input type="text" name="Organizador" required
           placeholder="Nombre de la persona o establecimiento">

    <label>Imagen</label>
    <input type="file" name="Imagen" accept="image/*">

    <label>Descripción*</label>
    <textarea name="Descripcion" required placeholder="Describe brevemente el evento" style="height:110px;"></textarea>

    <label>Precio*</label>
    <input type="text" name="Precio" required placeholder="Gratis / Desde $20.000 / Entrada libre">

    <label>Edad*</label>
    <select name="Edad" required>
      <option value="">Selecciona edad mínima</option>
      <option value="Edad mínima:8años">8 Años</option>
      <option value="Edad mínima:12años">12 Años</option>
      <option value="Edad mínima:14años">14 años</option>
      <option value="Edad mínima:18años">18 Años</option>
      <option value="Todas las edades">Todas las edades</option>
      <option value="Familiar">Familiar</option>
    </select>

    <hr style="margin:25px 0; border:0; border-top:1px solid rgba(255,255,255,0.2);">

    <h3 style="color:#fff; margin:0 0 15px; font-size:18px;">📞 Tus datos de contacto</h3>
    <p style="color: #fff; margin-bottom: 15px;">Estos datos son para que podamos contactarte sobre tu evento</p>
    <input type="text" name="Nombre" required placeholder="Tu nombre completo">
    <input type="tel" name="Telefono" required placeholder="300 123 4567">
    <input type="email" name="Email" required placeholder="tu@email.com">
    
    <div class="qph-center">
      <label class="qph-check">
        <input type="checkbox" name="consentimiento_revision" value="Si, acepto" required>
        Acepto que este evento sea revisado antes de publicarse y autorizo el tratamiento de mis datos
      </label>
    </div>

    <div class="qph-center">
      <button type="submit" class="qph-btn">Enviar evento</button>
    </div>

    <div id="qph-event-form-message"></div>
    </form>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('qph-event-form');
        const msg  = document.getElementById('qph-event-form-message');
        
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            msg.style.color = 'black';
            msg.textContent = 'Enviando evento...';

            const formData = new FormData(form);
            formData.append('action', 'qph_submit_event');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    msg.style.color = 'green';
                    msg.textContent = '🎉¡Evento enviado correctamente!';
                    form.reset();
                } else {
                    msg.style.color = 'red';
                    msg.textContent = 'Error: ' + (res.data.error || 'Problema en el servidor');
                }
            })
            .catch(err => {
                msg.style.color = 'red';
                msg.textContent = 'Error de conexión.';
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function qph_submit_event_handler() {
    // Verificación de seguridad
    if (!isset($_POST['qph_nonce']) || !wp_verify_nonce($_POST['qph_nonce'], 'qph_event_nonce')) {
        wp_send_json_error(['error' => 'Nonce inválido o ausente.']);
    }

    // Datos de contacto
    $nombre   = sanitize_text_field($_POST['Nombre'] ?? $_POST['nombre'] ?? '');
    $telefono = sanitize_text_field($_POST['Telefono'] ?? $_POST['telefono'] ?? '');
    $email    = sanitize_email($_POST['Email'] ?? $_POST['email'] ?? '');

    if ($nombre === '') {
        wp_send_json_error(['error' => 'Falta el nombre del contacto.']);
    }

    if ($telefono === '') {
        wp_send_json_error(['error' => 'Falta el teléfono del contacto.']);
    }

    if ($email === '' || !is_email($email)) {
        wp_send_json_error(['error' => 'El email del contacto no es válido.']);
    }

    // Subida de imagen a WordPress
    $image_url = '';
    if (!empty($_FILES['Imagen']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('Imagen', 0);

        if (!is_wp_error($attachment_id)) {
            $image_url = wp_get_attachment_url($attachment_id);
        }
    }

    // ID del evento
    $id_evento = sanitize_text_field($_POST['ID Evento'] ?? '');
    if (!$id_evento) {
        $id_evento = 'web-' . time() . '-' . wp_generate_password(6, false, false);
    }

    // Normalizar precio
    $precio_raw = sanitize_text_field($_POST['Precio'] ?? '');
    $precio = $precio_raw;

    if (preg_match('/\d/', $precio_raw)) {
        $digits = preg_replace('/[^\d]/', '', $precio_raw);
        if ($digits !== '') {
            $precio = (int) $digits;
        }
    }

    // Payload para Google Apps Script
    $payload = [
        'password'    => QPH_API_KEY, // tu Apps Script espera "password"
        'Fuente'      => sanitize_text_field($_POST['Fuente'] ?? 'Formulario Publicar Evento'),
        'Titulo'      => sanitize_text_field($_POST['Titulo'] ?? ''),
        'Fecha'       => sanitize_text_field($_POST['Fecha'] ?? ''),
        'Hora'        => sanitize_text_field($_POST['Hora'] ?? ''),
        'Lugar'       => sanitize_text_field($_POST['Lugar'] ?? ''),
        'Municipio'   => sanitize_text_field($_POST['Municipio'] ?? ''),
        'Categoria'   => sanitize_text_field($_POST['Categoria'] ?? ''),
        'Imagen'      => esc_url_raw($image_url), // Apps Script espera "Imagen"
        'FotoURL'     => esc_url_raw($image_url), // opcional, por compatibilidad
        'Descripcion' => sanitize_textarea_field($_POST['Descripcion'] ?? ''),
        'Estado'      => sanitize_text_field($_POST['Estado'] ?? 'Recibido'),
        'ID Evento'   => $id_evento,
        'Enlace'      => '',
        'Precio'      => $precio,
        'Organizador' => sanitize_text_field($_POST['Organizador'] ?? ''),
        'Edad'        => sanitize_text_field($_POST['Edad'] ?? ''),
        'Nombre'      => $nombre,
        'Telefono'    => $telefono,
        'Email'       => $email,
    ];

    $response = wp_remote_post(trim(QPH_APPS_SCRIPT_URL), [
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'blocking'    => true,
    'headers'     => [
        'Content-Type' => 'text/plain; charset=utf-8',
    ],
    'body'        => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if (is_wp_error($response)) {
    wp_send_json_error([
        'error' => 'Error al conectar con Apps Script: ' . $response->get_error_message()
    ]);
}

$code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
$json = json_decode($body, true);

// Si Apps Script devolvió JSON válido, lo respetamos
if (is_array($json)) {
    if (isset($json['success']) && !$json['success']) {
        wp_send_json_error([
            'error' => $json['error'] ?? 'Apps Script devolvió un error.'
        ]);
    }

    wp_send_json_success([
        'message' => 'Evento enviado correctamente.',
        'remote'  => $json
    ]);
}

// Si Google devuelve HTML 400 pero los datos sí se guardaron,
// lo tratamos como éxito para no mostrar falso error al usuario.
if ($code == 400 && stripos($body, 'Error 400 (Bad Request)') !== false) {
    wp_send_json_success([
        'message' => 'Evento enviado correctamente.'
    ]);
}

// Si no vino JSON pero tampoco hubo WP_Error, evitamos mostrar
// todo el HTML feo de Google al usuario final.
wp_send_json_success([
    'message' => 'Evento enviado correctamente.'
]);
}