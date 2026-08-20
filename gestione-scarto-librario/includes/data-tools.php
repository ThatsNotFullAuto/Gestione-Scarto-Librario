<?php
/**
 * Complete, private backup and restore tools for plugin-owned data.
 */

if (!defined('ABSPATH')) exit;

define('SCARTO_BACKUP_FORMAT', 3);
define('SCARTO_BACKUP_MAX_BYTES', 20 * 1024 * 1024);

function scarto_backup_legacy_import_allowed() {
    return defined('SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS')
        && SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS === true;
}

function scarto_backup_table_definitions() {
    global $wpdb;
    return [
        'books' => [
            'table' => $wpdb->scarto_books,
            'columns' => ['id', 'scatola', 'autore', 'titolo', 'editore', 'anno', 'inventario', 'collocazione', 'stato', 'motivazioni', 'note', 'created_at', 'updated_at'],
            'limit' => SCARTO_MAX_BOOKS_IMPORT,
        ],
        'orders' => [
            'table' => $wpdb->scarto_orders,
            'columns' => ['id', 'code', 'request_id', 'status', 'user_nome', 'user_cognome', 'user_email', 'user_indirizzo', 'user_via', 'user_civico', 'user_cap', 'user_citta', 'user_provincia', 'user_note_spedizione', 'reservation_source', 'created_at', 'updated_at', 'completed_at', 'expires_at', 'ip_address', 'user_agent', 'privacy_version', 'consent_at'],
            'limit' => 100000,
        ],
        'order_items' => [
            'table' => $wpdb->scarto_order_items,
            'columns' => ['id', 'order_code', 'book_id', 'titolo', 'autore', 'inventario', 'scatola', 'status', 'withdrawn_at'],
            'limit' => 2000000,
        ],
        'audit_log' => [
            'table' => $wpdb->scarto_audit_log,
            'columns' => ['id', 'category', 'action', 'outcome', 'entity_type', 'entity_id', 'subject_email', 'wp_user_id', 'details', 'ip_address', 'user_agent', 'created_at'],
            'limit' => 50000,
        ],
    ];
}

function scarto_backup_export_data() {
    global $wpdb;
    $tables = [];
    foreach (scarto_backup_table_definitions() as $key => $definition) {
        $columns = implode(', ', $definition['columns']);
        $tables[$key] = $wpdb->get_results("SELECT {$columns} FROM {$definition['table']} ORDER BY {$definition['columns'][0]} ASC", ARRAY_A) ?: [];
        if ($wpdb->last_error) throw new RuntimeException('Errore lettura tabella ' . $key . '.');
    }
    return [
        'tables' => $tables,
        'options' => [
            'settings' => scarto_get_settings(),
            'appearance' => scarto_get_appearance_settings(),
            'rate_limit_email_exemptions' => get_option('scarto_rate_limit_email_exemptions', ''),
            'reservation_email_blocklist' => get_option('scarto_reservation_email_blocklist', ''),
            'reservation_email_blocklist_v2' => get_option('scarto_reservation_email_blocklist_v2', []),
            'subject_processing_restrictions' => get_option('scarto_subject_processing_restrictions', []),
        ],
    ];
}

function scarto_backup_document() {
    $data = scarto_backup_export_data();
    $encoded = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) throw new RuntimeException('Impossibile codificare il backup.');
    $counts = [];
    foreach ($data['tables'] as $key => $rows) $counts[$key] = count($rows);
    return [
        'format' => 'gestione-scarto-librario-backup',
        'formatVersion' => SCARTO_BACKUP_FORMAT,
        'pluginVersion' => SCARTO_VERSION,
        'exportedAt' => gmdate('c'),
        'siteUrl' => home_url('/'),
        'counts' => $counts,
        'checksum' => hash('sha256', $encoded),
        'data' => $data,
    ];
}

function scarto_backup_validate_encryption_password($password) {
    $password = (string) $password;
    return strlen($password) >= SCARTO_MIN_PASSWORD_LENGTH
        && strlen($password) <= SCARTO_MAX_PASSWORD_LENGTH
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

function scarto_backup_encrypt_document($document, $password) {
    if (!scarto_backup_validate_encryption_password($password)) {
        throw new InvalidArgumentException('La password di cifratura deve avere 12-72 caratteri, maiuscola, minuscola e numero.');
    }
    if (!function_exists('openssl_encrypt') || !function_exists('hash_pbkdf2')) {
        throw new RuntimeException('Cifratura backup non disponibile sul server.');
    }
    $plain = wp_json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($plain)) throw new RuntimeException('Impossibile codificare il backup.');
    $salt = random_bytes(16);
    $iv = random_bytes(12);
    $iterations = 210000;
    $key = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    $tag = '';
    $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false) throw new RuntimeException('Cifratura del backup non riuscita.');
    return [
        'format' => 'gestione-scarto-librario-backup-encrypted',
        'formatVersion' => 1,
        'cipher' => 'AES-256-GCM',
        'kdf' => 'PBKDF2-HMAC-SHA256',
        'iterations' => $iterations,
        'salt' => base64_encode($salt),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ciphertext' => base64_encode($ciphertext),
    ];
}

