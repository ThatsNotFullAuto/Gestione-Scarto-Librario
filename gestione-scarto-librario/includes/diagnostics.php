<?php
/**
 * Read-only hosting and security diagnostics.
 */

if (!defined('ABSPATH')) exit;

function scarto_get_diagnostic_checks() {
    global $wpdb, $wp_version;

    $checks = [];
    $add = static function($id, $label, $passed, $detail, $severity = 'critical') use (&$checks) {
        $checks[$id] = compact('label', 'passed', 'detail', 'severity');
    };

    $add('php', 'PHP 8.2 o successivo', version_compare(PHP_VERSION, '8.2', '>='), 'Versione rilevata: ' . PHP_VERSION);
    $add('wordpress', 'WordPress 6.6 o successivo', version_compare($wp_version, '6.6', '>='), 'Versione rilevata: ' . $wp_version);
    $add('https', 'HTTPS attivo', is_ssl(), is_ssl() ? 'Connessione HTTPS rilevata.' : 'Account WordPress, nonce e dati personali richiedono HTTPS.');
    $add('rest', 'REST API disponibile', rest_url() !== '', esc_url_raw(rest_url('scarto/v1/')));
    $add('credentials', 'Password di sicurezza del plugin configurata', !get_option('scarto_credentials_setup_required'), get_option('scarto_credentials_setup_required') ? 'Configurazione ancora richiesta.' : 'Configurazione completata. Non coincide con la password MySQL di WordPress.');

    $file_editor_disabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
    $add(
        'file_editor',
        'Editor file WordPress disabilitato',
        $file_editor_disabled,
        $file_editor_disabled
            ? 'DISALLOW_FILE_EDIT è attivo.'
            : "Aggiungere define('DISALLOW_FILE_EDIT', true); in wp-config.php.",
        'advisory'
    );

    $cloudflare_enabled = defined('SCARTO_TRUST_CLOUDFLARE') && SCARTO_TRUST_CLOUDFLARE;
    $trusted_proxies = defined('SCARTO_TRUSTED_PROXIES') && is_array(SCARTO_TRUSTED_PROXIES)
        ? array_filter(SCARTO_TRUSTED_PROXIES, static fn($entry) => trim((string) $entry) !== '')
        : [];
    $proxy_configuration_valid = !$cloudflare_enabled || !empty($trusted_proxies);
    $add(
        'trusted_proxy',
        'Proxy e IP client configurati in sicurezza',
        $proxy_configuration_valid,
        $cloudflare_enabled
            ? ($trusted_proxies
                ? 'Cloudflare attivo con allowlist SCARTO_TRUSTED_PROXIES.'
                : 'SCARTO_TRUST_CLOUDFLARE richiede SCARTO_TRUSTED_PROXIES e il blocco dell’accesso diretto all’origine.')
            : 'Header proxy non considerati attendibili; viene usato REMOTE_ADDR.',
        $cloudflare_enabled ? 'critical' : 'advisory'
    );

    $temp_dir = get_temp_dir();
    $add('temp', 'Directory temporanea scrivibile', is_dir($temp_dir) && wp_is_writable($temp_dir), 'Directory: ' . $temp_dir);

    $table_names = [
        $wpdb->scarto_books,
        $wpdb->scarto_orders,
        $wpdb->scarto_order_items,
        $wpdb->scarto_audit_log,
        $wpdb->scarto_recovery_tokens,
        $wpdb->scarto_gdpr_tokens,
        $wpdb->scarto_rate_limits,
        $wpdb->scarto_reservation_verifications,
    ];
    $missing_tables = [];
    foreach ($table_names as $table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $missing_tables[] = $table;
        }
    }
    $add('database', 'Tabelle database presenti', !$missing_tables, $missing_tables ? 'Mancanti: ' . implode(', ', $missing_tables) : 'Tutte le tabelle richieste sono presenti.');

    $non_innodb = $missing_tables ? [] : scarto_get_non_innodb_transaction_tables();
    $add(
        'database_engine',
        'Tabelle transazionali InnoDB',
        !$missing_tables && !$non_innodb,
        $missing_tables
            ? 'Verifica non eseguibile finché mancano tabelle.'
            : ($non_innodb ? 'Tabelle non InnoDB: ' . implode(', ', $non_innodb) : 'Lock e transazioni InnoDB disponibili.')
    );
    $has_request_index = !$missing_tables && scarto_has_unique_request_id_index();
    $add(
        'database_idempotency',
        'Indice univoco richieste prenotazione',
        $has_request_index,
        $missing_tables
            ? 'Verifica non eseguibile finché mancano tabelle.'
            : ($has_request_index ? 'Indice univoco request_id disponibile.' : 'Indice univoco request_id mancante o non verificabile.')
    );

    $cron_hooks = [
        'scarto_check_expired_reservations',
        'scarto_cleanup_audit_logs',
        'scarto_gdpr_data_cleanup',
        'scarto_anonymize_old_ips',
        'scarto_rate_limit_cleanup',
        'scarto_cleanup_temp_files',
    ];
    $missing_cron = array_values(array_filter($cron_hooks, static fn($hook) => !wp_next_scheduled($hook)));
    $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    $cron_detail = $missing_cron ? 'Hook non pianificati: ' . implode(', ', $missing_cron) : 'Tutti gli hook sono pianificati.';
    if ($cron_disabled) $cron_detail .= ' DISABLE_WP_CRON è attivo: verificare il cron di sistema.';
    $add('cron', 'Cleanup pianificati', !$missing_cron, $cron_detail);

    $add('random', 'Generatore crittografico disponibile', function_exists('random_bytes'), function_exists('random_bytes') ? 'random_bytes disponibile.' : 'random_bytes non disponibile.');
    $add('openssl', 'Cifratura OpenSSL disponibile', function_exists('openssl_encrypt') && function_exists('openssl_decrypt'), function_exists('openssl_encrypt') ? 'AES-256-GCM disponibile per le richieste temporanee.' : 'Estensione OpenSSL non disponibile.');
    $legacy_backup_disabled = !function_exists('scarto_backup_legacy_import_allowed') || !scarto_backup_legacy_import_allowed();
    $add(
        'legacy_backup',
        'Importazione backup legacy non cifrati disabilitata',
        $legacy_backup_disabled,
        $legacy_backup_disabled
            ? 'Sono accettati soltanto backup cifrati.'
            : 'Compatibilità legacy attiva: usarla solo su staging e rimuovere SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS dopo la migrazione.',
        'advisory'
    );
    $add('mbstring', 'Estensione mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Disponibile.' : 'Non disponibile; il plugin userà il fallback compatibile.', 'advisory');
    $settings = scarto_get_settings();
    $sender_domain = strtolower((string) substr(strrchr((string) $settings['email_from'], '@') ?: '', 1));
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $sender_aligned = $sender_domain !== '' && ($sender_domain === $site_host || str_ends_with($site_host, '.' . $sender_domain));
    $add(
        'mail_sender',
        'Dominio mittente email',
        $sender_aligned,
        $sender_aligned
            ? 'Il mittente appartiene al dominio del sito.'
            : 'Il mittente ' . $sender_domain . ' non appartiene al dominio ' . $site_host . ': usare SMTP autenticato con SPF/DKIM/DMARC allineati.',
        'advisory'
    );
    $last_mail = get_option('scarto_last_mail_status', []);
    $last_mail_detail = 'Nessun tentativo registrato dalla versione corrente.';
    if (is_array($last_mail) && !empty($last_mail['timestamp'])) {
        $last_mail_detail = sprintf(
            'Ultimo tentativo %s (%s): %s%s',
            wp_date('d/m/Y H:i:s', absint($last_mail['timestamp'])),
            sanitize_key($last_mail['context'] ?? 'generic'),
            !empty($last_mail['accepted']) ? 'accettato da WordPress/PHPMailer' : 'rifiutato',
            empty($last_mail['error']) ? '.' : '. Errore: ' . scarto_sanitize_text($last_mail['error'], 500)
        );
    }
    $add('mail', 'Trasporto email', true, $last_mail_detail . ' Un esito accettato non garantisce la consegna finale.', 'manual');
    $add('cache', 'Cache risposte private', true, 'Verificare esternamente che CDN/proxy rispettino Cache-Control: no-store.', 'manual');

    return $checks;
}

