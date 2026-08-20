<?php
/**
 * WordPress administration area for Gestione Scarto Librario.
 */

if (!defined('ABSPATH')) exit;

define('SCARTO_CAP_VIEW', 'scarto_view_reservations');
define('SCARTO_CAP_MANAGE', 'scarto_manage_reservations');
define('SCARTO_CAP_CATALOG', 'scarto_manage_catalog');
define('SCARTO_CAP_SETTINGS', 'scarto_manage_settings');
define('SCARTO_CAP_PRIVACY', 'scarto_manage_privacy');
define('SCARTO_CAPABILITIES_VERSION', '2');

require_once __DIR__ . '/audit-admin.php';
require_once __DIR__ . '/data-tools.php';

function scarto_admin_capabilities() {
    return [
        SCARTO_CAP_VIEW,
        SCARTO_CAP_MANAGE,
        SCARTO_CAP_CATALOG,
        SCARTO_CAP_SETTINGS,
        SCARTO_CAP_PRIVACY,
    ];
}

function scarto_install_admin_capabilities() {
    $roles = [
        'scarto_librario_operator' => [
            'label' => 'Operatore Scarto Librario',
            'capabilities' => [SCARTO_CAP_VIEW, SCARTO_CAP_MANAGE],
        ],
        'scarto_librario_manager' => [
            'label' => 'Responsabile Scarto Librario',
            'capabilities' => scarto_admin_capabilities(),
        ],
    ];

    foreach ($roles as $role_key => $definition) {
        $role = get_role($role_key);
        if (!$role) {
            $role = add_role($role_key, $definition['label'], ['read' => true]);
        }
        if (!$role) continue;
        $role->add_cap('read');
        foreach (scarto_admin_capabilities() as $capability) {
            if (in_array($capability, $definition['capabilities'], true)) {
                $role->add_cap($capability);
            } else {
                $role->remove_cap($capability);
            }
        }
    }

    $administrator = get_role('administrator');
    if ($administrator) {
        foreach (scarto_admin_capabilities() as $capability) {
            $administrator->add_cap($capability);
        }
    }
    update_option('scarto_admin_capabilities_version', SCARTO_CAPABILITIES_VERSION, false);
}

add_action('admin_init', function() {
    if (get_option('scarto_admin_capabilities_version') !== SCARTO_CAPABILITIES_VERSION) {
        scarto_install_admin_capabilities();
    }
});

function scarto_get_appearance_settings() {
    $defaults = [
        'primary_color' => '#1e3a8a',
        'secondary_color' => '#3b82f6',
        'header_opacity' => 100,
        'accent_color' => '#2563eb',
        'background_color' => '#f9fafb',
        'text_color' => '#1f2937',
        'font_family' => 'titillium',
        'logo_id' => 0,
        'logo_alt' => 'Logo biblioteca',
        'site_title' => 'Prenotazione Scarto Librario',
        'site_subtitle' => 'Biblioteca Statale Stelio Crise',
        'contact_url' => '',
        'contact_label' => 'Contatti',
    ];
    $saved = get_option('scarto_appearance', []);
    return array_merge($defaults, is_array($saved) ? $saved : []);
}

function scarto_appearance_font_choices() {
    return [
        'titillium' => ['label' => 'Titillium Web', 'css' => "'Titillium Web', sans-serif"],
        'georgia' => ['label' => 'Georgia', 'css' => "Georgia, 'Times New Roman', serif"],
        'verdana' => ['label' => 'Verdana', 'css' => "Verdana, Geneva, sans-serif"],
        'trebuchet' => ['label' => 'Trebuchet MS', 'css' => "'Trebuchet MS', sans-serif"],
        'system' => ['label' => 'Font di sistema', 'css' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"],
    ];
}

function scarto_sanitize_hex_color_strict($value, $fallback) {
    $value = sanitize_hex_color((string) $value);
    return $value ?: $fallback;
}

function scarto_public_appearance_payload() {
    $appearance = scarto_get_appearance_settings();
    $fonts = scarto_appearance_font_choices();
    $font = isset($fonts[$appearance['font_family']]) ? $fonts[$appearance['font_family']]['css'] : $fonts['titillium']['css'];
    $logo_id = absint($appearance['logo_id']);
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

    return [
        'primaryColor' => $appearance['primary_color'],
        'secondaryColor' => $appearance['secondary_color'],
        'headerOpacity' => max(20, min(100, absint($appearance['header_opacity']))) / 100,
        'accentColor' => $appearance['accent_color'],
        'backgroundColor' => $appearance['background_color'],
        'textColor' => $appearance['text_color'],
        'fontFamily' => $font,
        'logoUrl' => $logo_url ?: '',
        'logoAlt' => $appearance['logo_alt'],
        'siteTitle' => $appearance['site_title'],
        'siteSubtitle' => $appearance['site_subtitle'],
        'contactUrl' => $appearance['contact_url'],
        'contactLabel' => $appearance['contact_label'],
    ];
}

function scarto_admin_icon() {
    return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iI2E3YWFhZCIgZD0iTTUgM2gxMWEzIDMgMCAwIDEgMyAzdjEzSDdhNCA0IDAgMCAxLTQtNFY1YTIgMiAwIDAgMSAyLTJabTAgMnY3LjU0QTMuOTggMy45OCAwIDAgMSA3IDEyaDEwVjZhMSAxIDAgMCAwLTEtMUg1Wm0yIDlhMiAyIDAgMSAwIDAgNGgxMHYtNEg3Wm0zLTdoNXYyaC01VjdaIi8+PC9zdmc+';
}

add_action('admin_menu', function() {
    add_menu_page(
        'Scarto Librario',
        'Scarto Librario',
        SCARTO_CAP_VIEW,
        'scarto-librario',
        'scarto_render_admin_app_page',
        scarto_admin_icon(),
        26
    );
    add_submenu_page('scarto-librario', 'Prenotazioni', 'Prenotazioni', SCARTO_CAP_VIEW, 'scarto-librario', 'scarto_render_admin_app_page');
    add_submenu_page('scarto-librario', 'Nuova prenotazione in sede', 'Nuova prenotazione', SCARTO_CAP_MANAGE, 'scarto-librario-nuova-prenotazione', 'scarto_render_admin_app_page');
    add_submenu_page('scarto-librario', 'Catalogo', 'Catalogo', SCARTO_CAP_CATALOG, 'scarto-librario-catalogo', 'scarto_render_admin_app_page');
    add_submenu_page('scarto-librario', 'Aspetto', 'Aspetto', SCARTO_CAP_SETTINGS, 'scarto-librario-aspetto', 'scarto_render_appearance_page');
    add_submenu_page('scarto-librario', 'Impostazioni', 'Impostazioni', SCARTO_CAP_SETTINGS, 'scarto-librario-impostazioni', 'scarto_render_settings_page');
    add_submenu_page('scarto-librario', 'Gestione interessati', 'Interessati', SCARTO_CAP_PRIVACY, 'scarto-librario-interessati', 'scarto_render_data_subject_page');
    add_submenu_page('scarto-librario', 'Privacy e sicurezza', 'Privacy e sicurezza', SCARTO_CAP_PRIVACY, 'scarto-security', 'scarto_render_security_page');
});

function scarto_is_plugin_admin_page() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return strpos($page, 'scarto-librario') === 0 || $page === 'scarto-security';
}

function scarto_manifest_assets($target = 'public') {
    $target = $target === 'admin' ? 'admin' : 'public';
    $manifest_path = SCARTO_PLUGIN_DIR . 'dist/' . $target . '/.vite/manifest.json';
    $manifest = file_exists($manifest_path) ? json_decode((string) file_get_contents($manifest_path), true) : [];
    $entry = is_array($manifest) ? ($manifest['src/index.tsx'] ?? null) : null;
    if (!is_array($entry) || empty($entry['file'])) return [];

    $css = [];
    foreach ($manifest as $asset) {
        if (is_array($asset) && !empty($asset['file']) && str_ends_with($asset['file'], '.css')) {
            $css[] = $asset['file'];
        }
    }
    return ['target' => $target, 'script' => $entry['file'], 'css' => array_values(array_unique($css))];
}

function scarto_admin_runtime_settings() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return [
        'root' => esc_url_raw(rest_url()),
        'version' => SCARTO_VERSION,
        'apiVersion' => SCARTO_VERSION,
        'isAdmin' => true,
        'adminPage' => $page === 'scarto-librario-catalogo'
            ? 'catalog'
            : ($page === 'scarto-librario-nuova-prenotazione' ? 'create-reservation' : 'reservations'),
        'nonce' => wp_create_nonce('wp_rest'),
        'publicUrl' => scarto_find_public_page_url(),
    ];
}