function scarto_backup_decrypt_document($envelope, $password) {
    if (!is_array($envelope) || ($envelope['format'] ?? '') !== 'gestione-scarto-librario-backup-encrypted') {
        if (scarto_backup_legacy_import_allowed()
            && is_array($envelope)
            && ($envelope['format'] ?? '') === 'gestione-scarto-librario-backup'
        ) {
            return $envelope;
        }
        throw new InvalidArgumentException('Sono ammessi soltanto backup cifrati. La compatibilità legacy può essere abilitata temporaneamente solo su staging.');
    }
    if (!scarto_backup_validate_encryption_password($password)) throw new InvalidArgumentException('Password di cifratura non valida.');
    $iterations = (int) ($envelope['iterations'] ?? 0);
    $salt = base64_decode((string) ($envelope['salt'] ?? ''), true);
    $iv = base64_decode((string) ($envelope['iv'] ?? ''), true);
    $tag = base64_decode((string) ($envelope['tag'] ?? ''), true);
    $ciphertext = base64_decode((string) ($envelope['ciphertext'] ?? ''), true);
    if ($iterations !== 210000 || strlen((string) $salt) !== 16 || strlen((string) $iv) !== 12 || strlen((string) $tag) !== 16 || $ciphertext === false) {
        throw new InvalidArgumentException('Busta cifrata non valida.');
    }
    $key = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($plain)) throw new InvalidArgumentException('Password di cifratura errata o backup alterato.');
    $document = json_decode($plain, true, 64);
    if (!is_array($document)) throw new InvalidArgumentException('Contenuto cifrato non valido.');
    return $document;
}

function scarto_backup_verify_step_up($password, $context) {
    $ip = scarto_get_rate_limit_ip();
    $key = 'backup_' . sanitize_key($context) . '_' . get_current_user_id() . '_' . $ip;
    $max = scarto_get_rate_limit('max_login_attempts');
    $window = scarto_get_rate_limit('login_lockout_minutes') * MINUTE_IN_SECONDS;
    if (!scarto_rate_limit_consume($key, $max, $window)) return new WP_Error('rate_limit', 'Troppi tentativi. Riprova più tardi.');
    if (!scarto_verify_password($password, get_option('scarto_db_admin_password_hash'))) return new WP_Error('password', 'Password di sicurezza errata.');
    scarto_rate_limit_reset($key);
    return true;
}

function scarto_backup_text($value, $max, $textarea = false) {
    $value = $textarea ? sanitize_textarea_field((string) $value) : scarto_sanitize_text((string) $value, $max);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $max, 'UTF-8');
    return substr($value, 0, $max);
}

function scarto_backup_nullable_uint($value) {
    return $value === null || $value === '' ? null : max(0, (int) $value);
}

function scarto_backup_mysql_datetime($value, $nullable = false) {
    $value = sanitize_text_field((string) $value);
    if ($nullable && $value === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        throw new InvalidArgumentException('Data backup non valida.');
    }
    return $value;
}

