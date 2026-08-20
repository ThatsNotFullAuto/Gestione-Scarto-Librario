<?php
/**
 * Privacy-restricted activity log and aggregate operational statistics.
 */

if (!defined('ABSPATH')) exit;

function scarto_audit_category_labels() {
    return [
        'reservations' => 'Prenotazioni',
        'catalog' => 'Catalogo',
        'email' => 'Email',
        'security' => 'Sicurezza',
        'privacy' => 'Privacy',
        'settings' => 'Impostazioni',
        'system' => 'Sistema',
    ];
}

function scarto_audit_outcome_labels() {
    return [
        'success' => 'Riuscita',
        'failed' => 'Non riuscita',
        'blocked' => 'Bloccata',
        'info' => 'Informativa',
    ];
}

function scarto_audit_action_label($action) {
    $labels = [
        'reservation_verification_requested' => 'Codice OTP richiesto e accettato dal trasporto email',
        'reservation_verification_email_failed' => 'Invio OTP rifiutato dal trasporto email',
        'reservation_verification_rate_limited' => 'Richiesta OTP bloccata dai limiti anti-abuso',
        'reservation_verification_failed' => 'Codice OTP non corretto',
        'reservation_verification_invalid_or_expired' => 'Verifica OTP inesistente, usata o scaduta',
        'reservation_verification_confirmed' => 'Codice OTP verificato',
        'reservation_confirmation_rejected' => 'Prenotazione rifiutata dopo la verifica',
        'reservation_rate_limited_ip' => 'Prenotazione bloccata dal limite IP',
        'reservation_rate_limited_email' => 'Prenotazione bloccata dal limite email',
        'reservation_active_limit_reached' => 'Limite prenotazioni attive raggiunto',
        'reservation_email_limits_exempted' => 'Applicata eccezione ai limiti per email',
        'reservation_blocklist_rejected' => 'Prenotazione bloccata dalla blacklist email',
        'reservation_created' => 'Prenotazione creata',
        'staff_reservation_created' => 'Prenotazione in sede creata dal personale',
        'reservation_summary_resent' => 'Riepilogo prenotazione reinviato',
        'order_status_changed' => 'Stato prenotazione modificato',
        'order_expired' => 'Prenotazione scaduta',
        'books_imported' => 'Catalogo importato',
        'database_reset' => 'Catalogo e prenotazioni azzerati',
        'settings_updated' => 'Impostazioni aggiornate',
        'appearance_updated' => 'Aspetto aggiornato',
        'mail_test' => 'Email di test richiesta',
        'plugin_activated' => 'Plugin attivato',
        'backup_restored' => 'Backup completo ripristinato',
        'backup_downloaded' => 'Backup completo cifrato scaricato',
        'privacy_subject_searched' => 'Dati dell’interessato consultati',
        'privacy_subject_export_downloaded' => 'Dati dell’interessato esportati',
        'privacy_subject_rectified' => 'Dati dell’interessato rettificati',
        'privacy_subject_restricted' => 'Trattamento temporaneamente limitato',
        'privacy_subject_deletion_authorized' => 'Cancellazione dell’interessato autorizzata',
        'retention_settings_auth_failed' => 'Modifica conservazione non autorizzata',
    ];
    return $labels[$action] ?? str_replace('_', ' ', ucfirst((string) $action));
}

add_action('admin_menu', function() {
    add_submenu_page(
        'scarto-librario',
        'Log attività',
        'Log attività',
        SCARTO_CAP_PRIVACY,
        'scarto-librario-log',
        'scarto_render_activity_log_page'
    );
    add_submenu_page(
        'scarto-librario',
        'Statistiche',
        'Statistiche',
        SCARTO_CAP_VIEW,
        'scarto-librario-statistiche',
        'scarto_render_statistics_page'
    );
}, 20);

function scarto_audit_filter_date($value, $end_of_day = false) {
    $value = sanitize_text_field(wp_unslash((string) $value));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) return '';
    if ($end_of_day) $date = $date->setTime(23, 59, 59);
    return get_gmt_from_date($date->format('Y-m-d H:i:s'));
}

function scarto_audit_build_filters($source) {
    global $wpdb;
    $category_labels = scarto_audit_category_labels();
    $outcome_labels = scarto_audit_outcome_labels();
    $category = sanitize_key(wp_unslash($source['log_category'] ?? ''));
    $outcome = sanitize_key(wp_unslash($source['log_outcome'] ?? ''));
    $email = strtolower(sanitize_email(wp_unslash($source['log_email'] ?? '')));
    $action = scarto_sanitize_text(wp_unslash($source['log_action'] ?? ''), 50);
    $from_input = sanitize_text_field(wp_unslash($source['log_from'] ?? ''));
    $to_input = sanitize_text_field(wp_unslash($source['log_to'] ?? ''));
    $from = scarto_audit_filter_date($from_input);
    $to = scarto_audit_filter_date($to_input, true);
    $where = ['1=1'];
    $params = [];
    if (isset($category_labels[$category])) { $where[] = 'category = %s'; $params[] = $category; }
    if (isset($outcome_labels[$outcome])) { $where[] = 'outcome = %s'; $params[] = $outcome; }
    if ($email && is_email($email)) { $where[] = 'subject_email = %s'; $params[] = $email; }
    if ($action !== '') { $where[] = 'action LIKE %s'; $params[] = '%' . $wpdb->esc_like($action) . '%'; }
    if ($from !== '') { $where[] = 'created_at >= %s'; $params[] = $from; }
    if ($to !== '') { $where[] = 'created_at <= %s'; $params[] = $to; }
    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
        'category' => $category,
        'outcome' => $outcome,
        'email' => $email,
        'action' => $action,
        'from_input' => $from_input,
        'to_input' => $to_input,
    ];
}