add_filter('script_loader_tag', function($tag, $handle) {
    if ($handle !== 'scarto-admin-app') return $tag;

    // Vite emits an ES module. Preserve attributes added by WordPress or security plugins.
    $tag = preg_replace('/\s+type=(["\']).*?\1/i', '', $tag, 1);
    return str_replace('<script ', '<script type="module" ', $tag);
}, 10, 2);

add_action('admin_enqueue_scripts', function() {
    if (!scarto_is_plugin_admin_page()) return;

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page === 'scarto-librario-aspetto') wp_enqueue_media();
    if (!in_array($page, ['scarto-librario', 'scarto-librario-catalogo', 'scarto-librario-nuova-prenotazione'], true)) return;

    wp_enqueue_style('scarto-admin-font', SCARTO_PLUGIN_URL . 'assets/fonts/titillium.css', [], SCARTO_VERSION);
    $assets = scarto_manifest_assets('admin');
    if (empty($assets['script'])) return;

    foreach ($assets['css'] as $index => $css) {
        wp_enqueue_style('scarto-admin-' . $index, SCARTO_PLUGIN_URL . 'dist/' . $assets['target'] . '/' . ltrim($css, '/'), [], SCARTO_VERSION);
    }
    wp_enqueue_script('scarto-admin-app', SCARTO_PLUGIN_URL . 'dist/' . $assets['target'] . '/' . ltrim($assets['script'], '/'), [], SCARTO_VERSION, true);
}, 20);

function scarto_find_public_page_url() {
    global $wpdb;
    $page_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s
         ORDER BY ID ASC LIMIT 1",
        '%' . $wpdb->esc_like('[scarto_librario') . '%'
    ));
    return $page_id ? get_permalink((int) $page_id) : home_url('/');
}

function scarto_render_admin_app_page() {
    if (!current_user_can(SCARTO_CAP_VIEW) && !current_user_can(SCARTO_CAP_MANAGE) && !current_user_can(SCARTO_CAP_CATALOG)) {
        wp_die('Accesso non consentito.');
    }
    $settings = wp_json_encode(scarto_admin_runtime_settings());
    $catalog_url = add_query_arg('page', 'scarto-librario-catalogo', admin_url('admin.php'));
    echo '<div class="wrap scarto-admin-wrap">';
    echo '<div id="scarto-librario-root" data-scarto-settings="' . esc_attr($settings) . '">';
    echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Caricamento Scarto Librario...', 'gestione-scarto-librario') . '</strong></p>';
    echo '<p>' . esc_html__('Se questo messaggio non scompare, il modulo amministrativo non e stato caricato. Svuota la cache del sito e verifica la console del browser.', 'gestione-scarto-librario') . '</p>';
    if (current_user_can(SCARTO_CAP_CATALOG)) {
        echo '<p><a class="button" href="' . esc_url($catalog_url) . '">' . esc_html__('Apri Catalogo e importazione Excel', 'gestione-scarto-librario') . '</a></p>';
    }
    echo '</div></div></div>';
}

add_action('admin_post_scarto_save_appearance', function() {
    if (!current_user_can(SCARTO_CAP_SETTINGS)) wp_die('Accesso non consentito.');
    check_admin_referer('scarto_save_appearance');

    $current = scarto_get_appearance_settings();
    $fonts = scarto_appearance_font_choices();
    $font = isset($_POST['font_family']) ? sanitize_key(wp_unslash($_POST['font_family'])) : 'titillium';
    if (!isset($fonts[$font])) $font = 'titillium';

    $logo_id = isset($_POST['logo_id']) ? absint($_POST['logo_id']) : 0;
    if ($logo_id && !wp_attachment_is_image($logo_id)) $logo_id = 0;

    $site_title = scarto_sanitize_text($_POST['site_title'] ?? '', 160);
    $site_subtitle = scarto_sanitize_text($_POST['site_subtitle'] ?? '', 200);
    $logo_alt = scarto_sanitize_text($_POST['logo_alt'] ?? '', 160);
    $appearance = [
        'primary_color' => scarto_sanitize_hex_color_strict($_POST['primary_color'] ?? '', $current['primary_color']),
        'secondary_color' => scarto_sanitize_hex_color_strict($_POST['secondary_color'] ?? '', $current['secondary_color']),
        'header_opacity' => max(20, min(100, absint($_POST['header_opacity'] ?? 100))),
        'accent_color' => scarto_sanitize_hex_color_strict($_POST['accent_color'] ?? '', $current['accent_color']),
        'background_color' => scarto_sanitize_hex_color_strict($_POST['background_color'] ?? '', $current['background_color']),
        'text_color' => scarto_sanitize_hex_color_strict($_POST['text_color'] ?? '', $current['text_color']),
        'font_family' => $font,
        'logo_id' => $logo_id,
        'logo_alt' => $logo_alt ?: $current['logo_alt'],
        'site_title' => $site_title ?: $current['site_title'],
        'site_subtitle' => $site_subtitle ?: $current['site_subtitle'],
        'contact_url' => esc_url_raw(trim((string) ($_POST['contact_url'] ?? '')), ['http', 'https', 'mailto', 'tel']),
        'contact_label' => scarto_sanitize_text($_POST['contact_label'] ?? '', 80),
    ];
    update_option('scarto_appearance', $appearance, false);
    scarto_audit_log('appearance_updated', 'wordpress_user', (string) get_current_user_id(), ['keys' => array_keys($appearance)]);
    wp_safe_redirect(add_query_arg(['page' => 'scarto-librario-aspetto', 'updated' => '1'], admin_url('admin.php')));
    exit;
});

