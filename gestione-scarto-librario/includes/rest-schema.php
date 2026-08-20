<?php
/**
 * Declarative REST API contracts.
 */

if (!defined('ABSPATH')) exit;

function scarto_rest_text_arg($required = false, $max_length = SCARTO_MAX_TEXT_LENGTH) {
    return [
        'type' => 'string',
        'required' => $required,
        'maxLength' => $max_length,
        'sanitize_callback' => static function($value) use ($max_length) {
            return scarto_sanitize_text($value, $max_length);
        },
    ];
}

function scarto_rest_password_arg($required = true) {
    return [
        'type' => 'string',
        'required' => $required,
        'minLength' => $required ? 1 : 0,
        'maxLength' => SCARTO_MAX_PASSWORD_LENGTH,
    ];
}

function scarto_rest_object_arg($required, $properties, $required_properties = [], $additional_properties = false, $min_properties = null) {
    $schema = [
        'type' => 'object',
        'properties' => $properties,
        'additionalProperties' => $additional_properties,
    ];
    if ($required_properties) {
        $schema['required'] = $required_properties;
    }
    if ($min_properties !== null) {
        $schema['minProperties'] = (int) $min_properties;
    }

    return [
        'type' => 'object',
        'required' => (bool) $required,
        'properties' => $properties,
        'additionalProperties' => $additional_properties,
        'validate_callback' => static function($value, $request, $param) use ($schema) {
            return rest_validate_value_from_schema($value, $schema, $param);
        },
        'sanitize_callback' => static function($value, $request, $param) use ($schema) {
            return rest_sanitize_value_from_schema($value, $schema, $param);
        },
    ];
}

function scarto_rest_settings_properties() {
    return [
        'reservation_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
        'email_from' => ['type' => 'string', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
        'email_to' => ['type' => 'string', 'maxLength' => 2000],
        'email_from_name' => ['type' => 'string', 'maxLength' => 200],
        'email_subject_prefix' => ['type' => 'string', 'maxLength' => 200],
        'library_name' => ['type' => 'string', 'maxLength' => 200],
        'library_address' => ['type' => 'string', 'maxLength' => 500],
        'library_phone' => ['type' => 'string', 'maxLength' => 100],
        'max_books_per_reservation' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        'homepage_url' => ['type' => 'string', 'maxLength' => 2048],
        'privacy_policy_url' => ['type' => 'string', 'maxLength' => 2048],
        'retention_completed' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 730],
        'retention_cancelled' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365],
        'retention_expired' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365],
        'retention_audit_logs' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365],
        'retention_ip' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90],
        'retention_plan_approved' => ['type' => 'boolean'],
        'max_login_attempts' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        'login_lockout_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
        'max_reservations_per_day' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
        'max_reservations_per_email' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        'max_active_reservations_per_email' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
        'rate_limit_email_exemptions' => ['type' => 'string', 'maxLength' => 5000],
        'reservation_email_blocklist' => ['type' => 'string', 'maxLength' => 20000],
        'dpo_name' => ['type' => 'string', 'maxLength' => 200],
        'dpo_email' => ['type' => 'string', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
        'dpo_phone' => ['type' => 'string', 'maxLength' => 100],
        'contact_pec' => ['type' => 'string', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
    ];
}

function scarto_rest_book_schema($import = false) {
    $properties = [
        'id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
    ];
    if ($import) {
        $properties += [
            'scatola' => ['type' => 'string', 'maxLength' => 100],
            'autore' => ['type' => 'string', 'maxLength' => 500],
            'titolo' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1000],
            'editore' => ['type' => 'string', 'maxLength' => 500],
            'anno' => ['type' => 'string', 'maxLength' => 100],
            'inventario' => ['type' => 'string', 'maxLength' => 100],
            'collocazione' => ['type' => 'string', 'maxLength' => 200],
            'stato' => ['type' => 'string', 'maxLength' => 100],
            'motivazioni' => ['type' => 'string', 'maxLength' => 2000],
            'note' => ['type' => 'string', 'maxLength' => 2000],
        ];
    }

    return [
        'type' => 'object',
        'required' => $import ? ['id', 'titolo'] : ['id'],
        'properties' => $properties,
        'additionalProperties' => !$import,
    ];
}

