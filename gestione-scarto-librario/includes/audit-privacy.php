<?php
/**
 * Privacy controls and resumable cleanup for audit metadata.
 */

if (!defined('ABSPATH')) exit;

function scarto_audit_sensitive_detail_keys() {
    return [
        'ip',
        'ip_address',
        'ip_hash',
        'user_agent',
        'user_agent_hash',
        'email',
        'subject_email',
        'email_hash',
        'email_fingerprint',
        'mail_hash',
        'password',
        'token',
        'otp',
        'verification_code',
        'address',
        'indirizzo',
        'via',
        'civico',
        'cap',
        'citta',
        'provincia',
        'note_spedizione',
        'notespedizione',
    ];
}

function scarto_audit_redact_string($value) {
    $value = scarto_sanitize_text((string) $value, 200);
    return preg_replace(
        '/[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+/iu',
        '[email rimossa]',
        $value
    ) ?: '';
}

function scarto_sanitize_audit_details($details) {
    if (!is_array($details)) return [];

    $blocked_keys = scarto_audit_sensitive_detail_keys();
    $clean = [];
    foreach ($details as $key => $value) {
        $safe_key = scarto_sanitize_text((string) $key, 50);
        $normalized_key = strtolower(str_replace(['-', ' '], '_', $safe_key));
        if ($safe_key === '' || in_array($normalized_key, $blocked_keys, true)) continue;

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            $clean[$safe_key] = $value;
        } elseif (is_string($value)) {
            $clean[$safe_key] = scarto_audit_redact_string($value);
        } elseif (is_array($value)) {
            $clean[$safe_key] = scarto_sanitize_audit_details($value);
        }
    }
    return $clean;
}

function scarto_sanitize_audit_entity_id($entity_id) {
    if ($entity_id === null || $entity_id === '') return null;
    $entity_id = scarto_sanitize_text($entity_id, 50);
    if ($entity_id === '' || preg_match('/@|%40/i', $entity_id)) return null;
    return $entity_id;
}

function scarto_audit_privacy_default_state() {
    return [
        'version' => SCARTO_AUDIT_PRIVACY_MIGRATION_VERSION,
        'status' => 'pending',
        'cursor' => 0,
        'examined' => 0,
        'updated' => 0,
        'errors' => 0,
        'updated_at' => null,
        'completed_at' => null,
    ];
}

function scarto_get_audit_privacy_migration_state() {
    $state = get_option('scarto_audit_privacy_migration', []);
    if (!is_array($state) || ($state['version'] ?? '') !== SCARTO_AUDIT_PRIVACY_MIGRATION_VERSION) {
        return scarto_audit_privacy_default_state();
    }
    return array_merge(scarto_audit_privacy_default_state(), $state);
}

function scarto_schedule_audit_privacy_migration() {
    $state = scarto_get_audit_privacy_migration_state();
    if ($state['status'] === 'completed') return;

    $saved_state = get_option('scarto_audit_privacy_migration', []);
    if (!is_array($saved_state) || ($saved_state['version'] ?? '') !== SCARTO_AUDIT_PRIVACY_MIGRATION_VERSION) {
        update_option('scarto_audit_privacy_migration', $state, false);
    }
    if (!wp_next_scheduled('scarto_audit_privacy_cleanup')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'scarto_audit_privacy_cleanup');
    }
}