function scarto_render_appearance_page() {
    if (!current_user_can(SCARTO_CAP_SETTINGS)) wp_die('Accesso non consentito.');
    $appearance = scarto_get_appearance_settings();
    $fonts = scarto_appearance_font_choices();
    $logo_url = $appearance['logo_id'] ? wp_get_attachment_image_url($appearance['logo_id'], 'medium') : '';
    ?>
    <div class="wrap scarto-native-admin">
        <h1>Aspetto del plugin</h1>
        <p>Personalizza la pagina pubblica usando solo valori convalidati e risorse locali.</p>
        <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Aspetto aggiornato.</p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="scarto_save_appearance">
            <?php wp_nonce_field('scarto_save_appearance'); ?>
            <h2>Identità</h2>
            <table class="form-table" role="presentation">
                <tr><th><label for="site_title">Titolo</label></th><td><input class="regular-text" id="site_title" name="site_title" maxlength="160" value="<?php echo esc_attr($appearance['site_title']); ?>"></td></tr>
                <tr><th><label for="site_subtitle">Sottotitolo</label></th><td><input class="regular-text" id="site_subtitle" name="site_subtitle" maxlength="200" value="<?php echo esc_attr($appearance['site_subtitle']); ?>"></td></tr>
                <tr><th>Logo</th><td>
                    <input type="hidden" id="scarto_logo_id" name="logo_id" value="<?php echo esc_attr($appearance['logo_id']); ?>">
                    <img id="scarto_logo_preview" src="<?php echo esc_url($logo_url ?: ''); ?>" alt="" style="max-width:180px;max-height:100px;margin-bottom:10px;<?php echo $logo_url ? 'display:block;' : 'display:none;'; ?>">
                    <button type="button" class="button" id="scarto_select_logo">Scegli dalla Libreria media</button>
                    <button type="button" class="button" id="scarto_remove_logo">Rimuovi logo</button>
                </td></tr>
                <tr><th><label for="logo_alt">Testo alternativo logo</label></th><td><input class="regular-text" id="logo_alt" name="logo_alt" maxlength="160" value="<?php echo esc_attr($appearance['logo_alt']); ?>"></td></tr>
            </table>
            <h2>Colori e font</h2>
            <table class="form-table" role="presentation">
                <?php foreach (['primary_color' => 'Header: colore iniziale', 'secondary_color' => 'Header: colore finale', 'accent_color' => 'Colore azioni', 'background_color' => 'Sfondo', 'text_color' => 'Testo'] as $key => $label): ?>
                    <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input type="color" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($appearance[$key]); ?>"> <code><?php echo esc_html($appearance[$key]); ?></code></td></tr>
                <?php endforeach; ?>
                <tr><th><label for="header_opacity">Header: opacità</label></th><td><input type="range" id="header_opacity" name="header_opacity" min="20" max="100" step="1" value="<?php echo esc_attr($appearance['header_opacity']); ?>"> <output id="header_opacity_value" for="header_opacity"><?php echo esc_html($appearance['header_opacity']); ?>%</output></td></tr>
                <tr><th><label for="font_family">Font</label></th><td><select id="font_family" name="font_family"><?php foreach ($fonts as $key => $font): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($appearance['font_family'], $key); ?>><?php echo esc_html($font['label']); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th>Contrasto WCAG</th><td><div id="scarto_contrast_status" role="status">Calcolo in corso...</div><p class="description">Il testo normale dovrebbe raggiungere almeno 4,5:1.</p></td></tr>
            </table>
            <h2>Collegamenti</h2>
            <table class="form-table" role="presentation">
                <tr><th><label for="contact_url">URL contatti</label></th><td><input type="url" class="regular-text" id="contact_url" name="contact_url" value="<?php echo esc_attr($appearance['contact_url']); ?>"></td></tr>
                <tr><th><label for="contact_label">Etichetta contatti</label></th><td><input class="regular-text" id="contact_label" name="contact_label" maxlength="80" value="<?php echo esc_attr($appearance['contact_label']); ?>"></td></tr>
            </table>
            <?php submit_button('Salva aspetto'); ?>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var frame;
        var select = document.getElementById('scarto_select_logo');
        var remove = document.getElementById('scarto_remove_logo');
        var input = document.getElementById('scarto_logo_id');
        var preview = document.getElementById('scarto_logo_preview');
        var contrastStatus = document.getElementById('scarto_contrast_status');
        var opacityInput = document.getElementById('header_opacity');
        var opacityValue = document.getElementById('header_opacity_value');
        function luminance(hex) {
            var values = hex.replace('#', '').match(/.{2}/g).map(function (part) { return parseInt(part, 16) / 255; });
            var linear = values.map(function (value) { return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4); });
            return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
        }
        function contrast(first, second) {
            var one = luminance(first);
            var two = luminance(second);
            return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05);
        }
        function blend(foreground, background, opacity) {
            var front = foreground.replace('#', '').match(/.{2}/g).map(function (part) { return parseInt(part, 16); });
            var back = background.replace('#', '').match(/.{2}/g).map(function (part) { return parseInt(part, 16); });
            var mixed = front.map(function (value, index) { return Math.round(value * opacity + back[index] * (1 - opacity)); });
            return '#' + mixed.map(function (value) { return value.toString(16).padStart(2, '0'); }).join('');
        }
        function updateContrast() {
            var text = document.getElementById('text_color').value;
            var background = document.getElementById('background_color').value;
            var primary = document.getElementById('primary_color').value;
            var opacity = Number(opacityInput.value) / 100;
            var bodyRatio = contrast(text, background);
            var headerRatio = contrast('#ffffff', blend(primary, background, opacity));
            var valid = bodyRatio >= 4.5 && headerRatio >= 4.5;
            contrastStatus.textContent = 'Testo/sfondo: ' + bodyRatio.toFixed(2) + ':1; testo bianco/intestazione: ' + headerRatio.toFixed(2) + ':1. ' + (valid ? 'Conforme AA.' : 'Da migliorare.');
            contrastStatus.style.color = valid ? '#008a20' : '#b32d2e';
        }
        ['text_color', 'background_color', 'primary_color', 'header_opacity'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', updateContrast);
        });
        opacityInput.addEventListener('input', function () { opacityValue.textContent = opacityInput.value + '%'; });
        updateContrast();
        if (select) select.addEventListener('click', function () {
            if (frame) { frame.open(); return; }
            frame = wp.media({ title: 'Seleziona il logo', button: { text: 'Usa questo logo' }, multiple: false, library: { type: 'image' } });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id;
                preview.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                preview.style.display = 'block';
            });
            frame.open();
        });
        if (remove) remove.addEventListener('click', function () { input.value = '0'; preview.src = ''; preview.style.display = 'none'; });
    });
    </script>
    <?php
}

add_action('admin_post_scarto_save_settings_native', function() {
    if (!current_user_can(SCARTO_CAP_SETTINGS)) wp_die('Accesso non consentito.');
    check_admin_referer('scarto_save_settings_native');

    $current = scarto_get_settings();
    $retention_values = [
        'retention_completed' => max(30, min(730, absint($_POST['retention_completed'] ?? $current['retention_completed']))),
        'retention_cancelled' => max(7, min(365, absint($_POST['retention_cancelled'] ?? $current['retention_cancelled']))),
        'retention_expired' => max(7, min(365, absint($_POST['retention_expired'] ?? $current['retention_expired']))),
        'retention_audit_logs' => max(7, min(365, absint($_POST['retention_audit_logs'] ?? $current['retention_audit_logs']))),
        'retention_ip' => max(1, min(90, absint($_POST['retention_ip'] ?? $current['retention_ip']))),
    ];
    $retention_plan_approved = !empty($_POST['retention_plan_approved']);
    $retention_changed = $retention_plan_approved !== !empty($current['retention_plan_approved']);
    foreach ($retention_values as $key => $value) {
        if ((int) $current[$key] !== $value) $retention_changed = true;
    }
    if ($retention_changed) {
        if (!$retention_plan_approved) {
            wp_die('Per modificare i periodi occorre attestare che corrispondono al piano di conservazione approvato dall’ente.', 'Piano di conservazione non confermato', ['response' => 400]);
        }
        $password = (string) wp_unslash($_POST['retention_password'] ?? '');
        if (!scarto_verify_password($password, get_option('scarto_db_admin_password_hash'))) {
            scarto_audit_log('retention_settings_auth_failed', 'wordpress_user', (string) get_current_user_id(), [], ['category' => 'security', 'outcome' => 'failed']);
            wp_die('Password di sicurezza errata. I periodi di conservazione non sono stati modificati.', 'Autorizzazione non riuscita', ['response' => 403]);
        }
    }
    $email_from = sanitize_email(wp_unslash($_POST['email_from'] ?? ''));
    $email_to_raw = wp_unslash($_POST['email_to'] ?? '');
    $recipients = [];
    foreach (preg_split('/[;,]+/', (string) $email_to_raw) ?: [] as $recipient) {
        $recipient = sanitize_email(trim($recipient));
        if ($recipient && is_email($recipient)) $recipients[] = $recipient;
    }
    $settings = array_merge($current, [
        'reservation_days' => max(1, min(30, absint($_POST['reservation_days'] ?? 7))),
        'max_books_per_reservation' => max(1, min(100, absint($_POST['max_books_per_reservation'] ?? 20))),
        // Kept only so legacy backups remain readable. Collection is route-based.
        'collect_domicile' => false,
        'email_from' => is_email($email_from) ? $email_from : $current['email_from'],
        'email_to' => $recipients ? implode(',', array_unique($recipients)) : $current['email_to'],
        'email_from_name' => scarto_sanitize_text($_POST['email_from_name'] ?? '', 200),
        'email_subject_prefix' => scarto_sanitize_text($_POST['email_subject_prefix'] ?? '', 200),
        'library_name' => scarto_sanitize_text($_POST['library_name'] ?? '', 200),
        'library_address' => scarto_sanitize_text($_POST['library_address'] ?? '', 500),
        'library_phone' => scarto_sanitize_text($_POST['library_phone'] ?? '', 100),
        'homepage_url' => esc_url_raw(trim((string) ($_POST['homepage_url'] ?? '')), ['http', 'https']),
        'privacy_policy_url' => esc_url_raw(trim((string) ($_POST['privacy_policy_url'] ?? '')), ['http', 'https']),
        'retention_completed' => $retention_values['retention_completed'],
        'retention_cancelled' => $retention_values['retention_cancelled'],
        'retention_expired' => $retention_values['retention_expired'],
        'retention_audit_logs' => $retention_values['retention_audit_logs'],
        'retention_ip' => $retention_values['retention_ip'],
        'retention_plan_approved' => $retention_plan_approved,
        'max_login_attempts' => max(1, min(20, absint($_POST['max_login_attempts'] ?? 5))),
        'login_lockout_minutes' => max(1, min(60, absint($_POST['login_lockout_minutes'] ?? 15))),
        'max_reservations_per_day' => max(1, min(10, absint($_POST['max_reservations_per_day'] ?? 1))),
        'max_reservations_per_email' => max(1, min(20, absint($_POST['max_reservations_per_email'] ?? 2))),
        'max_active_reservations_per_email' => max(1, min(10, absint($_POST['max_active_reservations_per_email'] ?? 2))),
        'rate_limit_email_exemptions' => scarto_sanitize_email_list(wp_unslash($_POST['rate_limit_email_exemptions'] ?? '')),
        'reservation_email_blocklist' => scarto_sanitize_email_blocklist(wp_unslash($_POST['reservation_email_blocklist'] ?? '')),
        'dpo_name' => scarto_sanitize_text($_POST['dpo_name'] ?? '', 200),
        'dpo_email' => sanitize_email(wp_unslash($_POST['dpo_email'] ?? '')),
        'dpo_phone' => scarto_sanitize_text($_POST['dpo_phone'] ?? '', 100),
        'contact_pec' => sanitize_email(wp_unslash($_POST['contact_pec'] ?? '')),
        'delete_data_on_uninstall' => !empty($_POST['delete_data_on_uninstall']),
    ]);
    $institutional_exemptions = scarto_validate_institutional_email_list($settings['rate_limit_email_exemptions'], $settings);
    if (is_wp_error($institutional_exemptions)) {
        wp_die(esc_html($institutional_exemptions->get_error_message()), 'Whitelist non valida', ['response' => 400]);
    }
    $settings['rate_limit_email_exemptions'] = $institutional_exemptions;
    update_option('scarto_settings', $settings);
    scarto_persist_email_control_settings($settings);
    scarto_audit_log('settings_updated', 'wordpress_user', (string) get_current_user_id(), ['keys' => array_keys($settings)]);
    wp_safe_redirect(add_query_arg(['page' => 'scarto-librario-impostazioni', 'updated' => '1'], admin_url('admin.php')));
    exit;
});