function scarto_csv_safe_cell($value) {
    $value = str_replace("\0", '', (string) $value);
    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
}

add_action('admin_post_scarto_export_audit_log', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_export_audit_log');
    global $wpdb;
    $filters = scarto_audit_build_filters($_POST);

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="scarto-log-' . gmdate('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    if (!$output) exit;
    fputcsv($output, ['ID', 'Data e ora', 'Categoria', 'Operazione', 'Esito', 'Email', 'Entita', 'ID entita', 'Utente WP', 'Dettagli', 'IP'], ';', '"', '');

    $last_id = 0;
    do {
        $query_params = array_merge($filters['params'], [$last_id, 1000]);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, category, action, outcome, entity_type, entity_id, subject_email,
                    wp_user_id, details, ip_address, created_at
             FROM {$wpdb->scarto_audit_log}
             WHERE {$filters['where']} AND id > %d
             ORDER BY id ASC LIMIT %d",
            $query_params
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $last_id = (int) $row['id'];
            $values = [
                $row['id'], get_date_from_gmt($row['created_at'], 'd/m/Y H:i:s'),
                $row['category'], $row['action'], $row['outcome'], $row['subject_email'],
                $row['entity_type'], $row['entity_id'], $row['wp_user_id'], $row['details'], $row['ip_address'],
            ];
            fputcsv($output, array_map('scarto_csv_safe_cell', $values), ';', '"', '');
        }
        if (function_exists('flush')) flush();
    } while (count($rows) === 1000);
    fclose($output);
    exit;
});

function scarto_render_activity_log_page() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    global $wpdb;

    $category_labels = scarto_audit_category_labels();
    $outcome_labels = scarto_audit_outcome_labels();
    $filters = scarto_audit_build_filters($_GET);
    $category = $filters['category'];
    $outcome = $filters['outcome'];
    $email = $filters['email'];
    $action_search = $filters['action'];
    $date_from_input = $filters['from_input'];
    $date_to_input = $filters['to_input'];
    $page = max(1, absint($_GET['paged'] ?? 1));
    $per_page = 50;

    $where_sql = $filters['where'];
    $params = $filters['params'];
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->scarto_audit_log} WHERE {$where_sql}";
    $total = (int) ($params
        ? $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : $wpdb->get_var($count_sql));
    $query_params = array_merge($params, [$per_page, ($page - 1) * $per_page]);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, category, action, outcome, entity_type, entity_id, subject_email,
                wp_user_id, details, ip_address, created_at
         FROM {$wpdb->scarto_audit_log}
         WHERE {$where_sql}
         ORDER BY id DESC LIMIT %d OFFSET %d",
        $query_params
    ), ARRAY_A) ?: [];
    ?>
    <div class="wrap scarto-audit-admin">
        <h1>Log attività Scarto Librario</h1>
        <p>Registro delle operazioni significative, delle verifiche e dei blocchi anti-abuso. Non contiene codici OTP, password o payload completi. Accesso riservato al personale autorizzato privacy.</p>
        <form method="get" class="scarto-log-filters">
            <input type="hidden" name="page" value="scarto-librario-log">
            <label>Categoria
                <select name="log_category"><option value="">Tutte</option><?php foreach ($category_labels as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($category, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            </label>
            <label>Esito
                <select name="log_outcome"><option value="">Tutti</option><?php foreach ($outcome_labels as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($outcome, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            </label>
            <label>Dal <input type="date" name="log_from" value="<?php echo esc_attr($date_from_input); ?>"></label>
            <label>Al <input type="date" name="log_to" value="<?php echo esc_attr($date_to_input); ?>"></label>
            <label>Email <input type="email" name="log_email" maxlength="254" value="<?php echo esc_attr($email); ?>"></label>
            <label>Operazione <input type="search" name="log_action" maxlength="50" value="<?php echo esc_attr($action_search); ?>" placeholder="es. reservation"></label>
            <?php submit_button('Filtra', 'secondary', '', false); ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=scarto-librario-log')); ?>">Azzera filtri</a>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scarto-inline-export-form">
            <input type="hidden" name="action" value="scarto_export_audit_log"><?php wp_nonce_field('scarto_export_audit_log'); ?>
            <?php foreach (['log_category' => $category, 'log_outcome' => $outcome, 'log_from' => $date_from_input, 'log_to' => $date_to_input, 'log_email' => $email, 'log_action' => $action_search] as $key => $value): ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
            <?php endforeach; ?>
            <?php submit_button('Esporta CSV completo', 'secondary', '', false); ?>
            <span>Il file include tutti gli eventi corrispondenti ai filtri, non soltanto la pagina corrente.</span>
        </form>
        <p><strong><?php echo esc_html(number_format_i18n($total)); ?></strong> eventi trovati. Conservazione configurata: <?php echo esc_html(scarto_get_retention_days('audit_logs')); ?> giorni.</p>
        <div class="scarto-table-scroll">
            <table class="widefat striped">
                <thead><tr><th>Data e ora</th><th>Categoria</th><th>Operazione</th><th>Esito</th><th>Email</th><th>Entità</th><th>Utente WP</th><th>Dettagli</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="8">Nessun evento corrisponde ai filtri selezionati.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row):
                    $user = !empty($row['wp_user_id']) ? get_userdata((int) $row['wp_user_id']) : null;
                    $details = json_decode((string) $row['details'], true);
                    $details_text = is_array($details) && $details ? wp_json_encode($details, JSON_UNESCAPED_UNICODE) : '';
                ?>
                    <tr>
                        <td><?php echo esc_html(get_date_from_gmt($row['created_at'], 'd/m/Y H:i:s')); ?></td>
                        <td><?php echo esc_html($category_labels[$row['category']] ?? $row['category']); ?></td>
                        <td><strong><?php echo esc_html(scarto_audit_action_label($row['action'])); ?></strong><br><code><?php echo esc_html($row['action']); ?></code></td>
                        <td><span class="scarto-outcome scarto-outcome-<?php echo esc_attr($row['outcome']); ?>"><?php echo esc_html($outcome_labels[$row['outcome']] ?? $row['outcome']); ?></span></td>
                        <td><?php echo $row['subject_email'] ? esc_html($row['subject_email']) : '<span aria-label="Non disponibile">-</span>'; ?></td>
                        <td><?php echo esc_html(trim(($row['entity_type'] ?: '') . ' ' . ($row['entity_id'] ?: '')) ?: '-'); ?></td>
                        <td><?php echo $user ? esc_html($user->display_name . ' (' . $user->user_login . ')') : 'Operazione pubblica/sistema'; ?></td>
                        <td><?php if ($details_text): ?><details><summary>Mostra</summary><code class="scarto-log-details"><?php echo esc_html($details_text); ?></code></details><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $page,
                'total' => $total_pages,
                'type' => 'plain',
            ])) . '</div></div>';
        }
        ?>
    </div>
    <?php scarto_render_native_admin_styles();
}