function scarto_backup_sanitize_settings($settings) {
    if (!is_array($settings)) throw new InvalidArgumentException('Impostazioni backup non valide.');
    $current = scarto_get_settings();
    $email_from = sanitize_email((string) ($settings['email_from'] ?? ''));
    $email_to = scarto_sanitize_email_list($settings['email_to'] ?? '');
    $dpo_email = sanitize_email((string) ($settings['dpo_email'] ?? ''));
    $contact_pec = sanitize_email((string) ($settings['contact_pec'] ?? ''));

    return array_merge($current, [
        'reservation_days' => max(1, min(30, (int) ($settings['reservation_days'] ?? 7))),
        'email_from' => is_email($email_from) ? $email_from : $current['email_from'],
        'email_to' => $email_to !== '' ? $email_to : $current['email_to'],
        'email_from_name' => scarto_backup_text($settings['email_from_name'] ?? '', 200),
        'email_subject_prefix' => scarto_backup_text($settings['email_subject_prefix'] ?? '', 200),
        'library_name' => scarto_backup_text($settings['library_name'] ?? '', 200),
        'library_address' => scarto_backup_text($settings['library_address'] ?? '', 500),
        'library_phone' => scarto_backup_text($settings['library_phone'] ?? '', 100),
        'max_books_per_reservation' => max(1, min(100, (int) ($settings['max_books_per_reservation'] ?? 20))),
        // Legacy key retained in the archive format; collection is route-based.
        'collect_domicile' => false,
        'homepage_url' => esc_url_raw((string) ($settings['homepage_url'] ?? ''), ['http', 'https']),
        'privacy_policy_url' => esc_url_raw((string) ($settings['privacy_policy_url'] ?? ''), ['http', 'https']),
        'retention_completed' => max(30, min(730, (int) ($settings['retention_completed'] ?? 365))),
        'retention_cancelled' => max(7, min(365, (int) ($settings['retention_cancelled'] ?? 90))),
        'retention_expired' => max(7, min(365, (int) ($settings['retention_expired'] ?? 90))),
        'retention_audit_logs' => max(7, min(365, (int) ($settings['retention_audit_logs'] ?? 90))),
        'retention_ip' => max(1, min(90, (int) ($settings['retention_ip'] ?? 30))),
        'retention_plan_approved' => !empty($settings['retention_plan_approved']),
        'max_login_attempts' => max(1, min(20, (int) ($settings['max_login_attempts'] ?? 5))),
        'login_lockout_minutes' => max(1, min(60, (int) ($settings['login_lockout_minutes'] ?? 15))),
        'max_reservations_per_day' => max(1, min(10, (int) ($settings['max_reservations_per_day'] ?? 1))),
        'max_reservations_per_email' => max(1, min(20, (int) ($settings['max_reservations_per_email'] ?? 2))),
        'max_active_reservations_per_email' => max(1, min(10, (int) ($settings['max_active_reservations_per_email'] ?? 2))),
        'rate_limit_email_exemptions' => scarto_sanitize_email_list($settings['rate_limit_email_exemptions'] ?? ''),
        'reservation_email_blocklist' => scarto_sanitize_email_blocklist($settings['reservation_email_blocklist'] ?? ''),
        'dpo_name' => scarto_backup_text($settings['dpo_name'] ?? '', 200),
        'dpo_email' => is_email($dpo_email) ? $dpo_email : '',
        'dpo_phone' => scarto_backup_text($settings['dpo_phone'] ?? '', 100),
        'contact_pec' => is_email($contact_pec) ? $contact_pec : '',
        'delete_data_on_uninstall' => !empty($settings['delete_data_on_uninstall']),
    ]);
}

function scarto_backup_sanitize_blocklist_entries($entries) {
    if (!is_array($entries)) return [];
    $clean = [];
    foreach (array_slice($entries, 0, 5000) as $entry) {
        if (!is_array($entry)) continue;
        $email = strtolower(sanitize_email((string) ($entry['email'] ?? '')));
        $type = sanitize_key($entry['schedule_type'] ?? '');
        $date = sanitize_text_field((string) ($entry['schedule_date'] ?? ''));
        $created_at = sanitize_text_field((string) ($entry['created_at'] ?? ''));
        if (!$email || !is_email($email) || !in_array($type, ['riesame', 'scadenza'], true)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at)) continue;
        $clean[$email] = [
            'email' => $email,
            'reason' => scarto_backup_text($entry['reason'] ?? '', 180),
            'created_at' => $created_at,
            'created_by' => max(0, (int) ($entry['created_by'] ?? 0)),
            'schedule_type' => $type,
            'schedule_date' => $date,
        ];
    }
    return array_values($clean);
}

function scarto_backup_sanitize_processing_restrictions($entries) {
    if (!is_array($entries)) return [];
    $clean = [];
    foreach (array_slice($entries, 0, 5000, true) as $entry) {
        if (!is_array($entry)) continue;
        $email = strtolower(sanitize_email((string) ($entry['email'] ?? '')));
        $until = sanitize_text_field((string) ($entry['until'] ?? ''));
        $created_at = sanitize_text_field((string) ($entry['created_at'] ?? ''));
        if (!$email || !is_email($email) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)
            || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at)) continue;
        $clean[$email] = [
            'email' => $email,
            'reason' => scarto_backup_text($entry['reason'] ?? '', 300),
            'until' => $until,
            'created_at' => $created_at,
            'created_by' => max(0, (int) ($entry['created_by'] ?? 0)),
        ];
    }
    return $clean;
}

