<?php
/**
 * Plugin Name: BibliocriSe - Hardening sito (opt-in)
 * Description: Controlli infrastrutturali separati dal plugin Scarto Librario e disattivati per impostazione predefinita.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) exit;

if (!defined('BIBLIOCRISE_RESTRICT_PUBLIC_USER_REST')) {
    define('BIBLIOCRISE_RESTRICT_PUBLIC_USER_REST', false);
}
if (!defined('BIBLIOCRISE_DISABLE_XMLRPC')) {
    define('BIBLIOCRISE_DISABLE_XMLRPC', false);
}

add_filter('rest_pre_dispatch', static function($result, $server, $request) {
    if (!BIBLIOCRISE_RESTRICT_PUBLIC_USER_REST || is_user_logged_in()) return $result;

    $route = (string) $request->get_route();
    if (preg_match('#^/wp/v2/users(?:/|$)#', $route)) {
        return new WP_Error(
            'bibliocrise_user_directory_restricted',
            'Endpoint non disponibile pubblicamente.',
            ['status' => 403]
        );
    }
    return $result;
}, 10, 3);

add_filter('xmlrpc_enabled', static function($enabled) {
    return BIBLIOCRISE_DISABLE_XMLRPC ? false : $enabled;
});

add_filter('wp_headers', static function($headers) {
    if (BIBLIOCRISE_DISABLE_XMLRPC) {
        $headers['X-Pingback'] = '';
    }
    return $headers;
});