function scarto_render_settings_page() {
    if (!current_user_can(SCARTO_CAP_SETTINGS)) wp_die('Accesso non consentito.');
    $s = scarto_get_settings();
    $cleanup_status = get_option('scarto_cleanup_status', []);
    $cleanup_status = is_array($cleanup_status) ? $cleanup_status : [];
    $blocklist_entries = get_option('scarto_reservation_email_blocklist_v2', []);
    $blocklist_entries = is_array($blocklist_entries) ? $blocklist_entries : [];
    ?>
    <div class="wrap scarto-native-admin">
        <h1>Impostazioni Scarto Librario</h1>
        <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="scarto_save_settings_native"><?php wp_nonce_field('scarto_save_settings_native'); ?>
            <h2>Prenotazioni</h2><table class="form-table" role="presentation">
                <tr><th><label for="reservation_days">Durata in giorni</label></th><td><input type="number" min="1" max="30" id="reservation_days" name="reservation_days" value="<?php echo esc_attr($s['reservation_days']); ?>"><p class="description">Periodo durante il quale una prenotazione confermata rimane attiva. Il catalogo mostra il volume come prenotato con conto alla rovescia; alla scadenza torna disponibile se il personale non lo ha segnato come consegnato.</p></td></tr>
                <tr><th><label for="max_books_per_reservation">Massimo libri</label></th><td><input type="number" min="1" max="100" id="max_books_per_reservation" name="max_books_per_reservation" value="<?php echo esc_attr($s['max_books_per_reservation']); ?>"><p class="description">Numero massimo di volumi inseribili in una singola prenotazione. Non limita il numero di prenotazioni giornaliere o contemporaneamente attive.</p></td></tr>
                <tr><th>Recapito dell’interessato</th><td><strong>Regola automatica per origine</strong><p class="description">Online l’email verificata è obbligatoria e il domicilio non viene raccolto. In sede l’operatore inserisce l’email oppure, se l’interessato non la fornisce, il domicilio completo per la spedizione del documento protocollato. Questa regola non è disattivabile dalle impostazioni.</p></td></tr>
            </table>
            <h2>Email e biblioteca</h2><table class="form-table" role="presentation">
                <?php foreach (['email_from' => 'Email mittente', 'email_to' => 'Destinatari notifiche', 'email_from_name' => 'Nome mittente', 'email_subject_prefix' => 'Prefisso oggetto', 'library_name' => 'Nome biblioteca', 'library_address' => 'Indirizzo biblioteca', 'library_phone' => 'Telefono'] as $key => $label): ?>
                    <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($s[$key] ?? ''); ?>"></td></tr>
                <?php endforeach; ?>
                <tr><th><label for="homepage_url">URL homepage</label></th><td><input type="url" class="regular-text" id="homepage_url" name="homepage_url" value="<?php echo esc_attr($s['homepage_url'] ?? ''); ?>"><p class="description">Destinazione del collegamento “Homepage Biblioteca” mostrato nella pagina pubblica.</p></td></tr>
                <tr><th><label for="privacy_policy_url">URL privacy</label></th><td><input type="url" class="regular-text" id="privacy_policy_url" name="privacy_policy_url" value="<?php echo esc_attr($s['privacy_policy_url'] ?? ''); ?>"><p class="description">Pagina istituzionale dell'informativa privacy aperta dal modulo di prenotazione. Configurarla prima dell'apertura al pubblico.</p></td></tr>
            </table>
            <h2>Conservazione e anti-abuso</h2>
            <p>Questi valori governano finalità diverse. I periodi di conservazione riguardano i dati già registrati; i limiti anti-abuso regolano nuove operazioni e non cancellano prenotazioni esistenti.</p>
            <?php if (empty($s['retention_plan_approved'])): ?><div class="notice notice-warning inline"><p><strong>Piano non attestato:</strong> i valori presenti sono fallback tecnici e devono essere verificati con il piano di conservazione approvato dall’ente prima dell’apertura al pubblico.</p></div><?php endif; ?>
            <table class="form-table" role="presentation">
                <?php foreach ([
                    'retention_completed' => ['Completate (giorni)', 30, 730, 365, 'Dopo questo periodo i dati personali delle prenotazioni consegnate vengono anonimizzati dal cleanup pianificato. Conservare il valore coerente con il piano di conservazione approvato dall’ente.'],
                    'retention_cancelled' => ['Annullate (giorni)', 7, 365, 90, 'Dopo questo periodo le prenotazioni annullate e i relativi dettagli vengono eliminati automaticamente.'],
                    'retention_expired' => ['Scadute (giorni)', 7, 365, 90, 'Dopo questo periodo vengono eliminate le prenotazioni scadute, già liberate e nuovamente disponibili nel catalogo.'],
                    'retention_audit_logs' => ['Audit log (giorni)', 7, 365, 90, 'Durata dei registri tecnici delle operazioni amministrative e di sicurezza. I log sono accessibili soltanto al personale autorizzato.'],
                    'retention_ip' => ['Indirizzi IP (giorni)', 1, 90, 30, 'Periodo massimo prima dell’anonimizzazione degli IP conservati nelle prenotazioni e nei log. Non modifica immediatamente i contatori anti-abuso ancora attivi.'],
                    'max_login_attempts' => ['Tentativi password sicurezza', 1, 20, 5, 'Tentativi ammessi per la password aggiuntiva usata nelle operazioni sensibili, come importazione, reset e cancellazioni privacy. Non riguarda la password dell’account WordPress.'],
                    'login_lockout_minutes' => ['Blocco (minuti)', 1, 60, 15, 'Durata del blocco temporaneo dopo il superamento dei tentativi della password di sicurezza.'],
                    'max_reservations_per_day' => ['Prenotazioni per IP/giorno', 1, 10, 1, 'Prenotazioni effettivamente confermate con OTP consentite in 24 ore dalla stessa connessione. In reti condivise più utenti possono apparire con lo stesso IP. Non regola l’invio dei codici OTP.'],
                    'max_reservations_per_email' => ['Prenotazioni per email/giorno', 1, 20, 2, 'Prenotazioni confermate consentite in 24 ore allo stesso indirizzo email. Il limite di invio OTP per email è separato ed è calcolato come tre volte questo valore, con minimo 6 e massimo 20 codici/ora.'],
                    'max_active_reservations_per_email' => ['Prenotazioni attive per email', 1, 10, 2, 'Numero massimo di prenotazioni contemporaneamente attive per la stessa email. Una prenotazione consegnata, annullata o scaduta non viene conteggiata come attiva.'],
                ] as $key => $meta): ?>
                    <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($meta[0]); ?></label></th><td><input type="number" min="<?php echo esc_attr($meta[1]); ?>" max="<?php echo esc_attr($meta[2]); ?>" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($s[$key] ?? $meta[3]); ?>"><p class="description"><?php echo esc_html($meta[4]); ?></p></td></tr>
                <?php endforeach; ?>
            </table>
            <table class="form-table" role="presentation">
                <tr><th>Approvazione del piano</th><td><label for="retention_plan_approved"><input type="checkbox" id="retention_plan_approved" name="retention_plan_approved" value="1" <?php checked(!empty($s['retention_plan_approved'])); ?>> Attesto che i cinque periodi sopra indicati corrispondono al piano approvato dall’ente.</label><p class="description">La modifica dei periodi o di questa attestazione richiede la password di sicurezza del plugin.</p></td></tr>
                <tr><th><label for="retention_password">Password di sicurezza</label></th><td><input type="password" class="regular-text" id="retention_password" name="retention_password" autocomplete="new-password" maxlength="72"><p class="description">Compilare solo quando si modificano i periodi o l’attestazione. La password non viene salvata nel modulo.</p></td></tr>
            </table>
            <h3>Eccezioni controllate per email</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="rate_limit_email_exemptions">Email esenti dai limiti per email</label></th>
                    <td>
                        <textarea class="large-text code" rows="4" id="rate_limit_email_exemptions" name="rate_limit_email_exemptions" placeholder="responsabile@cultura.gov.it"><?php echo esc_textarea(str_replace(',', ",\n", $s['rate_limit_email_exemptions'] ?? '')); ?></textarea>
                        <p class="description">Separare gli indirizzi con virgola, punto e virgola o una nuova riga. Sono ammessi account <code>.gov.it</code> oppure gli esatti indirizzi istituzionali già configurati come mittente, destinatari, DPO o PEC; non viene autorizzato automaticamente l’intero dominio di questi ultimi.</p>
                        <p class="description"><strong>Persistenza:</strong> la lista è conservata anche in un'opzione dedicata per proteggerla da salvataggi parziali e aggiornamenti. Se il campo è vuoto, nessuna esenzione è attualmente registrata.</p>
                        <p class="description"><strong>Ambito limitato:</strong> l'eccezione disattiva soltanto il limite OTP per email, il limite giornaliero per email e il massimo di prenotazioni attive per email. Restano sempre obbligatori OTP, disponibilità dei libri, limiti IP e globali, permessi WordPress e password di sicurezza. Ogni utilizzo viene registrato nel Log attività.</p>
                    </td>
                </tr>
            </table>
            <h3>Blocco prenotazioni</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="reservation_email_blocklist">Blacklist email</label></th>
                    <td>
                        <textarea class="large-text code" rows="6" id="reservation_email_blocklist" name="reservation_email_blocklist" placeholder="utente@example.org | ripetuti mancati ritiri | riesame:2026-12-31"><?php echo esc_textarea($s['reservation_email_blocklist'] ?? ''); ?></textarea>
                        <p class="description">Una voce per riga: <code>email | motivo sintetico | riesame:AAAA-MM-GG</code> oppure <code>email | motivo sintetico | scadenza:AAAA-MM-GG</code>. Non inserire dati ulteriori o valutazioni personali. Data di inserimento e autore WordPress sono registrati automaticamente.</p>
                        <p class="description"><strong>Persistenza:</strong> la lista è conservata anche in un'opzione dedicata. Se il campo è vuoto, nessun indirizzo è attualmente bloccato.</p>
                        <p class="description"><strong>Effetto:</strong> il plugin blocca la richiesta prima di inviare l'OTP, ricontrolla l'indirizzo alla conferma e impedisce anche la creazione di una prenotazione in sede. Se lo stesso indirizzo compare anche nelle eccezioni, prevale sempre la blacklist. Nominativo e motivo non sono mostrati all'utente e non sono inseriti nel log pubblico dell'evento.</p>
                    </td>
                </tr>
            </table>
            <?php if ($blocklist_entries): ?>
            <h4>Voci strutturate registrate</h4>
            <table class="widefat striped" style="max-width:1200px"><thead><tr><th>Email</th><th>Motivo sintetico</th><th>Inserita</th><th>Autore</th><th>Scadenza / riesame</th></tr></thead><tbody>
                <?php foreach ($blocklist_entries as $entry): $author = !empty($entry['created_by']) ? get_userdata((int) $entry['created_by']) : null; ?>
                <tr><td><?php echo esc_html($entry['email'] ?? ''); ?></td><td><?php echo esc_html($entry['reason'] ?? ''); ?></td><td><?php echo !empty($entry['created_at']) ? esc_html(get_date_from_gmt($entry['created_at'], 'd/m/Y H:i')) : 'Non disponibile'; ?></td><td><?php echo esc_html($author ? $author->display_name : 'Sistema'); ?></td><td><?php echo esc_html(($entry['schedule_type'] ?? 'riesame') . ': ' . ($entry['schedule_date'] ?? 'non disponibile')); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
            <div class="notice notice-info inline"><p><strong>Protezione OTP aggiuntiva:</strong> il plugin applica anche un massimo tecnico di 30 invii OTP per ora dalla stessa connessione e 60 verifiche OTP per ora dalla stessa connessione. Ogni singolo codice consente 5 inserimenti errati prima del blocco. Questi limiti proteggono il servizio e non coincidono con il numero di prenotazioni completate.</p></div>
            <h2>Privacy</h2><table class="form-table" role="presentation">
                <?php foreach (['dpo_name' => 'DPO', 'dpo_email' => 'Email DPO', 'dpo_phone' => 'Telefono DPO', 'contact_pec' => 'PEC privacy'] as $key => $label): ?>
                    <tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($s[$key] ?? ''); ?>"></td></tr>
                <?php endforeach; ?>
            </table>
            <h2>Dati alla disinstallazione</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th>Rimozione definitiva</th>
                    <td>
                        <label for="delete_data_on_uninstall">
                            <input type="checkbox" id="delete_data_on_uninstall" name="delete_data_on_uninstall" value="1" <?php checked(!empty($s['delete_data_on_uninstall'])); ?>>
                            Elimina catalogo, prenotazioni, audit e impostazioni quando il plugin viene eliminato da WordPress.
                        </label>
                        <p class="description"><strong>Lasciare disattivato per conservare i dati.</strong> Prima di abilitarlo creare e verificare un backup ripristinabile.</p>
                    </td>
                </tr>
            </table>
            <h2>Ultime pulizie automatiche</h2>
            <p>I conteggi si riferiscono all’ultima esecuzione di ciascun processo e non al totale storico.</p>
            <table class="widefat striped" style="max-width:1000px">
                <thead><tr><th>Processo</th><th>Ultima esecuzione</th><th>Record elaborati</th></tr></thead>
                <tbody>
                <?php foreach (['personal_data' => 'Dati personali', 'technical_data' => 'IP e User-Agent', 'audit' => 'Audit log'] as $job => $label): $entry = $cleanup_status[$job] ?? []; ?>
                    <tr><td><?php echo esc_html($label); ?></td><td><?php echo !empty($entry['timestamp']) ? esc_html(wp_date('d/m/Y H:i:s', (int) $entry['timestamp'])) : 'Non ancora eseguito'; ?></td><td><code><?php echo esc_html(wp_json_encode($entry['counts'] ?? [], JSON_UNESCAPED_UNICODE)); ?></code></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h2>Guida ai messaggi mostrati agli utenti</h2>
            <p>Usare questa tabella per fornire assistenza senza chiedere all'utente di comunicare il codice OTP o altre credenziali.</p>
            <table class="widefat striped" style="max-width:1200px">
                <thead><tr><th>Messaggio</th><th>Quando si verifica</th><th>Indicazione per il personale</th></tr></thead>
                <tbody>
                    <tr><td><strong>Limite di invio dei codici raggiunto</strong></td><td>Sono stati richiesti troppi OTP nell'ultima ora per la stessa email o connessione.</td><td>Attendere fino a un'ora. Verificare che l'utente non prema ripetutamente il pulsante e che non stia usando una rete condivisa particolarmente affollata.</td></tr>
                    <tr><td><strong>Troppe verifiche dalla stessa connessione</strong></td><td>La connessione ha inviato molte conferme OTP nell'ultima ora.</td><td>Attendere la fine della finestra oraria. Non chiedere mai il codice all'utente.</td></tr>
                    <tr><td><strong>Codice non valido. Tentativi rimasti: N</strong></td><td>Il codice inserito non corrisponde all'ultima email ricevuta.</td><td>Far usare il codice più recente, controllando tutte le sei cifre. Dopo 5 errori occorre richiedere un nuovo codice.</td></tr>
                    <tr><td><strong>Codice non valido o scaduto</strong></td><td>Il codice è scaduto dopo 15 minuti, la richiesta non esiste più oppure è già stata conclusa.</td><td>Avviare una nuova richiesta dal carrello; nessun libro viene bloccato prima della verifica riuscita.</td></tr>
                    <tr><td><strong>Un libro nel carrello non è più disponibile</strong></td><td>Un altro utente ha confermato prima lo stesso volume oppure il volume risulta già consegnato.</td><td>Il plugin rimuove il volume non disponibile dal carrello. Invitare l'utente a proseguire con gli altri libri.</td></tr>
                    <tr><td><strong>Limite giornaliero raggiunto</strong></td><td>È stato superato il numero di prenotazioni confermate per IP nelle ultime 24 ore.</td><td>Controllare “Prenotazioni per IP/giorno”; considerare che biblioteche, uffici e reti mobili possono condividere un IP.</td></tr>
                    <tr><td><strong>Troppe prenotazioni per questa email</strong></td><td>È stato raggiunto il limite giornaliero associato all'email.</td><td>Controllare “Prenotazioni per email/giorno” e le prenotazioni già registrate.</td></tr>
                    <tr><td><strong>Questa email ha già raggiunto il numero massimo di prenotazioni attive</strong></td><td>L'utente possiede già il massimo di prenotazioni non consegnate, annullate o scadute.</td><td>Controllare le prenotazioni attive; correggere lo stato di quelle già gestite oppure attendere la scadenza.</td></tr>
                    <tr><td><strong>Un indirizzo configurato come eccezione riceve comunque un blocco</strong></td><td>È stato raggiunto un limite IP o globale, un libro non è disponibile oppure la verifica OTP non è valida.</td><td>L'eccezione riguarda solo i contatori per email. Consultare “Log attività” per distinguere il limite email dai controlli che restano obbligatori.</td></tr>
                    <tr><td><strong>Non è possibile effettuare prenotazioni con questo indirizzo email</strong></td><td>L'indirizzo è presente nella blacklist al momento della richiesta OTP o della conferma.</td><td>Verificare la voce e il motivo interno nelle impostazioni. Non comunicare automaticamente il motivo; applicare la procedura della biblioteca per riesame o rimozione del blocco.</td></tr>
                    <tr><td><strong>Invio del codice non riuscito</strong></td><td>WordPress/PHPMailer non ha accettato l'invio dell'OTP.</td><td>Eseguire il test email in “Privacy e sicurezza” e controllare SMTP e log. È diverso da un messaggio accettato ma non consegnato.</td></tr>
                    <tr><td><strong>I dati anagrafici inseriti non sono stati riconosciuti</strong></td><td>Il browser ha inviato un modulo incompleto o non aggiornato, oppure nome, cognome o email non rispettano il formato previsto.</td><td>Controllare i tre campi, ricaricare completamente la pagina e riprovare. Se persiste, svuotare la cache del sito/CDN e comunicare l'orario del tentativo all'amministratore tecnico; la richiesta OTP non è stata elaborata.</td></tr>
                    <tr><td><strong>Servizio prenotazioni temporaneamente non disponibile</strong></td><td>Le tabelle database o gli indici necessari alla prenotazione atomica non risultano idonei.</td><td>Non invitare a riprovare ripetutamente. Eseguire la diagnostica e coinvolgere l'amministratore tecnico.</td></tr>
                </tbody>
            </table>
            <?php submit_button('Salva impostazioni'); ?>
        </form>
    </div>
    <?php
}