function scarto_backup_sanitize_appearance($appearance) {
    if (!is_array($appearance)) throw new InvalidArgumentException('Aspetto backup non valido.');
    $current = scarto_get_appearance_settings();
    $font = sanitize_key($appearance['font_family'] ?? '');
    if (!isset(scarto_appearance_font_choices()[$font])) $font = $current['font_family'];

    return [
        'primary_color' => scarto_sanitize_hex_color_strict($appearance['primary_color'] ?? '', $current['primary_color']),
        'secondary_color' => scarto_sanitize_hex_color_strict($appearance['secondary_color'] ?? '', $current['secondary_color']),
        'header_opacity' => max(20, min(100, absint($appearance['header_opacity'] ?? 100))),
        'accent_color' => scarto_sanitize_hex_color_strict($appearance['accent_color'] ?? '', $current['accent_color']),
        'background_color' => scarto_sanitize_hex_color_strict($appearance['background_color'] ?? '', $current['background_color']),
        'text_color' => scarto_sanitize_hex_color_strict($appearance['text_color'] ?? '', $current['text_color']),
        'font_family' => $font,
        'logo_id' => absint($appearance['logo_id'] ?? 0),
        'logo_alt' => scarto_backup_text($appearance['logo_alt'] ?? '', 160),
        'site_title' => scarto_backup_text($appearance['site_title'] ?? '', 160),
        'site_subtitle' => scarto_backup_text($appearance['site_subtitle'] ?? '', 200),
        'contact_url' => esc_url_raw((string) ($appearance['contact_url'] ?? ''), ['http', 'https', 'mailto', 'tel']),
        'contact_label' => scarto_backup_text($appearance['contact_label'] ?? '', 80),
    ];
}