function scarto_rest_route_args($route) {
    switch ($route) {
        case 'public_catalog':
            return [
                'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000],
                'per_page' => ['type' => 'integer', 'default' => SCARTO_DEFAULT_PER_PAGE, 'minimum' => 10, 'maximum' => SCARTO_MAX_PER_PAGE],
                'search' => scarto_rest_text_arg(false, 200),
            ];
        case 'catalog':
            return [
                'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000],
                'per_page' => ['type' => 'integer', 'default' => SCARTO_DEFAULT_PER_PAGE, 'minimum' => 10, 'maximum' => SCARTO_MAX_PER_PAGE],
                'search' => scarto_rest_text_arg(false, 200),
                'scatola' => scarto_rest_text_arg(false, 100),
            ];
        case 'orders':
            return [
                'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000],
                'per_page' => ['type' => 'integer', 'default' => 50, 'minimum' => 10, 'maximum' => 100],
                'search' => scarto_rest_text_arg(false, 200),
                'status' => ['type' => 'string', 'default' => 'all', 'enum' => ['all', 'active']],
            ];
        case 'books_search':
            return [
                'q' => scarto_rest_text_arg(true, 200),
                'limit' => ['type' => 'integer', 'default' => 20, 'minimum' => 5, 'maximum' => 50],
            ];
        case 'reserve':
        case 'staff_reserve':
            $staff_reservation = $route === 'staff_reserve';
            $required_user_fields = $staff_reservation
                ? ['nome', 'cognome']
                : ['nome', 'cognome', 'email'];
            $email_schema = ['type' => 'string', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH];
            if (!$staff_reservation) {
                $email_schema['format'] = 'email';
            }
            return [
                'reservation' => scarto_rest_object_arg(false, [], [], true),
                'booksDetails' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                    'maxItems' => $staff_reservation ? SCARTO_MAX_BOOKS_IMPORT : 100,
                    'items' => scarto_rest_book_schema(false),
                ],
                'userData' => scarto_rest_object_arg(
                    true,
                    [
                        'nome' => ['type' => 'string', 'minLength' => 1, 'maxLength' => SCARTO_MAX_NAME_LENGTH],
                        'cognome' => ['type' => 'string', 'minLength' => 1, 'maxLength' => SCARTO_MAX_NAME_LENGTH],
                        // Staff may omit email; the domain validator checks a non-empty value.
                        'email' => $email_schema,
                        // Keep the legacy aggregate address optional for one upgrade cycle.
                        'indirizzo' => ['type' => 'string', 'maxLength' => SCARTO_MAX_ADDRESS_LENGTH],
                        'via' => ['type' => 'string', 'maxLength' => SCARTO_MAX_STREET_LENGTH],
                        'civico' => ['type' => 'string', 'maxLength' => SCARTO_MAX_STREET_NUMBER_LENGTH],
                        // Empty optional values keep cached 9.4.1 clients compatible.
                        'cap' => ['type' => 'string', 'pattern' => '^(?:[0-9]{5})?$'],
                        'citta' => ['type' => 'string', 'maxLength' => SCARTO_MAX_CITY_LENGTH],
                        'provincia' => ['type' => 'string', 'pattern' => '^(?:[A-Za-z]{2})?$'],
                        'noteSpedizione' => ['type' => 'string', 'maxLength' => SCARTO_MAX_SHIPPING_NOTES_LENGTH],
                    ],
                    $required_user_fields
                ),
                'consent' => scarto_rest_object_arg(
                    true,
                    [
                        'accepted' => ['type' => 'boolean', 'enum' => [true]],
                        'privacyVersion' => ['type' => 'string', 'maxLength' => 20],
                    ],
                    ['accepted']
                ),
            ];
        case 'resend_summary':
            return [
                'code' => ['type' => 'string', 'required' => true, 'pattern' => '^[A-Z2-9]{6,10}$'],
            ];
        case 'reserve_confirm':
            return [
                'requestId' => ['type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{32}$'],
                'verificationCode' => ['type' => 'string', 'required' => true, 'pattern' => '^[0-9]{6}$'],
            ];
        case 'status':
            return [
                'code' => ['type' => 'string', 'required' => true, 'pattern' => '^[A-Z2-9]{6,10}$'],
                'action' => ['type' => 'string', 'required' => true, 'enum' => ['complete', 'cancel', 'expired', 'revoke']],
            ];
        case 'save_settings':
            return [
                'settings' => scarto_rest_object_arg(true, scarto_rest_settings_properties(), [], false, 1),
                'password' => scarto_rest_password_arg(false),
            ];
        case 'books_import':
            return [
                'password' => scarto_rest_password_arg(),
                'force' => ['type' => 'boolean', 'default' => false],
                'books' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                    'maxItems' => SCARTO_MAX_BOOKS_IMPORT,
                    'items' => scarto_rest_book_schema(true),
                ],
            ];
        case 'db_password':
            return ['password' => scarto_rest_password_arg()];
        case 'cleanup':
            return [
                'password' => scarto_rest_password_arg(),
                'job' => ['type' => 'string', 'default' => 'all', 'enum' => ['all', 'ip', 'gdpr', 'audit', 'expired']],
            ];
        case 'gdpr_request':
            return [
                'email' => ['type' => 'string', 'required' => true, 'format' => 'email', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
                'action' => ['type' => 'string', 'required' => true, 'enum' => ['export', 'delete']],
            ];
        case 'gdpr_verify':
            return [
                'email' => ['type' => 'string', 'required' => true, 'format' => 'email', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
                'token' => ['type' => 'string', 'required' => true, 'pattern' => '^[a-fA-F0-9]{64}$'],
            ];
        case 'gdpr_admin_export':
            return [
                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
                'code' => ['type' => 'string', 'maxLength' => 10],
            ];
        case 'gdpr_admin_delete':
            return [
                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => SCARTO_MAX_EMAIL_LENGTH],
                'code' => ['type' => 'string', 'maxLength' => 10],
                'confirm' => ['type' => 'boolean', 'required' => true, 'enum' => [true]],
                'password' => scarto_rest_password_arg(),
            ];
        default:
            return [];
    }
}

function scarto_rest_allowed_json_fields($route) {
    $map = [
        '/scarto/v1/reserve' => ['reservation', 'booksDetails', 'userData', 'consent'],
        '/scarto/v1/reserve/confirm' => ['requestId', 'verificationCode'],
        '/scarto/v1/admin/reservations' => ['booksDetails', 'userData', 'consent'],
        '/scarto/v1/admin/reservations/resend' => ['code'],
        '/scarto/v1/status' => ['code', 'action'],
        '/scarto/v1/admin/settings' => ['settings'],
        '/scarto/v1/orders' => ['page', 'per_page', 'search', 'status'],
        '/scarto/v1/books' => ['password', 'force', 'books'],
        '/scarto/v1/reset' => ['password'],
        '/scarto/v1/purge-all-data' => ['password'],
        '/scarto/v1/run-cleanup' => ['password', 'job'],
        '/scarto/v1/gdpr/request' => ['email', 'action'],
        '/scarto/v1/gdpr/verify' => ['email', 'token'],
        '/scarto/v1/gdpr/export' => ['email', 'code'],
        '/scarto/v1/gdpr/delete' => ['email', 'code', 'confirm', 'password'],
    ];
    return $map[$route] ?? null;
}