function scarto_stats_period($value) {
    $value = sanitize_key((string) $value);
    return in_array($value, ['week', '14', '30', '60', 'all'], true) ? $value : 'week';
}

function scarto_stats_period_definition($period) {
    $period = scarto_stats_period($period);
    $timezone = wp_timezone();
    $today = new DateTimeImmutable('today', $timezone);
    $definitions = [
        'week' => ['label' => 'Settimana corrente', 'days' => null],
        '14' => ['label' => 'Ultime 2 settimane', 'days' => 14],
        '30' => ['label' => 'Ultimo mese', 'days' => 30],
        '60' => ['label' => 'Ultimi 2 mesi', 'days' => 60],
        'all' => ['label' => 'Intero archivio', 'days' => null],
    ];
    if ($period === 'all') {
        return $definitions[$period] + ['start' => null, 'granularity' => 'month'];
    }
    $start = $period === 'week'
        ? $today->modify('monday this week')
        : $today->modify('-' . ($definitions[$period]['days'] - 1) . ' days');
    return $definitions[$period] + ['start' => $start, 'granularity' => 'day'];
}

function scarto_collect_statistics($period) {
    global $wpdb;
    $period = scarto_stats_period($period);
    $period_definition = scarto_stats_period_definition($period);
    $cutoff_ms = $period_definition['start'] instanceof DateTimeImmutable
        ? $period_definition['start']->getTimestamp() * 1000
        : 0;
    $order_where = $cutoff_ms ? $wpdb->prepare('created_at >= %d', $cutoff_ms) : '1=1';
    $joined_where = $cutoff_ms ? $wpdb->prepare('o.created_at >= %d', $cutoff_ms) : '1=1';

    $book_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->scarto_books}");
    $order_counts = array_fill_keys(['active', 'completed', 'cancelled', 'expired'], 0);
    foreach ($wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$wpdb->scarto_orders} WHERE {$order_where} GROUP BY status", ARRAY_A) ?: [] as $row) {
        if (isset($order_counts[$row['status']])) $order_counts[$row['status']] = (int) $row['total'];
    }
    $item_counts = ['reserved' => 0, 'withdrawn' => 0, 'released' => 0];
    foreach ($wpdb->get_results(
        "SELECT oi.status, COUNT(*) AS total FROM {$wpdb->scarto_order_items} oi
         INNER JOIN {$wpdb->scarto_orders} o ON o.code = oi.order_code
         WHERE {$joined_where} GROUP BY oi.status",
        ARRAY_A
    ) ?: [] as $row) {
        if (isset($item_counts[$row['status']])) $item_counts[$row['status']] = (int) $row['total'];
    }

    $summary = $wpdb->get_row(
        "SELECT COUNT(DISTINCT CASE WHEN user_email != 'deleted@gdpr.local' THEN LOWER(user_email) END) AS unique_users,
                AVG(CASE WHEN status = 'completed' AND completed_at >= created_at THEN (completed_at - created_at) / 3600000 END) AS pickup_hours
         FROM {$wpdb->scarto_orders} WHERE {$order_where}",
        ARRAY_A
    ) ?: [];
    $average_books = (float) $wpdb->get_var(
        "SELECT AVG(item_total) FROM (
            SELECT COUNT(oi.id) AS item_total FROM {$wpdb->scarto_orders} o
            LEFT JOIN {$wpdb->scarto_order_items} oi ON oi.order_code = o.code
            WHERE {$joined_where} GROUP BY o.code
         ) scarto_order_sizes"
    );
    $now = time() * 1000;
    $expiring_soon = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders}
         WHERE status = 'active' AND expires_at > %d AND expires_at <= %d",
        $now,
        $now + (3 * DAY_IN_SECONDS * 1000)
    ));

    $series_format = $period_definition['granularity'] === 'month' ? '%Y-%m' : '%Y-%m-%d';
    $completion_where = $cutoff_ms
        ? $wpdb->prepare("o.status = 'completed' AND o.completed_at >= %d", $cutoff_ms)
        : "o.status = 'completed' AND o.completed_at > 0";
    $reservation_rows = $wpdb->get_results(
        "SELECT DATE_FORMAT(FROM_UNIXTIME(o.created_at / 1000), '{$series_format}') AS day, COUNT(oi.id) AS total
         FROM {$wpdb->scarto_orders} o
         INNER JOIN {$wpdb->scarto_order_items} oi ON oi.order_code = o.code
         WHERE {$joined_where} GROUP BY day ORDER BY day ASC",
        ARRAY_A
    ) ?: [];
    $delivery_rows = $wpdb->get_results(
        "SELECT DATE_FORMAT(FROM_UNIXTIME(o.completed_at / 1000), '{$series_format}') AS day, COUNT(oi.id) AS total
         FROM {$wpdb->scarto_orders} o
         INNER JOIN {$wpdb->scarto_order_items} oi ON oi.order_code = o.code AND oi.status = 'withdrawn'
         WHERE {$completion_where} GROUP BY day ORDER BY day ASC",
        ARRAY_A
    ) ?: [];
    $daily_by_key = [];
    foreach ($reservation_rows as $row) {
        $daily_by_key[$row['day']] = ['day' => $row['day'], 'reserved_books' => (int) $row['total'], 'delivered_books' => 0];
    }
    foreach ($delivery_rows as $row) {
        if (!isset($daily_by_key[$row['day']])) {
            $daily_by_key[$row['day']] = ['day' => $row['day'], 'reserved_books' => 0, 'delivered_books' => 0];
        }
        $daily_by_key[$row['day']]['delivered_books'] = (int) $row['total'];
    }
    $daily = [];
    if ($period_definition['granularity'] === 'day') {
        $cursor = $period_definition['start'];
        $today = new DateTimeImmutable('today', wp_timezone());
        while ($cursor <= $today) {
            $key = $cursor->format('Y-m-d');
            $daily[] = $daily_by_key[$key] ?? ['day' => $key, 'reserved_books' => 0, 'delivered_books' => 0];
            $cursor = $cursor->modify('+1 day');
        }
    } elseif ($daily_by_key) {
        $first_key = array_key_first($daily_by_key);
        $cursor = DateTimeImmutable::createFromFormat('!Y-m', $first_key, wp_timezone());
        $last = new DateTimeImmutable('first day of this month', wp_timezone());
        while ($cursor && $cursor <= $last) {
            $key = $cursor->format('Y-m');
            $daily[] = $daily_by_key[$key] ?? ['day' => $key, 'reserved_books' => 0, 'delivered_books' => 0];
            $cursor = $cursor->modify('+1 month');
        }
    }

    $conservation = [];
    foreach ($wpdb->get_results(
        "SELECT CASE WHEN TRIM(stato) = '' THEN 'Non indicato' ELSE stato END AS label, COUNT(*) AS total
         FROM {$wpdb->scarto_books} GROUP BY label ORDER BY total DESC, label ASC LIMIT 12",
        ARRAY_A
    ) ?: [] as $row) {
        $conservation[] = ['label' => $row['label'], 'total' => (int) $row['total']];
    }

    $closed = $order_counts['completed'] + $order_counts['cancelled'] + $order_counts['expired'];
    return [
        'period' => $period,
        'period_label' => $period_definition['label'],
        'granularity' => $period_definition['granularity'],
        'book_total' => $book_total,
        'order_counts' => $order_counts,
        'item_counts' => $item_counts,
        'unique_users' => (int) ($summary['unique_users'] ?? 0),
        'average_books' => round($average_books, 2),
        'pickup_hours' => round((float) ($summary['pickup_hours'] ?? 0), 1),
        'completion_rate' => $closed ? round(($order_counts['completed'] / $closed) * 100, 1) : 0,
        'expiring_soon' => $expiring_soon,
        'daily' => $daily,
        'conservation' => $conservation,
    ];
}

