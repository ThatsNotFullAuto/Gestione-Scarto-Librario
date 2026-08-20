<?php
/**
 * Template isolato per Scarto Librario
 * Versione 9.4.6 - Frontend pubblico separato dal pannello WordPress
 *
 * v8.8.1 Changes:
 * - Enhanced privacy policy with GDPR-compliant sections
 * - Added DPO (Data Protection Officer) configurable contact info
 * - Added explicit Garante contact information
 *
 * v8.8.0 Changes:
 * - Added configurable data retention periods in settings
 * - Added separate IP retention with automatic anonymization
 * - Added purge all data admin function
 *
 * v8.7.1 Changes:
 * - Added apiVersion to scartoSettings for frontend compatibility detection
 * - Orders are now fetched separately via authenticated POST /orders endpoint
 */
if (!defined('ABSPATH')) exit;

/*
 * ============================================================
 * CONFIGURAZIONE PERCORSI
 * ============================================================
 * Usiamo le costanti definite nel file principale per puntare
 * alla cartella "dist" nella root del plugin.
 * Se le costanti non sono definite, le definiamo qui come fallback.
 */
if (!defined('SCARTO_PLUGIN_DIR')) {
    define('SCARTO_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}
if (!defined('SCARTO_PLUGIN_URL')) {
    define('SCARTO_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
}

$dist_url = SCARTO_PLUGIN_URL . 'dist/public/assets/';
$dist_path = SCARTO_PLUGIN_DIR . 'dist/public/assets/';

$js_url = '';
$css_url = '';
$manifest_path = SCARTO_PLUGIN_DIR . 'dist/public/.vite/manifest.json';
$manifest = file_exists($manifest_path)
    ? json_decode((string) file_get_contents($manifest_path), true)
    : null;
$appearance = function_exists('scarto_public_appearance_payload') ? scarto_public_appearance_payload() : [];
$page_title = !empty($appearance['siteTitle']) ? $appearance['siteTitle'] : 'Prenotazione Scarto Librario';
$initial_primary = !empty($appearance['primaryColor']) ? $appearance['primaryColor'] : '#1e3a8a';
$initial_secondary = !empty($appearance['secondaryColor']) ? $appearance['secondaryColor'] : '#3b82f6';
$initial_background = !empty($appearance['backgroundColor']) ? $appearance['backgroundColor'] : '#f3f4f6';

if (is_array($manifest) && !empty($manifest['src/index.tsx']['file'])) {
    $js_url = SCARTO_PLUGIN_URL . 'dist/public/' . ltrim($manifest['src/index.tsx']['file'], '/');
    foreach ($manifest as $asset) {
        if (!empty($asset['file']) && str_ends_with($asset['file'], '.css')) {
            $css_url = SCARTO_PLUGIN_URL . 'dist/public/' . ltrim($asset['file'], '/');
            break;
        }
    }
}

// Security headers per proteggere la pagina isolata
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$csp_nonce = base64_encode(random_bytes(18));
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'nonce-{$csp_nonce}'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "font-src 'self' data:; img-src 'self' data:; connect-src 'self'; " .
    "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?php echo esc_html($page_title); ?></title>
    
    <link rel="stylesheet" href="<?php echo esc_url(SCARTO_PLUGIN_URL . 'assets/fonts/titillium.css'); ?>">
    
    <!-- CSS dell'applicazione (Tailwind + Custom) -->
    <?php if($css_url): ?>
        <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>">
    <?php endif; ?>

    <!-- Configurazione passata da WordPress a React -->
    <script nonce="<?php echo esc_attr($csp_nonce); ?>">
        window.scartoSettings = {
            root: "<?php echo esc_url_raw(rest_url()); ?>",
            version: "<?php echo esc_js(SCARTO_VERSION); ?>",
            apiVersion: "<?php echo esc_js(SCARTO_VERSION); ?>",
            ordersRequireAuth: true
        };
    </script>
    
    <!-- Stili critici inline per il caricamento e gli errori -->
    <style nonce="<?php echo esc_attr($csp_nonce); ?>">
        /* Reset base per garantire consistenza */
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: <?php echo esc_html($initial_background); ?>;
        }
        
        /* Stile del Loader Iniziale (schermata blu) */
        .initial-loader {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, <?php echo esc_html($initial_primary); ?> 0%, <?php echo esc_html($initial_secondary); ?> 100%);
            color: white;
            font-family: 'Titillium Web', sans-serif;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }
        
        /* Animazione Spinner */
        .initial-loader .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .initial-loader p {
            margin-top: 20px;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Stile della schermata di errore (se mancano i file) */
        .js-error {
            padding: 40px 20px;
            text-align: center;
            background: #fff;
            color: #991b1b;
            font-family: 'Titillium Web', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .js-error h1 {
            font-size: 28px;
            margin-bottom: 16px;
            color: #dc2626;
        }
        
        .js-error p {
            max-width: 600px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 10px;
        }
        
        .js-error code {
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #1f2937;
            border: 1px solid #e5e7eb;
            font-size: 0.9em;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <!-- Container in cui React inietterà l'applicazione -->
    <div id="scarto-librario-root"></div>
    
    <!-- Loader visibile finché React non si avvia -->
    <div class="initial-loader" id="initial-loader">
        <div class="spinner"></div>
        <p>Caricamento sistema...</p>
    </div>
    
    <!-- Logica di caricamento script -->
    <?php if($js_url): ?>
        <!-- Caricamento applicazione React -->
        <script type="module" src="<?php echo esc_url($js_url); ?>"></script>
        
        <!-- Script per nascondere il loader al termine del rendering -->
        <script nonce="<?php echo esc_attr($csp_nonce); ?>">
            window.addEventListener('DOMContentLoaded', function() {
                // Controllo periodico per vedere se React ha renderizzato qualcosa
                var checkRender = setInterval(function() {
                    var root = document.getElementById('scarto-librario-root');
                    var loader = document.getElementById('initial-loader');
                    
                    // Se il div root non è vuoto, React ha iniziato a lavorare
                    if (root && root.innerHTML.trim().length > 0) {
                        clearInterval(checkRender);
                        if (loader) {
                            loader.style.opacity = '0';
                            setTimeout(function() {
                                loader.style.display = 'none';
                            }, 500);
                        }
                    }
                }, 100);

                // Fallback: rimuovi loader dopo 3 secondi comunque (in caso di render lento)
                setTimeout(function() {
                    clearInterval(checkRender);
                    var loader = document.getElementById('initial-loader');
                    if(loader) loader.style.display = 'none';
                }, 3000);
            });
        </script>
    <?php else: ?>
        <!-- Fallback errore se i file JS non vengono trovati -->
        <script nonce="<?php echo esc_attr($csp_nonce); ?>">document.getElementById('initial-loader').style.display = 'none';</script>
        <div class="js-error">
            <h1>⚠️ Errore di Installazione</h1>
            <p>
                Il sistema non riesce a trovare i file dell'applicazione (JavaScript).
            </p>
            <p>
                I file compilati dell'applicazione non sono disponibili.
            </p>
            <p>
                Assicurati di aver caricato correttamente la cartella <code>dist/assets</code> via FTP o ZIP.
            </p>
        </div>
    <?php endif; ?>
</body>
</html>
