<?php
/**
 * Security primitives for Gestione Scarto Librario.
 */

if (!defined('ABSPATH')) exit;

define('SCARTO_SESSION_COOKIE', '__Host-scarto_staff_session');
define('SCARTO_SESSION_TTL', 30 * MINUTE_IN_SECONDS);
define('SCARTO_SESSION_ABSOLUTE_TTL', 8 * HOUR_IN_SECONDS);
define('SCARTO_PUBLIC_BODY_LIMIT', 131072);
define('SCARTO_ADMIN_BODY_LIMIT', 1048576);
define('SCARTO_IMPORT_BODY_LIMIT', 20 * 1024 * 1024);

function scarto_request_body_within_limit($limit) {
    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    return $length <= $limit;
}

function scarto_normalize_origin($url) {
    $parts = wp_parse_url((string) $url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower(rtrim($parts['host'], '.'));
    $port = isset($parts['port'])
        ? (int) $parts['port']
        : ($scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : 0));

    if (!in_array($scheme, ['http', 'https'], true) || $port < 1) {
        return '';
    }

    return $scheme . '://' . $host . ':' . $port;
}

function scarto_verify_request_origin($request) {
    $origin = trim((string) $request->get_header('origin'));
    if ($origin === '') {
        return true;
    }

    $request_origin = scarto_normalize_origin($origin);
    $site_origin = scarto_normalize_origin(home_url('/'));
    if ($request_origin === '' || $site_origin === '' || !hash_equals($site_origin, $request_origin)) {
        return new WP_Error('invalid_origin', 'Origine della richiesta non consentita.', ['status' => 403]);
    }

    return true;
}

function scarto_verify_json_request($request, $limit = SCARTO_PUBLIC_BODY_LIMIT) {
    if (!scarto_request_body_within_limit($limit)) {
        return new WP_Error('request_too_large', 'Richiesta troppo grande.', ['status' => 413]);
    }

    $content_type = (string) $request->get_header('content-type');
    if (stripos($content_type, 'application/json') === false) {
        return new WP_Error('invalid_content_type', 'Content-Type non valido.', ['status' => 415]);
    }

    $json = $request->get_json_params();
    if (!is_array($json)) {
        return new WP_Error('invalid_json', 'JSON non valido.', ['status' => 400]);
    }

    if (function_exists('scarto_rest_allowed_json_fields')) {
        $allowed = scarto_rest_allowed_json_fields($request->get_route());
        if (is_array($allowed)) {
            $unexpected = array_diff(array_keys($json), $allowed);
            if ($unexpected) {
                return new WP_Error(
                    'unexpected_fields',
                    'Campi non consentiti: ' . implode(', ', array_slice($unexpected, 0, 10)),
                    ['status' => 400]
                );
            }
        }
    }

    return scarto_verify_request_origin($request);
}

function scarto_session_key($token) {
    return 'scarto_session_' . hash('sha256', $token);
}

function scarto_set_session_cookie($token, $expires) {
    setcookie(SCARTO_SESSION_COOKIE, $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function scarto_clear_session_cookie() {
    setcookie(SCARTO_SESSION_COOKIE, '', [
        'expires' => time() - HOUR_IN_SECONDS,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function scarto_create_staff_session() {
    $now = time();
    $token = bin2hex(random_bytes(32));
    $csrf = bin2hex(random_bytes(32));
    $session = [
        'csrf' => $csrf,
        'csrf_hash' => hash('sha256', $csrf),
        'created_at' => $now,
        'last_seen' => $now,
        'absolute_expires' => $now + SCARTO_SESSION_ABSOLUTE_TTL,
        'auth_generation' => (int) get_option('scarto_auth_generation', 1),
        'session_id' => bin2hex(random_bytes(8)),
    ];

    set_transient(scarto_session_key($token), $session, SCARTO_SESSION_TTL);
    scarto_set_session_cookie($token, $session['absolute_expires']);

    return ['csrf' => $csrf, 'session_id' => $session['session_id']];
}

function scarto_get_staff_session($request = null, $require_csrf = false) {
    $token = isset($_COOKIE[SCARTO_SESSION_COOKIE])
        ? sanitize_text_field(wp_unslash($_COOKIE[SCARTO_SESSION_COOKIE]))
        : '';

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return new WP_Error('rest_forbidden', 'Sessione non valida.', ['status' => 401]);
    }

    $key = scarto_session_key($token);
    $session = get_transient($key);
    $now = time();

    if (!is_array($session)
        || empty($session['absolute_expires'])
        || $session['absolute_expires'] < $now
        || (int) ($session['auth_generation'] ?? 0) !== (int) get_option('scarto_auth_generation', 1)
    ) {
        delete_transient($key);
        scarto_clear_session_cookie();
        return new WP_Error('rest_forbidden', 'Sessione scaduta.', ['status' => 401]);
    }

    if ($require_csrf) {
        $csrf = $request ? (string) $request->get_header('X-Scarto-CSRF') : '';
        if ($csrf === '' || !hash_equals((string) $session['csrf_hash'], hash('sha256', $csrf))) {
            return new WP_Error('rest_forbidden', 'Token CSRF non valido.', ['status' => 403]);
        }
    }

    $session['last_seen'] = $now;
    $remaining = min(SCARTO_SESSION_TTL, $session['absolute_expires'] - $now);
    set_transient($key, $session, max(1, $remaining));

    return ['token' => $token, 'key' => $key, 'data' => $session];
}

function scarto_verify_wp_admin_capability($request, $capability, $json_request = true, $body_limit = SCARTO_ADMIN_BODY_LIMIT) {
    $request_check = $json_request
        ? scarto_verify_json_request($request, $body_limit)
        : scarto_verify_request_origin($request);
    if (is_wp_error($request_check)) return $request_check;

    if (!is_user_logged_in() || !current_user_can($capability)) {
        return new WP_Error('rest_forbidden', 'Autorizzazione WordPress insufficiente.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_forbidden', 'Nonce WordPress non valido.', ['status' => 403]);
    }

    return true;
}

function scarto_verify_staff_session($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_MANAGE, true);
}

function scarto_verify_staff_read($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_VIEW, false);
}

function scarto_verify_catalog_read($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_CATALOG, false);
}

function scarto_verify_orders_access($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_VIEW, true);
}