function scarto_admin_gdpr_subject_from_post() {
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $code_raw = strtoupper(scarto_sanitize_text($_POST['code'] ?? '', 10));
    $code = preg_match('/^[A-Z2-9]{6,10}$/', $code_raw) ? $code_raw : '';
    if ((!$email || !is_email($email)) && $code === '') {
        wp_die('Inserire un indirizzo email valido oppure un codice prenotazione valido.', 'Dati non validi', ['response' => 400]);
    }
    return ['email' => $email && is_email($email) ? $email : '', 'code' => $code];
}

function scarto_admin_rest_request($route, $params) {
    $request = new WP_REST_Request('POST', $route);
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode($params));
    return $request;
}

function scarto_admin_die_rest_error($result) {
    if (!is_wp_error($result)) return;
    $data = $result->get_error_data();
    $status = is_array($data) && isset($data['status']) ? absint($data['status']) : 400;
    wp_die(esc_html($result->get_error_message()), 'Operazione non completata', ['response' => $status]);
}

function scarto_privacy_native_authorize($context) {
    $reason = scarto_sanitize_text(wp_unslash($_POST['reason'] ?? ''), 300);
    if (strlen($reason) < 10) wp_die('Inserire una motivazione di almeno 10 caratteri.', 'Motivazione richiesta', ['response' => 400]);
    $rate_key = 'privacy_action_' . sanitize_key($context) . '_' . get_current_user_id() . '_' . scarto_get_rate_limit_ip();
    if (!scarto_rate_limit_consume($rate_key, scarto_get_rate_limit('max_login_attempts'), scarto_get_rate_limit('login_lockout_minutes') * 60)) {
        scarto_audit_log('privacy_action_auth_blocked', 'wordpress_user', (string) get_current_user_id(), ['context' => $context], ['category' => 'security', 'outcome' => 'blocked']);
        wp_die('Troppi tentativi. Riprova più tardi.', '', ['response' => 429]);
    }
    if (!scarto_verify_password((string) wp_unslash($_POST['password'] ?? ''), get_option('scarto_db_admin_password_hash'))) {
        scarto_audit_log('privacy_action_auth_failed', 'wordpress_user', (string) get_current_user_id(), ['context' => $context], ['category' => 'security', 'outcome' => 'failed']);
        wp_die('Password di sicurezza errata.', '', ['response' => 403]);
    }
    scarto_rate_limit_reset($rate_key);
    return $reason;
}