function scarto_backup_clean_row($type, $row) {
    if (!is_array($row)) throw new InvalidArgumentException('Riga backup non valida.');
    if ($type === 'books') {
        $clean = [
            'id' => scarto_backup_text($row['id'] ?? '', 50), 'scatola' => scarto_backup_text($row['scatola'] ?? '', 100),
            'autore' => scarto_backup_text($row['autore'] ?? '', 500), 'titolo' => scarto_backup_text($row['titolo'] ?? '', 1000),
            'editore' => scarto_backup_text($row['editore'] ?? '', 500), 'anno' => scarto_backup_text($row['anno'] ?? '', 100),
            'inventario' => scarto_backup_text($row['inventario'] ?? '', 100), 'collocazione' => scarto_backup_text($row['collocazione'] ?? '', 200),
            'stato' => scarto_backup_text($row['stato'] ?? '', 100), 'motivazioni' => scarto_backup_text($row['motivazioni'] ?? '', 2000, true),
            'note' => scarto_backup_text($row['note'] ?? '', 2000, true), 'created_at' => scarto_backup_mysql_datetime($row['created_at'] ?? ''),
            'updated_at' => scarto_backup_mysql_datetime($row['updated_at'] ?? '', true),
        ];
        if ($clean['id'] === '' || $clean['titolo'] === '') throw new InvalidArgumentException('Volume privo di ID o titolo.');
        return $clean;
    }
    if ($type === 'orders') {
        $status = sanitize_key($row['status'] ?? '');
        if (!in_array($status, ['active', 'completed', 'cancelled', 'expired'], true)) throw new InvalidArgumentException('Stato prenotazione non valido.');
        $reservation_source = in_array(($row['reservation_source'] ?? 'online'), ['online', 'in_person'], true)
            ? $row['reservation_source']
            : 'online';
        $email = strtolower(sanitize_email((string) ($row['user_email'] ?? '')));
        $request_id = empty($row['request_id']) ? null : strtolower(sanitize_text_field((string) $row['request_id']));
        if (($email !== '' && !is_email($email))
            || ($reservation_source === 'online' && $email === '')
            || ($request_id !== null && !preg_match('/^[a-f0-9]{32}$/', $request_id))) {
            throw new InvalidArgumentException('Email, origine o request_id non valido.');
        }
        $clean = [
            'id' => max(1, (int) ($row['id'] ?? 0)), 'code' => scarto_backup_text($row['code'] ?? '', 10), 'request_id' => $request_id,
            'status' => $status, 'user_nome' => scarto_backup_text($row['user_nome'] ?? '', 100), 'user_cognome' => scarto_backup_text($row['user_cognome'] ?? '', 100),
            'user_email' => $email, 'user_indirizzo' => scarto_backup_text($row['user_indirizzo'] ?? '', 500),
            'user_via' => scarto_backup_text($row['user_via'] ?? '', 200),
            'user_civico' => scarto_backup_text($row['user_civico'] ?? '', 30),
            'user_cap' => preg_match('/^[0-9]{5}$/', (string) ($row['user_cap'] ?? '')) ? (string) $row['user_cap'] : '',
            'user_citta' => scarto_backup_text($row['user_citta'] ?? '', 120),
            'user_provincia' => preg_match('/^[A-Za-z]{2}$/', (string) ($row['user_provincia'] ?? '')) ? strtoupper((string) $row['user_provincia']) : '',
            'user_note_spedizione' => scarto_backup_text($row['user_note_spedizione'] ?? '', 500),
            'reservation_source' => $reservation_source,
            'created_at' => max(0, (int) ($row['created_at'] ?? 0)), 'updated_at' => scarto_backup_nullable_uint($row['updated_at'] ?? null),
            'completed_at' => scarto_backup_nullable_uint($row['completed_at'] ?? null), 'expires_at' => max(0, (int) ($row['expires_at'] ?? 0)),
            'ip_address' => empty($row['ip_address']) ? null : scarto_backup_text($row['ip_address'], 45),
            'user_agent' => null,
            'privacy_version' => empty($row['privacy_version']) ? null : scarto_backup_text($row['privacy_version'], 20),
            'consent_at' => scarto_backup_nullable_uint($row['consent_at'] ?? null),
        ];
        if (!preg_match('/^[A-Z2-9]{6,10}$/', $clean['code']) || $clean['created_at'] < 1 || $clean['expires_at'] < 1) {
            throw new InvalidArgumentException('Codice o date prenotazione non validi.');
        }
        if ($clean['reservation_source'] === 'in_person' && $clean['user_email'] === '') {
            $has_structured_address = $clean['user_via'] !== ''
                && $clean['user_civico'] !== ''
                && preg_match('/^[0-9]{5}$/', $clean['user_cap'])
                && $clean['user_citta'] !== ''
                && preg_match('/^[A-Z]{2}$/', $clean['user_provincia']);
            if (!$has_structured_address && $clean['user_indirizzo'] === '') {
                throw new InvalidArgumentException('Prenotazione in sede senza email e domicilio.');
            }
        }
        return $clean;
    }
    if ($type === 'order_items') {
        $status = sanitize_key($row['status'] ?? '');
        if (!in_array($status, ['reserved', 'withdrawn', 'released'], true)) throw new InvalidArgumentException('Stato volume prenotato non valido.');
        $clean = [
            'id' => max(1, (int) ($row['id'] ?? 0)), 'order_code' => scarto_backup_text($row['order_code'] ?? '', 10),
            'book_id' => scarto_backup_text($row['book_id'] ?? '', 50), 'titolo' => scarto_backup_text($row['titolo'] ?? '', 1000),
            'autore' => scarto_backup_text($row['autore'] ?? '', 500), 'inventario' => scarto_backup_text($row['inventario'] ?? '', 50),
            'scatola' => scarto_backup_text($row['scatola'] ?? '', 100), 'status' => $status,
            'withdrawn_at' => scarto_backup_nullable_uint($row['withdrawn_at'] ?? null),
        ];
        if ($clean['order_code'] === '' || $clean['book_id'] === '' || $clean['titolo'] === '') {
            throw new InvalidArgumentException('Dettaglio volume prenotato incompleto.');
        }
        return $clean;
    }
    if ($type === 'audit_log') {
        $category = sanitize_key($row['category'] ?? 'system');
        $outcome = sanitize_key($row['outcome'] ?? 'info');
        if (!in_array($category, array_keys(scarto_audit_category_labels()), true)) $category = 'system';
        if (!in_array($outcome, array_keys(scarto_audit_outcome_labels()), true)) $outcome = 'info';
        $details = json_decode((string) ($row['details'] ?? '{}'), true);
        $subject_email = strtolower(sanitize_email((string) ($row['subject_email'] ?? '')));
        return [
            'id' => max(1, (int) ($row['id'] ?? 0)), 'category' => $category, 'action' => scarto_backup_text($row['action'] ?? '', 50),
            'outcome' => $outcome, 'entity_type' => empty($row['entity_type']) ? null : scarto_backup_text($row['entity_type'], 50),
            'entity_id' => empty($row['entity_id']) ? null : scarto_backup_text($row['entity_id'], 50),
            'subject_email' => $subject_email && is_email($subject_email) ? $subject_email : null,
            'wp_user_id' => scarto_backup_nullable_uint($row['wp_user_id'] ?? null),
            'details' => wp_json_encode(scarto_sanitize_audit_details(is_array($details) ? $details : []), JSON_UNESCAPED_UNICODE),
            'ip_address' => empty($row['ip_address']) ? null : scarto_backup_text($row['ip_address'], 45),
            'user_agent' => empty($row['user_agent']) ? null : scarto_backup_text($row['user_agent'], 500),
            'created_at' => scarto_backup_mysql_datetime($row['created_at'] ?? ''),
        ];
    }
    throw new InvalidArgumentException('Sezione backup sconosciuta.');
}