function scarto_verify_settings_read($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_SETTINGS, false);
}

function scarto_verify_settings_write($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_SETTINGS, true);
}

function scarto_verify_privacy_access($request) {
    return scarto_verify_wp_admin_capability($request, SCARTO_CAP_PRIVACY, true);
}

function scarto_destroy_staff_session() {
    $token = isset($_COOKIE[SCARTO_SESSION_COOKIE])
        ? sanitize_text_field(wp_unslash($_COOKIE[SCARTO_SESSION_COOKIE]))
        : '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        delete_transient(scarto_session_key($token));
    }
    scarto_clear_session_cookie();
}

function scarto_invalidate_all_staff_sessions() {
    $generation = (int) get_option('scarto_auth_generation', 1);
    update_option('scarto_auth_generation', $generation + 1, false);
}

function scarto_private_response($data, $status = 200) {
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    $response->header('Pragma', 'no-cache');
    $response->header('Expires', '0');
    $response->header('Vary', 'Cookie, X-WP-Nonce');
    return $response;
}

function scarto_public_response($data, $status = 200) {
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'no-cache, must-revalidate');
    return $response;
}

add_filter('rest_post_dispatch', function($response, $server, $request) {
    $route = $request->get_route();
    $private_routes = [
        '/scarto/v1/reserve',
        '/scarto/v1/reserve/confirm',
        '/scarto/v1/orders',
        '/scarto/v1/status',
        '/scarto/v1/admin/catalog',
        '/scarto/v1/admin/settings',
        '/scarto/v1/admin/reservations',
        '/scarto/v1/admin/reservations/resend',
        '/scarto/v1/books',
        '/scarto/v1/reset',
        '/scarto/v1/purge-all-data',
        '/scarto/v1/run-cleanup',
        '/scarto/v1/gdpr/verify',
        '/scarto/v1/gdpr/export',
        '/scarto/v1/gdpr/delete',
    ];

    if (in_array($route, $private_routes, true) && $response instanceof WP_HTTP_Response) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->header('Vary', 'Cookie, X-WP-Nonce');
    }

    return $response;
}, 10, 3);

add_filter('rest_pre_serve_request', function($served, $result, $request) {
    if (strpos($request->get_route(), '/scarto/v1/') !== 0) {
        return $served;
    }

    // The plugin is same-origin only. Remove WordPress' reflective REST CORS headers.
    foreach ([
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Headers',
        'Access-Control-Expose-Headers',
    ] as $header) {
        header_remove($header);
    }

    return $served;
}, PHP_INT_MAX, 3);

function scarto_rate_limit_consume($key, $max_attempts, $window_seconds) {
    global $wpdb;

    $table = $wpdb->scarto_rate_limits;
    $max_attempts = max(1, (int) $max_attempts);
    $key_hash = hash_hmac('sha256', $key, wp_salt('auth'));
    $expires = gmdate('Y-m-d H:i:s', time() + max(1, (int) $window_seconds));

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (key_hash, attempts, window_expires)
         VALUES (%s, 1, %s)
         ON DUPLICATE KEY UPDATE
           attempts = IF(window_expires <= UTC_TIMESTAMP(), 1, LEAST(attempts + 1, %d)),
           window_expires = IF(window_expires <= UTC_TIMESTAMP(), VALUES(window_expires), window_expires)",
        $key_hash,
        $expires,
        $max_attempts + 1
    ));

    if ($wpdb->last_error) {
        return false;
    }

    $attempts = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT attempts FROM {$table} WHERE key_hash = %s",
        $key_hash
    ));

    return $attempts <= $max_attempts;
}

function scarto_rate_limit_reset($key) {
    global $wpdb;
    $wpdb->delete(
        $wpdb->scarto_rate_limits,
        ['key_hash' => hash_hmac('sha256', $key, wp_salt('auth'))],
        ['%s']
    );
}

function scarto_rate_limit_cleanup() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->scarto_rate_limits} WHERE window_expires <= UTC_TIMESTAMP()");
}