add_action('admin_post_scarto_gdpr_export_native', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_gdpr_export_native');
    $reason = scarto_privacy_native_authorize('export');

    $subject = scarto_admin_gdpr_subject_from_post();
    $result = scarto_api_gdpr_export_admin(scarto_admin_rest_request('/scarto/v1/gdpr/export', $subject));
    scarto_admin_die_rest_error($result);
    $data = $result instanceof WP_REST_Response ? $result->get_data() : $result;
    scarto_audit_log('privacy_subject_export_downloaded', 'wordpress_user', (string) get_current_user_id(), ['reason' => $reason], [
        'subject_email' => $subject['email'] ?: null,
        'category' => 'privacy',
    ]);

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="scarto-gdpr-export-' . gmdate('Ymd-His') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

add_action('admin_post_scarto_gdpr_delete_native', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_gdpr_delete_native');
    $reason = scarto_sanitize_text(wp_unslash($_POST['reason'] ?? ''), 300);
    if (strlen($reason) < 10) wp_die('Inserire una motivazione di almeno 10 caratteri.', 'Motivazione richiesta', ['response' => 400]);

    $subject = scarto_admin_gdpr_subject_from_post();
    $confirmation = strtoupper(trim((string) wp_unslash($_POST['confirmation'] ?? '')));
    if ($confirmation !== 'ELIMINA') {
        wp_die('Conferma non valida: scrivere ELIMINA.', 'Operazione non confermata', ['response' => 400]);
    }

    $rate_key = 'privacy_native_' . get_current_user_id() . '_' . scarto_get_rate_limit_ip();
    $max_attempts = scarto_get_rate_limit('max_login_attempts');
    $lockout_minutes = scarto_get_rate_limit('login_lockout_minutes');
    if (!scarto_rate_limit_consume($rate_key, $max_attempts, $lockout_minutes * 60)) {
        scarto_audit_log('privacy_db_auth_blocked', 'wordpress_user', (string) get_current_user_id());
        wp_die('Troppi tentativi. Riprova più tardi.', '', ['response' => 429]);
    }

    $password = (string) wp_unslash($_POST['password'] ?? '');
    if (!scarto_verify_password($password, get_option('scarto_db_admin_password_hash'))) {
        scarto_audit_log('privacy_db_auth_failed', 'wordpress_user', (string) get_current_user_id());
        wp_die('Password di sicurezza errata.', '', ['response' => 403]);
    }
    scarto_rate_limit_reset($rate_key);

    $params = array_merge($subject, ['confirm' => true]);
    $result = scarto_api_gdpr_delete_admin(scarto_admin_rest_request('/scarto/v1/gdpr/delete', $params));
    scarto_admin_die_rest_error($result);
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $operation_reference = scarto_sanitize_text($data['operation_reference'] ?? '', 36);
    scarto_audit_log(
        'privacy_subject_deletion_authorized',
        $subject['code'] !== '' ? 'order' : 'privacy_operation',
        $subject['code'] !== '' ? $subject['code'] : $operation_reference,
        [
            'reason' => $reason,
            'scope' => $subject['code'] !== '' ? 'reservation_code' : 'email_without_identifier_retention',
        ],
        ['category' => 'privacy']
    );

    wp_safe_redirect(add_query_arg([
        'page' => 'scarto-security',
        'gdpr_processed' => 1,
        'gdpr_anonymized' => absint($data['orders_anonymized'] ?? 0),
        'gdpr_deleted' => absint($data['orders_deleted'] ?? 0),
        'gdpr_transient' => absint($data['transient_data_deleted'] ?? 0),
    ], admin_url('admin.php')));
    exit;
});