function scarto_backup_validate_document($document) {
    $format_version = (int) ($document['formatVersion'] ?? 0);
    if (!is_array($document) || ($document['format'] ?? '') !== 'gestione-scarto-librario-backup' || !in_array($format_version, [1, 2, SCARTO_BACKUP_FORMAT], true)) {
        throw new InvalidArgumentException('Formato o versione backup non supportati.');
    }
    $data = $document['data'] ?? null;
    if (!is_array($data) || !is_array($data['tables'] ?? null) || !is_array($data['options'] ?? null)) throw new InvalidArgumentException('Contenuto backup incompleto.');
    $encoded = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || !hash_equals((string) ($document['checksum'] ?? ''), hash('sha256', $encoded))) throw new InvalidArgumentException('Checksum del backup non valido.');

    $clean_tables = [];
    $order_codes = [];
    $seen = [];
    foreach (scarto_backup_table_definitions() as $key => $definition) {
        $rows = $data['tables'][$key] ?? null;
        if (!is_array($rows) || count($rows) > $definition['limit']) throw new InvalidArgumentException('Numero righe non valido per ' . $key . '.');
        $clean_tables[$key] = [];
        foreach ($rows as $row) {
            $clean = scarto_backup_clean_row($key, $row);
            $primary = (string) $clean[$definition['columns'][0]];
            if (isset($seen[$key][$primary])) throw new InvalidArgumentException('Identificativo duplicato nella sezione ' . $key . '.');
            $seen[$key][$primary] = true;
            if ($key === 'orders') {
                if (isset($order_codes[$clean['code']])) throw new InvalidArgumentException('Codice prenotazione duplicato.');
                $order_codes[$clean['code']] = true;
            }
            $clean_tables[$key][] = $clean;
        }
    }
    foreach ($clean_tables['order_items'] as $item) {
        if (!isset($order_codes[$item['order_code']])) throw new InvalidArgumentException('Riga volume senza prenotazione corrispondente.');
    }
    $settings = scarto_backup_sanitize_settings($data['options']['settings'] ?? null);
    $settings['rate_limit_email_exemptions'] = scarto_sanitize_email_list(
        $data['options']['rate_limit_email_exemptions'] ?? $settings['rate_limit_email_exemptions']
    );
    $institutional = scarto_validate_institutional_email_list($settings['rate_limit_email_exemptions'], $settings);
    if (is_wp_error($institutional)) throw new InvalidArgumentException($institutional->get_error_message());
    $settings['rate_limit_email_exemptions'] = $institutional;
    $settings['reservation_email_blocklist'] = scarto_sanitize_email_blocklist(
        $data['options']['reservation_email_blocklist'] ?? $settings['reservation_email_blocklist']
    );
    return [
        'tables' => $clean_tables,
        'options' => [
            'settings' => $settings,
            'appearance' => scarto_backup_sanitize_appearance($data['options']['appearance'] ?? null),
            'rate_limit_email_exemptions' => $settings['rate_limit_email_exemptions'],
            'reservation_email_blocklist' => $settings['reservation_email_blocklist'],
            'reservation_email_blocklist_v2' => scarto_backup_sanitize_blocklist_entries($data['options']['reservation_email_blocklist_v2'] ?? []),
            'subject_processing_restrictions' => scarto_backup_sanitize_processing_restrictions($data['options']['subject_processing_restrictions'] ?? []),
        ],
    ];
}

function scarto_backup_verify_transaction_storage() {
    global $wpdb;
    $plugin_storage = scarto_verify_transaction_storage();
    if (is_wp_error($plugin_storage)) return $plugin_storage;
    $engine = $wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $wpdb->options
    ));
    if ($wpdb->last_error || strcasecmp((string) $engine, 'InnoDB') !== 0) {
        return new WP_Error('backup_storage_unavailable', 'Ripristino non disponibile: la tabella opzioni WordPress non supporta transazioni sicure.');
    }
    return true;
}