function scarto_run_audit_privacy_migration() {
    global $wpdb;

    $lock_time = (int) get_option('scarto_audit_privacy_migration_lock', 0);
    if ($lock_time > time() - (15 * MINUTE_IN_SECONDS)) return;
    if ($lock_time) delete_option('scarto_audit_privacy_migration_lock');
    if (!add_option('scarto_audit_privacy_migration_lock', time(), '', false)) return;

    try {
        $state = scarto_get_audit_privacy_migration_state();
        if ($state['status'] === 'completed') return;

        $state['status'] = 'running';
        $state['updated_at'] = gmdate('Y-m-d H:i:s');
        update_option('scarto_audit_privacy_migration', $state, false);

        $batch_size = 250;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, details FROM {$wpdb->scarto_audit_log} WHERE id > %d ORDER BY id ASC LIMIT %d",
            (int) $state['cursor'],
            $batch_size
        ), ARRAY_A);
        if ($wpdb->last_error) {
            $state['status'] = 'error';
            $state['errors'] = (int) $state['errors'] + 1;
            $state['updated_at'] = gmdate('Y-m-d H:i:s');
            update_option('scarto_audit_privacy_migration', $state, false);
            return;
        }

        foreach ($rows ?: [] as $row) {
            $state['cursor'] = max((int) $state['cursor'], (int) $row['id']);
            $state['examined'] = (int) $state['examined'] + 1;
            $decoded = json_decode((string) $row['details'], true);
            if (!is_array($decoded)) {
                $state['errors'] = (int) $state['errors'] + 1;
                // Corrupt legacy metadata cannot be safely interpreted: replace it with an empty object.
                $decoded = [];
            }

            $encoded = wp_json_encode(scarto_sanitize_audit_details($decoded), JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded) || hash_equals((string) $row['details'], $encoded)) continue;
            $updated = $wpdb->update(
                $wpdb->scarto_audit_log,
                ['details' => $encoded],
                ['id' => (int) $row['id']],
                ['%s'],
                ['%d']
            );
            if ($updated === false) {
                $state['status'] = 'error';
                $state['errors'] = (int) $state['errors'] + 1;
                $state['updated_at'] = gmdate('Y-m-d H:i:s');
                update_option('scarto_audit_privacy_migration', $state, false);
                return;
            }
            $state['updated'] = (int) $state['updated'] + $updated;
        }

        $state['updated_at'] = gmdate('Y-m-d H:i:s');
        if (count($rows ?: []) < $batch_size) {
            $state['status'] = 'completed';
            $state['completed_at'] = $state['updated_at'];
            update_option('scarto_audit_privacy_migration', $state, false);
            scarto_audit_log('audit_privacy_migration_completed', null, null, [
                'examined' => (int) $state['examined'],
                'updated' => (int) $state['updated'],
                'errors' => (int) $state['errors'],
            ], ['category' => 'privacy']);
            return;
        }

        update_option('scarto_audit_privacy_migration', $state, false);
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'scarto_audit_privacy_cleanup');
    } finally {
        delete_option('scarto_audit_privacy_migration_lock');
    }
}

add_action('admin_init', 'scarto_schedule_audit_privacy_migration', 30);
add_action('scarto_audit_privacy_cleanup', 'scarto_run_audit_privacy_migration');

add_action('admin_post_scarto_retry_audit_privacy_migration', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_retry_audit_privacy_migration');

    $rate_key = 'audit_privacy_retry_' . get_current_user_id() . '_' . scarto_get_rate_limit_ip();
    if (!scarto_rate_limit_consume($rate_key, scarto_get_rate_limit('max_login_attempts'), scarto_get_rate_limit('login_lockout_minutes') * MINUTE_IN_SECONDS)) {
        wp_die('Troppi tentativi. Riprova piu tardi.', '', ['response' => 429]);
    }
    if (!scarto_verify_password((string) wp_unslash($_POST['password'] ?? ''), get_option('scarto_db_admin_password_hash'))) {
        wp_die('Password di sicurezza errata.', '', ['response' => 403]);
    }
    scarto_rate_limit_reset($rate_key);

    $state = scarto_get_audit_privacy_migration_state();
    $state['status'] = 'pending';
    $state['updated_at'] = gmdate('Y-m-d H:i:s');
    update_option('scarto_audit_privacy_migration', $state, false);
    wp_clear_scheduled_hook('scarto_audit_privacy_cleanup');
    wp_schedule_single_event(time() + 1, 'scarto_audit_privacy_cleanup');
    scarto_audit_log('audit_privacy_migration_retried', 'wordpress_user', (string) get_current_user_id(), [], ['category' => 'privacy']);

    wp_safe_redirect(add_query_arg(['page' => 'scarto-security', 'audit_privacy_retry' => '1'], admin_url('admin.php')));
    exit;
});