function scarto_render_diagnostics() {
    $checks = scarto_get_diagnostic_checks();
    ?>
    <hr>
    <h2>Diagnostica non distruttiva</h2>
    <p>Questi controlli non inviano email e non modificano prenotazioni o catalogo.</p>
    <table class="widefat striped" style="max-width: 1000px">
        <thead><tr><th>Controllo</th><th>Esito</th><th>Dettaglio</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <?php
            $status = $check['severity'] === 'manual'
                ? 'DA VERIFICARE'
                : ($check['passed']
                    ? ($check['severity'] === 'advisory' ? 'OK / RACCOMANDATO' : 'OK')
                    : ($check['severity'] === 'advisory' ? 'RACCOMANDATO' : 'ERRORE'));
            ?>
            <tr>
                <td><?php echo esc_html($check['label']); ?></td>
                <td><strong><?php echo esc_html($status); ?></strong></td>
                <td><?php echo esc_html($check['detail']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

add_filter('site_status_tests', function($tests) {
    $tests['direct']['scarto_security'] = [
        'label' => 'Sicurezza Scarto Librario',
        'test' => static function() {
            $checks = scarto_get_diagnostic_checks();
            $failed = array_filter($checks, static fn($check) => $check['severity'] === 'critical' && !$check['passed']);
            return [
                'label' => $failed ? 'Scarto Librario richiede interventi' : 'Scarto Librario supera i controlli locali',
                'status' => $failed ? 'critical' : 'good',
                'badge' => ['label' => 'Scarto Librario', 'color' => 'blue'],
                'description' => '<p>' . esc_html($failed ? implode(' ', array_column($failed, 'detail')) : 'Versioni, HTTPS, tabelle, credenziali, directory temporanea e cron risultano coerenti.') . '</p>',
                'actions' => '<p><a href="' . esc_url(admin_url('admin.php?page=scarto-security')) . '">Apri diagnostica dettagliata</a></p>',
                'test' => 'scarto_security',
            ];
        },
    ];
    return $tests;
});