function scarto_backup_restore($validated) {
    global $wpdb;
    $definitions = scarto_backup_table_definitions();
    $storage = scarto_backup_verify_transaction_storage();
    if (is_wp_error($storage)) throw new RuntimeException($storage->get_error_message());
    $options = $validated['options'];
    $option_names = ['scarto_settings', 'scarto_appearance', 'scarto_rate_limit_email_exemptions', 'scarto_reservation_email_blocklist', 'scarto_reservation_email_blocklist_v2', 'scarto_subject_processing_restrictions'];
    $wpdb->query('START TRANSACTION');
    try {
        foreach (['audit_log', 'order_items', 'orders', 'books'] as $key) {
            if ($wpdb->query("DELETE FROM {$definitions[$key]['table']}") === false) throw new RuntimeException('Impossibile svuotare ' . $key . '.');
        }
        foreach (['books', 'orders', 'order_items', 'audit_log'] as $key) {
            foreach ($validated['tables'][$key] as $row) {
                if ($wpdb->insert($definitions[$key]['table'], $row) === false) throw new RuntimeException('Errore ripristino ' . $key . ': ' . $wpdb->last_error);
            }
        }
        foreach ([$wpdb->scarto_reservation_verifications, $wpdb->scarto_recovery_tokens, $wpdb->scarto_gdpr_tokens, $wpdb->scarto_rate_limits] as $temporary_table) {
            if ($wpdb->query("DELETE FROM {$temporary_table}") === false) throw new RuntimeException('Errore pulizia dati temporanei.');
        }
        update_option('scarto_settings', $options['settings'], false);
        update_option('scarto_appearance', $options['appearance'], false);
        update_option('scarto_rate_limit_email_exemptions', $options['rate_limit_email_exemptions'], false);
        update_option('scarto_reservation_email_blocklist', $options['reservation_email_blocklist'], false);
        update_option('scarto_reservation_email_blocklist_v2', $options['reservation_email_blocklist_v2'], false);
        update_option('scarto_subject_processing_restrictions', $options['subject_processing_restrictions'], false);
        if ($wpdb->last_error) throw new RuntimeException('Errore ripristino impostazioni: ' . $wpdb->last_error);
        if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Commit del ripristino non riuscito.');
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        foreach ($option_names as $option_name) wp_cache_delete($option_name, 'options');
        throw $error;
    }
    foreach ($option_names as $option_name) wp_cache_delete($option_name, 'options');
    scarto_invalidate_caches();
}

add_action('admin_menu', function() {
    add_submenu_page('scarto-librario', 'Backup e ripristino', 'Backup', SCARTO_CAP_PRIVACY, 'scarto-librario-backup', 'scarto_render_backup_page');
}, 25);

add_action('admin_post_scarto_export_backup', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_export_backup');
    $verified = scarto_backup_verify_step_up(wp_unslash($_POST['password'] ?? ''), 'export');
    if (is_wp_error($verified)) wp_die(esc_html($verified->get_error_message()), '', ['response' => 403]);
    try {
        $document = scarto_backup_document();
        $encrypted = scarto_backup_encrypt_document($document, wp_unslash($_POST['encryption_password'] ?? ''));
    } catch (Throwable $error) {
        wp_die(esc_html($error->getMessage()), '', ['response' => 400]);
    }
    $json = wp_json_encode($encrypted, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    scarto_audit_log('backup_downloaded', 'wordpress_user', (string) get_current_user_id(), [
        'encrypted' => true,
        'bytes' => strlen((string) $json),
    ], ['category' => 'privacy']);
    nocache_headers();
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="scarto-backup-cifrato-' . gmdate('Ymd-His') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
});

add_action('admin_post_scarto_import_backup', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_import_backup');
    if (empty($_POST['confirm_replace'])) wp_die('Conferma esplicita mancante.', '', ['response' => 400]);
    $verified = scarto_backup_verify_step_up(wp_unslash($_POST['password'] ?? ''), 'import');
    if (is_wp_error($verified)) wp_die(esc_html($verified->get_error_message()), '', ['response' => 403]);
    $file = $_FILES['backup_file'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) wp_die('Caricamento backup non riuscito.', '', ['response' => 400]);
    $size = (int) ($file['size'] ?? 0);
    if ($size < 10 || $size > SCARTO_BACKUP_MAX_BYTES || !is_uploaded_file($file['tmp_name'])) wp_die('Dimensione o origine del file non valida.', '', ['response' => 400]);
    $name = sanitize_file_name((string) ($file['name'] ?? ''));
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') wp_die('Selezionare un file JSON di backup.', '', ['response' => 400]);
    $raw = file_get_contents($file['tmp_name']);
    $envelope = is_string($raw) ? json_decode($raw, true, 64) : null;
    try {
        $document = scarto_backup_decrypt_document($envelope, wp_unslash($_POST['encryption_password'] ?? ''));
        $validated = scarto_backup_validate_document($document);
        scarto_backup_restore($validated);
        scarto_audit_log('backup_restored', 'wordpress_user', (string) get_current_user_id(), array_map('count', $validated['tables']));
    } catch (Throwable $error) {
        wp_die(esc_html('Ripristino annullato: ' . $error->getMessage()), '', ['response' => 400]);
    }
    wp_safe_redirect(add_query_arg(['page' => 'scarto-librario-backup', 'restored' => '1'], admin_url('admin.php')));
    exit;
});