add_action('admin_post_scarto_export_statistics', function() {
    if (!current_user_can(SCARTO_CAP_VIEW)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_export_statistics');
    $stats = scarto_collect_statistics($_POST['stats_period'] ?? 'week');
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="scarto-statistiche-' . gmdate('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    if (!$output) exit;
    fputcsv($output, ['Sezione', 'Chiave', 'Etichetta/Data', 'Valore'], ';', '"', '');
    $metrics = [
        'book_total' => 'Volumi nel catalogo', 'unique_users' => 'Utenti distinti',
        'average_books' => 'Media volumi per prenotazione', 'pickup_hours' => 'Ore medie al ritiro',
        'completion_rate' => 'Tasso di consegna percentuale', 'expiring_soon' => 'Prenotazioni in scadenza entro 3 giorni',
    ];
    foreach ($metrics as $key => $label) fputcsv($output, ['riepilogo', $key, $label, $stats[$key]], ';', '"', '');
    foreach ($stats['order_counts'] as $key => $value) fputcsv($output, ['prenotazioni', $key, $key, $value], ';', '"', '');
    foreach ($stats['item_counts'] as $key => $value) fputcsv($output, ['volumi', $key, $key, $value], ';', '"', '');
    foreach ($stats['daily'] as $row) {
        fputcsv($output, ['andamento', 'volumi_prenotati', $row['day'], $row['reserved_books']], ';', '"', '');
        fputcsv($output, ['andamento', 'volumi_consegnati', $row['day'], $row['delivered_books']], ';', '"', '');
    }
    foreach ($stats['conservation'] as $row) fputcsv($output, ['conservazione', 'stato', scarto_csv_safe_cell($row['label']), $row['total']], ';', '"', '');
    fclose($output);
    exit;
});

function scarto_render_statistics_page() {
    if (!current_user_can(SCARTO_CAP_VIEW)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    $period = scarto_stats_period($_GET['stats_period'] ?? 'week');
    $stats = scarto_collect_statistics($period);
    $status_labels = ['active' => 'Attive', 'completed' => 'Consegnate', 'cancelled' => 'Annullate', 'expired' => 'Scadute'];
    $status_colors = ['active' => '#996800', 'completed' => '#008a20', 'cancelled' => '#b32d2e', 'expired' => '#8a4b08'];
    $max_status = max(1, ...array_values($stats['order_counts']));
    $chart_daily = $stats['daily'];
    $max_reserved = 1;
    $max_delivered = 1;
    foreach ($chart_daily as $row) {
        $max_reserved = max($max_reserved, $row['reserved_books']);
        $max_delivered = max($max_delivered, $row['delivered_books']);
    }
    $svg_width = 1000;
    $svg_height = 620;
    $plot_left = 58;
    $plot_width = 910;
    $plot_height = 210;
    $delivered_top = 52;
    $reserved_top = 330;
    $series_count = max(1, count($chart_daily));
    $slot_width = $plot_width / $series_count;
    $bar_width = max(4, min(42, $slot_width * 0.56));
    $label_every = max(1, (int) ceil($series_count / 10));
    ?>
    <div class="wrap scarto-stats-admin">
        <h1>Statistiche Scarto Librario</h1>
        <p>Dati aggregati del database corrente. L'esportazione non contiene nominativi, email, indirizzi, IP o codici OTP.</p>
        <div class="scarto-stats-toolbar">
            <form method="get"><input type="hidden" name="page" value="scarto-librario-statistiche"><label for="stats_period">Periodo</label> <select id="stats_period" name="stats_period"><?php foreach (['week' => 'Settimana corrente', '14' => '2 settimane', '30' => '1 mese', '60' => '2 mesi', 'all' => 'Periodo totale'] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($period, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select> <?php submit_button('Applica', 'secondary', '', false); ?></form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="scarto_export_statistics"><input type="hidden" name="stats_period" value="<?php echo esc_attr($period); ?>"><?php wp_nonce_field('scarto_export_statistics'); ?><?php submit_button('Esporta statistiche CSV', 'secondary', '', false); ?></form>
        </div>
        <dl class="scarto-stat-summary">
            <div><dt>Volumi nel catalogo</dt><dd><?php echo esc_html(number_format_i18n($stats['book_total'])); ?></dd></div>
            <div><dt>Prenotazioni nel periodo</dt><dd><?php echo esc_html(number_format_i18n(array_sum($stats['order_counts']))); ?></dd></div>
            <div><dt>Utenti distinti</dt><dd><?php echo esc_html(number_format_i18n($stats['unique_users'])); ?></dd></div>
            <div><dt>Volumi per prenotazione</dt><dd><?php echo esc_html(number_format_i18n($stats['average_books'], 2)); ?></dd></div>
            <div><dt>Tasso di consegna</dt><dd><?php echo esc_html(number_format_i18n($stats['completion_rate'], 1)); ?>%</dd></div>
            <div><dt>Tempo medio al ritiro</dt><dd><?php echo esc_html(number_format_i18n($stats['pickup_hours'], 1)); ?> ore</dd></div>
            <div><dt>In scadenza entro 3 giorni</dt><dd><?php echo esc_html(number_format_i18n($stats['expiring_soon'])); ?></dd></div>
            <div><dt>Volumi consegnati</dt><dd><?php echo esc_html(number_format_i18n($stats['item_counts']['withdrawn'])); ?></dd></div>
        </dl>

        <div class="scarto-stats-columns">
            <section><h2>Stato delle prenotazioni</h2><div class="scarto-chart" role="img" aria-label="Distribuzione delle prenotazioni per stato"><?php foreach ($stats['order_counts'] as $status => $count): ?><div class="scarto-chart-row"><span><?php echo esc_html($status_labels[$status]); ?></span><div><i style="width:<?php echo esc_attr(round(($count / $max_status) * 100, 2)); ?>%;background:<?php echo esc_attr($status_colors[$status]); ?>"></i></div><strong><?php echo esc_html(number_format_i18n($count)); ?></strong></div><?php endforeach; ?></div></section>
            <section><h2>Stato di conservazione</h2><table class="widefat striped"><thead><tr><th>Stato</th><th>Volumi</th></tr></thead><tbody><?php foreach ($stats['conservation'] as $row): ?><tr><td><?php echo esc_html($row['label']); ?></td><td><?php echo esc_html(number_format_i18n($row['total'])); ?></td></tr><?php endforeach; ?></tbody></table></section>
        </div>

        <h2>Andamento: <?php echo esc_html($stats['period_label']); ?></h2>
        <div class="scarto-chart-legend" aria-label="Legenda del grafico"><span><i class="deliveries"></i>Volumi consegnati</span><span><i class="orders"></i>Volumi prenotati</span></div>
        <div class="scarto-trend-scroll">
            <?php if (!$chart_daily): ?>
                <p class="scarto-empty-chart">Nessuna prenotazione disponibile nel periodo selezionato.</p>
            <?php else: ?>
                <svg class="scarto-combination-svg" viewBox="0 0 <?php echo esc_attr($svg_width); ?> <?php echo esc_attr($svg_height); ?>" role="img" aria-labelledby="scarto-trend-title scarto-trend-description">
                    <title id="scarto-trend-title">Volumi prenotati e consegnati, <?php echo esc_html($stats['period_label']); ?></title>
                    <desc id="scarto-trend-description">Il grafico superiore mostra i volumi consegnati; il grafico inferiore mostra i volumi prenotati. Le serie hanno scale separate e date allineate.</desc>
                    <?php foreach ([
                        ['label' => 'Volumi consegnati', 'key' => 'delivered_books', 'class' => 'deliveries-bar', 'top' => $delivered_top, 'max' => $max_delivered],
                        ['label' => 'Volumi prenotati', 'key' => 'reserved_books', 'class' => 'orders-bar', 'top' => $reserved_top, 'max' => $max_reserved],
                    ] as $panel): ?>
                        <text class="panel-title" x="<?php echo esc_attr($plot_left); ?>" y="<?php echo esc_attr($panel['top'] - 14); ?>"><?php echo esc_html($panel['label']); ?></text>
                        <?php for ($grid = 0; $grid <= 4; $grid++):
                            $grid_value = (int) round(($panel['max'] / 4) * (4 - $grid));
                            $grid_y = $panel['top'] + (($plot_height / 4) * $grid);
                        ?>
                            <line class="grid" x1="<?php echo esc_attr($plot_left); ?>" y1="<?php echo esc_attr($grid_y); ?>" x2="<?php echo esc_attr($plot_left + $plot_width); ?>" y2="<?php echo esc_attr($grid_y); ?>"></line>
                            <text class="axis-value" x="<?php echo esc_attr($plot_left - 12); ?>" y="<?php echo esc_attr($grid_y + 4); ?>"><?php echo esc_html($grid_value); ?></text>
                        <?php endfor; ?>
                        <?php foreach ($chart_daily as $index => $row):
                            $center_x = $plot_left + ($slot_width * $index) + ($slot_width / 2);
                            $bar_height = ($row[$panel['key']] / $panel['max']) * $plot_height;
                            $bar_x = $center_x - ($bar_width / 2);
                            $bar_y = $panel['top'] + $plot_height - $bar_height;
                            $label = $stats['granularity'] === 'month'
                                ? mysql2date('m/Y', $row['day'] . '-01')
                                : mysql2date('d/m', $row['day']);
                        ?>
                            <g tabindex="0" aria-label="<?php echo esc_attr($label . ': ' . $row[$panel['key']] . ' ' . strtolower($panel['label'])); ?>">
                                <title><?php echo esc_html($label . ': ' . $row[$panel['key']] . ' ' . strtolower($panel['label'])); ?></title>
                                <rect class="<?php echo esc_attr($panel['class']); ?>" x="<?php echo esc_attr(round($bar_x, 2)); ?>" y="<?php echo esc_attr(round($bar_y, 2)); ?>" width="<?php echo esc_attr(round($bar_width, 2)); ?>" height="<?php echo esc_attr(round($bar_height, 2)); ?>"></rect>
                            </g>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php foreach ($chart_daily as $index => $row):
                        $center_x = $plot_left + ($slot_width * $index) + ($slot_width / 2);
                        $label = $stats['granularity'] === 'month' ? mysql2date('m/Y', $row['day'] . '-01') : mysql2date('d/m', $row['day']);
                        if ($index % $label_every !== 0 && $index !== count($chart_daily) - 1) continue;
                    ?>
                        <text class="axis-label" x="<?php echo esc_attr(round($center_x, 2)); ?>" y="<?php echo esc_attr($reserved_top + $plot_height + 30); ?>"><?php echo esc_html($label); ?></text>
                    <?php endforeach; ?>
                </svg>
            <?php endif; ?>
        </div>
        <?php if ($chart_daily): ?><details class="scarto-chart-data"><summary>Mostra i dati del grafico</summary><table class="widefat striped"><thead><tr><th>Periodo</th><th>Volumi prenotati</th><th>Volumi consegnati</th></tr></thead><tbody><?php foreach ($chart_daily as $row): ?><tr><td><?php echo esc_html($stats['granularity'] === 'month' ? mysql2date('m/Y', $row['day'] . '-01') : mysql2date('d/m/Y', $row['day'])); ?></td><td><?php echo esc_html(number_format_i18n($row['reserved_books'])); ?></td><td><?php echo esc_html(number_format_i18n($row['delivered_books'])); ?></td></tr><?php endforeach; ?></tbody></table></details><?php endif; ?>
        <p class="description">I dati eliminati o anonimizzati secondo i periodi di conservazione non possono essere ricostruiti dalle statistiche.</p>
    </div>
    <?php scarto_render_native_admin_styles();
}

function scarto_render_statistics_page_legacy() {
    if (!current_user_can(SCARTO_CAP_VIEW)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    global $wpdb;

    $book_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->scarto_books}");
    $order_counts = array_fill_keys(['active', 'completed', 'cancelled', 'expired'], 0);
    foreach ($wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$wpdb->scarto_orders} GROUP BY status", ARRAY_A) ?: [] as $row) {
        if (isset($order_counts[$row['status']])) $order_counts[$row['status']] = (int) $row['total'];
    }
    $item_counts = ['reserved' => 0, 'withdrawn' => 0, 'released' => 0];
    foreach ($wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$wpdb->scarto_order_items} GROUP BY status", ARRAY_A) ?: [] as $row) {
        if (isset($item_counts[$row['status']])) $item_counts[$row['status']] = (int) $row['total'];
    }
    $day_start = wp_date('Y-m-d', strtotime('-29 days'), new DateTimeZone('UTC'));
    $daily_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT FROM_UNIXTIME(created_at / 1000, '%%Y-%%m-%%d') AS day, COUNT(*) AS total
         FROM {$wpdb->scarto_orders}
         WHERE created_at >= %d
         GROUP BY day ORDER BY day ASC",
        strtotime($day_start . ' 00:00:00 UTC') * 1000
    ), ARRAY_A) ?: [];
    $daily = [];
    foreach ($daily_rows as $row) $daily[$row['day']] = (int) $row['total'];
    $max_daily = max(1, ...array_values($daily ?: [0]));
    $status_labels = ['active' => 'Attive', 'completed' => 'Consegnate', 'cancelled' => 'Annullate', 'expired' => 'Scadute'];
    $max_status = max(1, ...array_values($order_counts));
    ?>
    <div class="wrap scarto-stats-admin">
        <h1>Statistiche Scarto Librario</h1>
        <p>Dati aggregati aggiornati al caricamento della pagina. Nessun nominativo, email, IP o codice di verifica è incluso.</p>
        <div class="scarto-stat-cards">
            <div><strong><?php echo esc_html(number_format_i18n($book_total)); ?></strong><span>Volumi nel catalogo</span></div>
            <div><strong><?php echo esc_html(number_format_i18n(array_sum($order_counts))); ?></strong><span>Prenotazioni totali</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($order_counts['active'])); ?></strong><span>Prenotazioni attive</span></div>
            <div><strong><?php echo esc_html(number_format_i18n($item_counts['withdrawn'])); ?></strong><span>Volumi consegnati</span></div>
        </div>
        <h2>Stato delle prenotazioni</h2>
        <div class="scarto-chart" role="img" aria-label="Diagramma dello stato delle prenotazioni">
            <?php foreach ($order_counts as $status => $count): ?>
                <div class="scarto-chart-row"><span><?php echo esc_html($status_labels[$status]); ?></span><div><i style="width:<?php echo esc_attr(round(($count / $max_status) * 100, 2)); ?>%"></i></div><strong><?php echo esc_html(number_format_i18n($count)); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <table class="widefat striped scarto-stats-table"><thead><tr><th>Stato</th><th>Numero</th></tr></thead><tbody><?php foreach ($order_counts as $status => $count): ?><tr><td><?php echo esc_html($status_labels[$status]); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr><?php endforeach; ?></tbody></table>

        <h2>Prenotazioni create negli ultimi 30 giorni</h2>
        <div class="scarto-daily-chart" aria-label="Andamento giornaliero delle prenotazioni">
            <?php for ($offset = 29; $offset >= 0; $offset--):
                $day = gmdate('Y-m-d', strtotime("-{$offset} days"));
                $count = $daily[$day] ?? 0;
                $height = max(2, round(($count / $max_daily) * 100, 2));
            ?><div><span style="height:<?php echo esc_attr($height); ?>%" title="<?php echo esc_attr($day . ': ' . $count); ?>"></span><small><?php echo esc_html($offset % 5 === 0 ? gmdate('d/m', strtotime($day)) : ''); ?></small><b class="screen-reader-text"><?php echo esc_html($day . ': ' . $count . ' prenotazioni'); ?></b></div><?php endfor; ?>
        </div>
        <p class="description">Le statistiche descrivono il database corrente; i dati rimossi secondo la retention non sono più conteggiati.</p>
    </div>
    <?php scarto_render_native_admin_styles();
}