add_action('admin_post_scarto_subject_rectify', function() {
    global $wpdb;
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_subject_rectify');
    $reason = scarto_privacy_native_authorize('rectify');
    $old_email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));
    $code_raw = strtoupper(scarto_sanitize_text(wp_unslash($_POST['code'] ?? ''), 10));
    $code = preg_match('/^[A-Z2-9]{6,10}$/', $code_raw) ? $code_raw : '';
    if ((!$old_email || !is_email($old_email)) && $code === '') {
        wp_die('Indicare l’email o il codice della prenotazione da rettificare.', '', ['response' => 400]);
    }
    $target = $code !== ''
        ? $wpdb->get_row($wpdb->prepare("SELECT code, reservation_source, user_email FROM {$wpdb->scarto_orders} WHERE code = %s LIMIT 1", $code), ARRAY_A)
        : $wpdb->get_row($wpdb->prepare("SELECT code, reservation_source, user_email FROM {$wpdb->scarto_orders} WHERE user_email = %s ORDER BY created_at DESC LIMIT 1", $old_email), ARRAY_A);
    if (!$target) wp_die('Prenotazione da rettificare non trovata.', '', ['response' => 404]);
    // Only a code-scoped in-person record may be converted to postal contact.
    // Email-scoped changes can affect multiple records and therefore keep email mandatory.
    $staff_record = $code !== '' && ($target['reservation_source'] ?? 'online') === 'in_person';
    $corrected = scarto_prepare_reservation_user([
        'nome' => wp_unslash($_POST['name'] ?? ''),
        'cognome' => wp_unslash($_POST['surname'] ?? ''),
        'email' => wp_unslash($_POST['new_email'] ?? ''),
        'via' => wp_unslash($_POST['via'] ?? ''),
        'civico' => wp_unslash($_POST['civico'] ?? ''),
        'cap' => wp_unslash($_POST['cap'] ?? ''),
        'citta' => wp_unslash($_POST['citta'] ?? ''),
        'provincia' => wp_unslash($_POST['provincia'] ?? ''),
        'noteSpedizione' => wp_unslash($_POST['note_spedizione'] ?? ''),
    ], $staff_record);
    if (is_wp_error($corrected)) wp_die(esc_html($corrected->get_error_message()), '', ['response' => 400]);
    $new_email = $corrected['email'];
    $where_sql = $code !== '' ? 'code = %s' : 'user_email = %s';
    $where_value = $code !== '' ? $code : $old_email;
    $wpdb->query('START TRANSACTION');
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = %s, user_cognome = %s, user_email = %s, user_indirizzo = %s,
             user_via = %s, user_civico = %s, user_cap = %s, user_citta = %s,
             user_provincia = %s, user_note_spedizione = %s
         WHERE {$where_sql}",
        $corrected['nome'], $corrected['cognome'], $new_email, $corrected['indirizzo'],
        $corrected['via'], $corrected['civico'], $corrected['cap'], $corrected['citta'],
        $corrected['provincia'], $corrected['noteSpedizione'], $where_value
    ));
    $logs_updated = true;
    $transient_cleanup = ['success' => true];
    if ($old_email && is_email($old_email)) {
        $logs_updated = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->scarto_audit_log} SET subject_email = %s WHERE subject_email = %s", $new_email, $old_email));
        $transient_cleanup = scarto_delete_transient_personal_data($old_email);
    }
    if ($updated === false || $logs_updated === false || empty($transient_cleanup['success'])) {
        $wpdb->query('ROLLBACK');
        wp_die('Rettifica non completata.', '', ['response' => 500]);
    }
    $wpdb->query('COMMIT');
    scarto_audit_log('privacy_subject_rectified', 'wordpress_user', (string) get_current_user_id(), ['reason' => $reason, 'records' => (int) $updated, 'code' => $code ?: null], ['subject_email' => $new_email ?: null, 'category' => 'privacy']);
    wp_safe_redirect(add_query_arg(['page' => 'scarto-librario-interessati', 'subject_updated' => '1'], admin_url('admin.php')));
    exit;
});

add_action('admin_post_scarto_subject_restrict', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_subject_restrict');
    $reason = scarto_privacy_native_authorize('restrict');
    $email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));
    $until = sanitize_text_field(wp_unslash($_POST['until'] ?? ''));
    if (!$email || !is_email($email) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || $until < wp_date('Y-m-d')) wp_die('Email o scadenza non valida.', '', ['response' => 400]);
    $restrictions = get_option('scarto_subject_processing_restrictions', []);
    $restrictions = is_array($restrictions) ? $restrictions : [];
    $restrictions[$email] = ['email' => $email, 'reason' => $reason, 'until' => $until, 'created_at' => current_time('mysql', true), 'created_by' => get_current_user_id()];
    update_option('scarto_subject_processing_restrictions', $restrictions, false);
    scarto_audit_log('privacy_subject_restricted', 'wordpress_user', (string) get_current_user_id(), ['reason' => $reason, 'until' => $until], ['subject_email' => $email, 'category' => 'privacy']);
    wp_safe_redirect(add_query_arg(['page' => 'scarto-librario-interessati', 'subject_restricted' => '1'], admin_url('admin.php')));
    exit;
});