function scarto_render_backup_page() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    $legacy_import_allowed = scarto_backup_legacy_import_allowed();
    ?>
    <div class="wrap scarto-backup-admin">
        <h1>Backup e ripristino Scarto Librario</h1>
        <?php if (!empty($_GET['restored'])): ?><div class="notice notice-success"><p>Backup ripristinato correttamente. Catalogo, prenotazioni, stati, log e impostazioni sono stati riallineati.</p></div><?php endif; ?>
        <p>Il backup contiene dati personali. Conservare il file in un archivio protetto, limitarne l'accesso e applicare i tempi di conservazione della biblioteca.</p>
        <div class="scarto-backup-grid">
            <section>
                <h2>Esporta backup completo</h2>
                <p>Include catalogo, prenotazioni, dettagli e stati dei volumi, dati degli utenti, log, impostazioni e aspetto. Non include password, codici OTP, token temporanei, sessioni o contatori anti-abuso.</p>
                <p><strong>Avviso:</strong> il file contiene l’intero archivio personale. È cifrato prima del download, trasmesso direttamente al browser e non viene inserito nella Media Library né conservato in file temporanei dal plugin.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="scarto_export_backup"><?php wp_nonce_field('scarto_export_backup'); ?><p><label for="scarto_backup_export_password">Password di sicurezza amministrativa</label><br><input type="password" id="scarto_backup_export_password" name="password" required maxlength="72" autocomplete="current-password"></p><p><label for="scarto_backup_export_encryption_password">Nuova password di cifratura del file</label><br><input type="password" id="scarto_backup_export_encryption_password" name="encryption_password" required minlength="12" maxlength="72" autocomplete="new-password"></p><p class="description">Richiede maiuscola, minuscola e numero. Custodirla separatamente: senza questa password il file non è recuperabile.</p><?php submit_button('Scarica backup cifrato'); ?></form>
            </section>
            <section>
                <h2>Ripristina backup</h2>
                <p><strong>Operazione sostitutiva:</strong> i dati correnti del plugin vengono sostituiti. Scaricare prima un backup aggiornato. Il file viene interamente validato prima della transazione.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="scarto_import_backup"><?php wp_nonce_field('scarto_import_backup'); ?><p><label for="scarto_backup_file">File JSON cifrato</label><br><input type="file" id="scarto_backup_file" name="backup_file" accept="application/json,.json" required></p><p><label for="scarto_backup_import_password">Password di sicurezza del plugin</label><br><input type="password" id="scarto_backup_import_password" name="password" required maxlength="72" autocomplete="current-password"></p><p><label for="scarto_backup_import_encryption_password">Password di cifratura del file</label><br><input type="password" id="scarto_backup_import_encryption_password" name="encryption_password" maxlength="72" autocomplete="current-password" <?php echo $legacy_import_allowed ? '' : 'required'; ?>></p><?php if ($legacy_import_allowed): ?><p class="notice notice-warning inline">Compatibilità legacy temporaneamente attiva tramite <code>SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS</code>. Disattivarla subito dopo la migrazione controllata.</p><?php else: ?><p class="description">Per sicurezza sono accettati soltanto backup cifrati generati dal plugin.</p><?php endif; ?><p><label><input type="checkbox" name="confirm_replace" value="1" required> Confermo di avere un backup corrente e di voler sostituire i dati del plugin.</label></p><?php submit_button('Valida e ripristina', 'delete'); ?></form>
            </section>
        </div>
        <p class="description">Il backup riguarda esclusivamente i dati posseduti dal plugin. Non sostituisce il backup WordPress di file, Media Library, tema, altri plugin o configurazione SMTP.</p>
    </div>
    <style>.scarto-backup-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;max-width:1100px;margin-top:20px}.scarto-backup-grid section{background:#fff;border:1px solid #c3c4c7;padding:20px}.scarto-backup-grid h2{margin-top:0}.scarto-backup-grid input[type=password]{width:100%;max-width:420px}</style>
    <?php
}