function scarto_render_native_admin_styles() {
    ?>
    <style>
        .scarto-log-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end;background:#fff;border:1px solid #c3c4c7;padding:16px;margin:16px 0}.scarto-log-filters label{display:flex;flex-direction:column;gap:4px;font-weight:600}.scarto-inline-export-form,.scarto-stats-toolbar,.scarto-stats-toolbar form{display:flex;flex-wrap:wrap;align-items:center;gap:10px}.scarto-inline-export-form{margin:12px 0}.scarto-inline-export-form p,.scarto-stats-toolbar p{margin:0}.scarto-stats-toolbar{justify-content:space-between;max-width:1180px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:16px 0}.scarto-table-scroll{overflow:auto}.scarto-table-scroll table{min-width:1180px}.scarto-log-details{display:block;max-width:360px;white-space:normal;overflow-wrap:anywhere;margin-top:8px}.scarto-outcome{display:inline-block;border-left:4px solid #646970;padding:3px 7px;background:#f6f7f7}.scarto-outcome-success{border-color:#008a20}.scarto-outcome-failed,.scarto-outcome-blocked{border-color:#d63638}.scarto-outcome-info{border-color:#2271b1}.scarto-stat-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1px;max-width:1180px;margin:20px 0;background:#c3c4c7;border:1px solid #c3c4c7}.scarto-stat-summary>div{display:flex;flex-direction:column-reverse;justify-content:flex-end;background:#fff;padding:16px;min-height:82px}.scarto-stat-summary dt{margin-top:6px;color:#50575e}.scarto-stat-summary dd{margin:0;font-size:27px;font-weight:650;line-height:1.1;color:#1d2327}.scarto-stats-columns{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(320px,.8fr);gap:24px;max-width:1180px}.scarto-stats-columns section{min-width:0}.scarto-chart{background:#fff;border:1px solid #c3c4c7;padding:20px}.scarto-chart-row{display:grid;grid-template-columns:130px 1fr 70px;gap:12px;align-items:center;margin:12px 0}.scarto-chart-row>div{height:18px;background:#e2e4e7}.scarto-chart-row i{display:block;height:100%}.scarto-chart-legend{display:flex;flex-wrap:wrap;gap:22px;max-width:1180px;margin:0 0 10px;font-weight:600}.scarto-chart-legend span{display:flex;align-items:center;gap:7px}.scarto-chart-legend i{display:inline-block;width:22px;height:10px}.scarto-chart-legend i.books{background:#9a6b08}.scarto-chart-legend i.orders{height:3px;background:#1f8a32}.scarto-trend-scroll{max-width:1180px;overflow-x:auto;background:#fff;border:1px solid #c3c4c7}.scarto-combination-svg{display:block;width:100%;min-width:760px;height:auto}.scarto-combination-svg .grid{stroke:#dcdcde;stroke-width:1}.scarto-combination-svg .books-bar{fill:#9a6b08}.scarto-combination-svg .orders-line{fill:none;stroke:#1f8a32;stroke-width:4;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}.scarto-combination-svg .orders-point{fill:#fff;stroke:#1f8a32;stroke-width:3;vector-effect:non-scaling-stroke}.scarto-combination-svg .axis-value{font-size:12px;text-anchor:end;fill:#50575e}.scarto-combination-svg .axis-label{font-size:12px;text-anchor:middle;fill:#3c434a}.scarto-combination-svg .axis-caption{font-size:12px;text-anchor:middle;fill:#50575e}.scarto-combination-svg g:focus .books-bar{stroke:#1d2327;stroke-width:3}.scarto-chart-data{max-width:1180px;margin-top:12px}.scarto-chart-data summary{cursor:pointer;font-weight:600}.scarto-chart-data table{max-width:680px;margin-top:10px}.scarto-empty-chart{margin:0;padding:48px 20px;text-align:center;color:#50575e}.scarto-stat-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;max-width:1100px;margin:20px 0}.scarto-stat-cards>div{background:#fff;border:1px solid #c3c4c7;border-top:4px solid #2271b1;padding:18px}.scarto-stat-cards strong{display:block;font-size:30px;line-height:1.1}.scarto-stat-cards span{display:block;margin-top:6px}.scarto-stats-table{max-width:520px;margin-top:16px}.scarto-daily-chart{display:flex;align-items:end;gap:3px;height:240px;max-width:1000px;background:#fff;border:1px solid #c3c4c7;padding:20px 14px 30px}.scarto-daily-chart>div{position:relative;display:flex;align-items:end;flex:1;height:100%}.scarto-daily-chart span{display:block;width:100%;min-height:2px;background:#008a20}.scarto-daily-chart small{position:absolute;top:calc(100% + 6px);left:0;font-size:10px;white-space:nowrap}@media(max-width:782px){.scarto-chart-row{grid-template-columns:90px 1fr 48px}.scarto-daily-chart{height:180px}.scarto-log-filters>*,.scarto-stats-columns{width:100%}.scarto-stats-columns{grid-template-columns:1fr}.scarto-stats-toolbar{align-items:flex-start}.scarto-stats-toolbar form{width:100%}}@media(prefers-reduced-motion:reduce){.scarto-audit-admin *,.scarto-stats-admin *{scroll-behavior:auto!important}}
        .scarto-chart-legend i.orders{height:10px;background:#1f8a32}.scarto-chart-legend i.deliveries{background:#9a6b08}.scarto-combination-svg .orders-bar{fill:#1f8a32}.scarto-combination-svg .deliveries-bar{fill:#9a6b08}.scarto-combination-svg .panel-title{font-size:15px;font-weight:600;fill:#1d2327}.scarto-combination-svg g:focus rect{stroke:#1d2327;stroke-width:3}
    </style>
    <?php
}