function scarto_render_data_subject_page() {
    global $wpdb;
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    $email = '';
    $code = '';
    $orders = [];
    $searched = false;
    if (isset($_POST['scarto_subject_search'])) {
        check_admin_referer('scarto_subject_search');
        $reason = scarto_privacy_native_authorize('search');
        $email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));
        $code_raw = strtoupper(scarto_sanitize_text(wp_unslash($_POST['code'] ?? ''), 10));
        $code = preg_match('/^[A-Z2-9]{6,10}$/', $code_raw) ? $code_raw : '';
        if ((!$email || !is_email($email)) && $code === '') {
            wp_die('Inserire un’email valida oppure un codice prenotazione valido.', '', ['response' => 400]);
        }
        $where_sql = $email && is_email($email) ? 'user_email = %s' : 'code = %s';
        $where_value = $email && is_email($email) ? $email : $code;
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT code, status, user_nome, user_cognome, user_email, user_indirizzo,
                    user_via, user_civico, user_cap, user_citta, user_provincia,
                    user_note_spedizione, reservation_source, created_at
             FROM {$wpdb->scarto_orders} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 200",
            $where_value
        ), ARRAY_A) ?: [];
        $searched = true;
        scarto_audit_log('privacy_subject_searched', 'wordpress_user', (string) get_current_user_id(), [
            'reason' => $reason,
            'results' => count($orders),
            'code_search' => $code !== '',
        ], ['subject_email' => $email ?: null, 'category' => 'privacy']);
    }
    $first = $orders[0] ?? null;
    $subject_email = $email ?: (is_array($first) ? strtolower((string) $first['user_email']) : '');
    $subject_code = $code ?: (is_array($first) ? (string) $first['code'] : '');
    $in_person = $code !== '' && is_array($first) && ($first['reservation_source'] ?? 'online') === 'in_person';
    ?>
    <div class="wrap scarto-native-admin">
        <h1>Gestione interessati</h1>
        <?php if (!empty($_GET['subject_updated'])): ?><div class="notice notice-success"><p>Dati rettificati e richieste temporanee precedenti invalidate.</p></div><?php endif; ?>
        <?php if (!empty($_GET['subject_restricted'])): ?><div class="notice notice-success"><p>Limitazione temporanea registrata.</p></div><?php endif; ?>
        <p>Usare questa funzione soltanto nell’ambito di una richiesta verificata. Cercare per email oppure, per le prenotazioni raccolte senza email, per codice. Ogni consultazione o modifica richiede motivazione, password di sicurezza e viene registrata con l’utente WordPress.</p>
        <form method="post" style="max-width:900px;background:#fff;border:1px solid #c3c4c7;padding:20px">
            <?php wp_nonce_field('scarto_subject_search'); ?><input type="hidden" name="scarto_subject_search" value="1">
            <table class="form-table" role="presentation">
                <tr><th><label for="subject_search_email">Email interessato</label></th><td><input class="regular-text" type="email" id="subject_search_email" name="email" value="<?php echo esc_attr($email); ?>"><p class="description">Lasciare vuoto se si usa il codice.</p></td></tr>
                <tr><th><label for="subject_search_code">Codice prenotazione</label></th><td><input class="regular-text" id="subject_search_code" name="code" maxlength="10" pattern="[A-Z2-9]{6,10}" value="<?php echo esc_attr($code); ?>"><p class="description">Necessario per gli interessati senza email.</p></td></tr>
                <tr><th><label for="subject_search_reason">Motivazione</label></th><td><input class="regular-text" id="subject_search_reason" name="reason" required minlength="10" maxlength="300" placeholder="Riferimento della richiesta o della pratica"></td></tr>
                <tr><th><label for="subject_search_password">Password sicurezza</label></th><td><input class="regular-text" type="password" id="subject_search_password" name="password" required maxlength="72" autocomplete="current-password"></td></tr>
            </table>
            <?php submit_button('Cerca tutti i dati', 'primary', 'submit', false); ?>
        </form>
        <?php if ($searched): ?>
            <h2>Risultati per <?php echo esc_html($email ?: $code); ?></h2>
            <p><?php echo esc_html(count($orders)); ?> prenotazioni trovate.<?php if ($subject_email): ?> <?php echo esc_html(count(scarto_get_subject_audit_metadata($subject_email))); ?> eventi di log correlati. Restrizione anti-abuso: <?php echo scarto_get_email_blocklist_entry($subject_email) ? 'presente' : 'assente'; ?>. Limitazione trattamento: <?php echo scarto_get_subject_processing_restriction($subject_email) ? 'attiva' : 'assente'; ?>.<?php else: ?> Nessuna email registrata: log e restrizioni basati sull’email non sono applicabili.<?php endif; ?></p>
            <table class="widefat striped"><thead><tr><th>Codice</th><th>Stato</th><th>Nominativo</th><th>Data</th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?><tr><td><?php echo esc_html($order['code']); ?></td><td><?php echo esc_html($order['status']); ?></td><td><?php echo esc_html(trim($order['user_nome'] . ' ' . $order['user_cognome'])); ?></td><td><?php echo esc_html(wp_date('d/m/Y H:i', (int) $order['created_at'] / 1000)); ?></td></tr><?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="4">Nessuna prenotazione registrata; possono comunque esistere log o restrizioni.</td></tr><?php endif; ?>
            </tbody></table>
            <?php if ($orders): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:24px;max-width:1200px">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #c3c4c7;padding:20px">
                        <input type="hidden" name="action" value="scarto_gdpr_export_native"><?php wp_nonce_field('scarto_gdpr_export_native'); ?>
                        <input type="hidden" name="email" value="<?php echo esc_attr($subject_email); ?>"><input type="hidden" name="code" value="<?php echo esc_attr($subject_email ? '' : $subject_code); ?>">
                        <h3>Esporta tutto</h3><p><label>Motivazione<br><input class="regular-text" name="reason" required minlength="10" maxlength="300"></label></p><p><label>Password sicurezza<br><input class="regular-text" type="password" name="password" required maxlength="72" autocomplete="current-password"></label></p><?php submit_button('Scarica JSON', 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #c3c4c7;padding:20px">
                        <input type="hidden" name="action" value="scarto_subject_rectify"><?php wp_nonce_field('scarto_subject_rectify'); ?>
                        <input type="hidden" name="email" value="<?php echo esc_attr($subject_email); ?>"><input type="hidden" name="code" value="<?php echo esc_attr($email ? '' : $subject_code); ?>">
                        <h3>Rettifica dati</h3>
                        <p><label>Nome<br><input class="regular-text" name="name" required maxlength="100" value="<?php echo esc_attr($first['user_nome']); ?>"></label></p>
                        <p><label>Cognome<br><input class="regular-text" name="surname" required maxlength="100" value="<?php echo esc_attr($first['user_cognome']); ?>"></label></p>
                        <p><label>Email<?php echo $in_person ? ' (facoltativa se si mantiene il domicilio)' : ''; ?><br><input class="regular-text" type="email" name="new_email" <?php echo $in_person ? '' : 'required'; ?> maxlength="254" value="<?php echo esc_attr($first['user_email']); ?>"></label></p>
                        <?php if ($in_person): ?>
                            <p class="description">Compilare l’email oppure il domicilio completo. Se si inserisce l’email, il domicilio viene rimosso.</p>
                            <p><label>Via o piazza<br><input class="regular-text" name="via" maxlength="200" value="<?php echo esc_attr($first['user_via']); ?>"></label></p>
                            <p><label>Numero civico<br><input name="civico" maxlength="30" value="<?php echo esc_attr($first['user_civico']); ?>"></label></p>
                            <p><label>CAP<br><input name="cap" pattern="[0-9]{5}" maxlength="5" value="<?php echo esc_attr($first['user_cap']); ?>"></label></p>
                            <p><label>Città<br><input class="regular-text" name="citta" maxlength="120" value="<?php echo esc_attr($first['user_citta']); ?>"></label></p>
                            <p><label>Provincia<br><input name="provincia" pattern="[A-Za-z]{2}" maxlength="2" value="<?php echo esc_attr($first['user_provincia']); ?>"></label></p>
                            <p><label>Note spedizione (facoltative)<br><input class="regular-text" name="note_spedizione" maxlength="500" value="<?php echo esc_attr($first['user_note_spedizione']); ?>"></label></p>
                        <?php endif; ?>
                        <p><label>Motivazione<br><input class="regular-text" name="reason" required minlength="10" maxlength="300"></label></p><p><label>Password sicurezza<br><input class="regular-text" type="password" name="password" required maxlength="72" autocomplete="current-password"></label></p><?php submit_button('Applica rettifica', 'secondary', 'submit', false); ?>
                    </form>
                    <?php if ($subject_email): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #c3c4c7;padding:20px"><input type="hidden" name="action" value="scarto_subject_restrict"><?php wp_nonce_field('scarto_subject_restrict'); ?><input type="hidden" name="email" value="<?php echo esc_attr($subject_email); ?>"><h3>Limitazione temporanea</h3><p>Impedisce nuove prenotazioni e mantiene i dati già registrati per il solo periodo indicato.</p><p><label>Fino al<br><input type="date" name="until" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label></p><p><label>Motivazione<br><input class="regular-text" name="reason" required minlength="10" maxlength="300"></label></p><p><label>Password sicurezza<br><input class="regular-text" type="password" name="password" required maxlength="72" autocomplete="current-password"></label></p><?php submit_button('Registra limitazione', 'secondary', 'submit', false); ?></form>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #d63638;padding:20px"><input type="hidden" name="action" value="scarto_gdpr_delete_native"><?php wp_nonce_field('scarto_gdpr_delete_native'); ?><input type="hidden" name="email" value="<?php echo esc_attr($subject_email); ?>"><input type="hidden" name="code" value="<?php echo esc_attr($subject_email ? '' : $subject_code); ?>"><h3>Cancella o anonimizza</h3><p>Le prenotazioni attive impediscono l’operazione.</p><p><label>Motivazione operativa<br><input class="regular-text" name="reason" required minlength="10" maxlength="300" aria-describedby="scarto-deletion-reason-help"></label><br><small id="scarto-deletion-reason-help">Descrivere richiesta e presupposto senza ripetere nome, email, domicilio o altri dati identificativi.</small></p><p><label>Password sicurezza<br><input class="regular-text" type="password" name="password" required maxlength="72" autocomplete="current-password"></label></p><p><label>Scrivere ELIMINA<br><input class="regular-text" name="confirmation" required></label></p><?php submit_button('Elabora richiesta', 'delete', 'submit', false); ?></form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
