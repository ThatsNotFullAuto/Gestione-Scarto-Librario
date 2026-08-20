<?php
/**
 * Plugin Name: Gestione Scarto Librario
 * Description: Sistema professionale per la gestione dello scarto librario con form utente, notifiche email e generazione PDF.
 * Version: 9.4.6
 * Author: Biblioteca Stelio Crise
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Tested up to: 7.0
 * Text Domain: gestione-scarto-librario
 * Domain Path: /languages
 *
 * Version 9.4.6:
 * - Fixed catalog Excel exports reporting delivered volumes as available
 * - Exported a fresh global availability snapshot without internal state columns
 *
 * Version 9.4.5:
 * - Unified reservation PDF downloads and email attachments from one server-generated file
 * - Aligned configurable PDF footers and strengthened anonymized GDPR audit evidence
 *
 * Version 9.4.4:
 * - Preserved valid in-person reservations without email during encrypted backup restore
 * - Added executable offline backup and deterministic release verification
 *
 * Version 9.4.3:
 * - Enforced private no-store headers for all staff reservation responses
 * - Restricted global personal-data purge to the privacy capability
 * - Disabled unencrypted legacy backup imports by default
 * - Clarified the plugin security password and isolated lockouts per WordPress user
 *
 * Version 9.4.2:
 * - Fixed online OTP requests rejected by optional empty domicile fields
 * - Limited online reservation payloads to name, surname and email
 * - Added actionable feedback for invalid personal-data payloads
 *
 * Version 9.3.2:
 * - Added a two-step, operator-confirmed catalog import when active reservations exist
 * - Replaced the internal force:true error with an actionable Italian warning and audit details
 *
 * Version 9.3.1:
 * - Fixed staff reservation validation feedback and admin checkbox visibility
 * - Added explicit transactional storage verification to staff-created reservations
 *
 * Version 9.3.0:
 * - Added structured shipping-address fields with legacy-address compatibility
 * - Added authenticated in-person reservations without OTP or public rate limits
 * - Added staff reservation-summary email resend with operator feedback and audit logs
 * - Linked the configurable public logo to the configured library homepage
 * - Integrated the updated privacy notice for structured shipping data
 *
 * Version 9.2.3:
 * - Replaced the reservation status dropdown with reliable, re-applicable filter buttons
 * - Added immediate loading and result-count feedback for staff filters
 *
 * Version 9.2.2:
 * - Separated delivered and reserved volumes into vertically aligned charts with independent scales
 * - Added server-side filtering for pending staff reservations
 *
 * Version 9.2.1:
 * - Replaced the recent trend view with an accessible reserved/delivered volumes chart
 * - Added current week, 2-week, 1-month, 2-month and full-history periods
 * - Added a server-synchronized live reservation countdown without catalog reloads
 *
 * Version 9.2.0:
 * - Added global server-side reservation search and pagination
 * - Added filtered log and aggregate statistics CSV exports
 * - Added validated, transactional backup and restore for plugin-owned data
 * - Expanded operational statistics with period selection and accessible charts
 *
 * Version 9.1.3:
 * - Preserved email exemptions and blocklist entries in dedicated options across partial saves and upgrades
 * - Reworded reservation instructions with neutral infinitive forms
 *
 * Version 9.1.2:
 * - Removed unnecessary full-catalog reloads after OTP confirmation
 * - Added lightweight availability snapshots for concurrent reservations
 * - Kept the reservations dashboard visible during bootstrap, status updates and PDF generation
 * - Added an explicit, accessible reservation-creation progress state
 *
 * Version 9.1.1:
 * - Restored title, author and inventory details in staff withdrawal PDFs
 * - Added multipage output for the pure-PHP reservation PDF fallback
 * - Added an email blocklist checked before OTP delivery and final confirmation
 *
 * Version 9.1.0:
 * - Added controlled per-email rate-limit exceptions and privacy-aware activity logs
 * - Added aggregate statistics and measurable concurrent catalog loading
 *
 * Version 9.0.7:
 * - Restored reservation countdowns and consistent availability states
 * - Corrected OTP throttling so only actual verification failures consume attempts
 * - Improved reservation-panel loading and action feedback
 *
 * Version 9.0.6:
 * - Unified privacy wording around acknowledgement and public-interest processing
 * - Restricted storage-box metadata to authenticated catalog staff
 * - Restored book details in pending-reservation GDPR exports
 *
 * Version 9.0.5:
 * - Exposed the catalog conservation status and added accessible text labels
 *
 * Version 9.0.4:
 * - Added nonce-protected email transport testing and PHPMailer failure diagnostics
 * - Added sender-domain alignment guidance for authenticated SMTP
 *
 * Version 9.0.3:
 * - Accepted repeated or missing inventory values as distinct catalog records
 * - Validated Excel files before requesting the security password
 *
 * Version 9.0.2:
 * - Fixed the shared wp-admin ES module loader for Reservations and Catalog
 * - Restored the Excel import workflow with validation and result reporting
 * - Preserved plugin data on uninstall unless deletion is explicitly enabled
 *
 * Version 9.0.1:
 * - Hardened reservation abuse limits and trusted proxy handling
 * - Added a concurrent-safe limit for active reservations per verified email
 *
 * Version 9.0.0:
 * - Moved staff operations to wp-admin with WordPress roles, capabilities and REST nonces
 * - Added separate public/admin bundles and configurable visual identity
 * - Added native GDPR staff tools with destructive-action step-up authentication
 *
 * Version 8.8.1:
 * - Enhanced privacy policy with GDPR-compliant sections
 * - Added DPO (Data Protection Officer) configurable contact info
 * - Added "No automated decision-making" statement
 * - Added explicit Garante contact information
 * - Added consent withdrawal information
 * - Improved data subject rights documentation
 *
 * Version 8.8.0:
 * - Added configurable data retention periods in settings
 * - Added separate IP retention with automatic anonymization
 * - Added "purge all data" admin function
 * - Added configurable rate limiting (login attempts, reservations per day/email)
 * - Added privacy_policy_url setting for public page link
 * - Improved GDPR compliance
 *
 * Security Update 8.7.1:
 * - Fixed GDPR endpoints to require email verification
 * - Protected /init endpoint from exposing PII to unauthenticated users
 * - Added rate limiting to admin authentication
 * - Removed PII from debug logging
 * - Fixed SQL escaping in uninstall.php
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// CONSTANTS
// ============================================================================

define('SCARTO_VERSION', '9.4.6');
define('SCARTO_DB_VERSION', '8.15');
define('SCARTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCARTO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Input length limits
define('SCARTO_MAX_NAME_LENGTH', 100);
define('SCARTO_MAX_EMAIL_LENGTH', 254);
define('SCARTO_MAX_ADDRESS_LENGTH', 500);
define('SCARTO_MAX_STREET_LENGTH', 200);
define('SCARTO_MAX_STREET_NUMBER_LENGTH', 30);
define('SCARTO_MAX_CITY_LENGTH', 120);
define('SCARTO_MAX_SHIPPING_NOTES_LENGTH', 500);
define('SCARTO_MAX_TEXT_LENGTH', 1000);
define('SCARTO_MAX_PASSWORD_LENGTH', 72);
define('SCARTO_MIN_PASSWORD_LENGTH', 12);

// Rate limiting - DEFAULT values (can be overridden in settings)
define('SCARTO_DEFAULT_MAX_LOGIN_ATTEMPTS', 5);
define('SCARTO_DEFAULT_LOGIN_LOCKOUT_MINUTES', 15);
define('SCARTO_DEFAULT_MAX_RESERVATIONS_PER_DAY', 1);
define('SCARTO_DEFAULT_MAX_RESERVATIONS_PER_EMAIL', 2);
define('SCARTO_DEFAULT_MAX_ACTIVE_RESERVATIONS_PER_EMAIL', 2);
define('SCARTO_DEFAULT_RECOVERY_COOLDOWN_MINUTES', 5);
define('SCARTO_RESERVATION_VERIFICATION_EXPIRY_MINUTES', 15);
define('SCARTO_RESERVATION_VERIFICATION_MAX_ATTEMPTS', 5);

// Performance limits
define('SCARTO_MAX_BOOKS_IMPORT', 50000);
define('SCARTO_DEFAULT_PER_PAGE', 100);
define('SCARTO_MAX_PER_PAGE', 500);
define('SCARTO_ORDERS_LIMIT', 1000);

// GDPR Data Retention - DEFAULT values (can be overridden in settings)
define('SCARTO_DEFAULT_RETENTION_COMPLETED', 365);  // Default: Keep completed orders 1 year
define('SCARTO_DEFAULT_RETENTION_CANCELLED', 90);   // Default: Keep cancelled orders 90 days
define('SCARTO_DEFAULT_RETENTION_EXPIRED', 90);     // Default: Keep expired orders 90 days
define('SCARTO_DEFAULT_AUDIT_LOG_RETENTION', 90);   // Default: Keep audit logs 90 days
define('SCARTO_DEFAULT_IP_RETENTION', 30);          // Default: Keep IP addresses 30 days

// GDPR Verification
define('SCARTO_GDPR_TOKEN_EXPIRY_MINUTES', 30);  // GDPR verification token validity
define('SCARTO_GDPR_MAX_REQUESTS_PER_HOUR', 3);  // Rate limit for GDPR requests per email

// ============================================================================
// DATABASE TABLES
// ============================================================================

global $wpdb;
$wpdb->scarto_books = $wpdb->prefix . 'scarto_books';
$wpdb->scarto_orders = $wpdb->prefix . 'scarto_orders';
$wpdb->scarto_order_items = $wpdb->prefix . 'scarto_order_items';
$wpdb->scarto_audit_log = $wpdb->prefix . 'scarto_audit_log';
$wpdb->scarto_recovery_tokens = $wpdb->prefix . 'scarto_recovery_tokens';
$wpdb->scarto_gdpr_tokens = $wpdb->prefix . 'scarto_gdpr_tokens';
$wpdb->scarto_rate_limits = $wpdb->prefix . 'scarto_rate_limits';
$wpdb->scarto_reservation_verifications = $wpdb->prefix . 'scarto_reservation_verifications';

require_once SCARTO_PLUGIN_DIR . 'includes/security.php';
require_once SCARTO_PLUGIN_DIR . 'includes/rest-schema.php';
require_once SCARTO_PLUGIN_DIR . 'includes/diagnostics.php';
require_once SCARTO_PLUGIN_DIR . 'includes/admin.php';

// ============================================================================
// DATABASE MIGRATIONS
// ============================================================================
add_action('admin_init', 'scarto_run_migrations');
function scarto_run_migrations() {
    global $wpdb;
    static $ran = false;
    if ($ran) return;
    $ran = true;
    
    // 1. Correzione tabella ORDINI (già presente)
    $cols_items = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_order_items}");
    if ($cols_items) {
        if (!in_array('inventario', $cols_items)) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_order_items} ADD COLUMN inventario VARCHAR(50) DEFAULT '' AFTER autore");
        }
        if (!in_array('scatola', $cols_items)) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_order_items} ADD COLUMN scatola VARCHAR(100) DEFAULT '' AFTER inventario");
        }
    }

    // 2. Correzione tabella LIBRI (NUOVA AGGIUNTA FONDAMENTALE)
    // Questo è il pezzo che mancava e che impedisce il salvataggio delle scatole
    $cols_books = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_books}");
    if ($cols_books) {
        if (!in_array('scatola', $cols_books)) {
            // Forza l'aggiunta della colonna scatola se manca
            $wpdb->query("ALTER TABLE {$wpdb->scarto_books} ADD COLUMN scatola VARCHAR(100) DEFAULT '' AFTER id");
        }
    }

    // 3. FIX v8.6.2: Arricchisci le scatole mancanti nelle prenotazioni esistenti
    // Questo riempie retroattivamente i dati scatola per ordini creati con la versione buggata
    // Nota: gli ID dei libri cambiano ad ogni import (sono generati casualmente), quindi
    // facciamo JOIN sia su ID che su inventario per massimizzare le corrispondenze
    $current_db_version = get_option('scarto_db_version', '1.0');
    if (version_compare($current_db_version, '8.6.2', '<')) {
        // Prima prova con book_id = id
        $wpdb->query("
            UPDATE {$wpdb->scarto_order_items} oi
            INNER JOIN {$wpdb->scarto_books} b ON oi.book_id = b.id
            SET oi.scatola = b.scatola
            WHERE (oi.scatola IS NULL OR oi.scatola = '') AND b.scatola != ''
        ");
        // Poi prova con inventario (per ordini con ID che non matchano più)
        $wpdb->query("
            UPDATE {$wpdb->scarto_order_items} oi
            INNER JOIN {$wpdb->scarto_books} b ON oi.inventario = b.inventario AND oi.inventario != ''
            SET oi.scatola = b.scatola
            WHERE (oi.scatola IS NULL OR oi.scatola = '') AND b.scatola != ''
        ");
    }

    // 4. v8.7.1: Create GDPR verification tokens table if not exists
    if (version_compare($current_db_version, '8.7', '<')) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql_gdpr_tokens = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_gdpr_tokens} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            email VARCHAR(254) NOT NULL,
            token VARCHAR(64) NOT NULL,
            action VARCHAR(20) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_token (token),
            KEY idx_email_action (email, action),
            KEY idx_expires (expires_at)
        ) $charset_collate;";
        dbDelta($sql_gdpr_tokens);
    }

    if (version_compare($current_db_version, '8.9', '<')) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->scarto_rate_limits} (
            key_hash CHAR(64) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_expires DATETIME NOT NULL,
            PRIMARY KEY (key_hash),
            KEY idx_expires (window_expires)
        ) ENGINE=InnoDB $charset_collate;");

        $cols_orders = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_orders}");
        if ($cols_orders && !in_array('privacy_version', $cols_orders, true)) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_orders} ADD COLUMN privacy_version VARCHAR(20) DEFAULT NULL AFTER user_agent");
        }
        if ($cols_orders && !in_array('consent_at', $cols_orders, true)) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_orders} ADD COLUMN consent_at BIGINT UNSIGNED DEFAULT NULL AFTER privacy_version");
        }
        delete_transient('scarto_initial_password');
        delete_transient('scarto_initial_db_password');
    }

    if (version_compare($current_db_version, '8.10', '<')) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->scarto_reservation_verifications} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            request_id CHAR(32) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            email_hash CHAR(64) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_request_id (request_id),
            KEY idx_expires (expires_at),
            KEY idx_email_hash (email_hash)
        ) ENGINE=InnoDB $charset_collate;");
    }

    if (version_compare($current_db_version, '8.11', '<')) {
        $cols_orders = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_orders}");
        if ($cols_orders && !in_array('request_id', $cols_orders, true)) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_orders} ADD COLUMN request_id CHAR(32) DEFAULT NULL AFTER code");
        }

        $request_index = $wpdb->get_var($wpdb->prepare(
            "SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'request_id'
               AND NON_UNIQUE = 0
             LIMIT 1",
            $wpdb->scarto_orders
        ));
        if (!$request_index) {
            $wpdb->query("ALTER TABLE {$wpdb->scarto_orders} ADD UNIQUE KEY idx_request_id (request_id)");
        }
    }

    if (version_compare($current_db_version, '8.12', '<')) {
        $migration_result = $wpdb->query("ALTER TABLE {$wpdb->scarto_books} MODIFY COLUMN anno VARCHAR(100) DEFAULT ''");
        if ($migration_result === false) return;
    }

    if (version_compare($current_db_version, '8.13', '<')) {
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->scarto_audit_log} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            category VARCHAR(30) NOT NULL DEFAULT 'system',
            action VARCHAR(50) NOT NULL,
            outcome VARCHAR(20) NOT NULL DEFAULT 'success',
            entity_type VARCHAR(50) DEFAULT NULL,
            entity_id VARCHAR(50) DEFAULT NULL,
            subject_email VARCHAR(254) DEFAULT NULL,
            wp_user_id BIGINT UNSIGNED DEFAULT NULL,
            details TEXT,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action (action),
            KEY idx_category_created (category, created_at),
            KEY idx_outcome_created (outcome, created_at),
            KEY idx_subject_email (subject_email),
            KEY idx_wp_user (wp_user_id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB $charset_collate;");

        $audit_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_audit_log}");
        foreach (['category', 'outcome', 'subject_email', 'wp_user_id'] as $required_column) {
            if (!in_array($required_column, $audit_columns ?: [], true)) return;
        }
        $wpdb->query("UPDATE {$wpdb->scarto_audit_log}
            SET outcome = CASE
                WHEN action LIKE '%blocked%' OR action LIKE '%rate_limited%' OR action LIKE '%limit_reached%' THEN 'blocked'
                WHEN action LIKE '%failed%' OR action LIKE '%invalid%' OR action LIKE '%rejected%' THEN 'failed'
                ELSE outcome
            END");
        $wpdb->query("UPDATE {$wpdb->scarto_audit_log}
            SET category = CASE
                WHEN action LIKE 'reservation_%' OR action LIKE 'order_%' OR action LIKE 'orders_%' THEN 'reservations'
                WHEN action LIKE 'gdpr_%' OR action LIKE 'privacy_%' OR action = 'ip_anonymization' THEN 'privacy'
                WHEN action LIKE '%login%' OR action LIKE '%password%' OR action LIKE '%auth%' OR action LIKE '%credentials%' THEN 'security'
                WHEN action LIKE '%book%' OR action LIKE '%catalog%' OR action = 'database_reset' THEN 'catalog'
                WHEN action LIKE '%mail%' THEN 'email'
                WHEN action LIKE '%settings%' OR action LIKE '%appearance%' THEN 'settings'
                ELSE category
            END");
    }

    if (version_compare($current_db_version, '8.14', '<')) {
        $cols_orders = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->scarto_orders}");
        $address_columns = [
            'user_via' => "VARCHAR(200) DEFAULT '' AFTER user_indirizzo",
            'user_civico' => "VARCHAR(30) DEFAULT '' AFTER user_via",
            'user_cap' => "CHAR(5) DEFAULT '' AFTER user_civico",
            'user_citta' => "VARCHAR(120) DEFAULT '' AFTER user_cap",
            'user_provincia' => "CHAR(2) DEFAULT '' AFTER user_citta",
            'user_note_spedizione' => "VARCHAR(500) DEFAULT '' AFTER user_provincia",
            'reservation_source' => "VARCHAR(20) NOT NULL DEFAULT 'online' AFTER user_note_spedizione",
        ];
        foreach ($address_columns as $column => $definition) {
            if ($cols_orders && !in_array($column, $cols_orders, true)) {
                if ($wpdb->query("ALTER TABLE {$wpdb->scarto_orders} ADD COLUMN {$column} {$definition}") === false) return;
            }
        }
    }

    // User-Agent is retained only in the audit log. Remove legacy copies from orders.
    if (version_compare($current_db_version, '8.15', '<')) {
        if ($wpdb->query("UPDATE {$wpdb->scarto_orders} SET user_agent = NULL WHERE user_agent IS NOT NULL") === false) return;
    }

    update_option('scarto_db_version', SCARTO_DB_VERSION);
}

// ============================================================================
// ACTIVATION
// ============================================================================

register_activation_hook(__FILE__, 'scarto_activate');
function scarto_activate() {
    global $wpdb;
    scarto_install_admin_capabilities();
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql_books = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_books} (
        id VARCHAR(50) NOT NULL,
        scatola VARCHAR(100) DEFAULT '',
        autore VARCHAR(500) DEFAULT '',
        titolo VARCHAR(1000) NOT NULL,
        editore VARCHAR(500) DEFAULT '',
        anno VARCHAR(100) DEFAULT '',
        inventario VARCHAR(100) DEFAULT '',
        collocazione VARCHAR(200) DEFAULT '',
        stato VARCHAR(100) DEFAULT '',
        motivazioni TEXT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_scatola (scatola),
        KEY idx_inventario (inventario),
        KEY idx_titolo (titolo(100)),
        KEY idx_autore (autore(100))
    ) ENGINE=InnoDB $charset_collate;";
    
    $sql_orders = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_orders} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        code VARCHAR(10) NOT NULL UNIQUE,
        request_id CHAR(32) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'active',
        user_nome VARCHAR(100) NOT NULL,
        user_cognome VARCHAR(100) NOT NULL,
        user_email VARCHAR(254) NOT NULL,
        user_indirizzo VARCHAR(500) NOT NULL,
        user_via VARCHAR(200) DEFAULT '',
        user_civico VARCHAR(30) DEFAULT '',
        user_cap CHAR(5) DEFAULT '',
        user_citta VARCHAR(120) DEFAULT '',
        user_provincia CHAR(2) DEFAULT '',
        user_note_spedizione VARCHAR(500) DEFAULT '',
        reservation_source VARCHAR(20) NOT NULL DEFAULT 'online',
        created_at BIGINT UNSIGNED NOT NULL,
        updated_at BIGINT UNSIGNED DEFAULT NULL,
        completed_at BIGINT UNSIGNED DEFAULT NULL,
        expires_at BIGINT UNSIGNED NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        privacy_version VARCHAR(20) DEFAULT NULL,
        consent_at BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY idx_code (code),
        UNIQUE KEY idx_request_id (request_id),
        KEY idx_status (status),
        KEY idx_status_expires (status, expires_at),
        KEY idx_created (created_at),
        KEY idx_email (user_email)
    ) ENGINE=InnoDB $charset_collate;";
    
    $sql_order_items = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_order_items} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        order_code VARCHAR(10) NOT NULL,
        book_id VARCHAR(50) NOT NULL,
        titolo VARCHAR(1000) NOT NULL,
        autore VARCHAR(500) DEFAULT '',
        inventario VARCHAR(50) DEFAULT '',
        scatola VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'reserved',
        withdrawn_at BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_order_code (order_code),
        KEY idx_book_id (book_id),
        KEY idx_status (status),
        KEY idx_book_status (book_id, status),
        KEY idx_order_status (order_code, status),
        CONSTRAINT fk_order FOREIGN KEY (order_code) REFERENCES {$wpdb->scarto_orders}(code) ON DELETE CASCADE
    ) ENGINE=InnoDB $charset_collate;";
    
    $sql_audit = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_audit_log} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        category VARCHAR(30) NOT NULL DEFAULT 'system',
        action VARCHAR(50) NOT NULL,
        outcome VARCHAR(20) NOT NULL DEFAULT 'success',
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id VARCHAR(50) DEFAULT NULL,
        subject_email VARCHAR(254) DEFAULT NULL,
        wp_user_id BIGINT UNSIGNED DEFAULT NULL,
        details TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_action (action),
        KEY idx_category_created (category, created_at),
        KEY idx_outcome_created (outcome, created_at),
        KEY idx_subject_email (subject_email),
        KEY idx_wp_user (wp_user_id),
        KEY idx_entity (entity_type, entity_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB $charset_collate;";
    
    $sql_tokens = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_recovery_tokens} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_token (token),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB $charset_collate;";

    // GDPR verification tokens table (v8.7.1)
    $sql_gdpr_tokens = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_gdpr_tokens} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        email VARCHAR(254) NOT NULL,
        token VARCHAR(64) NOT NULL,
        action VARCHAR(20) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_token (token),
        KEY idx_email_action (email, action),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB $charset_collate;";

    $sql_rate_limits = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_rate_limits} (
        key_hash CHAR(64) NOT NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        window_expires DATETIME NOT NULL,
        PRIMARY KEY (key_hash),
        KEY idx_expires (window_expires)
    ) ENGINE=InnoDB $charset_collate;";

    $sql_reservation_verifications = "CREATE TABLE IF NOT EXISTS {$wpdb->scarto_reservation_verifications} (
        id BIGINT UNSIGNED AUTO_INCREMENT,
        request_id CHAR(32) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        email_hash CHAR(64) NOT NULL,
        payload LONGTEXT NOT NULL,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_request_id (request_id),
        KEY idx_expires (expires_at),
        KEY idx_email_hash (email_hash)
    ) ENGINE=InnoDB $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_books);
    dbDelta($sql_orders);
    dbDelta($sql_order_items);
    dbDelta($sql_audit);
    dbDelta($sql_tokens);
    dbDelta($sql_gdpr_tokens);
    dbDelta($sql_rate_limits);
    dbDelta($sql_reservation_verifications);
    
    if (!get_option('scarto_auth_generation')) {
        add_option('scarto_auth_generation', 1, '', false);
    }
    
    if (!get_option('scarto_db_admin_password_hash')) {
        $hash = password_hash(wp_generate_password(64, true, true), PASSWORD_BCRYPT, ['cost' => 12]);
        update_option('scarto_db_admin_password_hash', $hash);
        update_option('scarto_credentials_setup_required', true, false);
    }
    scarto_audit_log('plugin_activated', null, null, ['version' => SCARTO_VERSION]);
    delete_transient('scarto_initial_password');
    delete_transient('scarto_initial_db_password');
    
    $default_settings = [
        'reservation_days' => 7,
        'email_from' => get_option('admin_email'),
        'email_to' => get_option('admin_email'),
        'email_from_name' => get_bloginfo('name'),
        'email_subject_prefix' => 'Nuova Prenotazione Scarto',
        'library_name' => get_bloginfo('name'),
        'library_address' => '',
        'library_phone' => '',
        'max_books_per_reservation' => 20,
        // Legacy backup key. Since 9.4.1 the collection rule depends on reservation origin.
        'collect_domicile' => false,
        'homepage_url' => home_url()
    ];
    
    if (!get_option('scarto_settings')) {
        update_option('scarto_settings', $default_settings);
    }
    
    update_option('scarto_db_version', SCARTO_DB_VERSION);
    
    if (!wp_next_scheduled('scarto_check_expired_reservations')) {
        wp_schedule_event(time(), 'hourly', 'scarto_check_expired_reservations');
    }
    if (!wp_next_scheduled('scarto_cleanup_audit_logs')) {
        wp_schedule_event(time(), 'daily', 'scarto_cleanup_audit_logs');
    }
    if (!wp_next_scheduled('scarto_gdpr_data_cleanup')) {
        wp_schedule_event(time(), 'daily', 'scarto_gdpr_data_cleanup');
    }
    if (!wp_next_scheduled('scarto_anonymize_old_ips')) {
        wp_schedule_event(time(), 'daily', 'scarto_anonymize_old_ips');
    }
    if (!wp_next_scheduled('scarto_rate_limit_cleanup')) {
        wp_schedule_event(time(), 'hourly', 'scarto_rate_limit_cleanup');
    }
    if (!wp_next_scheduled('scarto_cleanup_temp_files')) {
        wp_schedule_event(time(), 'hourly', 'scarto_cleanup_temp_files');
    }
}

register_deactivation_hook(__FILE__, 'scarto_deactivate');
function scarto_deactivate() {
    wp_clear_scheduled_hook('scarto_check_expired_reservations');
    wp_clear_scheduled_hook('scarto_cleanup_audit_logs');
    wp_clear_scheduled_hook('scarto_gdpr_data_cleanup');
    wp_clear_scheduled_hook('scarto_anonymize_old_ips');
    wp_clear_scheduled_hook('scarto_rate_limit_cleanup');
    wp_clear_scheduled_hook('scarto_cleanup_temp_files');
}

// ============================================================================
// WORDPRESS ADMIN SECURITY MANAGEMENT
// ============================================================================

add_action('admin_notices', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY) || !get_option('scarto_credentials_setup_required')) return;
    echo '<div class="notice notice-error"><p><strong>Gestione Scarto Librario:</strong> ';
    echo 'configurare la password di sicurezza prima di usare importazione o reset. ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=scarto-security')) . '">Apri configurazione sicurezza</a>.';
    echo '</p></div>';
});

function scarto_render_security_page() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.');
    ?>
    <div class="wrap">
            <h1>Privacy e sicurezza Scarto Librario</h1>
        <p>L'accesso personale usa gli account WordPress. Qui puoi impostare o ruotare la password aggiuntiva richiesta per importazione, reset e operazioni distruttive. Non è la password MySQL configurata in <code>wp-config.php</code>.</p>
        <p><a href="<?php echo esc_url(admin_url('profile.php')); ?>">Gestisci password e secondo fattore dell'account WordPress</a></p>
        <?php if (!empty($_GET['updated'])): ?>
            <div class="notice notice-success"><p>Credenziali aggiornate e sessioni precedenti invalidate.</p></div>
        <?php endif; ?>
        <?php if (!empty($_GET['gdpr_processed'])): ?>
            <div class="notice notice-success"><p>Richiesta GDPR elaborata: <?php echo esc_html(absint($_GET['gdpr_anonymized'] ?? 0)); ?> record anonimizzati, <?php echo esc_html(absint($_GET['gdpr_deleted'])); ?> eliminati e <?php echo esc_html(absint($_GET['gdpr_transient'] ?? 0)); ?> dati temporanei rimossi.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['mail_test'])): ?>
            <?php $mail_test_ok = sanitize_key(wp_unslash($_GET['mail_test'])) === 'accepted'; ?>
            <div class="notice <?php echo $mail_test_ok ? 'notice-success' : 'notice-error'; ?>"><p>
                <?php echo esc_html($mail_test_ok
                    ? 'WordPress/PHPMailer ha accettato il messaggio di test. Verificare la casella: questo esito non garantisce la consegna del server destinatario.'
                    : 'WordPress/PHPMailer ha rifiutato il messaggio di test. Consulta la diagnostica sottostante per il dettaglio tecnico.'); ?>
            </p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="scarto_update_credentials">
            <?php wp_nonce_field('scarto_update_credentials'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="scarto_db_password">Password di sicurezza del plugin</label></th>
                    <td><input id="scarto_db_password" name="db_password" type="password" class="regular-text" autocomplete="new-password"></td>
                </tr>
            </table>
            <p>Minimo 12 caratteri con maiuscola, minuscola e numero.</p>
            <?php submit_button('Aggiorna credenziali'); ?>
        </form>

        <hr>
        <h2>Test invio email</h2>
        <p>Invia un messaggio diagnostico senza codice OTP e registra solo esito, data e contesto. Se il mittente usa un dominio diverso dal sito, configurare un SMTP autenticato con SPF, DKIM e DMARC coerenti.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="scarto_test_email">
            <?php wp_nonce_field('scarto_test_email'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="scarto_test_email_address">Destinatario test</label></th>
                    <td><input class="regular-text" type="email" id="scarto_test_email_address" name="email" maxlength="254" required value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"></td>
                </tr>
            </table>
            <?php submit_button('Invia email di test', 'secondary'); ?>
        </form>

        <hr>
        <h2>Strumenti GDPR per il personale autorizzato</h2>
        <p>Usare questi strumenti solo dopo aver verificato l'identità del richiedente e registrato la pratica secondo le procedure della biblioteca.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;max-width:1100px">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #dcdcde;padding:20px">
                <input type="hidden" name="action" value="scarto_gdpr_export_native">
                <?php wp_nonce_field('scarto_gdpr_export_native'); ?>
                <h3>Esporta dati personali</h3>
                <p><label for="scarto_gdpr_export_email">Email interessato</label><br><input class="regular-text" type="email" id="scarto_gdpr_export_email" name="email" maxlength="254"></p>
                <p><label for="scarto_gdpr_export_code">oppure codice prenotazione</label><br><input class="regular-text" id="scarto_gdpr_export_code" name="code" maxlength="10" pattern="[A-Z2-9]{6,10}"></p>
                <p><label for="scarto_gdpr_export_reason">Motivazione</label><br><input class="regular-text" id="scarto_gdpr_export_reason" name="reason" required minlength="10" maxlength="300"></p>
                <p><label for="scarto_gdpr_export_password">Password di sicurezza del plugin</label><br><input class="regular-text" type="password" id="scarto_gdpr_export_password" name="password" required maxlength="72" autocomplete="current-password"></p>
                <p class="description">Il file JSON contiene dati personali: conservarlo e trasmetterlo solo con canali autorizzati.</p>
                <?php submit_button('Scarica esportazione JSON', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #d63638;padding:20px">
                <input type="hidden" name="action" value="scarto_gdpr_delete_native">
                <?php wp_nonce_field('scarto_gdpr_delete_native'); ?>
                <h3>Cancella o anonimizza dati</h3>
                <p><label for="scarto_gdpr_delete_email">Email interessato</label><br><input class="regular-text" type="email" id="scarto_gdpr_delete_email" name="email" maxlength="254"></p>
                <p><label for="scarto_gdpr_delete_code">oppure codice prenotazione</label><br><input class="regular-text" id="scarto_gdpr_delete_code" name="code" maxlength="10" pattern="[A-Z2-9]{6,10}"></p>
                <p><label for="scarto_gdpr_delete_password">Password di sicurezza del plugin</label><br><input class="regular-text" type="password" id="scarto_gdpr_delete_password" name="password" autocomplete="current-password" required></p>
                <p><label for="scarto_gdpr_delete_reason">Motivazione</label><br><input class="regular-text" id="scarto_gdpr_delete_reason" name="reason" required minlength="10" maxlength="300"></p>
                <p><label for="scarto_gdpr_delete_confirm">Scrivere <code>ELIMINA</code> per confermare</label><br><input class="regular-text" id="scarto_gdpr_delete_confirm" name="confirmation" autocomplete="off" required></p>
                <p class="description">Le prenotazioni attive bloccano l'operazione. I record completati vengono anonimizzati.</p>
                <?php submit_button('Elabora cancellazione GDPR', 'delete', 'submit', false); ?>
            </form>
        </div>
        <?php scarto_render_diagnostics(); ?>
    </div>
    <?php
}

add_action('admin_post_scarto_update_credentials', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.');
    check_admin_referer('scarto_update_credentials');

    $db_password = isset($_POST['db_password']) ? (string) wp_unslash($_POST['db_password']) : '';
    $passwords = array_filter([$db_password], static fn($value) => $value !== '');

    foreach ($passwords as $password) {
        if (strlen($password) < 12
            || strlen($password) > SCARTO_MAX_PASSWORD_LENGTH
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
        ) {
            wp_die('La password non rispetta i requisiti di sicurezza.');
        }
    }

    if ($db_password !== '') {
        update_option('scarto_db_admin_password_hash', password_hash($db_password, PASSWORD_BCRYPT, ['cost' => 12]));
        delete_option('scarto_credentials_setup_required');
    }

    scarto_invalidate_all_staff_sessions();
    scarto_audit_log('credentials_rotated', 'wordpress_user', (string) get_current_user_id(), [
        'db_changed' => $db_password !== '',
    ]);

    wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=scarto-security')));
    exit;
});

add_action('admin_post_scarto_test_email', function() {
    if (!current_user_can(SCARTO_CAP_PRIVACY)) wp_die('Accesso non consentito.', '', ['response' => 403]);
    check_admin_referer('scarto_test_email');

    $rate_key = 'mail_test_' . get_current_user_id();
    if (!scarto_rate_limit_consume($rate_key, 3, 10 * MINUTE_IN_SECONDS)) {
        wp_die('Troppi test email. Riprova tra alcuni minuti.', '', ['response' => 429]);
    }

    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    if (!$email || !is_email($email)) wp_die('Indirizzo email non valido.', '', ['response' => 400]);

    $settings = scarto_get_settings();
    $subject = 'Test email Scarto Librario - ' . $settings['library_name'];
    $body = "Questo e un messaggio di test del trasporto email di Gestione Scarto Librario.\n\n";
    $body .= 'Data: ' . wp_date('d/m/Y H:i:s') . "\n";
    $body .= "Se ricevi questo messaggio, il percorso WordPress verso questa casella e operativo.\n";
    $headers = [
        'From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $accepted = scarto_send_mail_with_status($email, $subject, $body, $headers, [], 'admin_test');
    scarto_audit_log('mail_test', 'wordpress_user', (string) get_current_user_id(), [
        'accepted' => $accepted,
    ], ['subject_email' => $email, 'outcome' => $accepted ? 'success' : 'failed', 'category' => 'email']);

    wp_safe_redirect(add_query_arg([
        'page' => 'scarto-security',
        'mail_test' => $accepted ? 'accepted' : 'failed',
    ], admin_url('admin.php')));
    exit;
});

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function scarto_get_settings() {
    $defaults = [
        'reservation_days' => 7,
        'email_from' => get_option('admin_email'),
        'email_to' => get_option('admin_email'),
        'email_from_name' => get_bloginfo('name'),
        'email_subject_prefix' => 'Nuova Prenotazione Scarto',
        'library_name' => get_bloginfo('name'),
        'library_address' => '',
        'library_phone' => '',
        'max_books_per_reservation' => 20,
        // Retained for backup compatibility; no longer controls data collection.
        'collect_domicile' => false,
        'homepage_url' => home_url(),
        // GDPR retention settings (days)
        'retention_completed' => SCARTO_DEFAULT_RETENTION_COMPLETED,
        'retention_cancelled' => SCARTO_DEFAULT_RETENTION_CANCELLED,
        'retention_expired' => SCARTO_DEFAULT_RETENTION_EXPIRED,
        'retention_audit_logs' => SCARTO_DEFAULT_AUDIT_LOG_RETENTION,
        'retention_ip' => SCARTO_DEFAULT_IP_RETENTION,
        'retention_plan_approved' => false,
        // Rate limiting settings
        'max_login_attempts' => SCARTO_DEFAULT_MAX_LOGIN_ATTEMPTS,
        'login_lockout_minutes' => SCARTO_DEFAULT_LOGIN_LOCKOUT_MINUTES,
        'max_reservations_per_day' => SCARTO_DEFAULT_MAX_RESERVATIONS_PER_DAY,
        'max_reservations_per_email' => SCARTO_DEFAULT_MAX_RESERVATIONS_PER_EMAIL,
        'max_active_reservations_per_email' => SCARTO_DEFAULT_MAX_ACTIVE_RESERVATIONS_PER_EMAIL,
        'rate_limit_email_exemptions' => '',
        'reservation_email_blocklist' => '',
        // Privacy policy link (shown on public page)
        'privacy_policy_url' => '',
        // DPO (Data Protection Officer) contact info
        'dpo_name' => '',
        'dpo_email' => '',
        'dpo_phone' => '',
        // PEC for GDPR requests (preferred over regular email)
        'contact_pec' => '',
        // Preserve catalog and reservations when the plugin is deleted by default.
        'delete_data_on_uninstall' => false,
    ];
    $saved = get_option('scarto_settings', []);
    $saved = is_array($saved) ? $saved : [];

    // These security lists also live in dedicated options so a legacy or
    // partial settings save cannot silently discard them.
    $email_controls = [
        'rate_limit_email_exemptions' => 'scarto_rate_limit_email_exemptions',
        'reservation_email_blocklist' => 'scarto_reservation_email_blocklist',
    ];
    foreach ($email_controls as $key => $option_name) {
        $has_embedded_value = array_key_exists($key, $saved);
        $embedded = $key === 'reservation_email_blocklist'
            ? scarto_sanitize_email_blocklist($saved[$key] ?? '')
            : scarto_sanitize_email_list($saved[$key] ?? '');
        $dedicated = get_option($option_name, null);
        if ($dedicated === null) {
            $dedicated = $embedded;
            add_option($option_name, $dedicated, '', false);
        } elseif ($has_embedded_value && $embedded !== $dedicated) {
            // A supported older build may have changed the embedded value.
            // Treat an explicit embedded key as newer than the backup option.
            $dedicated = $embedded;
            update_option($option_name, $dedicated, false);
        }
        $saved[$key] = $key === 'reservation_email_blocklist'
            ? scarto_sanitize_email_blocklist($dedicated)
            : scarto_sanitize_email_list($dedicated);
    }

    return array_merge($defaults, $saved);
}

function scarto_record_mail_status($context, $accepted, $error = '') {
    $safe_error = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email rimossa]', (string) $error);
    update_option('scarto_last_mail_status', [
        'context' => sanitize_key($context),
        'accepted' => (bool) $accepted,
        'error' => scarto_sanitize_text($safe_error, 500),
        'timestamp' => time(),
    ], false);
}

function scarto_send_mail_with_status($to, $subject, $body, $headers = [], $attachments = [], $context = 'generic') {
    $failure = '';
    $capture_failure = static function($error) use (&$failure) {
        if ($error instanceof WP_Error) $failure = $error->get_error_message();
    };
    add_action('wp_mail_failed', $capture_failure);
    try {
        $accepted = wp_mail($to, $subject, $body, $headers, $attachments);
    } finally {
        remove_action('wp_mail_failed', $capture_failure);
    }
    scarto_record_mail_status($context, $accepted, $failure);
    return $accepted;
}

/**
 * Get retention period in days for a specific data type
 */
function scarto_get_retention_days($type) {
    $settings = scarto_get_settings();
    $map = [
        'completed' => 'retention_completed',
        'cancelled' => 'retention_cancelled',
        'expired' => 'retention_expired',
        'audit_logs' => 'retention_audit_logs',
        'ip' => 'retention_ip'
    ];
    $key = $map[$type] ?? null;
    if (!$key) return 90; // Fallback
    return max(1, intval($settings[$key]));
}

/**
 * Get rate limit setting value
 */
function scarto_get_rate_limit($type) {
    $settings = scarto_get_settings();
    $defaults = [
        'max_login_attempts' => SCARTO_DEFAULT_MAX_LOGIN_ATTEMPTS,
        'login_lockout_minutes' => SCARTO_DEFAULT_LOGIN_LOCKOUT_MINUTES,
        'max_reservations_per_day' => SCARTO_DEFAULT_MAX_RESERVATIONS_PER_DAY,
        'max_reservations_per_email' => SCARTO_DEFAULT_MAX_RESERVATIONS_PER_EMAIL,
        'max_active_reservations_per_email' => SCARTO_DEFAULT_MAX_ACTIVE_RESERVATIONS_PER_EMAIL
    ];
    return isset($settings[$type]) ? max(1, intval($settings[$type])) : ($defaults[$type] ?? 5);
}

function scarto_sanitize_email_list($value) {
    $emails = [];
    foreach (preg_split('/[,;\r\n]+/', (string) $value) ?: [] as $candidate) {
        $email = strtolower(sanitize_email(trim((string) $candidate)));
        if ($email && is_email($email)) $emails[$email] = true;
    }
    return implode(',', array_keys($emails));
}

function scarto_validate_institutional_email_list($value, $settings = []) {
    $normalized = scarto_sanitize_email_list($value);
    if ($normalized === '') return '';

    $allowed_addresses = [];
    foreach (['email_from', 'email_to', 'dpo_email', 'contact_pec'] as $key) {
        foreach (preg_split('/[,;]+/', (string) ($settings[$key] ?? '')) ?: [] as $configured) {
            $configured = sanitize_email(trim($configured));
            if ($configured && is_email($configured)) $allowed_addresses[strtolower($configured)] = true;
        }
    }

    $invalid = [];
    foreach (explode(',', $normalized) as $email) {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (!str_ends_with($domain, '.gov.it') && empty($allowed_addresses[$email])) $invalid[] = $email;
    }
    if ($invalid) {
        return new WP_Error('non_institutional_email', 'La whitelist accetta solo account istituzionali: ' . implode(', ', $invalid));
    }
    return $normalized;
}

function scarto_is_email_rate_limit_exempt($email) {
    $email = strtolower(sanitize_email((string) $email));
    if (!$email || !is_email($email)) return false;

    $settings = scarto_get_settings();
    $configured = scarto_sanitize_email_list($settings['rate_limit_email_exemptions'] ?? '');
    return in_array($email, $configured === '' ? [] : explode(',', $configured), true);
}

function scarto_sanitize_email_blocklist($value) {
    $entries = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $value) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') continue;
        $candidates = strpos($line, '|') === false ? (preg_split('/[,;]+/', $line) ?: []) : [$line];
        foreach ($candidates as $candidate) {
            $parts = array_map('trim', explode('|', (string) $candidate, 3));
            $email = strtolower(sanitize_email($parts[0] ?? ''));
            if (!$email || !is_email($email)) continue;
            $reference = scarto_sanitize_text($parts[1] ?? '', 150);
            $reason = scarto_sanitize_text($parts[2] ?? '', 300);
            $entries[$email] = implode(' | ', [$email, $reference, $reason]);
        }
    }
    return implode("\n", array_values($entries));
}

function scarto_persist_email_control_settings($settings) {
    update_option(
        'scarto_rate_limit_email_exemptions',
        scarto_sanitize_email_list($settings['rate_limit_email_exemptions'] ?? ''),
        false
    );
    update_option(
        'scarto_reservation_email_blocklist',
        scarto_sanitize_email_blocklist($settings['reservation_email_blocklist'] ?? ''),
        false
    );
    scarto_persist_email_blocklist_entries($settings['reservation_email_blocklist'] ?? '');
}

function scarto_persist_email_blocklist_entries($value) {
    $stored = get_option('scarto_reservation_email_blocklist_v2', []);
    $existing = [];
    foreach (is_array($stored) ? $stored : [] as $entry) {
        if (!empty($entry['email'])) $existing[strtolower($entry['email'])] = $entry;
    }

    $entries = [];
    foreach (explode("\n", scarto_sanitize_email_blocklist($value)) as $line) {
        $parts = array_map('trim', explode('|', $line, 3));
        $email = strtolower(sanitize_email($parts[0] ?? ''));
        if (!$email || !is_email($email)) continue;

        $reason = scarto_sanitize_text($parts[1] ?? '', 180);
        $schedule = strtolower(scarto_sanitize_text($parts[2] ?? '', 40));
        $schedule_type = '';
        $schedule_date = '';
        if (preg_match('/^(scadenza|riesame)\s*:\s*(\d{4}-\d{2}-\d{2})$/', $schedule, $match)) {
            $schedule_type = $match[1];
            $schedule_date = $match[2];
        } else {
            // Legacy lines are retained and assigned a bounded review date.
            $legacy_reason = scarto_sanitize_text(trim($reason . ' ' . ($parts[2] ?? '')), 180);
            $reason = $legacy_reason ?: 'Voce migrata da una versione precedente';
            $schedule_type = 'riesame';
            $schedule_date = wp_date('Y-m-d', time() + (90 * DAY_IN_SECONDS));
        }
        $old = $existing[$email] ?? [];
        $entries[$email] = [
            'email' => $email,
            'reason' => $reason ?: 'Restrizione del servizio',
            'created_at' => $old['created_at'] ?? current_time('mysql', true),
            'created_by' => isset($old['created_by']) ? (int) $old['created_by'] : get_current_user_id(),
            'schedule_type' => $schedule_type,
            'schedule_date' => $schedule_date,
        ];
    }
    update_option('scarto_reservation_email_blocklist_v2', array_values($entries), false);
}

function scarto_get_email_blocklist_entry($email) {
    $email = strtolower(sanitize_email((string) $email));
    if (!$email || !is_email($email)) return null;
    $structured = get_option('scarto_reservation_email_blocklist_v2', []);
    foreach (is_array($structured) ? $structured : [] as $entry) {
        if (($entry['email'] ?? '') !== $email) continue;
        if (($entry['schedule_type'] ?? '') === 'scadenza'
            && !empty($entry['schedule_date'])
            && $entry['schedule_date'] < wp_date('Y-m-d')) return null;
        $author = !empty($entry['created_by']) ? get_userdata((int) $entry['created_by']) : null;
        return array_merge($entry, ['author' => $author ? $author->display_name : 'Sistema']);
    }

    $settings = scarto_get_settings();
    $blocklist = scarto_sanitize_email_blocklist($settings['reservation_email_blocklist'] ?? '');
    foreach (explode("\n", $blocklist) as $line) {
        $parts = array_map('trim', explode('|', $line, 3));
        if (($parts[0] ?? '') === $email) {
            return [
                'email' => $email,
                'reference' => $parts[1] ?? '',
                'reason' => $parts[2] ?? '',
                'created_at' => null,
                'created_by' => 0,
                'author' => 'Voce precedente alla registrazione strutturata',
                'schedule_type' => 'riesame',
                'schedule_date' => null,
            ];
        }
    }
    return null;
}

function scarto_get_subject_processing_restriction($email) {
    $email = strtolower(sanitize_email((string) $email));
    if (!$email || !is_email($email)) return null;
    $restrictions = get_option('scarto_subject_processing_restrictions', []);
    $entry = is_array($restrictions) ? ($restrictions[$email] ?? null) : null;
    if (!is_array($entry)) return null;
    if (empty($entry['until']) || $entry['until'] < wp_date('Y-m-d')) return null;
    return $entry;
}

function scarto_reservation_blocked_error() {
    return new WP_Error(
        'reservation_not_allowed',
        'Non è possibile effettuare prenotazioni con questo indirizzo email. Contatta la biblioteca per assistenza.',
        ['status' => 403]
    );
}

function scarto_get_reservation_duration_ms() {
    $settings = scarto_get_settings();
    return max(1, min(30, intval($settings['reservation_days']))) * 86400000;
}

function scarto_verify_password($password, $hash) {
    if (empty($password) || empty($hash)) return false;
    if (strlen($password) > SCARTO_MAX_PASSWORD_LENGTH) return false;
    return password_verify($password, $hash);
}

function scarto_ip_matches_trusted_proxy($ip, $entry) {
    $ip = trim((string) $ip);
    $entry = trim((string) $entry);
    if ($ip === '' || $entry === '') return false;

    if (strpos($entry, '/') === false) {
        $ip_binary = @inet_pton($ip);
        $entry_binary = @inet_pton($entry);
        return $ip_binary !== false && $entry_binary !== false && hash_equals($entry_binary, $ip_binary);
    }

    [$network, $prefix_raw] = array_pad(explode('/', $entry, 2), 2, '');
    $ip_binary = @inet_pton($ip);
    $network_binary = @inet_pton($network);
    if ($ip_binary === false || $network_binary === false || strlen($ip_binary) !== strlen($network_binary)) return false;

    $prefix = filter_var($prefix_raw, FILTER_VALIDATE_INT);
    $max_bits = strlen($ip_binary) * 8;
    if ($prefix === false || $prefix < 0 || $prefix > $max_bits) return false;

    $full_bytes = intdiv($prefix, 8);
    $remaining_bits = $prefix % 8;
    if ($full_bytes > 0 && !hash_equals(substr($network_binary, 0, $full_bytes), substr($ip_binary, 0, $full_bytes))) return false;
    if ($remaining_bits === 0) return true;

    $mask = (0xff << (8 - $remaining_bits)) & 0xff;
    return (ord($network_binary[$full_bytes]) & $mask) === (ord($ip_binary[$full_bytes]) & $mask);
}

function scarto_is_trusted_proxy($ip) {
    if (!defined('SCARTO_TRUSTED_PROXIES') || !is_array(SCARTO_TRUSTED_PROXIES)) return false;
    foreach (SCARTO_TRUSTED_PROXIES as $entry) {
        if (scarto_ip_matches_trusted_proxy($ip, $entry)) return true;
    }
    return false;
}

function scarto_get_client_ip() {
    $remote = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    if ($remote === 'unknown' || !scarto_is_trusted_proxy($remote)) return $remote;

    if (defined('SCARTO_TRUST_CLOUDFLARE') && SCARTO_TRUST_CLOUDFLARE && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cloudflare_ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cloudflare_ip, FILTER_VALIDATE_IP)) return $cloudflare_ip;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($chain as $candidate) {
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) continue;
            if (!scarto_is_trusted_proxy($candidate)) return $candidate;
        }
    }

    return $remote;
}

function scarto_get_rate_limit_ip($ip = null) {
    $ip = $ip === null ? scarto_get_client_ip() : (string) $ip;
    $binary = @inet_pton($ip);
    if ($binary === false || strlen($binary) !== 16) return $ip;

    // Aggregate temporary IPv6 addresses so rotating the interface identifier cannot bypass limits.
    return inet_ntop(substr($binary, 0, 8) . str_repeat("\0", 8)) . '/64';
}

function scarto_email_fingerprint($email) {
    $normalized = strtolower(trim((string) $email));
    return $normalized === ''
        ? 'N/A'
        : substr(hash_hmac('sha256', $normalized, wp_salt('auth')), 0, 16);
}

function scarto_email_lookup_hash($email) {
    return hash_hmac('sha256', strtolower(trim((string) $email)), wp_salt('auth'));
}

function scarto_get_gdpr_request_metadata($email) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT action, expires_at, used, created_at
         FROM {$wpdb->scarto_gdpr_tokens} WHERE email = %s ORDER BY created_at DESC",
        strtolower(trim((string) $email))
    ), ARRAY_A) ?: [];
}

function scarto_get_pending_reservation_metadata($email) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT payload, expires_at, used, created_at
         FROM {$wpdb->scarto_reservation_verifications}
         WHERE email_hash = %s ORDER BY created_at DESC",
        scarto_email_lookup_hash($email)
    ), ARRAY_A);
    $result = [];
    foreach ($rows ?: [] as $row) {
        $decrypted = scarto_decrypt_reservation_payload($row['payload']);
        $payload = is_wp_error($decrypted) ? [] : json_decode($decrypted, true);
        $book_ids = is_array($payload) ? ($payload['bookIds'] ?? []) : [];
        $result[] = [
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'used' => (bool) $row['used'],
            'personal_data' => is_array($payload) ? ($payload['userData'] ?? []) : [],
            'requested_books' => scarto_get_pending_book_details($book_ids),
        ];
    }
    return $result;
}

function scarto_get_pending_book_details($book_ids) {
    global $wpdb;

    if (!is_array($book_ids)) return [];
    $book_ids = array_values(array_unique(array_filter(array_map('strval', $book_ids))));
    if (!$book_ids) return [];

    $book_ids = array_slice($book_ids, 0, 100);
    $placeholders = implode(',', array_fill(0, count($book_ids), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, titolo, autore, inventario FROM {$wpdb->scarto_books} WHERE id IN ({$placeholders})",
        $book_ids
    ), ARRAY_A) ?: [];
    $by_id = [];
    foreach ($rows as $row) {
        $by_id[(string) $row['id']] = $row;
    }

    $details = [];
    foreach ($book_ids as $book_id) {
        $details[] = $by_id[$book_id] ?? [
            'id' => $book_id,
            'record_status' => 'non_piu_presente_nel_catalogo',
        ];
    }
    return $details;
}

function scarto_delete_transient_personal_data($email) {
    global $wpdb;
    $email = strtolower(trim((string) $email));
    if ($email === '') return ['success' => true, 'verifications_deleted' => 0, 'gdpr_tokens_deleted' => 0];

    $verifications_deleted = $wpdb->delete(
        $wpdb->scarto_reservation_verifications,
        ['email_hash' => scarto_email_lookup_hash($email)],
        ['%s']
    );
    $gdpr_tokens_deleted = $wpdb->delete($wpdb->scarto_gdpr_tokens, ['email' => $email], ['%s']);
    foreach (['reserve_verify_email_', 'reserve_email_', 'gdpr_request_email_'] as $prefix) {
        scarto_rate_limit_reset($prefix . $email);
    }

    return [
        'success' => $verifications_deleted !== false && $gdpr_tokens_deleted !== false,
        'verifications_deleted' => max(0, (int) $verifications_deleted),
        'gdpr_tokens_deleted' => max(0, (int) $gdpr_tokens_deleted),
    ];
}

function scarto_transient_cleanup_count($result) {
    return (int) ($result['verifications_deleted'] ?? 0) + (int) ($result['gdpr_tokens_deleted'] ?? 0);
}

function scarto_sanitize_text($text, $max_length = SCARTO_MAX_TEXT_LENGTH) {
    // Convert scalars (numbers, booleans) to string; discard arrays/objects/null
    if (!is_string($text)) $text = is_scalar($text) ? (string)$text : '';
    $text = sanitize_text_field($text);
    // mbstring is not guaranteed (especially on older Debian/PHP setups).
    // Fallback to strlen/substr without breaking the plugin.
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max_length) {
            $text = mb_substr($text, 0, $max_length, 'UTF-8');
        }
    } else {
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length);
        }
    }
    return $text;
}

function scarto_substr($text, $start, $length) {
    $text = (string) $text;
    return function_exists('mb_substr')
        ? mb_substr($text, $start, $length, 'UTF-8')
        : substr($text, $start, $length);
}

function scarto_generate_code($length = 6) {
    // Excluded: 0, O (zero/oh confusion), 1, I, L (one/el/eye confusion)
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function scarto_get_non_innodb_transaction_tables() {
    global $wpdb;

    $tables = [
        $wpdb->scarto_books,
        $wpdb->scarto_orders,
        $wpdb->scarto_order_items,
        $wpdb->scarto_rate_limits,
        $wpdb->scarto_reservation_verifications,
    ];
    $placeholders = implode(',', array_fill(0, count($tables), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ($placeholders)",
        ...$tables
    ), ARRAY_A);

    if ($wpdb->last_error || count($rows ?: []) !== count($tables)) {
        return $tables;
    }

    $invalid = [];
    foreach ($rows as $row) {
        if (strcasecmp((string) $row['ENGINE'], 'InnoDB') !== 0) {
            $invalid[] = $row['TABLE_NAME'];
        }
    }
    return $invalid;
}

function scarto_has_unique_request_id_index() {
    global $wpdb;

    $index = $wpdb->get_var($wpdb->prepare(
        "SELECT INDEX_NAME
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = %s
           AND COLUMN_NAME = 'request_id'
           AND NON_UNIQUE = 0
         LIMIT 1",
        $wpdb->scarto_orders
    ));
    return !$wpdb->last_error && is_string($index) && $index !== '';
}

function scarto_verify_transaction_storage() {
    $invalid = scarto_get_non_innodb_transaction_tables();
    $has_idempotency_index = scarto_has_unique_request_id_index();
    if ($invalid || !$has_idempotency_index) {
        $detail = $invalid
            ? 'tabelle transazionali non InnoDB: ' . implode(', ', $invalid)
            : 'indice univoco request_id mancante';
        error_log('Scarto: storage prenotazioni non idoneo: ' . $detail);
        return new WP_Error(
            'transaction_storage_unavailable',
            'Servizio prenotazioni temporaneamente non disponibile.',
            ['status' => 503]
        );
    }
    return true;
}

function scarto_sanitize_audit_details($details) {
    if (!is_array($details)) return [];

    $blocked_keys = ['ip', 'ip_address', 'email', 'password', 'token', 'address', 'indirizzo', 'via', 'civico', 'cap', 'citta', 'provincia', 'note_spedizione', 'noteSpedizione'];
    $clean = [];
    foreach ($details as $key => $value) {
        $safe_key = scarto_sanitize_text((string) $key, 50);
        if ($safe_key === '' || in_array(strtolower($safe_key), $blocked_keys, true)) continue;

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            $clean[$safe_key] = $value;
        } elseif (is_string($value)) {
            $clean[$safe_key] = scarto_sanitize_text($value, 200);
        } elseif (is_array($value)) {
            $clean[$safe_key] = scarto_sanitize_audit_details($value);
        }
    }
    return $clean;
}

function scarto_audit_category($action) {
    $action = (string) $action;
    if (str_starts_with($action, 'reservation_') || str_starts_with($action, 'order_') || str_starts_with($action, 'orders_')) return 'reservations';
    if (str_starts_with($action, 'gdpr_') || str_starts_with($action, 'privacy_') || $action === 'ip_anonymization') return 'privacy';
    if (str_contains($action, 'login') || str_contains($action, 'password') || str_contains($action, 'auth') || str_contains($action, 'credentials')) return 'security';
    if (str_contains($action, 'book') || str_contains($action, 'catalog') || $action === 'database_reset') return 'catalog';
    if (str_contains($action, 'mail')) return 'email';
    if (str_contains($action, 'settings') || str_contains($action, 'appearance')) return 'settings';
    return 'system';
}

function scarto_audit_log($action, $entity_type = null, $entity_id = null, $details = [], $context = []) {
    global $wpdb;
    static $in_audit = false;
    if ($in_audit) return;
    $in_audit = true;
    
    $wp_user_id = null;
    if (is_user_logged_in()) {
        foreach (scarto_admin_capabilities() as $capability) {
            if (current_user_can($capability)) {
                $wp_user_id = get_current_user_id();
                break;
            }
        }
    }
    $subject_email = strtolower(sanitize_email((string) ($context['subject_email'] ?? '')));
    if (!$subject_email || !is_email($subject_email)) $subject_email = null;
    $default_outcome = 'success';
    if (str_contains($action, 'blocked') || str_contains($action, 'rate_limited') || str_contains($action, 'limit_reached')) {
        $default_outcome = 'blocked';
    } elseif (str_contains($action, 'failed') || str_contains($action, 'invalid') || str_contains($action, 'rejected')) {
        $default_outcome = 'failed';
    }
    $outcome = sanitize_key($context['outcome'] ?? $default_outcome);
    if (!in_array($outcome, ['success', 'failed', 'blocked', 'info'], true)) $outcome = 'info';
    $category = sanitize_key($context['category'] ?? scarto_audit_category($action));
    if (!in_array($category, ['reservations', 'catalog', 'email', 'security', 'privacy', 'settings', 'system'], true)) $category = 'system';
    $wpdb->insert(
        $wpdb->scarto_audit_log,
        [
            'category' => $category,
            'action' => scarto_sanitize_text($action, 50),
            'outcome' => $outcome,
            'entity_type' => $entity_type ? scarto_sanitize_text($entity_type, 50) : null,
            'entity_id' => $entity_id ? scarto_sanitize_text($entity_id, 50) : null,
            'subject_email' => $subject_email,
            'wp_user_id' => $wp_user_id,
            'details' => wp_json_encode(scarto_sanitize_audit_details($details), JSON_UNESCAPED_UNICODE),
            'ip_address' => scarto_get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (function_exists('mb_substr') ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500, 'UTF-8') : substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) : null
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    $in_audit = false;
}

function scarto_invalidate_caches() {
    delete_transient('scarto_scatole_list');
    delete_transient('scarto_reserved_ids');
}

function scarto_anonymize_audit_email($email) {
    global $wpdb;
    $email = strtolower(sanitize_email((string) $email));
    if (!$email || !is_email($email)) return 0;
    return $wpdb->update(
        $wpdb->scarto_audit_log,
        ['subject_email' => null],
        ['subject_email' => $email],
        [null],
        ['%s']
    );
}

// ============================================================================
// CRON JOBS
// ============================================================================

add_action('scarto_rate_limit_cleanup', 'scarto_rate_limit_cleanup');
add_action('scarto_rate_limit_cleanup', 'scarto_cleanup_reservation_verifications', 20);
add_action('scarto_cleanup_temp_files', 'scarto_cleanup_temp_files');
add_action('scarto_check_expired_reservations', 'scarto_process_expired_reservations');

function scarto_cleanup_temp_files() {
    $files = glob(trailingslashit(get_temp_dir()) . 'scarto-reservation-*.pdf');
    foreach ($files ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - HOUR_IN_SECONDS) {
            @unlink($file);
        }
    }
    $directories = glob(trailingslashit(get_temp_dir()) . 'scarto-pdf-*', GLOB_ONLYDIR);
    foreach ($directories ?: [] as $directory) {
        if (is_dir($directory) && filemtime($directory) < time() - HOUR_IN_SECONDS) {
            foreach (glob(trailingslashit($directory) . '*.pdf') ?: [] as $pdf_file) {
                if (is_file($pdf_file)) @unlink($pdf_file);
            }
            @rmdir($directory);
        }
    }
}

function scarto_cleanup_reservation_verifications() {
    global $wpdb;
    return $wpdb->query("DELETE FROM {$wpdb->scarto_reservation_verifications} WHERE expires_at <= UTC_TIMESTAMP()");
}

function scarto_record_cleanup_status($job, $counts) {
    $status = get_option('scarto_cleanup_status', []);
    $status = is_array($status) ? $status : [];
    $status[sanitize_key($job)] = [
        'timestamp' => time(),
        'counts' => array_map('intval', is_array($counts) ? $counts : []),
    ];
    update_option('scarto_cleanup_status', $status, false);
}
function scarto_process_expired_reservations() {
    global $wpdb;
    $now = time() * 1000;
    
    $wpdb->query('START TRANSACTION');
    
    $expired = $wpdb->get_results($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders} WHERE status = 'active' AND expires_at < %d FOR UPDATE",
        $now
    ));
    
    if ($wpdb->last_error) {
        $wpdb->query('ROLLBACK');
        return;
    }
    
    if ($expired) {
        $codes = wp_list_pluck($expired, 'code');
        $ph = implode(',', array_fill(0, count($codes), '%s'));
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->scarto_orders} SET status = 'expired', updated_at = %d WHERE code IN ($ph)",
            array_merge([$now], $codes)
        ));
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->scarto_order_items} SET status = 'released' WHERE order_code IN ($ph) AND status = 'reserved'",
            $codes
        ));
        
        foreach ($codes as $code) {
            scarto_audit_log('order_expired', 'order', $code, ['auto' => true]);
        }
        scarto_invalidate_caches();
    }
    
    $wpdb->query('COMMIT');
}

add_action('scarto_cleanup_audit_logs', 'scarto_cleanup_audit_logs');
function scarto_cleanup_audit_logs() {
    global $wpdb;
    $retention_days = scarto_get_retention_days('audit_logs');
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->scarto_audit_log} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $retention_days
    ));
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->scarto_audit_log}");
    if ($count > 50000) {
        $capped = $wpdb->query("DELETE FROM {$wpdb->scarto_audit_log} ORDER BY id ASC LIMIT " . ($count - 50000));
        if ($capped !== false) $deleted += $capped;
    }
    scarto_record_cleanup_status('audit', ['deleted' => max(0, (int) $deleted)]);
}

// GDPR: Automatic data retention cleanup
add_action('scarto_gdpr_data_cleanup', 'scarto_gdpr_data_cleanup');
function scarto_gdpr_data_cleanup() {
    global $wpdb;

    $now_ms = time() * 1000;
    $retention_completed = scarto_get_retention_days('completed');
    $retention_cancelled = scarto_get_retention_days('cancelled');
    $retention_expired = scarto_get_retention_days('expired');

    // Anonymize completed orders older than retention period
    $completed_cutoff = $now_ms - ($retention_completed * 86400 * 1000);
    $anonymized = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = 'ANONIMO',
             user_cognome = 'GDPR',
             user_email = 'deleted@gdpr.local',
             user_indirizzo = 'Dati rimossi per GDPR',
             user_via = '', user_civico = '', user_cap = '', user_citta = '',
             user_provincia = '', user_note_spedizione = '',
             ip_address = NULL,
             user_agent = NULL
         WHERE status = 'completed'
         AND completed_at < %d
         AND user_email != 'deleted@gdpr.local'",
        $completed_cutoff
    ));

    $cancelled_cutoff = $now_ms - ($retention_cancelled * 86400 * 1000);
    $expired_cutoff = $now_ms - ($retention_expired * 86400 * 1000);
    
    $orders_to_delete = $wpdb->get_col($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders} 
         WHERE (status = 'cancelled' AND updated_at < %d)
            OR (status = 'expired' AND updated_at < %d)",
        $cancelled_cutoff,
        $expired_cutoff
    ));
    
    $deleted_orders = 0;
    if (!empty($orders_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($orders_to_delete), '%s'));
        
        // Delete order items first (FK constraint)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->scarto_order_items} WHERE order_code IN ($placeholders)",
            $orders_to_delete
        ));
        
        // Delete orders
        $orders_deleted_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->scarto_orders} WHERE code IN ($placeholders)",
            $orders_to_delete
        ));
        $deleted_orders = max(0, (int) $orders_deleted_result);
        
        scarto_audit_log('gdpr_auto_cleanup', null, null, [
            'deleted_orders' => count($orders_to_delete)
        ]);
    }
    
    // Clean old recovery tokens
    $recovery_deleted = $wpdb->query("DELETE FROM {$wpdb->scarto_recovery_tokens} WHERE expires_at < NOW() OR used = 1");

    // Clean old GDPR tokens
    $gdpr_tokens_deleted = $wpdb->query("DELETE FROM {$wpdb->scarto_gdpr_tokens} WHERE expires_at < NOW() OR used = 1");

    $verifications_deleted = scarto_cleanup_reservation_verifications();
    scarto_record_cleanup_status('personal_data', [
        'anonymized' => max(0, (int) $anonymized),
        'deleted' => $deleted_orders,
        'recovery_tokens_deleted' => max(0, (int) $recovery_deleted),
        'privacy_tokens_deleted' => max(0, (int) $gdpr_tokens_deleted),
        'pending_otp_deleted' => max(0, (int) $verifications_deleted),
    ]);
}

// IP Anonymization: Remove IP addresses after configurable retention period
add_action('scarto_anonymize_old_ips', 'scarto_anonymize_old_ips');
function scarto_anonymize_old_ips() {
    global $wpdb;

    $ip_retention_days = scarto_get_retention_days('ip');
    $cutoff_ms = (time() - ($ip_retention_days * 86400)) * 1000;

    // Anonymize IPs in orders older than retention period (but keep other data)
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET ip_address = NULL
         WHERE ip_address IS NOT NULL
         AND created_at < %d",
        $cutoff_ms
    ));

    // IP and User-Agent have the same retention period in the audit log.
    $audit_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_audit_log}
         SET ip_address = NULL,
             user_agent = NULL,
             details = CASE
                 WHEN action IN ('login_success', 'login_failed', 'password_recovery_requested') THEN '{}'
                 ELSE details
             END
         WHERE (ip_address IS NOT NULL OR user_agent IS NOT NULL)
         AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $ip_retention_days
    ));

    if ($updated > 0 || $audit_updated > 0) {
        scarto_audit_log('ip_anonymization', null, null, [
            'orders_anonymized' => max(0, (int) $updated),
            'audit_records_anonymized' => max(0, (int) $audit_updated),
            'retention_days' => $ip_retention_days
        ]);
    }
    scarto_record_cleanup_status('technical_data', [
        'orders_anonymized' => max(0, (int) $updated),
        'audit_records_anonymized' => max(0, (int) $audit_updated),
    ]);
}

// ============================================================================
// TEMPLATE
// ============================================================================

add_action('template_redirect', 'scarto_intercept_page_rendering');
function scarto_intercept_page_rendering() {
    global $post;
    if ($post && has_shortcode($post->post_content, 'scarto_librario')) {
        include SCARTO_PLUGIN_DIR . 'templates/app.php';
        exit;
    }
}

add_shortcode('scarto_librario', '__return_false');

// ============================================================================
// REST API ROUTES
// ============================================================================

add_action('rest_api_init', 'scarto_register_api_routes');
function scarto_register_api_routes() {
    $ns = 'scarto/v1';

    // Public endpoints: responses never change according to authentication state.
    register_rest_route($ns, '/init', ['methods' => 'GET', 'callback' => 'scarto_api_init', 'permission_callback' => '__return_true']);
    register_rest_route($ns, '/catalog', ['methods' => 'GET', 'callback' => 'scarto_api_catalog', 'permission_callback' => '__return_true', 'args' => scarto_rest_route_args('public_catalog')]);
    register_rest_route($ns, '/catalog/availability', ['methods' => 'GET', 'callback' => 'scarto_api_catalog_availability', 'permission_callback' => '__return_true']);
    register_rest_route($ns, '/books/search', ['methods' => 'GET', 'callback' => 'scarto_api_books_search', 'permission_callback' => '__return_true', 'args' => scarto_rest_route_args('books_search')]);
    register_rest_route($ns, '/settings', ['methods' => 'GET', 'callback' => 'scarto_api_get_settings', 'permission_callback' => '__return_true']);

    // Public JSON actions protected by origin checks, limits and endpoint throttling.
    register_rest_route($ns, '/reserve', ['methods' => 'POST', 'callback' => 'scarto_api_reserve', 'permission_callback' => 'scarto_verify_json_request', 'args' => scarto_rest_route_args('reserve')]);
    register_rest_route($ns, '/reserve/confirm', ['methods' => 'POST', 'callback' => 'scarto_api_confirm_reservation', 'permission_callback' => 'scarto_verify_json_request', 'args' => scarto_rest_route_args('reserve_confirm')]);
    // Staff endpoints use WordPress accounts, capabilities and REST nonces.
    register_rest_route($ns, '/status', ['methods' => 'POST', 'callback' => 'scarto_api_status', 'permission_callback' => 'scarto_verify_staff_session', 'args' => scarto_rest_route_args('status')]);
    register_rest_route($ns, '/admin/catalog', ['methods' => 'GET', 'callback' => 'scarto_api_admin_catalog', 'permission_callback' => 'scarto_verify_catalog_read', 'args' => scarto_rest_route_args('catalog')]);
    register_rest_route($ns, '/admin/settings', ['methods' => 'GET', 'callback' => 'scarto_api_get_admin_settings', 'permission_callback' => 'scarto_verify_settings_read']);
    register_rest_route($ns, '/admin/settings', ['methods' => 'POST', 'callback' => 'scarto_api_save_settings', 'permission_callback' => 'scarto_verify_settings_write', 'args' => scarto_rest_route_args('save_settings')]);
    register_rest_route($ns, '/orders', ['methods' => 'POST', 'callback' => 'scarto_api_get_orders', 'permission_callback' => 'scarto_verify_orders_access', 'args' => scarto_rest_route_args('orders')]);
    register_rest_route($ns, '/admin/reservations', ['methods' => 'POST', 'callback' => 'scarto_api_create_staff_reservation', 'permission_callback' => 'scarto_verify_staff_session', 'args' => scarto_rest_route_args('staff_reserve')]);
    register_rest_route($ns, '/admin/reservations/resend', ['methods' => 'POST', 'callback' => 'scarto_api_resend_reservation_email', 'permission_callback' => 'scarto_verify_staff_session', 'args' => scarto_rest_route_args('resend_summary')]);

    // Catalog actions require the catalog capability plus the plugin step-up password.
    register_rest_route($ns, '/books', ['methods' => 'POST', 'callback' => 'scarto_api_books_import', 'permission_callback' => 'scarto_verify_db_admin_auth', 'args' => scarto_rest_route_args('books_import')]);
    register_rest_route($ns, '/reset', ['methods' => 'POST', 'callback' => 'scarto_api_reset', 'permission_callback' => 'scarto_verify_db_admin_auth', 'args' => scarto_rest_route_args('db_password')]);
    register_rest_route($ns, '/run-cleanup', ['methods' => 'POST', 'callback' => 'scarto_api_run_cleanup', 'permission_callback' => 'scarto_verify_db_admin_auth', 'args' => scarto_rest_route_args('cleanup')]);
    // Global personal-data purge belongs to the privacy role and keeps the same step-up password.
    register_rest_route($ns, '/purge-all-data', ['methods' => 'POST', 'callback' => 'scarto_api_purge_all_data', 'permission_callback' => 'scarto_verify_privacy_db_auth', 'args' => scarto_rest_route_args('db_password')]);

    // GDPR Endpoints - Secure with email verification flow
    register_rest_route($ns, '/gdpr/privacy-info', ['methods' => 'GET', 'callback' => 'scarto_api_gdpr_privacy_info', 'permission_callback' => '__return_true']);
    register_rest_route($ns, '/gdpr/request', ['methods' => 'POST', 'callback' => 'scarto_api_gdpr_request', 'permission_callback' => 'scarto_verify_json_request', 'args' => scarto_rest_route_args('gdpr_request')]);
    register_rest_route($ns, '/gdpr/verify', ['methods' => 'POST', 'callback' => 'scarto_api_gdpr_verify', 'permission_callback' => 'scarto_verify_json_request', 'args' => scarto_rest_route_args('gdpr_verify')]);
    // Back-office GDPR tools are restricted to the dedicated privacy capability.
    register_rest_route($ns, '/gdpr/export', ['methods' => 'POST', 'callback' => 'scarto_api_gdpr_export_admin', 'permission_callback' => 'scarto_verify_privacy_access', 'args' => scarto_rest_route_args('gdpr_admin_export')]);
    register_rest_route($ns, '/gdpr/delete', ['methods' => 'POST', 'callback' => 'scarto_api_gdpr_delete_admin', 'permission_callback' => 'scarto_verify_privacy_db_auth', 'args' => scarto_rest_route_args('gdpr_admin_delete')]);
}

function scarto_verify_admin_auth($request) {
    return scarto_verify_staff_session($request);
}

function scarto_verify_privacy_db_auth($request) {
    $access = scarto_verify_wp_admin_capability($request, SCARTO_CAP_PRIVACY, true, SCARTO_ADMIN_BODY_LIMIT);
    if (is_wp_error($access)) return $access;

    $ip = scarto_get_rate_limit_ip();
    $rate_key = 'privacy_db_auth_' . get_current_user_id() . '_' . $ip;
    $max_attempts = scarto_get_rate_limit('max_login_attempts');
    $lockout_minutes = scarto_get_rate_limit('login_lockout_minutes');
    if (!scarto_rate_limit_consume($rate_key, $max_attempts, $lockout_minutes * 60)) {
        scarto_audit_log('privacy_db_auth_blocked', 'wordpress_user', (string) get_current_user_id());
        return new WP_Error('too_many_attempts', 'Troppi tentativi. Riprova più tardi.', ['status' => 429]);
    }

    $params = $request->get_json_params();
    if (!scarto_verify_password($params['password'] ?? '', get_option('scarto_db_admin_password_hash'))) {
        scarto_audit_log('privacy_db_auth_failed', 'wordpress_user', (string) get_current_user_id());
        return new WP_Error('rest_forbidden', 'Password di sicurezza errata.', ['status' => 403]);
    }

    scarto_rate_limit_reset($rate_key);
    return true;
}

function scarto_verify_db_admin_auth($request) {
    $json_check = scarto_verify_json_request($request, SCARTO_IMPORT_BODY_LIMIT);
    if (is_wp_error($json_check)) return $json_check;

    if (!is_user_logged_in() || !current_user_can(SCARTO_CAP_CATALOG)) {
        return new WP_Error('rest_forbidden', 'Autorizzazione WordPress insufficiente.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_forbidden', 'Nonce WordPress non valido.', ['status' => 403]);
    }

    $ip = scarto_get_rate_limit_ip();
    // Include the WordPress account so one staff member cannot lock out colleagues behind the same IP.
    $rate_key = 'db_admin_auth_' . get_current_user_id() . '_' . $ip;
    $max_attempts = scarto_get_rate_limit('max_login_attempts');
    $lockout_minutes = scarto_get_rate_limit('login_lockout_minutes');
    if (!scarto_rate_limit_consume($rate_key, $max_attempts, $lockout_minutes * 60)) {
        scarto_audit_log('db_admin_auth_blocked', null, null, ['ip_hash' => substr(hash('sha256', $ip), 0, 16)]);
        return new WP_Error('too_many_attempts', 'Troppi tentativi. Riprova tra ' . $lockout_minutes . ' minuti.', ['status' => 429]);
    }

    $p = $request->get_json_params();
    if (!scarto_verify_password($p['password'] ?? '', get_option('scarto_db_admin_password_hash'))) {
        scarto_audit_log('db_admin_auth_failed', null, null, ['ip_hash' => substr(hash('sha256', $ip), 0, 16)]);
        return new WP_Error('rest_forbidden', 'Password di sicurezza del plugin errata.', ['status' => 403]);
    }

    scarto_rate_limit_reset($rate_key);
    return true;
}

// ============================================================================
// API: INIT (with optional pagination)
// Security: v8.7.1 - Orders with PII only returned if admin password provided
// ============================================================================

function scarto_get_public_catalog_page($page, $per_page, $search = '') {
    global $wpdb;

    $page = max(1, (int) $page);
    $per_page = min(SCARTO_MAX_PER_PAGE, max(10, (int) $per_page));
    $where = [];
    $params = [];

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(titolo LIKE %s OR autore LIKE %s OR inventario LIKE %s OR editore LIKE %s)';
        array_push($params, $like, $like, $like, $like);
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->scarto_books} {$where_sql}";
    $total = $params
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : (int) $wpdb->get_var($count_sql);

    $offset = ($page - 1) * $per_page;
    $sql = "SELECT id, autore, titolo, editore, anno, inventario, stato
            FROM {$wpdb->scarto_books} {$where_sql}
            ORDER BY titolo, autore, id LIMIT %d OFFSET %d";
    $books = $wpdb->get_results(
        $wpdb->prepare($sql, array_merge($params, [$per_page, $offset])),
        ARRAY_A
    );

    scarto_enrich_catalog_availability($books);

    return [
        'books' => $books ?: [],
        'pagination' => [
            'page' => $page,
            'perPage' => $per_page,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $per_page)),
        ],
    ];
}

function scarto_get_admin_catalog_page($page, $per_page, $search = '', $scatola = '') {
    global $wpdb;

    $page = max(1, (int) $page);
    $per_page = min(SCARTO_MAX_PER_PAGE, max(10, (int) $per_page));
    $where = [];
    $params = [];

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(titolo LIKE %s OR autore LIKE %s OR inventario LIKE %s OR editore LIKE %s OR scatola LIKE %s)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($scatola !== '') {
        $where[] = 'scatola = %s';
        $params[] = $scatola;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->scarto_books} {$where_sql}";
    $total = $params
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : (int) $wpdb->get_var($count_sql);
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT id, scatola, autore, titolo, editore, anno, inventario, stato
            FROM {$wpdb->scarto_books} {$where_sql}
            ORDER BY scatola, titolo LIMIT %d OFFSET %d";
    $books = $wpdb->get_results(
        $wpdb->prepare($sql, array_merge($params, [$per_page, $offset])),
        ARRAY_A
    );

    scarto_enrich_catalog_availability($books);

    return [
        'books' => $books ?: [],
        'pagination' => [
            'page' => $page,
            'perPage' => $per_page,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $per_page)),
        ],
    ];
}

function scarto_enrich_catalog_availability(&$books) {
    global $wpdb;

    if (!is_array($books) || !$books) return;
    $book_ids = array_values(array_filter(array_map(static function($book) {
        return isset($book['id']) ? (string) $book['id'] : '';
    }, $books)));
    if (!$book_ids) return;

    $placeholders = implode(',', array_fill(0, count($book_ids), '%s'));
    $now = time() * 1000;
    $params = array_merge([$now], $book_ids);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.book_id,
                MAX(CASE WHEN o.status = 'active' AND oi.status = 'reserved' AND o.expires_at > %d THEN o.expires_at ELSE 0 END) AS reserved_until,
                MAX(CASE WHEN o.status = 'completed' AND oi.status = 'withdrawn' THEN 1 ELSE 0 END) AS delivered
         FROM {$wpdb->scarto_order_items} oi
         INNER JOIN {$wpdb->scarto_orders} o ON o.code = oi.order_code
         WHERE oi.book_id IN ({$placeholders})
         GROUP BY oi.book_id",
        $params
    ), ARRAY_A) ?: [];
    $state_by_id = [];
    foreach ($rows as $row) {
        $state_by_id[(string) $row['book_id']] = $row;
    }

    foreach ($books as &$book) {
        $state = $state_by_id[(string) $book['id']] ?? [];
        $reserved_until = (int) ($state['reserved_until'] ?? 0);
        $delivered = !empty($state['delivered']);
        $book['_availability'] = $delivered ? 'delivered' : ($reserved_until > $now ? 'reserved' : 'available');
        $book['_reserved'] = $book['_availability'] === 'reserved';
        $book['_delivered'] = $book['_availability'] === 'delivered';
        if ($reserved_until > $now) $book['reservedUntil'] = $reserved_until;
    }
    unset($book);
}

/**
 * Return only unavailable catalog states so clients can refresh without
 * downloading the complete catalog or replacing an in-progress form.
 */
function scarto_api_catalog_availability() {
    global $wpdb;

    $ip = scarto_get_rate_limit_ip();
    if (!scarto_rate_limit_consume('catalog_availability_' . $ip, 180, 5 * MINUTE_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }

    $now = time() * 1000;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.book_id,
                MAX(CASE WHEN o.status = 'active' AND oi.status = 'reserved' AND o.expires_at > %d THEN o.expires_at ELSE 0 END) AS reserved_until,
                MAX(CASE WHEN o.status = 'completed' AND oi.status = 'withdrawn' THEN 1 ELSE 0 END) AS delivered
         FROM {$wpdb->scarto_order_items} oi
         INNER JOIN {$wpdb->scarto_orders} o ON o.code = oi.order_code
         WHERE (o.status = 'active' AND oi.status = 'reserved' AND o.expires_at > %d)
            OR (o.status = 'completed' AND oi.status = 'withdrawn')
         GROUP BY oi.book_id",
        $now,
        $now
    ), ARRAY_A);

    if ($wpdb->last_error) {
        error_log('Scarto availability snapshot: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Errore aggiornamento disponibilita.', ['status' => 500]);
    }

    $states = [];
    foreach ($rows ?: [] as $row) {
        $reserved_until = (int) $row['reserved_until'];
        $delivered = !empty($row['delivered']);
        $state = [
            'id' => (string) $row['book_id'],
            '_availability' => $delivered ? 'delivered' : 'reserved',
        ];
        if (!$delivered && $reserved_until > $now) {
            $state['reservedUntil'] = $reserved_until;
        }
        $states[] = $state;
    }

    return scarto_public_response([
        'states' => $states,
        'serverTime' => $now,
    ]);
}

function scarto_api_init($request) {
    $ip = scarto_get_rate_limit_ip();
    if (!scarto_rate_limit_consume('catalog_init_' . $ip, 60, MINUTE_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }

    $include_catalog = (string) $request->get_param('include_catalog') !== '0';
    $catalog = $include_catalog
        ? scarto_get_public_catalog_page(1, SCARTO_MAX_PER_PAGE)
        : ['books' => [], 'pagination' => ['page' => 1, 'perPage' => 0, 'total' => 0, 'totalPages' => 0]];
    $settings = scarto_get_settings();

    $appearance = function_exists('scarto_public_appearance_payload') ? scarto_public_appearance_payload() : [];
    return scarto_public_response([
        'books' => $catalog['books'],
        'serverTime' => time() * 1000,
        'settings' => [
            'reservationDays' => (int) $settings['reservation_days'],
            'maxBooksPerReservation' => (int) $settings['max_books_per_reservation'],
            'libraryName' => $settings['library_name'],
            'libraryAddress' => $settings['library_address'],
            'libraryPhone' => $settings['library_phone'] ?? '',
            'libraryEmail' => $settings['email_from'] ?? '',
            'homepageUrl' => $settings['homepage_url'] ?? '',
            'privacyPolicyUrl' => $settings['privacy_policy_url'] ?? '',
            // Public reservations never collect domicile data.
            'collectDomicile' => false,
            'appearance' => $appearance,
        ],
        'pagination' => $catalog['pagination'],
        'apiVersion' => SCARTO_VERSION,
    ]);
}

function scarto_api_catalog($request) {
    $ip = scarto_get_rate_limit_ip();
    if (!scarto_rate_limit_consume('catalog_page_' . $ip, 180, 5 * MINUTE_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }

    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(SCARTO_MAX_PER_PAGE, max(10, (int) $request->get_param('per_page')));
    $search = scarto_sanitize_text($request->get_param('search') ?: '', 200);
    return scarto_public_response(scarto_get_public_catalog_page($page, $per_page, $search));
}

function scarto_api_admin_catalog($request) {
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(SCARTO_MAX_PER_PAGE, max(10, (int) $request->get_param('per_page')));
    $search = scarto_sanitize_text($request->get_param('search') ?: '', 200);
    $scatola = scarto_sanitize_text($request->get_param('scatola') ?: '', 100);

    return scarto_private_response(scarto_get_admin_catalog_page($page, $per_page, $search, $scatola));
}

/**
 * Internal helper: Fetch orders with full details (used by init and orders endpoints)
 */
function scarto_fetch_orders_internal() {
    global $wpdb;

    $wpdb->query("SET SESSION group_concat_max_len = 1000000");

    $orders_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT o.code, o.status, o.user_nome, o.user_cognome, o.user_email, o.user_indirizzo,
                o.user_via, o.user_civico, o.user_cap, o.user_citta, o.user_provincia, o.user_note_spedizione, o.reservation_source,
                o.created_at, o.updated_at, o.completed_at, o.expires_at,
                GROUP_CONCAT(oi.book_id) as book_ids,
                GROUP_CONCAT(CONCAT_WS('|||',
                    oi.book_id,
                    COALESCE(oi.titolo,''),
                    COALESCE(oi.autore,''),
                    COALESCE(oi.inventario,''),
                    COALESCE(NULLIF(oi.scatola,''), '')
                ) SEPARATOR ';;;') as book_details
         FROM {$wpdb->scarto_orders} o
         LEFT JOIN {$wpdb->scarto_order_items} oi ON o.code = oi.order_code
         GROUP BY o.code ORDER BY o.created_at DESC LIMIT %d",
        SCARTO_ORDERS_LIMIT
    ), ARRAY_A);

    // Collect all book IDs and inventari for enrichment
    $all_order_book_ids = [];
    $all_order_inventari = [];
    foreach ($orders_raw ?: [] as $r) {
        if (!empty($r['book_ids'])) {
            foreach (explode(',', $r['book_ids']) as $bid) {
                $all_order_book_ids[$bid] = true;
            }
        }
        if (!empty($r['book_details'])) {
            foreach (explode(';;;', $r['book_details']) as $item) {
                $parts = explode('|||', $item);
                if (count($parts) >= 4 && !empty($parts[3])) {
                    $all_order_inventari[$parts[3]] = true;
                }
            }
        }
    }

    // Fetch enrichment data
    $books_enrichment = [];
    $books_by_inventario = [];

    if (!empty($all_order_book_ids)) {
        $enrich_ids = array_keys($all_order_book_ids);
        $ph_enrich = implode(',', array_fill(0, count($enrich_ids), '%s'));
        $enrich_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scatola, inventario FROM {$wpdb->scarto_books} WHERE id IN ($ph_enrich)",
            ...$enrich_ids
        ), ARRAY_A);
        foreach ($enrich_results ?: [] as $er) {
            $books_enrichment[$er['id']] = $er;
        }
    }

    if (!empty($all_order_inventari)) {
        $inv_results = [];
        foreach (array_chunk(array_keys($all_order_inventari), 500) as $inventory_chunk) {
            $inventory_placeholders = implode(',', array_fill(0, count($inventory_chunk), '%s'));
            $chunk_results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, scatola, inventario FROM {$wpdb->scarto_books} WHERE inventario IN ({$inventory_placeholders}) AND scatola != ''",
                $inventory_chunk
            ), ARRAY_A);
            if ($chunk_results) $inv_results = array_merge($inv_results, $chunk_results);
        }
        foreach ($inv_results ?: [] as $ir) {
            if (!empty($ir['inventario'])) {
                $books_by_inventario[$ir['inventario']] = $ir;
                $normalized = preg_replace('/\.0+$/', '', trim($ir['inventario']));
                if ($normalized !== $ir['inventario']) {
                    $books_by_inventario[$normalized] = $ir;
                }
                if (is_numeric($ir['inventario'])) {
                    $int_ver = (string)intval(floatval($ir['inventario']));
                    $books_by_inventario[$int_ver] = $ir;
                }
            }
        }
    }

    // Build orders array
    $orders = [];
    foreach ($orders_raw ?: [] as $r) {
        $book_ids = $r['book_ids'] ? explode(',', $r['book_ids']) : [];

        $books_data = [];
        if (!empty($r['book_details'])) {
            $items = explode(';;;', $r['book_details']);
            foreach ($items as $item) {
                $parts = explode('|||', $item);
                if (count($parts) >= 5) {
                    $bid = $parts[0];
                    $scatola_val = $parts[4];
                    $inventario_val = $parts[3];

                    $normalized_scatola = trim((string)$scatola_val);
                    if ($normalized_scatola === '' || $normalized_scatola === '-') {
                        if (isset($books_enrichment[$bid]['scatola'])) {
                            $scatola_val = $books_enrichment[$bid]['scatola'];
                        } elseif (!empty($inventario_val)) {
                            if (isset($books_by_inventario[$inventario_val]['scatola'])) {
                                $scatola_val = $books_by_inventario[$inventario_val]['scatola'];
                            } else {
                                $inv_normalized = preg_replace('/\.0+$/', '', trim($inventario_val));
                                if (isset($books_by_inventario[$inv_normalized]['scatola'])) {
                                    $scatola_val = $books_by_inventario[$inv_normalized]['scatola'];
                                } elseif (is_numeric($inventario_val)) {
                                    $inv_int = (string)intval(floatval($inventario_val));
                                    if (isset($books_by_inventario[$inv_int]['scatola'])) {
                                        $scatola_val = $books_by_inventario[$inv_int]['scatola'];
                                    }
                                }
                            }
                        }
                    }
                    if (empty($inventario_val) && isset($books_enrichment[$bid]['inventario'])) {
                        $inventario_val = $books_enrichment[$bid]['inventario'];
                    }

                    $books_data[$bid] = [
                        'id' => $bid,
                        'titolo' => $parts[1],
                        'autore' => $parts[2],
                        'inventario' => $inventario_val,
                        'scatola' => $scatola_val
                    ];
                } elseif (count($parts) >= 4) {
                    $bid = $parts[0];
                    $scatola_val = $parts[3];
                    $inventario_val = '';

                    $normalized_scatola_old = trim((string)$scatola_val);
                    if ($normalized_scatola_old === '' || $normalized_scatola_old === '-') {
                        if (isset($books_enrichment[$bid]['scatola'])) {
                            $scatola_val = $books_enrichment[$bid]['scatola'];
                        }
                    }
                    if (isset($books_enrichment[$bid]['inventario'])) {
                        $inventario_val = $books_enrichment[$bid]['inventario'];
                    }
                    $normalized_scatola_old = trim((string)$scatola_val);
                    if (($normalized_scatola_old === '' || $normalized_scatola_old === '-') && !empty($inventario_val)) {
                        if (isset($books_by_inventario[$inventario_val]['scatola'])) {
                            $scatola_val = $books_by_inventario[$inventario_val]['scatola'];
                        } else {
                            $inv_normalized = preg_replace('/\.0+$/', '', trim($inventario_val));
                            if (isset($books_by_inventario[$inv_normalized]['scatola'])) {
                                $scatola_val = $books_by_inventario[$inv_normalized]['scatola'];
                            } elseif (is_numeric($inventario_val)) {
                                $inv_int = (string)intval(floatval($inventario_val));
                                if (isset($books_by_inventario[$inv_int]['scatola'])) {
                                    $scatola_val = $books_by_inventario[$inv_int]['scatola'];
                                }
                            }
                        }
                    }

                    $books_data[$bid] = [
                        'id' => $bid,
                        'titolo' => $parts[1],
                        'autore' => $parts[2],
                        'inventario' => $inventario_val,
                        'scatola' => $scatola_val
                    ];
                }
            }
        }

        $orders[] = [
            'code' => $r['code'],
            'status' => $r['status'],
            'userData' => [
                'nome' => $r['user_nome'],
                'cognome' => $r['user_cognome'],
                'email' => $r['user_email'],
                'indirizzo' => $r['user_indirizzo'],
                'via' => $r['user_via'],
                'civico' => $r['user_civico'],
                'cap' => $r['user_cap'],
                'citta' => $r['user_citta'],
                'provincia' => $r['user_provincia'],
                'noteSpedizione' => $r['user_note_spedizione']
            ],
            'source' => $r['reservation_source'] ?: 'online',
            'bookIds' => $book_ids,
            'booksData' => $books_data,
            'createdAt' => (int)$r['created_at'],
            'updatedAt' => $r['updated_at'] ? (int)$r['updated_at'] : null,
            'completedAt' => $r['completed_at'] ? (int)$r['completed_at'] : null,
            'expiresAt' => (int)$r['expires_at']
        ];
    }

    return $orders;
}

// ============================================================================
// API: ORDERS (Admin authenticated) - Contains PII
// Security: v8.7.1 - Orders with PII require admin authentication
// ============================================================================

function scarto_api_get_orders($request) {
    global $wpdb;

    $page = max(1, (int) $request->get_param('page'));
    $per_page = max(10, min(100, (int) $request->get_param('per_page')));
    $search = scarto_sanitize_text($request->get_param('search') ?: '', 200);
    $status = $request->get_param('status') === 'active' ? 'active' : 'all';
    $where = ['1=1'];
    $params = [];
    if ($status === 'active') {
        $where[] = 'o.status = %s';
        $params[] = 'active';
    }
    foreach (array_slice(preg_split('/\s+/', trim($search)) ?: [], 0, 8) as $term) {
        if ($term === '') continue;
        $like = '%' . $wpdb->esc_like($term) . '%';
        $where[] = "(o.code LIKE %s OR o.user_nome LIKE %s OR o.user_cognome LIKE %s
                    OR CONCAT_WS(' ', o.user_nome, o.user_cognome) LIKE %s
                    OR CONCAT_WS(' ', o.user_cognome, o.user_nome) LIKE %s
                    OR o.user_email LIKE %s
                    OR EXISTS (
                        SELECT 1 FROM {$wpdb->scarto_order_items} search_item
                        WHERE search_item.order_code = o.code
                          AND (search_item.titolo LIKE %s OR search_item.autore LIKE %s OR search_item.inventario LIKE %s)
                    ))";
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->scarto_orders} o WHERE {$where_sql}";
    $total = (int) ($params
        ? $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : $wpdb->get_var($count_sql));
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $total_pages);

    // Increase group_concat_max_len to avoid truncation of book lists
    $wpdb->query("SET SESSION group_concat_max_len = 1000000");

    // JOIN with scarto_books to get scatola directly
    $orders_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT o.code, o.status, o.user_nome, o.user_cognome, o.user_email, o.user_indirizzo,
                o.user_via, o.user_civico, o.user_cap, o.user_citta, o.user_provincia, o.user_note_spedizione, o.reservation_source,
                o.created_at, o.updated_at, o.completed_at, o.expires_at,
                GROUP_CONCAT(oi.book_id) as book_ids,
                GROUP_CONCAT(CONCAT_WS('|||',
                    oi.book_id,
                    COALESCE(oi.titolo,''),
                    COALESCE(oi.autore,''),
                    COALESCE(oi.inventario,''),
                    COALESCE(NULLIF(oi.scatola,''), '')
                ) SEPARATOR ';;;') as book_details
         FROM {$wpdb->scarto_orders} o
         LEFT JOIN {$wpdb->scarto_order_items} oi ON o.code = oi.order_code
         WHERE {$where_sql}
         GROUP BY o.code ORDER BY o.created_at DESC LIMIT %d OFFSET %d",
        array_merge($params, [$per_page, ($page - 1) * $per_page])
    ), ARRAY_A);

    // Collect all book IDs and inventari from orders to enrich missing scatola values
    $all_order_book_ids = [];
    $all_order_inventari = [];
    foreach ($orders_raw ?: [] as $r) {
        if (!empty($r['book_ids'])) {
            foreach (explode(',', $r['book_ids']) as $bid) {
                $all_order_book_ids[$bid] = true;
            }
        }
        if (!empty($r['book_details'])) {
            foreach (explode(';;;', $r['book_details']) as $item) {
                $parts = explode('|||', $item);
                if (count($parts) >= 4 && !empty($parts[3])) {
                    $all_order_inventari[$parts[3]] = true;
                }
            }
        }
    }

    // Fetch scatola/inventario from books table for enrichment
    $books_enrichment = [];
    $books_by_inventario = [];

    if (!empty($all_order_book_ids)) {
        $enrich_ids = array_keys($all_order_book_ids);
        $ph_enrich = implode(',', array_fill(0, count($enrich_ids), '%s'));
        $enrich_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scatola, inventario FROM {$wpdb->scarto_books} WHERE id IN ($ph_enrich)",
            ...$enrich_ids
        ), ARRAY_A);
        foreach ($enrich_results ?: [] as $er) {
            $books_enrichment[$er['id']] = $er;
        }
    }

    if (!empty($all_order_inventari)) {
        $inv_results = [];
        foreach (array_chunk(array_keys($all_order_inventari), 500) as $inventory_chunk) {
            $inventory_placeholders = implode(',', array_fill(0, count($inventory_chunk), '%s'));
            $chunk_results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, scatola, inventario FROM {$wpdb->scarto_books} WHERE inventario IN ({$inventory_placeholders}) AND scatola != ''",
                $inventory_chunk
            ), ARRAY_A);
            if ($chunk_results) $inv_results = array_merge($inv_results, $chunk_results);
        }
        foreach ($inv_results ?: [] as $ir) {
            if (!empty($ir['inventario'])) {
                $books_by_inventario[$ir['inventario']] = $ir;
                $normalized = preg_replace('/\.0+$/', '', trim($ir['inventario']));
                if ($normalized !== $ir['inventario']) {
                    $books_by_inventario[$normalized] = $ir;
                }
                if (is_numeric($ir['inventario'])) {
                    $int_ver = (string)intval(floatval($ir['inventario']));
                    $books_by_inventario[$int_ver] = $ir;
                }
            }
        }
    }

    $orders = [];
    foreach ($orders_raw ?: [] as $r) {
        $book_ids = $r['book_ids'] ? explode(',', $r['book_ids']) : [];

        $books_data = [];
        if (!empty($r['book_details'])) {
            $items = explode(';;;', $r['book_details']);
            foreach ($items as $item) {
                $parts = explode('|||', $item);
                if (count($parts) >= 5) {
                    $bid = $parts[0];
                    $scatola_val = $parts[4];
                    $inventario_val = $parts[3];

                    $normalized_scatola = trim((string)$scatola_val);
                    if ($normalized_scatola === '' || $normalized_scatola === '-') {
                        if (isset($books_enrichment[$bid]['scatola'])) {
                            $scatola_val = $books_enrichment[$bid]['scatola'];
                        } elseif (!empty($inventario_val)) {
                            if (isset($books_by_inventario[$inventario_val]['scatola'])) {
                                $scatola_val = $books_by_inventario[$inventario_val]['scatola'];
                            } else {
                                $inv_normalized = preg_replace('/\.0+$/', '', trim($inventario_val));
                                if (isset($books_by_inventario[$inv_normalized]['scatola'])) {
                                    $scatola_val = $books_by_inventario[$inv_normalized]['scatola'];
                                } elseif (is_numeric($inventario_val)) {
                                    $inv_int = (string)intval(floatval($inventario_val));
                                    if (isset($books_by_inventario[$inv_int]['scatola'])) {
                                        $scatola_val = $books_by_inventario[$inv_int]['scatola'];
                                    }
                                }
                            }
                        }
                    }
                    if (empty($inventario_val) && isset($books_enrichment[$bid]['inventario'])) {
                        $inventario_val = $books_enrichment[$bid]['inventario'];
                    }

                    $books_data[$bid] = [
                        'id' => $bid,
                        'titolo' => $parts[1],
                        'autore' => $parts[2],
                        'inventario' => $inventario_val,
                        'scatola' => $scatola_val
                    ];
                } elseif (count($parts) >= 4) {
                    $bid = $parts[0];
                    $scatola_val = $parts[3];
                    $inventario_val = '';

                    $normalized_scatola_old = trim((string)$scatola_val);
                    if ($normalized_scatola_old === '' || $normalized_scatola_old === '-') {
                        if (isset($books_enrichment[$bid]['scatola'])) {
                            $scatola_val = $books_enrichment[$bid]['scatola'];
                        }
                    }
                    if (isset($books_enrichment[$bid]['inventario'])) {
                        $inventario_val = $books_enrichment[$bid]['inventario'];
                    }
                    $normalized_scatola_old = trim((string)$scatola_val);
                    if (($normalized_scatola_old === '' || $normalized_scatola_old === '-') && !empty($inventario_val)) {
                        if (isset($books_by_inventario[$inventario_val]['scatola'])) {
                            $scatola_val = $books_by_inventario[$inventario_val]['scatola'];
                        } else {
                            $inv_normalized = preg_replace('/\.0+$/', '', trim($inventario_val));
                            if (isset($books_by_inventario[$inv_normalized]['scatola'])) {
                                $scatola_val = $books_by_inventario[$inv_normalized]['scatola'];
                            } elseif (is_numeric($inventario_val)) {
                                $inv_int = (string)intval(floatval($inventario_val));
                                if (isset($books_by_inventario[$inv_int]['scatola'])) {
                                    $scatola_val = $books_by_inventario[$inv_int]['scatola'];
                                }
                            }
                        }
                    }

                    $books_data[$bid] = [
                        'id' => $bid,
                        'titolo' => $parts[1],
                        'autore' => $parts[2],
                        'inventario' => $inventario_val,
                        'scatola' => $scatola_val
                    ];
                }
            }
        }

        $orders[] = [
            'code' => $r['code'],
            'status' => $r['status'],
            'userData' => [
                'nome' => $r['user_nome'],
                'cognome' => $r['user_cognome'],
                'email' => $r['user_email'],
                'indirizzo' => $r['user_indirizzo'],
                'via' => $r['user_via'],
                'civico' => $r['user_civico'],
                'cap' => $r['user_cap'],
                'citta' => $r['user_citta'],
                'provincia' => $r['user_provincia'],
                'noteSpedizione' => $r['user_note_spedizione']
            ],
            'source' => $r['reservation_source'] ?: 'online',
            'bookIds' => $book_ids,
            'booksData' => $books_data,
            'createdAt' => (int)$r['created_at'],
            'updatedAt' => $r['updated_at'] ? (int)$r['updated_at'] : null,
            'completedAt' => $r['completed_at'] ? (int)$r['completed_at'] : null,
            'expiresAt' => (int)$r['expires_at']
        ];
    }

    scarto_audit_log('orders_accessed', null, null, [
        'count' => count($orders),
        'page' => $page,
        'search' => $search !== '',
        'status' => $status,
    ]);

    return rest_ensure_response([
        'orders' => $orders,
        'serverTime' => time() * 1000,
        'pagination' => [
            'page' => $page,
            'perPage' => $per_page,
            'total' => $total,
            'totalPages' => $total_pages,
        ],
    ]);
}

function scarto_api_books_search($request) {
    global $wpdb;
    if (!scarto_rate_limit_consume('book_search_' . scarto_get_rate_limit_ip(), 120, 5 * MINUTE_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }
    $q = scarto_sanitize_text($request->get_param('q') ?: '', 200);
    $limit = min(50, max(5, intval($request->get_param('limit') ?: 20)));
    
    $q_len = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
    if ($q_len < 2) return rest_ensure_response(['results' => []]);
    
    $like = '%' . $wpdb->esc_like($q) . '%';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, titolo, autore, inventario FROM {$wpdb->scarto_books}
         WHERE titolo LIKE %s OR autore LIKE %s OR inventario LIKE %s
         ORDER BY CASE WHEN titolo LIKE %s THEN 0 ELSE 1 END, titolo LIMIT %d",
        $like, $like, $like, $q.'%', $limit
    ), ARRAY_A);
    
    return scarto_public_response(['results' => $results ?: []]);
}

// ============================================================================
// API: AUTH
// ============================================================================

function scarto_api_login($request) {
    $ip = scarto_get_rate_limit_ip();
    $key = 'login_' . $ip;
    $max_attempts = scarto_get_rate_limit('max_login_attempts');
    $lockout_minutes = scarto_get_rate_limit('login_lockout_minutes');

    if (!scarto_rate_limit_consume($key, $max_attempts, $lockout_minutes * 60)) {
        return new WP_Error('too_many_attempts', 'Troppi tentativi. Riprova tra ' . $lockout_minutes . ' minuti.', ['status' => 429]);
    }

    $p = $request->get_json_params();
    if (scarto_verify_password($p['password'] ?? '', get_option('scarto_admin_password_hash'))) {
        scarto_rate_limit_reset($key);
        $session = scarto_create_staff_session();
        scarto_audit_log('login_success', 'session', $session['session_id']);
        return scarto_private_response([
            'success' => true,
            'csrf' => $session['csrf'],
            'mustChangePassword' => (bool) get_option('scarto_password_must_change'),
        ]);
    }

    scarto_audit_log('login_failed');
    return new WP_Error('auth', 'Password errata', ['status' => 401]);
}

function scarto_api_session($request) {
    $session = scarto_get_staff_session($request, false);
    if (is_wp_error($session)) {
        return scarto_private_response(['authenticated' => false], 200);
    }

    return scarto_private_response([
        'authenticated' => true,
        'csrf' => $session['data']['csrf'],
        'mustChangePassword' => (bool) get_option('scarto_password_must_change'),
    ]);
}

function scarto_api_logout($request) {
    $session = scarto_get_staff_session($request, true);
    $session_id = is_wp_error($session) ? null : ($session['data']['session_id'] ?? null);
    scarto_destroy_staff_session();
    scarto_audit_log('logout', 'session', $session_id);
    return scarto_private_response(['success' => true]);
}

function scarto_api_recover_password($request) {
    global $wpdb;
    $ip = scarto_get_rate_limit_ip();
    $key = 'recover_' . $ip;
    
    if (!scarto_rate_limit_consume($key, 1, SCARTO_DEFAULT_RECOVERY_COOLDOWN_MINUTES * 60)
        || !scarto_rate_limit_consume('recover_global', 20, HOUR_IN_SECONDS)
    ) {
        return new WP_Error('rate_limit', 'Email già inviata. Riprova più tardi.', ['status' => 429]);
    }
    
    $token = bin2hex(random_bytes(32));
    $wpdb->insert($wpdb->scarto_recovery_tokens, ['token' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)], ['%s', '%s']);
    $wpdb->query("DELETE FROM {$wpdb->scarto_recovery_tokens} WHERE expires_at < NOW() OR used = 1");
    
    $settings = scarto_get_settings();
    $body = "RECUPERO PASSWORD\n=====================================\n\nIl tuo codice: $token\n\nValido per 1 ora.\n\n" . $settings['library_name'];
    
    if (wp_mail($settings['email_to'], 'Recupero Password - Scarto Librario', $body, ['From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>'])) {
        scarto_audit_log('password_recovery_requested');
        return rest_ensure_response(['success' => true]);
    }
    return new WP_Error('email_error', 'Errore invio email.', ['status' => 500]);
}

function scarto_api_reset_password($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $token = $p['token'] ?? '';
    $new_pass = $p['newPassword'] ?? '';
    
    if (strlen($token) !== 64) return new WP_Error('bad_request', 'Codice non valido.', ['status' => 400]);
    if (!scarto_rate_limit_consume('password_reset_' . scarto_get_rate_limit_ip(), 20, HOUR_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }
    if (strlen($new_pass) < SCARTO_MIN_PASSWORD_LENGTH) return new WP_Error('bad_request', 'Password troppo corta.', ['status' => 400]);
    if (strlen($new_pass) > SCARTO_MAX_PASSWORD_LENGTH) return new WP_Error('bad_request', 'Password troppo lunga.', ['status' => 400]);
    if (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        return new WP_Error('bad_request', 'Servono maiuscole, minuscole e numeri.', ['status' => 400]);
    }
    
    $valid = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->scarto_recovery_tokens} WHERE token = %s AND expires_at > NOW() AND used = 0",
        hash('sha256', $token)
    ));
    
    if (!$valid) {
        scarto_audit_log('password_reset_invalid_token', null, null, []);
        return new WP_Error('invalid_token', 'Codice non valido o scaduto.', ['status' => 400]);
    }
    
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_recovery_tokens} SET used = 1 WHERE id = %d AND used = 0",
        $valid->id
    ));
    if ($claimed !== 1) {
        return new WP_Error('invalid_token', 'Codice non valido o già usato.', ['status' => 400]);
    }
    update_option('scarto_admin_password_hash', password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]));
    delete_option('scarto_password_must_change');
    scarto_invalidate_all_staff_sessions();
    scarto_audit_log('password_reset_success', null, null, []);
    
    return rest_ensure_response(['success' => true]);
}

function scarto_api_change_password($request) {
    $p = $request->get_json_params();
    $new_pass = $p['newPassword'] ?? '';
    
    if (strlen($new_pass) > SCARTO_MAX_PASSWORD_LENGTH) return new WP_Error('bad_request', 'Password troppo lunga.', ['status' => 400]);
    if (strlen($new_pass) < SCARTO_MIN_PASSWORD_LENGTH) return new WP_Error('bad_request', 'Password troppo corta (min 12).', ['status' => 400]);
    if (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        return new WP_Error('bad_request', 'Servono maiuscole, minuscole e numeri.', ['status' => 400]);
    }
    
    update_option('scarto_admin_password_hash', password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]));
    delete_option('scarto_password_must_change');
    delete_transient('scarto_initial_password');
    scarto_invalidate_all_staff_sessions();
    scarto_audit_log('password_changed', null, null, []);
    
    return rest_ensure_response(['success' => true]);
}

// ============================================================================
// API: RESERVE (email verification followed by atomic creation)
// ============================================================================

function scarto_format_shipping_address($user) {
    if (!empty($user['via']) || !empty($user['civico']) || !empty($user['cap']) || !empty($user['citta']) || !empty($user['provincia'])) {
        $address = trim(($user['via'] ?? '') . ' ' . ($user['civico'] ?? ''));
        $address .= ', ' . trim(($user['cap'] ?? '') . ' ' . ($user['citta'] ?? ''));
        if (!empty($user['provincia'])) $address .= ' (' . strtoupper($user['provincia']) . ')';
        if (!empty($user['noteSpedizione'])) $address .= ' - Note di spedizione: ' . $user['noteSpedizione'];
        return scarto_substr($address, 0, SCARTO_MAX_ADDRESS_LENGTH);
    }
    return scarto_substr(scarto_sanitize_text($user['indirizzo'] ?? '', SCARTO_MAX_ADDRESS_LENGTH), 0, SCARTO_MAX_ADDRESS_LENGTH);
}

function scarto_prepare_reservation_user($user_data, $staff_created = false) {
    if (!is_array($user_data) || empty($user_data['nome']) || empty($user_data['cognome'])) {
        return new WP_Error('bad_request', 'Dati utente incompleti.', ['status' => 400]);
    }

    $raw_email = trim((string) ($user_data['email'] ?? ''));
    if (!$staff_created && $raw_email === '') {
        return new WP_Error('bad_request', 'L\'indirizzo email è obbligatorio per le prenotazioni online.', ['status' => 400]);
    }
    $email = $raw_email === '' ? '' : sanitize_email($raw_email);
    if ($raw_email !== '' && (!$email || !is_email($email))) {
        return new WP_Error('bad_request', 'Email non valida.', ['status' => 400]);
    }

    $structured_keys = ['via', 'civico', 'cap', 'citta', 'provincia'];
    $has_structured_address = false;
    foreach ($structured_keys as $key) {
        if (trim((string) ($user_data[$key] ?? '')) !== '') $has_structured_address = true;
    }

    $user = [
        'nome' => scarto_sanitize_text($user_data['nome'], SCARTO_MAX_NAME_LENGTH),
        'cognome' => scarto_sanitize_text($user_data['cognome'], SCARTO_MAX_NAME_LENGTH),
        'email' => scarto_substr(strtolower($email), 0, SCARTO_MAX_EMAIL_LENGTH),
        'via' => scarto_sanitize_text($user_data['via'] ?? '', SCARTO_MAX_STREET_LENGTH),
        'civico' => scarto_sanitize_text($user_data['civico'] ?? '', SCARTO_MAX_STREET_NUMBER_LENGTH),
        'cap' => preg_replace('/\D+/', '', (string) ($user_data['cap'] ?? '')),
        'citta' => scarto_sanitize_text($user_data['citta'] ?? '', SCARTO_MAX_CITY_LENGTH),
        'provincia' => strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($user_data['provincia'] ?? ''))),
        'noteSpedizione' => scarto_sanitize_text($user_data['noteSpedizione'] ?? '', SCARTO_MAX_SHIPPING_NOTES_LENGTH),
    ];

    // Online reservations always use the verified email. Staff reservations use
    // email when supplied; in both cases address fields are discarded server-side.
    if (!$staff_created || $user['email'] !== '') {
        $user['via'] = '';
        $user['civico'] = '';
        $user['cap'] = '';
        $user['citta'] = '';
        $user['provincia'] = '';
        $user['noteSpedizione'] = '';
        $user['indirizzo'] = '';
        return $user;
    }

    // A staff reservation without email needs a complete postal address so the
    // protocol document can be delivered by post.
    if ($has_structured_address) {
        if ($user['via'] === '' || $user['civico'] === '' || !preg_match('/^[0-9]{5}$/', $user['cap'])
            || $user['citta'] === '' || !preg_match('/^[A-Z]{2}$/', $user['provincia'])) {
            return new WP_Error('bad_request', 'Via, numero civico, CAP, città e provincia sono obbligatori.', ['status' => 400]);
        }
    } elseif (empty($user_data['indirizzo'])) {
        return new WP_Error('bad_request', 'Inserire un indirizzo email valido oppure il domicilio completo.', ['status' => 400]);
    }

    $user['indirizzo'] = scarto_format_shipping_address($has_structured_address ? $user : $user_data);
    return $user;
}

function scarto_prepare_reservation_payload($params, $staff_created = false) {
    $details = $params['booksDetails'] ?? [];
    $user_data = $params['userData'] ?? [];
    $consent = $params['consent'] ?? [];
    $settings = scarto_get_settings();
    $max_books = (int) $settings['max_books_per_reservation'];

    if (!is_array($details) || empty($details)) {
        return new WP_Error('bad_request', 'Nessun libro selezionato.', ['status' => 400]);
    }
    if (!$staff_created && count($details) > $max_books) {
        return new WP_Error('limit_exceeded', "Massimo $max_books libri.", ['status' => 400]);
    }
    if (!is_array($consent) || empty($consent['accepted'])) {
        return new WP_Error('consent_required', 'Presa visione dell\'informativa richiesta.', ['status' => 400]);
    }

    $user = scarto_prepare_reservation_user($user_data, $staff_created);
    if (is_wp_error($user)) return $user;

    $book_ids = [];
    foreach ($details as $b) {
        $id = isset($b['id']) ? scarto_sanitize_text($b['id'], 50) : '';
        if ($id && !in_array($id, $book_ids, true)) $book_ids[] = $id;
    }
    if (empty($book_ids)) {
        return new WP_Error('bad_request', 'Nessun libro valido.', ['status' => 400]);
    }

    return [
        'bookIds' => $book_ids,
        'userData' => $user,
        'privacyVersion' => scarto_sanitize_text($consent['privacyVersion'] ?? SCARTO_VERSION, 20),
        'source' => $staff_created ? 'in_person' : 'online',
    ];
}

function scarto_load_reservable_books($book_ids, $lock = false) {
    global $wpdb;

    $placeholders = implode(',', array_fill(0, count($book_ids), '%s'));
    $lock_clause = $lock ? ' FOR UPDATE' : '';
    $books = $wpdb->get_results($wpdb->prepare(
        "SELECT id, scatola, inventario, titolo, autore
         FROM {$wpdb->scarto_books}
         WHERE id IN ($placeholders)
         ORDER BY id{$lock_clause}",
        ...$book_ids
    ), ARRAY_A);

    if ($wpdb->last_error) {
        error_log('Scarto book lookup error: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Errore database.', ['status' => 500]);
    }
    if (count($books ?: []) !== count($book_ids)) {
        return new WP_Error('invalid_books', 'Alcuni libri non esistono.', ['status' => 400]);
    }

    $lookup = [];
    foreach ($books as $book) {
        $lookup[$book['id']] = $book;
    }

    $availability_params = array_merge($book_ids, [time() * 1000]);
    $reserved = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT oi.book_id
         FROM {$wpdb->scarto_order_items} oi
         INNER JOIN {$wpdb->scarto_orders} o ON oi.order_code = o.code
         WHERE oi.book_id IN ($placeholders)
           AND (
               (o.status = 'active' AND oi.status = 'reserved' AND o.expires_at > %d)
               OR (o.status = 'completed' AND oi.status = 'withdrawn')
           )",
        ...$availability_params
    ));
    if ($wpdb->last_error) {
        error_log('Scarto availability lookup error: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Errore database.', ['status' => 500]);
    }
    if ($reserved) {
        $unavailable_books = [];
        foreach ($reserved as $book_id) {
            if (!isset($lookup[$book_id])) continue;
            $unavailable_books[] = [
                'id' => $book_id,
                'titolo' => scarto_sanitize_text($lookup[$book_id]['titolo'], 1000),
                'autore' => scarto_sanitize_text($lookup[$book_id]['autore'], 500),
                'inventario' => scarto_sanitize_text($lookup[$book_id]['inventario'], 100),
            ];
        }
        return new WP_Error(
            'conflict',
            count($unavailable_books) === 1
                ? 'Un libro nel carrello non è più disponibile.'
                : 'Alcuni libri nel carrello non sono più disponibili.',
            [
                'status' => 409,
                'unavailableBooks' => $unavailable_books,
            ]
        );
    }

    return $lookup;
}

function scarto_reservation_verification_hash($request_id, $verification_code) {
    return hash_hmac('sha256', $request_id . ':' . $verification_code, wp_salt('auth'));
}

function scarto_encrypt_reservation_payload($plaintext) {
    if (!function_exists('openssl_encrypt')) {
        return new WP_Error('crypto_unavailable', 'Cifratura temporanea non disponibile.', ['status' => 500]);
    }

    $iv = random_bytes(12);
    $tag = '';
    $key = hash('sha256', wp_salt('secure_auth'), true);
    $ciphertext = openssl_encrypt(
        (string) $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'scarto-reservation-v1',
        16
    );
    if ($ciphertext === false || strlen($tag) !== 16) {
        return new WP_Error('encryption_failed', 'Impossibile proteggere la richiesta.', ['status' => 500]);
    }

    return base64_encode($iv . $tag . $ciphertext);
}

function scarto_decrypt_reservation_payload($encoded) {
    if (!function_exists('openssl_decrypt')) return false;

    $packed = base64_decode((string) $encoded, true);
    if ($packed === false || strlen($packed) < 29) return false;

    $iv = substr($packed, 0, 12);
    $tag = substr($packed, 12, 16);
    $ciphertext = substr($packed, 28);
    $key = hash('sha256', wp_salt('secure_auth'), true);

    return openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'scarto-reservation-v1'
    );
}

function scarto_api_reserve($request) {
    global $wpdb;

    $storage_check = scarto_verify_transaction_storage();
    if (is_wp_error($storage_check)) return $storage_check;

    $payload = scarto_prepare_reservation_payload($request->get_json_params());
    if (is_wp_error($payload)) return $payload;

    $ip = scarto_get_client_ip();
    $rate_limit_ip = scarto_get_rate_limit_ip($ip);
    $email = strtolower($payload['userData']['email']);
    if (scarto_get_email_blocklist_entry($email) || scarto_get_subject_processing_restriction($email)) {
        scarto_audit_log('reservation_blocklist_rejected', 'email', null, [], [
            'subject_email' => $email,
            'outcome' => 'blocked',
            'category' => 'security',
        ]);
        return scarto_reservation_blocked_error();
    }
    $books = scarto_load_reservable_books($payload['bookIds']);
    if (is_wp_error($books)) return $books;

    // OTP throttling is independent from completed-reservation limits. Versioned keys
    // prevent obsolete counters from locking users after a plugin upgrade.
    $email_exempt = scarto_is_email_rate_limit_exempt($email);
    $max_email_otp = max(6, min(20, scarto_get_rate_limit('max_reservations_per_email') * 3));
    $global_allowed = scarto_rate_limit_consume('reserve_verify_v2_global', 300, HOUR_IN_SECONDS);
    $ip_allowed = scarto_rate_limit_consume('reserve_verify_v2_ip_' . $rate_limit_ip, 30, HOUR_IN_SECONDS);
    $email_allowed = $email_exempt
        || scarto_rate_limit_consume('reserve_verify_v2_email_' . $email, $max_email_otp, HOUR_IN_SECONDS);
    if (!$global_allowed || !$ip_allowed || !$email_allowed) {
        scarto_audit_log('reservation_verification_rate_limited', 'email', null, [
            'global_allowed' => $global_allowed,
            'ip_allowed' => $ip_allowed,
            'email_allowed' => $email_allowed,
            'email_exempt' => $email_exempt,
        ], ['subject_email' => $email, 'outcome' => 'blocked']);
        return new WP_Error(
            'rate_limit',
            'Limite di invio dei codici raggiunto. Attendi fino a un\'ora prima di richiedere un nuovo codice.',
            ['status' => 429]
        );
    }

    scarto_cleanup_reservation_verifications();
    $request_id = bin2hex(random_bytes(16));
    $payload['requestId'] = $request_id;
    $verification_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = gmdate('Y-m-d H:i:s', time() + SCARTO_RESERVATION_VERIFICATION_EXPIRY_MINUTES * MINUTE_IN_SECONDS);
    $encoded_payload = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($encoded_payload === false) {
        return new WP_Error('server_error', 'Impossibile preparare la richiesta.', ['status' => 500]);
    }
    $protected_payload = scarto_encrypt_reservation_payload($encoded_payload);
    if (is_wp_error($protected_payload)) return $protected_payload;

    $inserted = $wpdb->insert(
        $wpdb->scarto_reservation_verifications,
        [
            'request_id' => $request_id,
            'token_hash' => scarto_reservation_verification_hash($request_id, $verification_code),
            'email_hash' => scarto_email_lookup_hash($email),
            'payload' => $protected_payload,
            'attempts' => 0,
            'expires_at' => $expires_at,
            'used' => 0,
        ],
        ['%s', '%s', '%s', '%s', '%d', '%s', '%d']
    );
    if ($inserted === false) {
        error_log('Scarto verification insert: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Impossibile avviare la verifica.', ['status' => 500]);
    }

    $settings = scarto_get_settings();
    $subject = 'Codice di verifica prenotazione - ' . $settings['library_name'];
    $body = "È stata richiesta una prenotazione a tuo nome.\n\n";
    $body .= "Codice di verifica: {$verification_code}\n\n";
    $body .= 'Il codice scade tra ' . SCARTO_RESERVATION_VERIFICATION_EXPIRY_MINUTES . " minuti.\n";
    $body .= "Se non hai effettuato la richiesta, ignora questo messaggio: nessun libro è stato prenotato.\n";
    $headers = ['From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>'];

    if (!scarto_send_mail_with_status($email, $subject, $body, $headers, [], 'reservation_otp')) {
        $wpdb->delete($wpdb->scarto_reservation_verifications, ['request_id' => $request_id], ['%s']);
        scarto_audit_log('reservation_verification_email_failed', 'verification', $request_id, [
            'email_hash' => scarto_email_fingerprint($email),
        ], ['subject_email' => $email, 'outcome' => 'failed', 'category' => 'email']);
        return new WP_Error('email_delivery_failed', 'Invio del codice non riuscito. Riprova più tardi.', ['status' => 503]);
    }

    scarto_audit_log('reservation_verification_requested', 'verification', $request_id, [
        'books' => count($payload['bookIds']),
        'email_hash' => scarto_email_fingerprint($email),
        'email_exempt' => $email_exempt,
    ], ['subject_email' => $email, 'outcome' => 'success']);

    return scarto_private_response([
        'success' => true,
        'verificationRequired' => true,
        'requestId' => $request_id,
        'expiresIn' => SCARTO_RESERVATION_VERIFICATION_EXPIRY_MINUTES * MINUTE_IN_SECONDS,
    ], 202);
}

function scarto_api_confirm_reservation($request) {
    global $wpdb;

    $params = $request->get_json_params();
    $request_id = strtolower(scarto_sanitize_text($params['requestId'] ?? '', 32));
    $verification_code = scarto_sanitize_text($params['verificationCode'] ?? '', 6);
    $ip = scarto_get_rate_limit_ip();

    if (!scarto_rate_limit_consume('reserve_confirm_v2_ip_' . $ip, 60, HOUR_IN_SECONDS)) {
        scarto_audit_log('reservation_verification_rate_limited', 'verification', $request_id, [], [
            'outcome' => 'blocked',
        ]);
        return new WP_Error('rate_limit', 'Troppe verifiche dalla stessa connessione. Riprova più tardi.', ['status' => 429]);
    }

    $storage_check = scarto_verify_transaction_storage();
    if (is_wp_error($storage_check)) return $storage_check;

    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT id, request_id, token_hash, payload, attempts, used
         FROM {$wpdb->scarto_reservation_verifications}
         WHERE request_id = %s
           AND expires_at > UTC_TIMESTAMP()
           AND attempts < %d",
        $request_id,
        SCARTO_RESERVATION_VERIFICATION_MAX_ATTEMPTS
    ));
    if (!$record) {
        scarto_audit_log('reservation_verification_invalid_or_expired', 'verification', $request_id, [], [
            'outcome' => 'failed',
        ]);
        return new WP_Error('invalid_verification', 'Codice non valido o scaduto.', ['status' => 400]);
    }

    $decrypted_payload = scarto_decrypt_reservation_payload($record->payload);
    $payload = is_string($decrypted_payload) ? json_decode($decrypted_payload, true) : null;
    $audit_email = is_array($payload) ? strtolower(sanitize_email((string) ($payload['userData']['email'] ?? ''))) : '';

    $provided_hash = scarto_reservation_verification_hash($request_id, $verification_code);
    if (!hash_equals($record->token_hash, $provided_hash)) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->scarto_reservation_verifications}
             SET attempts = attempts + 1
             WHERE id = %d AND used = 0 AND attempts < %d",
            $record->id,
            SCARTO_RESERVATION_VERIFICATION_MAX_ATTEMPTS
        ));
        $remaining_attempts = max(0, SCARTO_RESERVATION_VERIFICATION_MAX_ATTEMPTS - ((int) $record->attempts + 1));
        scarto_audit_log('reservation_verification_failed', 'verification', $request_id, [
            'remaining_attempts' => $remaining_attempts,
        ], ['subject_email' => $audit_email, 'outcome' => 'failed']);
        return new WP_Error(
            'invalid_verification',
            $remaining_attempts > 0
                ? 'Codice non valido. Tentativi rimasti: ' . $remaining_attempts . '.'
                : 'Codice bloccato dopo troppi tentativi. Richiedi un nuovo codice.',
            ['status' => 400]
        );
    }

    if ((int) $record->used === 1) {
        return scarto_get_existing_reservation_response($request_id);
    }

    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_reservation_verifications}
         SET used = 1, attempts = attempts + 1
         WHERE id = %d
           AND used = 0
           AND expires_at > UTC_TIMESTAMP()
           AND attempts < %d",
        $record->id,
        SCARTO_RESERVATION_VERIFICATION_MAX_ATTEMPTS
    ));
    if ($claimed !== 1) {
        return scarto_get_existing_reservation_response($request_id);
    }

    if (!is_array($payload)) {
        $wpdb->delete($wpdb->scarto_reservation_verifications, ['id' => $record->id], ['%d']);
        return new WP_Error('server_error', 'Richiesta di verifica non valida.', ['status' => 500]);
    }

    $result = scarto_create_verified_reservation($payload);
    if (is_wp_error($result)) {
        $error_data = $result->get_error_data();
        $status = is_array($error_data) ? (int) ($error_data['status'] ?? 500) : 500;
        if ($status >= 500) {
            $wpdb->update(
                $wpdb->scarto_reservation_verifications,
                ['used' => 0],
                ['id' => $record->id],
                ['%d'],
                ['%d']
            );
        } else {
            $wpdb->delete($wpdb->scarto_reservation_verifications, ['id' => $record->id], ['%d']);
        }
        scarto_audit_log('reservation_confirmation_rejected', 'verification', $request_id, [
            'error_code' => $result->get_error_code(),
        ], ['subject_email' => $audit_email, 'outcome' => 'failed']);
        return $result;
    }

    scarto_audit_log('reservation_verification_confirmed', 'verification', $request_id, [], [
        'subject_email' => $audit_email,
        'outcome' => 'success',
    ]);

    return $result;
}

function scarto_create_verified_reservation($payload, $staff_created = false) {
    global $wpdb;

    $book_ids = isset($payload['bookIds']) && is_array($payload['bookIds']) ? array_values(array_unique($payload['bookIds'])) : [];
    $user = isset($payload['userData']) && is_array($payload['userData']) ? $payload['userData'] : [];
    $request_id = strtolower(scarto_sanitize_text($payload['requestId'] ?? '', 32));
    if (!$book_ids || (!$staff_created && empty($user['email'])) || !preg_match('/^[a-f0-9]{32}$/', $request_id)) {
        return new WP_Error('bad_request', 'Dati di prenotazione non validi.', ['status' => 400]);
    }

    $existing_code = $wpdb->get_var($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders} WHERE request_id = %s LIMIT 1",
        $request_id
    ));
    if ($existing_code) {
        return scarto_get_existing_reservation_response($request_id);
    }

    $now = time() * 1000;
    $expires = $now + scarto_get_reservation_duration_ms();

    $wpdb->query('START TRANSACTION');

    // Lock catalog rows first so concurrent confirmations cannot reserve the same book.
    $books_lookup = scarto_load_reservable_books($book_ids, true);
    if (is_wp_error($books_lookup)) {
        $wpdb->query('ROLLBACK');
        return $books_lookup;
    }

    $ip = scarto_get_client_ip();
    $rate_limit_ip = scarto_get_rate_limit_ip($ip);
    $max_per_day = scarto_get_rate_limit('max_reservations_per_day');
    $max_per_email = scarto_get_rate_limit('max_reservations_per_email');
    $max_active_per_email = scarto_get_rate_limit('max_active_reservations_per_email');
    $normalized_email = strtolower((string) ($user['email'] ?? ''));
    if ($normalized_email !== '' && (scarto_get_email_blocklist_entry($normalized_email) || scarto_get_subject_processing_restriction($normalized_email))) {
        $wpdb->query('ROLLBACK');
        scarto_audit_log('reservation_blocklist_rejected', 'verification', $request_id, [], [
            'subject_email' => $normalized_email,
            'outcome' => 'blocked',
            'category' => 'security',
        ]);
        return scarto_reservation_blocked_error();
    }
    $email_exempt = $normalized_email !== '' && scarto_is_email_rate_limit_exempt($normalized_email);
    if (!$staff_created && !scarto_rate_limit_consume('reserve_' . $rate_limit_ip, $max_per_day, DAY_IN_SECONDS)) {
        $wpdb->query('ROLLBACK');
        scarto_audit_log('reservation_rate_limited_ip', 'verification', $request_id, [], [
            'subject_email' => $normalized_email,
            'outcome' => 'blocked',
        ]);
        return new WP_Error('rate_limit', 'Limite giornaliero raggiunto.', ['status' => 429]);
    }
    if (!$staff_created && !$email_exempt && !scarto_rate_limit_consume('reserve_email_' . $normalized_email, $max_per_email, DAY_IN_SECONDS)) {
        $wpdb->query('ROLLBACK');
        scarto_audit_log('reservation_rate_limited_email', 'verification', $request_id, [], [
            'subject_email' => $normalized_email,
            'outcome' => 'blocked',
        ]);
        return new WP_Error('rate_limit', 'Troppe prenotazioni per questa email.', ['status' => 429]);
    }

    // The rate-limit row above remains locked until COMMIT, serializing concurrent requests
    // for the same verified email before this active-reservation count is evaluated.
    $active_reservations = 0;
    if (!$staff_created) {
        $active_reservations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->scarto_orders}
             WHERE user_email = %s AND status = 'active' AND expires_at > %d",
            $normalized_email,
            $now
        ));
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Impossibile verificare le prenotazioni attive.', ['status' => 500]);
        }
    }
    if (!$staff_created && !$email_exempt && $active_reservations >= $max_active_per_email) {
        $wpdb->query('ROLLBACK');
        scarto_audit_log('reservation_active_limit_reached', 'verification', $request_id, [
            'active_reservations' => $active_reservations,
        ], ['subject_email' => $normalized_email, 'outcome' => 'blocked']);
        return new WP_Error(
            'active_reservation_limit',
            'Questa email ha già raggiunto il numero massimo di prenotazioni attive.',
            ['status' => 429]
        );
    }
    if (!$staff_created && $email_exempt) {
        scarto_audit_log('reservation_email_limits_exempted', 'verification', $request_id, [], [
            'subject_email' => $normalized_email,
            'outcome' => 'info',
            'category' => 'security',
        ]);
    }

    $code = null;
    for ($i = 0; $i < 10; $i++) {
        $candidate = scarto_generate_code();
        $result = $wpdb->insert($wpdb->scarto_orders, [
            'code' => $candidate, 'request_id' => $request_id, 'status' => 'active',
            'user_nome' => $user['nome'], 'user_cognome' => $user['cognome'],
            'user_email' => $user['email'], 'user_indirizzo' => $user['indirizzo'],
            'user_via' => $user['via'] ?? '', 'user_civico' => $user['civico'] ?? '',
            'user_cap' => $user['cap'] ?? '', 'user_citta' => $user['citta'] ?? '',
            'user_provincia' => $user['provincia'] ?? '', 'user_note_spedizione' => $user['noteSpedizione'] ?? '',
            'reservation_source' => $staff_created ? 'in_person' : 'online',
            'created_at' => $now, 'expires_at' => $expires,
            'ip_address' => $ip,
            'user_agent' => null,
            'privacy_version' => scarto_sanitize_text($payload['privacyVersion'] ?? SCARTO_VERSION, 20),
            // Legacy column name: this timestamp records privacy acknowledgement, not GDPR consent.
            'consent_at' => $now
        ], ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%d']);

        if ($result !== false) { $code = $candidate; break; }

        $insert_error = $wpdb->last_error;
        if (stripos($insert_error, 'Duplicate') !== false && stripos($insert_error, 'idx_request_id') !== false) {
            $wpdb->query('ROLLBACK');
            return scarto_get_existing_reservation_response($request_id);
        }
        if (stripos($insert_error, 'Duplicate') === false) {
            $wpdb->query('ROLLBACK');
            error_log('Scarto order insert: ' . $insert_error);
            return new WP_Error('db_error', 'Errore creazione ordine.', ['status' => 500]);
        }
    }

    if (!$code) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('server_error', 'Impossibile generare codice.', ['status' => 500]);
    }

    $inserted = 0;
    foreach ($book_ids as $bid) {
        $book_from_db = $books_lookup[$bid] ?? null;
        if (!$book_from_db) continue;

        $item_data = [
            'order_code' => $code, 'book_id' => $bid,
            'titolo' => scarto_sanitize_text($book_from_db['titolo'], 1000),
            'autore' => scarto_sanitize_text($book_from_db['autore'], 500),
            'inventario' => scarto_sanitize_text($book_from_db['inventario'], 50),
            'scatola' => scarto_sanitize_text($book_from_db['scatola'], 100),
            'status' => 'reserved'
        ];

        $result = $wpdb->insert($wpdb->scarto_order_items, $item_data, ['%s','%s','%s','%s','%s','%s','%s']);

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            error_log('Scarto item insert: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Errore salvataggio libri.', ['status' => 500]);
        }
        $inserted++;
    }

    if ($inserted === 0) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('bad_request', 'Nessun libro salvato.', ['status' => 400]);
    }

    if ($wpdb->query('COMMIT') === false) {
        error_log('Scarto reservation commit: ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Errore finalizzazione prenotazione.', ['status' => 500]);
    }

    scarto_invalidate_caches();
    scarto_audit_log($staff_created ? 'staff_reservation_created' : 'reservation_created', 'order', $code, [
        'books' => $inserted,
        'email_hash' => $normalized_email !== '' ? scarto_email_fingerprint($normalized_email) : null,
        'email_present' => $normalized_email !== '',
        'email_exempt' => $email_exempt,
        'source' => $staff_created ? 'in_person' : 'online',
    ], ['subject_email' => $normalized_email !== '' ? $normalized_email : null, 'outcome' => 'success']);

    $enriched_details = [];
    foreach ($book_ids as $bid) {
        if (isset($books_lookup[$bid])) {
            $enriched_details[] = $books_lookup[$bid];
        }
    }

    $reservation_pdf = null;
    $pdf_path = null;
    if (!$staff_created && !empty($user['email']) && is_email($user['email'])) {
        $pdf_path = scarto_generate_reservation_pdf(
            $code,
            $user,
            $enriched_details,
            $now,
            max(1, min(30, (int) scarto_get_settings()['reservation_days']))
        );
    }
    $emailSent = scarto_send_notification_email($code, $user, $enriched_details, $now, true, $pdf_path);
    if ($pdf_path) {
        $reservation_pdf = scarto_reservation_pdf_payload($pdf_path, $code);
        scarto_delete_temp_reservation_pdf($pdf_path);
    }

    return scarto_private_response([
        'success' => true,
        'code' => $code,
        'emailSent' => $emailSent,
        'booksReserved' => $inserted,
        'reservationPdf' => $reservation_pdf,
    ]);
}

function scarto_api_create_staff_reservation($request) {
    $storage_check = scarto_verify_transaction_storage();
    if (is_wp_error($storage_check)) return $storage_check;

    $params = $request->get_json_params();
    $payload = scarto_prepare_reservation_payload(is_array($params) ? $params : [], true);
    if (is_wp_error($payload)) return $payload;

    $payload['requestId'] = bin2hex(random_bytes(16));
    $result = scarto_create_verified_reservation($payload, true);
    if (is_wp_error($result)) return $result;

    return $result;
}

function scarto_api_resend_reservation_email($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $code = strtoupper(scarto_sanitize_text($params['code'] ?? '', 10));
    if (!preg_match('/^[A-Z2-9]{6,10}$/', $code)) {
        return new WP_Error('bad_request', 'Codice prenotazione non valido.', ['status' => 400]);
    }

    if (!scarto_rate_limit_consume('reservation_resend_' . get_current_user_id() . '_' . $code, 10, HOUR_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppi reinvii per questa prenotazione. Attendere prima di riprovare.', ['status' => 429]);
    }

    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT code, status, user_nome, user_cognome, user_email, user_indirizzo,
                user_via, user_civico, user_cap, user_citta, user_provincia, user_note_spedizione,
                created_at
         FROM {$wpdb->scarto_orders} WHERE code = %s LIMIT 1",
        $code
    ), ARRAY_A);
    if (!$order) return new WP_Error('not_found', 'Prenotazione non trovata.', ['status' => 404]);
    if ($order['status'] !== 'active') {
        return new WP_Error('invalid_status', 'Il riepilogo può essere reinviato soltanto per una prenotazione pendente.', ['status' => 409]);
    }
    if (empty($order['user_email']) || !is_email($order['user_email'])) {
        return new WP_Error('email_not_available', 'La prenotazione è stata raccolta senza email: il riepilogo non può essere reinviato.', ['status' => 409]);
    }

    $books = $wpdb->get_results($wpdb->prepare(
        "SELECT book_id AS id, titolo, autore, inventario, scatola
         FROM {$wpdb->scarto_order_items} WHERE order_code = %s ORDER BY id ASC",
        $code
    ), ARRAY_A) ?: [];
    if (!$books) return new WP_Error('not_found', 'La prenotazione non contiene volumi.', ['status' => 404]);

    $user = [
        'nome' => $order['user_nome'],
        'cognome' => $order['user_cognome'],
        'email' => $order['user_email'],
        'indirizzo' => $order['user_indirizzo'],
        'via' => $order['user_via'],
        'civico' => $order['user_civico'],
        'cap' => $order['user_cap'],
        'citta' => $order['user_citta'],
        'provincia' => $order['user_provincia'],
        'noteSpedizione' => $order['user_note_spedizione'],
    ];
    $accepted = scarto_send_notification_email($code, $user, $books, (int) $order['created_at'], false);
    scarto_audit_log('reservation_summary_resent', 'order', $code, [
        'accepted' => (bool) $accepted,
    ], ['subject_email' => $order['user_email'], 'outcome' => $accepted ? 'success' : 'failed', 'category' => 'email']);

    if (!$accepted) {
        return new WP_Error('mail_failed', 'Il sistema di posta non ha accettato il messaggio. Consultare la diagnostica email.', ['status' => 502]);
    }
    return scarto_private_response([
        'success' => true,
        'message' => 'Email accettata dal sistema di posta per l\'invio. La consegna finale dipende dal server del destinatario.',
    ]);
}

function scarto_get_existing_reservation_response($request_id) {
    global $wpdb;

    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->scarto_orders} WHERE request_id = %s LIMIT 1",
        $request_id
    ), ARRAY_A);
    if (!$order) {
        return new WP_Error(
            'reservation_in_progress',
            'La prenotazione è in elaborazione. Attendi alcuni secondi e riprova.',
            ['status' => 409, 'retryable' => true]
        );
    }

    $books_reserved = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_order_items} WHERE order_code = %s",
        $order['code']
    ));
    $reservation_pdf = null;
    if (!empty($order['user_email']) && is_email($order['user_email'])) {
        $books = $wpdb->get_results($wpdb->prepare(
            "SELECT book_id AS id, titolo, autore, inventario, scatola
             FROM {$wpdb->scarto_order_items} WHERE order_code = %s ORDER BY id ASC",
            $order['code']
        ), ARRAY_A) ?: [];
        $user = [
            'nome' => $order['user_nome'],
            'cognome' => $order['user_cognome'],
            'email' => $order['user_email'],
            'indirizzo' => $order['user_indirizzo'],
        ];
        $pdf_path = scarto_generate_reservation_pdf(
            $order['code'],
            $user,
            $books,
            (int) $order['created_at'],
            max(1, min(30, (int) scarto_get_settings()['reservation_days']))
        );
        $reservation_pdf = scarto_reservation_pdf_payload($pdf_path, $order['code']);
        scarto_delete_temp_reservation_pdf($pdf_path);
    }

    return scarto_private_response([
        'success' => true,
        'code' => $order['code'],
        'emailSent' => null,
        'booksReserved' => $books_reserved,
        'idempotentReplay' => true,
        'reservationPdf' => $reservation_pdf,
    ]);
}

function scarto_send_notification_email($code, $user, $books, $ts, $notify_staff = true, $provided_pdf_path = null) {
    $s = scarto_get_settings();
    $reservation_days = max(1, min(30, intval($s['reservation_days'])));
    $expiry_date = wp_date('d/m/Y', intval($ts/1000) + ($reservation_days * 86400));
    
    // Email to staff (biblioteca)
    $staff_body = "NUOVA PRENOTAZIONE\n==================\n\nCodice: $code\nData: " . wp_date('d/m/Y H:i', intval($ts/1000)) . "\n\n";
    $staff_body .= "UTENTE:\n- {$user['nome']} {$user['cognome']}\n";
    if (!empty($user['email'])) {
        $staff_body .= "- {$user['email']}\n";
    }
    if (!empty($user['indirizzo'])) {
        $staff_body .= "- {$user['indirizzo']}\n";
    }
    $staff_body .= "\nLIBRI (" . count($books) . "):\n";
    foreach ($books as $i => $b) {
        $staff_body .= ($i+1) . ") " . ($b['titolo'] ?? 'N/D') . " - " . ($b['autore'] ?? 'N/D') . " [" . ($b['scatola'] ?? '') . "]\n";
    }
    $staff_body .= "\n" . $s['library_name'];
    
    $staff_sent = false;
    if ($notify_staff) {
        $staff_sent = scarto_send_mail_with_status($s['email_to'], $s['email_subject_prefix'] . " - $code", $staff_body, [
            'From: ' . $s['email_from_name'] . ' <' . $s['email_from'] . '>',
            'Reply-To: ' . $s['email_from']
        ], [], 'reservation_staff');
    }
    
    // Email to user with PDF attachment
    $user_sent = false;
    if (!empty($user['email']) && is_email($user['email'])) {
        // Generate PDF for user
        $pdf_path = $provided_pdf_path ?: scarto_generate_reservation_pdf($code, $user, $books, $ts, $reservation_days);
        
        $user_subject = "Conferma Prenotazione Scarto Librario - Codice: $code";
        $user_body = "Gentile {$user['nome']} {$user['cognome']},\n\n";
        $user_body .= "La tua prenotazione è stata confermata con successo!\n\n";
        $user_body .= "========================================\n";
        $user_body .= "CODICE PRENOTAZIONE: $code\n";
        $user_body .= "========================================\n\n";
        $user_body .= "Data prenotazione: " . wp_date('d/m/Y H:i', intval($ts/1000)) . "\n";
        $user_body .= "Scadenza ritiro: $expiry_date\n\n";
        $user_body .= "LIBRI PRENOTATI (" . count($books) . "):\n";
        $user_body .= "----------------------------------------\n";
        foreach ($books as $i => $b) {
            $user_body .= ($i+1) . ". " . ($b['titolo'] ?? 'N/D') . "\n";
            $user_body .= "   Autore: " . ($b['autore'] ?? 'N/D') . "\n";
            if (!empty($b['inventario'])) {
                $user_body .= "   Inventario: " . $b['inventario'] . "\n";
            }
            $user_body .= "\n";
        }
        $user_body .= "----------------------------------------\n\n";
        $user_body .= "ISTRUZIONI PER IL RITIRO:\n";
        $user_body .= "1. Presentarsi presso la " . $s['library_name'] . "\n";
        if (!empty($s['library_address'])) {
            $user_body .= "   Indirizzo: " . $s['library_address'] . "\n";
        }
        $user_body .= "2. Mostrare questo codice al personale: $code\n";
        $user_body .= "3. Ritirare i libri entro il $expiry_date\n\n";
        $user_body .= "IMPORTANTE: Se non ritiri i libri entro la data di scadenza,\n";
        $user_body .= "la prenotazione verrà automaticamente annullata.\n\n";
        $user_body .= "In allegato trovi il riepilogo della prenotazione in formato PDF.\n\n";
        $user_body .= "Cordiali saluti,\n";
        $user_body .= $s['library_name'] . "\n";
        if (!empty($s['library_phone'])) {
            $user_body .= "Tel: " . $s['library_phone'] . "\n";
        }
        
        $headers = [
            'From: ' . $s['email_from_name'] . ' <' . $s['email_from'] . '>',
            'Reply-To: ' . $s['email_from'],
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        $attachments = [];
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
        
        $user_sent = scarto_send_mail_with_status($user['email'], $user_subject, $user_body, $headers, $attachments, $notify_staff ? 'reservation_user' : 'reservation_resend');
        
        // Clean up PDF file after sending
        if (!$provided_pdf_path) scarto_delete_temp_reservation_pdf($pdf_path);
    }
    
    return $user_sent;
}

function scarto_create_temp_reservation_pdf_path($code) {
    $temp_root = trailingslashit(get_temp_dir());
    try {
        $directory = $temp_root . 'scarto-pdf-' . bin2hex(random_bytes(12));
    } catch (Throwable $error) {
        error_log('Scarto: generatore casuale non disponibile per il PDF temporaneo.');
        return null;
    }
    if (!wp_mkdir_p($directory)) return null;
    @chmod($directory, 0700);
    return trailingslashit($directory) . 'prenotazione_' . sanitize_file_name($code) . '.pdf';
}

function scarto_delete_temp_reservation_pdf($pdf_path) {
    if (!$pdf_path || !is_string($pdf_path)) return;
    $real_temp = realpath(get_temp_dir());
    $directory = dirname($pdf_path);
    $real_directory = realpath($directory);
    if (!$real_temp || !$real_directory || strpos($real_directory, trailingslashit($real_temp) . 'scarto-pdf-') !== 0) return;
    if (is_file($pdf_path)) @unlink($pdf_path);
    @rmdir($directory);
}

function scarto_reservation_pdf_payload($pdf_path, $code) {
    if (!$pdf_path || !is_file($pdf_path)) return null;
    $size = filesize($pdf_path);
    if ($size === false || $size > 2 * MB_IN_BYTES) return null;
    $contents = file_get_contents($pdf_path);
    if ($contents === false || strncmp($contents, '%PDF-', 5) !== 0) return null;
    return [
        'filename' => 'prenotazione_' . sanitize_file_name($code) . '.pdf',
        'contentBase64' => base64_encode($contents),
    ];
}

function scarto_reservation_pdf_footer($settings) {
    $contacts = [];
    if (!empty($settings['library_phone'])) $contacts[] = 'Tel. ' . $settings['library_phone'];
    if (!empty($settings['email_from'])) $contacts[] = 'email: ' . $settings['email_from'];
    return [
        (string) ($settings['library_name'] ?? 'Biblioteca'),
        (string) ($settings['library_address'] ?? ''),
        implode(' - ', $contacts),
    ];
}

/**
 * Generate PDF for reservation confirmation
 * Uses a robust pure-PHP PDF generation approach
 */
function scarto_generate_reservation_pdf($code, $user, $books, $ts, $reservation_days) {
    $pdf_path = scarto_create_temp_reservation_pdf_path($code);
    if (!$pdf_path) {
        error_log('Scarto: impossibile creare il file temporaneo PDF.');
        return null;
    }
    $s = scarto_get_settings();
    $expiry_date = wp_date('d/m/Y', intval($ts/1000) + ($reservation_days * 86400));
    $creation_date = wp_date('d/m/Y H:i', intval($ts/1000));
    
    // Try TCPDF first (commonly available)
    if (class_exists('TCPDF')) {
        $generated = scarto_generate_pdf_tcpdf($pdf_path, $code, $user, $books, $ts, $reservation_days, $s);
        if ($generated) @chmod($generated, 0600);
        if (!$generated) scarto_delete_temp_reservation_pdf($pdf_path);
        return $generated;
    }
    
    // Try FPDF if available
    if (class_exists('FPDF')) {
        $generated = scarto_generate_pdf_fpdf($pdf_path, $code, $user, $books, $s, $creation_date, $expiry_date);
        if ($generated) @chmod($generated, 0600);
        if (!$generated) scarto_delete_temp_reservation_pdf($pdf_path);
        return $generated;
    }
    
    // Fallback: Create a proper minimal PDF manually
    // This creates a valid PDF 1.4 document that will open in any reader
    $content_lines = [];
    $content_lines[] = 'RIEPILOGO PRENOTAZIONE SCARTO LIBRARIO';
    $content_lines[] = '================================================';
    $content_lines[] = '';
    $content_lines[] = 'CODICE PRENOTAZIONE: ' . $code;
    $content_lines[] = '';
    $content_lines[] = 'Data prenotazione: ' . $creation_date;
    $content_lines[] = 'Scadenza ritiro: ' . $expiry_date . ' (' . $reservation_days . ' giorni)';
    $content_lines[] = '';
    $content_lines[] = '------------------------------------------------';
    $content_lines[] = 'DATI RICHIEDENTE';
    $content_lines[] = '------------------------------------------------';
    $content_lines[] = 'Nome: ' . $user['nome'] . ' ' . $user['cognome'];
    if (!empty($user['email'])) {
        $content_lines[] = 'Email: ' . $user['email'];
    }
    if (!empty($user['indirizzo'])) {
        $content_lines[] = 'Indirizzo: ' . $user['indirizzo'];
    }
    $content_lines[] = '';
    $content_lines[] = '------------------------------------------------';
    $content_lines[] = 'LIBRI PRENOTATI (' . count($books) . ' volumi)';
    $content_lines[] = '------------------------------------------------';
    
    foreach ($books as $i => $b) {
        $num = $i + 1;
        $titolo = isset($b['titolo']) ? scarto_substr($b['titolo'], 0, 45) : 'N/D';
        $autore = isset($b['autore']) ? scarto_substr($b['autore'], 0, 30) : 'N/D';
        $content_lines[] = $num . '. ' . $titolo;
        $content_lines[] = '   Autore: ' . $autore . ' | Inv: ' . ($b['inventario'] ?? '-');
    }
    
    $content_lines[] = '';
    $content_lines[] = '------------------------------------------------';
    $content_lines[] = 'ISTRUZIONI PER IL RITIRO';
    $content_lines[] = '------------------------------------------------';
    $content_lines[] = '1. Presentarsi presso ' . $s['library_name'];
    if (!empty($s['library_address'])) {
        $content_lines[] = '   ' . $s['library_address'];
    }
    $content_lines[] = '2. Mostrare questo codice al personale';
    $content_lines[] = '3. Ritirare i libri entro il ' . $expiry_date;
    $content_lines[] = '';
    $content_lines[] = 'Conservare questo documento come promemoria.';
    
    $safe_pdf_line = static function($line) {
        $line = (string) $line;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);
            $line = $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $line) : $converted;
        } else {
            $line = preg_replace('/[^\x20-\x7E]/', '?', $line);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $line);
    };

    // Keep every volume: the dependency-free fallback paginates instead of
    // silently truncating content when TCPDF/FPDF are unavailable.
    $page_lines = array_chunk($content_lines, 43);
    if (!$page_lines) $page_lines = [[]];
    $page_count = count($page_lines);
    $streams = [];
    foreach ($page_lines as $page_index => $lines) {
        $stream = "BT\n/F1 11 Tf\n";
        $y = 800;
        foreach ($lines as $line) {
            $line = $safe_pdf_line($line);
            $stream .= "1 0 0 1 50 {$y} Tm\n({$line}) Tj\n";
            $y -= 14;
        }
        $footer = array_map($safe_pdf_line, scarto_reservation_pdf_footer($s));
        $page_label = $safe_pdf_line('Pagina ' . ($page_index + 1) . ' di ' . $page_count);
        $stream .= "ET\n0.5 w\n50 70 m\n545 70 l\nS\nBT\n/F2 8 Tf\n1 0 0 1 50 56 Tm\n({$footer[0]}) Tj\n";
        $stream .= "/F1 8 Tf\n1 0 0 1 50 44 Tm\n({$footer[1]}) Tj\n1 0 0 1 50 32 Tm\n({$footer[2]}) Tj\n";
        $stream .= "/F1 8 Tf\n1 0 0 1 500 18 Tm\n({$page_label}) Tj\nET\n";
        $streams[] = $stream;
    }

    // Build a valid PDF 1.4 document with one Page/Contents pair per chunk.
    $pdf = "%PDF-1.4\n";
    $pdf .= "%\xE2\xE3\xCF\xD3\n";
    $obj_offsets = [];
    $append_object = static function($number, $body) use (&$pdf, &$obj_offsets) {
        $obj_offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    };

    $page_refs = [];
    foreach ($streams as $page_index => $_stream) {
        $page_refs[] = (4 + ($page_index * 2)) . ' 0 R';
    }
    $append_object(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $append_object(2, '<< /Type /Pages /Kids [' . implode(' ', $page_refs) . '] /Count ' . $page_count . ' >>');
    $append_object(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $bold_font_object = 4 + ($page_count * 2);
    foreach ($streams as $page_index => $stream) {
        $page_object = 4 + ($page_index * 2);
        $content_object = $page_object + 1;
        $append_object(
            $page_object,
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ' . $content_object . ' 0 R /Resources << /Font << /F1 3 0 R /F2 ' . $bold_font_object . ' 0 R >> >> >>'
        );
        $append_object(
            $content_object,
            "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream"
        );
    }
    $append_object($bold_font_object, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

    $xref_offset = strlen($pdf);
    $object_count = max(array_keys($obj_offsets));
    $pdf .= "xref\n0 " . ($object_count + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($object_number = 1; $object_number <= $object_count; $object_number++) {
        $pdf .= sprintf("%010d 00000 n \n", $obj_offsets[$object_number]);
    }
    $pdf .= "trailer\n<< /Size " . ($object_count + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xref_offset\n%%EOF";
    
    $written = file_put_contents($pdf_path, $pdf);
    
    if ($written === false) {
        error_log('Scarto: Failed to write PDF to ' . $pdf_path);
        scarto_delete_temp_reservation_pdf($pdf_path);
        return null;
    }
    @chmod($pdf_path, 0600);
    
    return file_exists($pdf_path) ? $pdf_path : null;
}

function scarto_generate_pdf_content($code, $user, $books, $ts, $reservation_days) {
    $s = scarto_get_settings();
    $expiry_date = wp_date('d/m/Y', intval($ts/1000) + ($reservation_days * 86400));
    
    $content = "RIEPILOGO PRENOTAZIONE SCARTO LIBRARIO\n";
    $content .= "========================================\n\n";
    $content .= "CODICE PRENOTAZIONE: $code\n\n";
    $content .= "Data: " . wp_date('d/m/Y H:i', intval($ts/1000)) . "\n";
    $content .= "Scadenza: $expiry_date\n\n";
    $content .= "DATI RICHIEDENTE:\n";
    $content .= "Nome: {$user['nome']} {$user['cognome']}\n";
    if (!empty($user['email'])) {
        $content .= "Email: {$user['email']}\n";
    }
    if (!empty($user['indirizzo'])) {
        $content .= "Indirizzo: {$user['indirizzo']}\n";
    }
    $content .= "\n";
    $content .= "LIBRI PRENOTATI (" . count($books) . "):\n";
    $content .= "----------------------------------------\n";
    
    foreach ($books as $i => $b) {
        $num = $i + 1;
        $titolo = scarto_substr($b['titolo'] ?? 'N/D', 0, 50);
        $autore = scarto_substr($b['autore'] ?? 'N/D', 0, 30);
        $content .= "$num. $titolo\n";
        $content .= "   Autore: $autore\n";
    }
    
    $content .= "\n----------------------------------------\n";
    $content .= "Presentare questo documento per il ritiro.\n";
    $content .= $s['library_name'] . "\n";
    if (!empty($s['library_address'])) {
        $content .= $s['library_address'] . "\n";
    }
    
    return $content;
}

function scarto_generate_pdf_tcpdf($pdf_path, $code, $user, $books, $ts, $reservation_days, $s) {
    $expiry_date = wp_date('d/m/Y', intval($ts/1000) + ($reservation_days * 86400));
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Gestione Scarto Librario');
    $pdf->SetAuthor($s['library_name']);
    $pdf->SetTitle('Prenotazione ' . $code);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 32);
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'Riepilogo Prenotazione Scarto Librario', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Code
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'CODICE PRENOTAZIONE:', 0, 1);
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 15, $code, 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);
    
    // Dates
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 7, 'Data prenotazione: ' . wp_date('d/m/Y H:i', intval($ts/1000)), 0, 1);
    $pdf->Cell(0, 7, 'Scadenza ritiro: ' . $expiry_date . ' (' . $reservation_days . ' giorni)', 0, 1);
    $pdf->Ln(5);
    
    // User data
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'DATI PRENOTAZIONE', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Nome: ' . $user['nome'] . ' ' . $user['cognome'], 0, 1);
    if (!empty($user['email'])) {
        $pdf->Cell(0, 6, 'Email: ' . $user['email'], 0, 1);
    }
    if (!empty($user['indirizzo'])) {
        $pdf->Cell(0, 6, 'Indirizzo: ' . $user['indirizzo'], 0, 1);
    }
    $pdf->Ln(5);
    
    // Books
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'LIBRI PRENOTATI (' . count($books) . ' volumi)', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    foreach ($books as $i => $b) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, ($i+1) . '. ' . scarto_substr($b['titolo'] ?? 'N/D', 0, 60), 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, '   Autore: ' . scarto_substr($b['autore'] ?? 'N/D', 0, 40) . ' | Inv: ' . ($b['inventario'] ?? '-'), 0, 1);
    }
    
    // Footer instructions
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'ISTRUZIONI PER IL RITIRO', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, '1. Presentarsi presso ' . $s['library_name'], 0, 1);
    $pdf->Cell(0, 6, '2. Mostrare questo codice al personale', 0, 1);
    $pdf->Cell(0, 6, '3. Ritirare i libri entro ' . $expiry_date, 0, 1);

    $footer = scarto_reservation_pdf_footer($s);
    $page_count = $pdf->getNumPages();
    for ($page = 1; $page <= $page_count; $page++) {
        $pdf->setPage($page);
        $pdf->SetY(-25);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 4, $footer[0], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        if ($footer[1] !== '') $pdf->Cell(0, 4, $footer[1], 0, 1, 'C');
        if ($footer[2] !== '') $pdf->Cell(0, 4, $footer[2], 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }
    
    $pdf->Output($pdf_path, 'F');
    
    return file_exists($pdf_path) ? $pdf_path : null;
}

/**
 * Generate PDF using FPDF (alternative library)
 */
function scarto_generate_pdf_fpdf($pdf_path, $code, $user, $books, $s, $creation_date, $expiry_date) {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 32);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'Riepilogo Prenotazione Scarto Librario', 0, 1, 'C');
    $pdf->Ln(10);
    
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'CODICE PRENOTAZIONE: ' . $code, 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(0, 7, 'Data: ' . $creation_date, 0, 1);
    $pdf->Cell(0, 7, 'Scadenza: ' . $expiry_date, 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'DATI RICHIEDENTE', 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, 'Nome: ' . $user['nome'] . ' ' . $user['cognome'], 0, 1);
    if (!empty($user['email'])) {
        $pdf->Cell(0, 6, 'Email: ' . $user['email'], 0, 1);
    }
    if (!empty($user['indirizzo'])) {
        $pdf->Cell(0, 6, 'Indirizzo: ' . $user['indirizzo'], 0, 1);
    }
    $pdf->Ln(5);
    
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'LIBRI PRENOTATI (' . count($books) . ')', 0, 1);
    $pdf->SetFont('Helvetica', '', 9);
    
    foreach ($books as $i => $b) {
        $titolo = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', scarto_substr($b['titolo'] ?? 'N/D', 0, 50));
        $autore = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', scarto_substr($b['autore'] ?? 'N/D', 0, 35));
        $pdf->Cell(0, 5, ($i+1) . '. ' . $titolo, 0, 1);
        $pdf->Cell(0, 5, '   Autore: ' . $autore, 0, 1);
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'ISTRUZIONI', 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, 'Presentare questo documento per il ritiro presso ' . $s['library_name'], 0, 1);

    $footer = scarto_reservation_pdf_footer($s);
    $page_count = $pdf->PageNo();
    $first_footer_page = method_exists($pdf, 'setPage') ? 1 : $page_count;
    for ($page = $first_footer_page; $page <= $page_count; $page++) {
        if (method_exists($pdf, 'setPage')) $pdf->setPage($page);
        $pdf->SetY(-25);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $footer[0]), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8);
        if ($footer[1] !== '') $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $footer[1]), 0, 1, 'C');
        if ($footer[2] !== '') $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $footer[2]), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }
    
    $pdf->Output('F', $pdf_path);
    
    return file_exists($pdf_path) ? $pdf_path : null;
}

// ============================================================================
// API: STATUS
// ============================================================================

function scarto_api_status($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $code = scarto_sanitize_text($p['code'] ?? '', 10);
    $action = scarto_sanitize_text($p['action'] ?? '', 20);
    $ts = time() * 1000;
    
    if (!$code || !in_array($action, ['complete','cancel','expired','revoke'], true)) {
        return new WP_Error('bad_request', 'Dati non validi', ['status' => 400]);
    }
    
    $wpdb->query('START TRANSACTION');
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->scarto_orders} WHERE code = %s FOR UPDATE", $code));
    if (!$order) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('not_found', 'Ordine non trovato', ['status' => 404]);
    }
    
    $status_map = ['complete' => 'completed', 'cancel' => 'cancelled', 'revoke' => 'cancelled', 'expired' => 'expired'];
    $new_status = $status_map[$action];

    $transition_allowed = (
        $order->status === 'active' && in_array($action, ['complete', 'cancel', 'expired'], true)
    ) || (
        $order->status === 'completed' && $action === 'revoke'
    );
    if (!$transition_allowed) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('invalid_transition', 'Transizione di stato non consentita.', ['status' => 409]);
    }
    
    $result = $wpdb->update($wpdb->scarto_orders, [
        'status' => $new_status, 'updated_at' => $ts, 'completed_at' => $action === 'complete' ? $ts : null
    ], ['code' => $code], ['%s','%d','%d'], ['%s']);
    
    if ($result === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Errore aggiornamento.', ['status' => 500]);
    }
    
    if ($action === 'complete') {
        $wpdb->update($wpdb->scarto_order_items, ['status' => 'withdrawn', 'withdrawn_at' => $ts], ['order_code' => $code, 'status' => 'reserved'], ['%s','%d'], ['%s','%s']);
    } else {
        $wpdb->update($wpdb->scarto_order_items, ['status' => 'released'], ['order_code' => $code, 'status' => 'reserved'], ['%s'], ['%s','%s']);
    }
    
    $wpdb->query('COMMIT');
    scarto_invalidate_caches();
    scarto_audit_log('order_status_changed', 'order', $code, [
        'action' => $action,
        'previous_status' => $order->status,
        'status' => $new_status,
    ], ['subject_email' => $order->user_email, 'outcome' => 'success']);
    
    return rest_ensure_response(['success' => true]);
}

// ============================================================================
// API: SETTINGS
// ============================================================================

function scarto_api_get_settings($request) {
    $settings = scarto_get_settings();

    return scarto_public_response([
        'reservation_days' => (int)$settings['reservation_days'],
        'library_name' => $settings['library_name'],
        'library_address' => $settings['library_address'],
        'library_phone' => $settings['library_phone'] ?? '',
        'max_books_per_reservation' => (int)$settings['max_books_per_reservation'],
        'homepage_url' => $settings['homepage_url'] ?? '',
        'privacy_policy_url' => $settings['privacy_policy_url'] ?? '',
        'collect_domicile' => false,
    ]);
}

function scarto_api_get_admin_settings($request) {
    return scarto_private_response(scarto_get_settings());
}

function scarto_api_save_settings($request) {
    $p = $request->get_json_params();
    $new = $p['settings'] ?? [];
    if (empty($new)) return new WP_Error('bad_request', 'Nessuna impostazione', ['status' => 400]);
    
    $current = scarto_get_settings();
    $sanitized = [];
    
    if (isset($new['reservation_days'])) $sanitized['reservation_days'] = max(1, min(30, (int)$new['reservation_days']));
    if (isset($new['email_from'])) {
        $from = sanitize_email(trim((string)$new['email_from']));
        if ($from && is_email($from)) {
            $sanitized['email_from'] = $from;
        }
    }

    // Allow one or multiple notification recipients (comma/semicolon separated).
    if (isset($new['email_to'])) {
        $raw = trim((string)$new['email_to']);
        $parts = preg_split('/[;,]+/', $raw);
        $valid = [];
        if (is_array($parts)) {
            foreach ($parts as $pemail) {
                $pemail = sanitize_email(trim((string)$pemail));
                if ($pemail && is_email($pemail)) {
                    $valid[] = $pemail;
                }
            }
        }
        if (!empty($valid)) {
            // Store as comma-separated string (wp_mail accepts it).
            $sanitized['email_to'] = implode(',', array_unique($valid));
        }
    }
    if (isset($new['email_from_name'])) $sanitized['email_from_name'] = scarto_sanitize_text($new['email_from_name'], 200);
    if (isset($new['email_subject_prefix'])) $sanitized['email_subject_prefix'] = scarto_sanitize_text($new['email_subject_prefix'], 200);
    if (isset($new['library_name'])) $sanitized['library_name'] = scarto_sanitize_text($new['library_name'], 200);
    if (isset($new['library_address'])) $sanitized['library_address'] = scarto_sanitize_text($new['library_address'], 500);
    if (isset($new['library_phone'])) $sanitized['library_phone'] = scarto_sanitize_text($new['library_phone'], 100);
    if (isset($new['max_books_per_reservation'])) $sanitized['max_books_per_reservation'] = max(1, min(100, (int)$new['max_books_per_reservation']));
    if (isset($new['homepage_url'])) $sanitized['homepage_url'] = esc_url_raw($new['homepage_url']);

    // GDPR retention settings (v8.8.0)
    if (isset($new['retention_completed'])) $sanitized['retention_completed'] = max(30, min(730, (int)$new['retention_completed'])); // 30 days to 2 years
    if (isset($new['retention_cancelled'])) $sanitized['retention_cancelled'] = max(7, min(365, (int)$new['retention_cancelled']));   // 7 days to 1 year
    if (isset($new['retention_expired'])) $sanitized['retention_expired'] = max(7, min(365, (int)$new['retention_expired']));         // 7 days to 1 year
    if (isset($new['retention_audit_logs'])) $sanitized['retention_audit_logs'] = max(7, min(365, (int)$new['retention_audit_logs'])); // 7 days to 1 year
    if (isset($new['retention_ip'])) $sanitized['retention_ip'] = max(1, min(90, (int)$new['retention_ip']));                          // 1 to 90 days
    if (isset($new['retention_plan_approved'])) $sanitized['retention_plan_approved'] = (bool) $new['retention_plan_approved'];

    // Rate limiting settings (v8.8.0)
    if (isset($new['max_login_attempts'])) $sanitized['max_login_attempts'] = max(1, min(20, (int)$new['max_login_attempts']));           // 1 to 20 attempts
    if (isset($new['login_lockout_minutes'])) $sanitized['login_lockout_minutes'] = max(1, min(60, (int)$new['login_lockout_minutes']));  // 1 to 60 minutes
    if (isset($new['max_reservations_per_day'])) $sanitized['max_reservations_per_day'] = max(1, min(10, (int)$new['max_reservations_per_day'])); // 1 to 10 per day
    if (isset($new['max_reservations_per_email'])) $sanitized['max_reservations_per_email'] = max(1, min(20, (int)$new['max_reservations_per_email'])); // 1 to 20 per email
    if (isset($new['max_active_reservations_per_email'])) $sanitized['max_active_reservations_per_email'] = max(1, min(10, (int)$new['max_active_reservations_per_email']));
    if (isset($new['rate_limit_email_exemptions'])) $sanitized['rate_limit_email_exemptions'] = scarto_sanitize_email_list($new['rate_limit_email_exemptions']);
    if (isset($new['reservation_email_blocklist'])) $sanitized['reservation_email_blocklist'] = scarto_sanitize_email_blocklist($new['reservation_email_blocklist']);

    // Privacy policy URL (v8.8.0)
    if (isset($new['privacy_policy_url'])) $sanitized['privacy_policy_url'] = esc_url_raw(trim((string)$new['privacy_policy_url']));

    // DPO (Data Protection Officer) contact info (v8.8.1)
    if (isset($new['dpo_name'])) $sanitized['dpo_name'] = scarto_sanitize_text($new['dpo_name'], 200);
    if (isset($new['dpo_email'])) {
        $dpo_email = sanitize_email(trim((string)$new['dpo_email']));
        $sanitized['dpo_email'] = ($dpo_email && is_email($dpo_email)) ? $dpo_email : '';
    }
    if (isset($new['dpo_phone'])) $sanitized['dpo_phone'] = scarto_sanitize_text($new['dpo_phone'], 100);

    // PEC for GDPR requests (v8.8.1)
    if (isset($new['contact_pec'])) {
        $pec = sanitize_email(trim((string)$new['contact_pec']));
        $sanitized['contact_pec'] = ($pec && is_email($pec)) ? $pec : '';
    }

    $retention_keys = ['retention_completed', 'retention_cancelled', 'retention_expired', 'retention_audit_logs', 'retention_ip', 'retention_plan_approved'];
    $retention_changed = false;
    foreach ($retention_keys as $key) {
        if (array_key_exists($key, $sanitized) && $sanitized[$key] != ($current[$key] ?? null)) $retention_changed = true;
    }
    if ($retention_changed) {
        if (empty($sanitized['retention_plan_approved']) && empty($current['retention_plan_approved'])) {
            return new WP_Error('retention_plan_required', 'Confermare il piano di conservazione approvato dall’ente.', ['status' => 400]);
        }
        if (!scarto_verify_password((string) ($p['password'] ?? ''), get_option('scarto_db_admin_password_hash'))) {
            return new WP_Error('invalid_password', 'Password di sicurezza richiesta per modificare la conservazione.', ['status' => 403]);
        }
    }
    $final = array_merge($current, $sanitized);
    if (isset($sanitized['rate_limit_email_exemptions'])) {
        $institutional = scarto_validate_institutional_email_list($sanitized['rate_limit_email_exemptions'], $final);
        if (is_wp_error($institutional)) return $institutional;
        $final['rate_limit_email_exemptions'] = $institutional;
    }
    update_option('scarto_settings', $final);
    scarto_persist_email_control_settings($final);
    scarto_audit_log('settings_updated', null, null, ['keys' => array_keys($sanitized)]);
    
    return scarto_private_response(['success' => true, 'settings' => $final]);
}

// ============================================================================
// API: IMPORT / RESET
// ============================================================================

function scarto_api_books_import($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $books = $p['books'] ?? [];
    
    if (!is_array($books) || empty($books)) return new WP_Error('bad_request', 'Nessun libro', ['status' => 400]);
    if (count($books) > SCARTO_MAX_BOOKS_IMPORT) return new WP_Error('bad_request', 'Troppi libri (max ' . SCARTO_MAX_BOOKS_IMPORT . ')', ['status' => 400]);

    $ids = [];
    $seen = [];
    $validation_errors = [];
    foreach ($books as $i => $book) {
        if (!is_array($book)) {
            $validation_errors[] = "Riga $i: formato non valido";
            continue;
        }
        $id = sanitize_text_field($book['id'] ?? '');
        $id_len = function_exists('mb_strlen') ? mb_strlen($id, 'UTF-8') : strlen($id);
        $normalized_id = strtolower($id);
        if ($id === '') $validation_errors[] = "Riga $i: ID mancante";
        if ($id_len > 50) $validation_errors[] = "Riga $i: ID troppo lungo";
        if ($id !== '' && isset($seen[$normalized_id])) $validation_errors[] = "Riga $i: ID duplicato";
        if (empty($book['titolo'])) $validation_errors[] = "Riga $i: titolo mancante";
        $seen[$normalized_id] = true;
        $ids[] = $id;
    }
    if ($validation_errors) {
        return new WP_Error(
            'invalid_import',
            'Importazione non valida: ' . implode('; ', array_slice($validation_errors, 0, 10)),
            ['status' => 400]
        );
    }
    
    $force = !empty($p['force']);
    
    $wpdb->query('START TRANSACTION');
    
    $active = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->scarto_orders} WHERE status = 'active'");
    if (!$force && $active > 0) {
        $wpdb->query('ROLLBACK');
        return new WP_Error(
            'conflict',
            "$active prenotazioni attive richiedono una conferma esplicita.",
            ['status' => 409, 'activeReservations' => $active]
        );
    }
    if ($force && $active > 0) {
        $active_book_ids = $wpdb->get_col(
            "SELECT DISTINCT oi.book_id
             FROM {$wpdb->scarto_order_items} oi
             INNER JOIN {$wpdb->scarto_orders} o ON o.code = oi.order_code
             WHERE o.status = 'active' AND oi.status = 'reserved'"
        );
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Impossibile verificare i volumi delle prenotazioni attive.', ['status' => 500]);
        }
        $incoming_ids = array_fill_keys(array_map('strval', $ids), true);
        $missing_active_ids = array_values(array_filter(
            array_map('strval', $active_book_ids ?: []),
            static fn($book_id) => !isset($incoming_ids[$book_id])
        ));
        if ($missing_active_ids) {
            $wpdb->query('ROLLBACK');
            return new WP_Error(
                'active_books_missing',
                count($missing_active_ids) === 1
                    ? 'Il nuovo file non contiene un volume incluso in una prenotazione attiva. Ripristinare la riga o il relativo ID/inventario.'
                    : 'Il nuovo file non contiene ' . count($missing_active_ids) . ' volumi inclusi in prenotazioni attive. Ripristinare le righe o i relativi ID/inventario.',
                ['status' => 409, 'missingActiveBooks' => count($missing_active_ids)]
            );
        }
    }
    
    $ph = implode(',', array_fill(0, count($ids), '%s'));
    $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->scarto_books} WHERE id NOT IN ($ph)", ...$ids));
    if ($deleted === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Errore aggiornamento catalogo.', ['status' => 500]);
    }

    // Fetch existing IDs once to avoid per-row SELECT. Build a map for O(1) checks.
    $existing_map = [];
    if ($ids) {
        $phIds = implode(',', array_fill(0, count($ids), '%s'));
        $existing_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->scarto_books} WHERE id IN ($phIds)", ...$ids));
        if (is_array($existing_ids)) {
            $existing_map = array_flip($existing_ids);
        }
    }

    // Upsert
    $inserted = 0;
    $updated = 0;
    $errors = [];
    $seen = [];

    foreach ($books as $i => $b) {
        $rawId = $b['id'] ?? '';
        $id = $rawId ? sanitize_text_field($rawId) : '';
        if (!$id) {
            $errors[] = "Riga $i: ID mancante";
            continue;
        }
        $id_len = function_exists('mb_strlen') ? mb_strlen($id, 'UTF-8') : strlen($id);
        if ($id_len > 50) {
            $errors[] = "Riga $i: ID troppo lungo (max 50 caratteri)";
            continue;
        }
        // Normalize ID for duplicate detection (case-insensitive)
        $lowerId = strtolower($id);
        if (isset($seen[$lowerId])) {
            $errors[] = "Riga $i: ID duplicato";
            continue;
        }
        $seen[$lowerId] = true;

        $data = [
            'scatola' => scarto_sanitize_text($b['scatola'] ?? '', 100),
            'autore' => scarto_sanitize_text($b['autore'] ?? '', 500),
            'titolo' => scarto_sanitize_text($b['titolo'] ?? '', 1000),
            'editore' => scarto_sanitize_text($b['editore'] ?? '', 500),
            'anno' => scarto_sanitize_text($b['anno'] ?? '', 100),
            'inventario' => scarto_sanitize_text($b['inventario'] ?? '', 100),
            'collocazione' => scarto_sanitize_text($b['collocazione'] ?? '', 200),
            'stato' => scarto_sanitize_text($b['stato'] ?? '', 100),
            'motivazioni' => scarto_sanitize_text($b['motivazioni'] ?? '', 2000),
            'note' => scarto_sanitize_text($b['note'] ?? '', 2000)
        ];

        if (isset($existing_map[$id])) {
            // Perform update.
            $r = $wpdb->update($wpdb->scarto_books, $data, ['id' => $id]);
            if ($r !== false) {
                $updated++;
            } else {
                $errors[] = "Riga $i: " . $wpdb->last_error;
            }
        } else {
            // Insert new record.
            $data['id'] = $id;
            $r = $wpdb->insert($wpdb->scarto_books, $data);
            if ($r !== false) {
                $inserted++;
            } else {
                $errors[] = "Riga $i: " . $wpdb->last_error;
            }
        }
    }
    
    if ($errors || ($inserted === 0 && $updated === 0)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Importazione fallita: ' . implode('; ', array_slice($errors, 0, 5)), ['status' => 500]);
    }
    
    $wpdb->query('COMMIT');
    scarto_invalidate_caches();
    scarto_audit_log('books_imported', null, null, [
        'count' => count($books),
        'inserted' => $inserted,
        'updated' => $updated,
        'deleted' => max(0, (int) $deleted),
        'forced' => $force,
        'active_reservations' => $active,
    ]);
    
    return scarto_private_response([
        'success' => true,
        'count' => count($books),
        'inserted' => $inserted,
        'updated' => $updated,
        'deleted' => max(0, (int) $deleted),
        'errors' => array_slice($errors, 0, 20),
    ]);
}

function scarto_api_reset($request) {
    global $wpdb;
    
    $wpdb->query('START TRANSACTION');
    $r1 = $wpdb->query("DELETE FROM {$wpdb->scarto_order_items}");
    $r2 = $wpdb->query("DELETE FROM {$wpdb->scarto_orders}");
    $r3 = $wpdb->query("DELETE FROM {$wpdb->scarto_books}");
    $r4 = $wpdb->query("DELETE FROM {$wpdb->scarto_reservation_verifications}");
    $r5 = $wpdb->query("DELETE FROM {$wpdb->scarto_gdpr_tokens}");
    $r6 = $wpdb->query("DELETE FROM {$wpdb->scarto_rate_limits}");
    
    if ($r1 === false || $r2 === false || $r3 === false || $r4 === false || $r5 === false || $r6 === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Errore reset.', ['status' => 500]);
    }
    
    $wpdb->query('COMMIT');
    scarto_invalidate_caches();
    scarto_audit_log('database_reset', null, null, [
        'order_items_deleted' => (int) $r1,
        'orders_deleted' => (int) $r2,
        'books_deleted' => (int) $r3,
        'pending_otp_deleted' => (int) $r4,
        'privacy_tokens_deleted' => (int) $r5,
        'temporary_counters_deleted' => (int) $r6,
    ]);

    return rest_ensure_response(['success' => true]);
}

/**
 * Purge all personal data (GDPR compliance)
 * Anonymizes all orders while preserving book inventory
 */
function scarto_api_purge_all_data($request) {
    global $wpdb;

    $wpdb->query('START TRANSACTION');

    // Anonymize all orders (keep for statistics but remove PII)
    $anonymized = $wpdb->query(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = 'ANONIMO',
             user_cognome = 'GDPR',
             user_email = 'deleted@gdpr.local',
             user_indirizzo = 'Dati rimossi per GDPR',
             user_via = '', user_civico = '', user_cap = '', user_citta = '',
             user_provincia = '', user_note_spedizione = '',
             ip_address = NULL,
             user_agent = NULL
         WHERE user_email != 'deleted@gdpr.local'"
    );

    // Clear audit logs IP addresses
    $audit_cleared = $wpdb->query(
        "UPDATE {$wpdb->scarto_audit_log}
         SET ip_address = NULL, subject_email = NULL, details = '{}'
         WHERE ip_address IS NOT NULL OR subject_email IS NOT NULL OR details LIKE '%\"ip\"%'"
    );

    // Clear recovery, GDPR and pending reservation verification tokens.
    $recovery_deleted = $wpdb->query("DELETE FROM {$wpdb->scarto_recovery_tokens}");
    $gdpr_deleted = $wpdb->query("DELETE FROM {$wpdb->scarto_gdpr_tokens}");
    $verification_deleted = $wpdb->query("DELETE FROM {$wpdb->scarto_reservation_verifications}");

    if ($anonymized === false
        || $audit_cleared === false
        || $recovery_deleted === false
        || $gdpr_deleted === false
        || $verification_deleted === false
    ) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Operazione non completata.', ['status' => 500]);
    }

    $wpdb->query('COMMIT');
    scarto_invalidate_caches();
    scarto_audit_log('purge_all_data', null, null, ['anonymized_orders' => $anonymized]);

    return rest_ensure_response([
        'success' => true,
        'anonymized_orders' => $anonymized,
        'message' => 'Tutti i dati personali sono stati anonimizzati. I libri e le statistiche sono stati conservati.'
    ]);
}

/**
 * Manually run cleanup jobs (IP anonymization, old data cleanup)
 */
function scarto_api_run_cleanup($request) {
    $p = $request->get_json_params();
    $job = $p['job'] ?? 'all';

    $results = [];

    if ($job === 'all' || $job === 'ip') {
        scarto_anonymize_old_ips();
        $results['ip_anonymization'] = 'completed';
    }

    if ($job === 'all' || $job === 'gdpr') {
        scarto_gdpr_data_cleanup();
        $results['gdpr_cleanup'] = 'completed';
    }

    if ($job === 'all' || $job === 'audit') {
        scarto_cleanup_audit_logs();
        $results['audit_cleanup'] = 'completed';
    }

    if ($job === 'all' || $job === 'expired') {
        scarto_process_expired_reservations();
        $results['expired_reservations'] = 'completed';
    }

    scarto_audit_log('manual_cleanup', null, null, ['job' => $job]);

    return rest_ensure_response([
        'success' => true,
        'results' => $results,
        'retention_settings' => [
            'completed_orders' => scarto_get_retention_days('completed') . ' giorni',
            'cancelled_orders' => scarto_get_retention_days('cancelled') . ' giorni',
            'audit_logs' => scarto_get_retention_days('audit_logs') . ' giorni',
            'ip_addresses' => scarto_get_retention_days('ip') . ' giorni'
        ]
    ]);
}

// ============================================================================
// GDPR COMPLIANCE ENDPOINTS - v8.7.1 Security Update
// ============================================================================

/**
 * GDPR: Get privacy information
 * Returns data retention policies and privacy details
 */
function scarto_api_gdpr_privacy_info($request) {
    $settings = scarto_get_settings();
    $privacy_contact = $settings['contact_pec'] ?: ($settings['dpo_email'] ?: $settings['email_from']);

    return scarto_public_response([
        'controller' => [
            'name' => $settings['library_name'],
            'address' => $settings['library_address'],
            'email' => $privacy_contact
        ],
        'data_collected' => array_filter([
            'nome' => 'Nome e cognome - per identificazione ritiro',
            'email' => 'Indirizzo email - per comunicazioni',
            'domicilio' => 'Solo per prenotazioni raccolte in sede senza email: via o piazza, numero civico, CAP, città e provincia per spedire il documento protocollato',
            'note_spedizione' => 'Solo nel medesimo caso: indicazioni facoltative utili al recapito postale',
            'ip_address' => 'Indirizzo IP - per sicurezza e prevenzione abusi',
            'user_agent' => 'Browser/dispositivo - conservato esclusivamente nei log di sicurezza con la stessa scadenza degli IP',
            'audit_logs' => 'Operazioni, esiti, data e ora, email correlata e utente WordPress per sicurezza e accountability',
            'blacklist' => 'Email, motivo sintetico, autore, inserimento e scadenza o riesame per prevenire abusi',
            'backups' => 'Copie cifrate dell’archivio generate esclusivamente dal personale autorizzato',
        ]),
        'retention_periods' => [
            'active_reservations' => 'Fino a completamento o scadenza',
            'completed_orders' => scarto_get_retention_days('completed') . ' giorni (poi anonimizzati)',
            'cancelled_orders' => scarto_get_retention_days('cancelled') . ' giorni (poi eliminati)',
            'expired_orders' => scarto_get_retention_days('expired') . ' giorni (poi eliminati)',
            'audit_logs' => scarto_get_retention_days('audit_logs') . ' giorni',
            'ip_addresses' => scarto_get_retention_days('ip') . ' giorni (poi anonimizzati)'
        ],
        'purposes' => array_values(array_filter([
            'Gestione della prenotazione e della consegna dei volumi presso la biblioteca',
            'Invio delle comunicazioni operative e del codice di verifica email',
            'Per le sole prenotazioni raccolte in sede senza email, predisposizione, protocollazione e spedizione al domicilio del documento che conferma la prenotazione e la consegna',
            'Sicurezza del servizio, prevenzione degli abusi e adempimenti amministrativi connessi',
        ])),
        'legal_basis' => 'Compito di interesse pubblico (Art. 6(1)(e) GDPR) e, ove applicabile, obblighi legali e amministrativi (Art. 6(1)(c) GDPR). Il trattamento necessario al servizio non si basa sul consenso.',
        'privacy_acknowledgement' => 'La casella nel modulo attesta la presa visione dell\'informativa privacy.',
        'rights' => [
            'access' => 'Diritto di accesso ai propri dati',
            'rectification' => 'Diritto di rettifica',
            'erasure' => 'Diritto alla cancellazione',
            'portability' => 'Diritto alla portabilità dei dati',
            'objection' => 'Diritto di opposizione'
        ],
        'data_sharing' => 'I dati possono essere trattati dai fornitori tecnici di hosting e posta elettronica nominati dal titolare.',
        'automated_decisions' => 'Non vengono effettuate decisioni automatizzate.',
        'contact' => $privacy_contact
    ]);
}

/**
 * GDPR: Request data export or deletion (Step 1 - Email Verification)
 * v8.7.1 Security: Requires email verification before accessing/deleting data
 */
function scarto_api_gdpr_request($request) {
    global $wpdb;

    $p = $request->get_json_params();
    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    $action = isset($p['action']) && in_array($p['action'], ['export', 'delete']) ? $p['action'] : '';

    if (!$email || !is_email($email)) {
        return new WP_Error('bad_request', 'Indirizzo email richiesto e valido', ['status' => 400]);
    }
    if (!$action) {
        return new WP_Error('bad_request', 'Azione richiesta (export o delete)', ['status' => 400]);
    }

    $generic_response = [
        'success' => true,
        'message' => 'Se l\'email è associata a prenotazioni, riceverai un messaggio di verifica.',
    ];
    $email_hash = strtolower($email);
    $ip = scarto_get_rate_limit_ip();
    $allowed = scarto_rate_limit_consume(
        'gdpr_request_email_' . $email_hash,
        SCARTO_GDPR_MAX_REQUESTS_PER_HOUR,
        HOUR_IN_SECONDS
    ) && scarto_rate_limit_consume(
        'gdpr_request_ip_' . $ip,
        20,
        HOUR_IN_SECONDS
    );
    if (!$allowed) {
        return scarto_public_response($generic_response, 202);
    }

    $email_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders} WHERE user_email = %s",
        $email
    ));
    if (!$email_exists && !scarto_get_email_blocklist_entry($email)) {
        hash('sha256', random_bytes(32));
        return scarto_public_response($generic_response, 202);
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $hashed_token = hash('sha256', $token);

    // Store token
    $wpdb->insert(
        $wpdb->scarto_gdpr_tokens,
        [
            'email' => $email,
            'token' => $hashed_token,
            'action' => $action,
            'expires_at' => date('Y-m-d H:i:s', time() + (SCARTO_GDPR_TOKEN_EXPIRY_MINUTES * 60))
        ],
        ['%s', '%s', '%s', '%s']
    );

    // Send verification email
    $settings = scarto_get_settings();
    $action_label = $action === 'export' ? 'esportare' : 'cancellare';

    $subject = 'Verifica richiesta GDPR - ' . $settings['library_name'];
    $body = "Hai richiesto di $action_label i tuoi dati personali.\n\n";
    $body .= "Per confermare la tua identità, inserisci questo codice nella pagina di verifica:\n\n";
    $body .= "CODICE: $token\n\n";
    $body .= "Questo codice scade tra " . SCARTO_GDPR_TOKEN_EXPIRY_MINUTES . " minuti.\n\n";
    $body .= "Se non hai fatto questa richiesta, ignora questa email.\n\n";
    $body .= "Cordiali saluti,\n" . $settings['library_name'];

    $headers = [
        'From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>',
        'Content-Type: text/plain; charset=UTF-8'
    ];

    wp_mail($email, $subject, $body, $headers);
    scarto_audit_log('gdpr_verification_requested', null, null, [
        'email_hash' => scarto_email_fingerprint($email),
        'action' => $action
    ]);

    return scarto_public_response($generic_response, 202);
}

/**
 * GDPR: Verify token and perform action (Step 2)
 * v8.7.1 Security: Only executes after email verification
 */
function scarto_api_gdpr_verify($request) {
    global $wpdb;

    $p = $request->get_json_params();
    $token = isset($p['token']) ? sanitize_text_field($p['token']) : '';
    $email = isset($p['email']) ? sanitize_email($p['email']) : '';

    if (strlen($token) !== 64) {
        return new WP_Error('bad_request', 'Codice non valido.', ['status' => 400]);
    }
    if (!$email || !is_email($email)) {
        return new WP_Error('bad_request', 'Email non valida.', ['status' => 400]);
    }

    if (!scarto_rate_limit_consume('gdpr_verify_' . scarto_get_rate_limit_ip(), 20, HOUR_IN_SECONDS)) {
        return new WP_Error('rate_limit', 'Troppe richieste.', ['status' => 429]);
    }

    $hashed_token = hash('sha256', $token);

    // Find valid token
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->scarto_gdpr_tokens}
         WHERE token = %s AND email = %s AND expires_at > NOW() AND used = 0",
        $hashed_token, $email
    ));

    if (!$record) {
        scarto_audit_log('gdpr_verification_failed', null, null, [
            'email_hash' => scarto_email_fingerprint($email)
        ]);
        return new WP_Error('invalid_token', 'Codice non valido, scaduto o già usato.', ['status' => 400]);
    }

    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_gdpr_tokens} SET used = 1 WHERE id = %d AND used = 0",
        $record->id
    ));
    if ($claimed !== 1) {
        return new WP_Error('invalid_token', 'Codice non valido, scaduto o già usato.', ['status' => 400]);
    }

    $result = null;
    if ($record->action === 'export') {
        $result = scarto_perform_gdpr_export($email);
    } else {
        $result = scarto_perform_gdpr_delete($email);
    }

    if (is_wp_error($result)) {
        $wpdb->update($wpdb->scarto_gdpr_tokens, ['used' => 0], ['id' => $record->id], ['%d'], ['%d']);
    }

    return $result;
}

function scarto_get_subject_audit_metadata($email) {
    global $wpdb;
    $email = strtolower(sanitize_email((string) $email));
    if (!$email || !is_email($email)) return [];

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, category, action, outcome, entity_type, entity_id,
                ip_address, user_agent, created_at
         FROM {$wpdb->scarto_audit_log}
         WHERE subject_email = %s
         ORDER BY id ASC LIMIT %d",
        $email,
        SCARTO_ORDERS_LIMIT
    ), ARRAY_A) ?: [];

    return array_map(static function($row) {
        return [
            'id' => (int) $row['id'],
            'category' => $row['category'],
            'operation' => $row['action'],
            'outcome' => $row['outcome'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'created_at' => get_date_from_gmt($row['created_at'], 'Y-m-d H:i:s'),
        ];
    }, $rows);
}

/**
 * Internal: Perform GDPR export after verification
 */
function scarto_perform_gdpr_export($email) {
    global $wpdb;

    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT code, status, user_nome, user_cognome, user_email, user_indirizzo,
                user_via, user_civico, user_cap, user_citta, user_provincia, user_note_spedizione, reservation_source,
                created_at, updated_at, completed_at, expires_at, ip_address
         FROM {$wpdb->scarto_orders} WHERE user_email = %s ORDER BY created_at DESC",
        $email
    ), ARRAY_A);

    $pending_reservations = scarto_get_pending_reservation_metadata($email);
    $privacy_requests = scarto_get_gdpr_request_metadata($email);
    $restriction = scarto_get_email_blocklist_entry($email);
    $processing_restriction = scarto_get_subject_processing_restriction($email);
    $audit_logs = scarto_get_subject_audit_metadata($email);
    if (empty($orders) && empty($pending_reservations) && empty($privacy_requests) && !$restriction && !$processing_restriction && empty($audit_logs)) {
        return new WP_Error('not_found', 'Nessun dato trovato', ['status' => 404]);
    }

    $export_data = [
        'export_date' => wp_date('Y-m-d H:i:s'),
        'export_format' => 'GDPR Data Export',
        'verified' => true,
        'privacy_requests' => $privacy_requests,
        'pending_reservations' => $pending_reservations,
        'reservation_restriction' => $restriction,
        'processing_restriction' => $processing_restriction,
        'audit_logs' => $audit_logs,
        'orders' => []
    ];

    foreach ($orders as $order) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT book_id, titolo, autore, scatola, status, withdrawn_at
             FROM {$wpdb->scarto_order_items} WHERE order_code = %s",
            $order['code']
        ), ARRAY_A);

        $export_data['orders'][] = [
            'reservation_code' => $order['code'],
            'status' => $order['status'],
            'source' => $order['reservation_source'],
            'personal_data' => [
                'nome' => $order['user_nome'],
                'cognome' => $order['user_cognome'],
                'email' => $order['user_email'],
                'indirizzo' => $order['user_indirizzo'],
                'via' => $order['user_via'],
                'civico' => $order['user_civico'],
                'cap' => $order['user_cap'],
                'citta' => $order['user_citta'],
                'provincia' => $order['user_provincia'],
                'note_spedizione' => $order['user_note_spedizione']
            ],
            'technical_data' => [
                'ip_address' => $order['ip_address'],
                'created_at' => $order['created_at'] ? wp_date('Y-m-d H:i:s', intval($order['created_at'] / 1000)) : null,
                'completed_at' => $order['completed_at'] ? wp_date('Y-m-d H:i:s', intval($order['completed_at'] / 1000)) : null,
                'expires_at' => $order['expires_at'] ? wp_date('Y-m-d H:i:s', intval($order['expires_at'] / 1000)) : null
            ],
            'books' => $items
        ];
    }

    scarto_audit_log('gdpr_data_export_verified', null, null, [
        'email_hash' => scarto_email_fingerprint($email),
        'orders_count' => count($orders)
    ]);

    return rest_ensure_response($export_data);
}

/**
 * Internal: Perform GDPR delete after verification
 */
function scarto_perform_gdpr_delete($email) {
    global $wpdb;

    $wpdb->query('START TRANSACTION');

    $active_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders} WHERE user_email = %s AND status = 'active'",
        $email
    ));

    if ($active_count > 0) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('active_reservations', "Hai $active_count prenotazioni attive. Completa o annulla prima.", ['status' => 409]);
    }

    // Anonymize completed orders
    $anonymized = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = 'GDPR_DELETED', user_cognome = 'GDPR_DELETED',
             user_email = 'deleted@gdpr.local', user_indirizzo = 'Dati cancellati su richiesta GDPR',
             user_via = '', user_civico = '', user_cap = '', user_citta = '',
             user_provincia = '', user_note_spedizione = '',
             ip_address = NULL, user_agent = NULL
         WHERE user_email = %s AND status = 'completed'",
        $email
    ));

    // Delete cancelled/expired orders
    $orders_to_delete = $wpdb->get_col($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders} WHERE user_email = %s AND status IN ('cancelled', 'expired')",
        $email
    ));

    $deleted = 0;
    if (!empty($orders_to_delete)) {
        $ph = implode(',', array_fill(0, count($orders_to_delete), '%s'));
        $items_deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->scarto_order_items} WHERE order_code IN ($ph)", ...$orders_to_delete));
        $deleted = $items_deleted === false
            ? false
            : $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->scarto_orders} WHERE code IN ($ph)", ...$orders_to_delete));
    }

    $transient_cleanup = scarto_delete_transient_personal_data($email);
    $audit_anonymized = scarto_anonymize_audit_email($email);
    if ($anonymized === false || $deleted === false || $audit_anonymized === false || empty($transient_cleanup['success'])) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Impossibile completare la cancellazione dei dati.', ['status' => 500]);
    }
    $wpdb->query('COMMIT');

    scarto_audit_log('gdpr_data_deletion_verified', null, null, [
        'email_hash' => scarto_email_fingerprint($email),
        'anonymized' => $anonymized,
        'deleted' => $deleted,
        'transient_cleanup' => $transient_cleanup,
    ]);

    return rest_ensure_response([
        'success' => true,
        'message' => 'Dati elaborati secondo GDPR',
        'orders_anonymized' => (int) $anonymized,
        'orders_deleted' => (int) $deleted,
        'transient_data_deleted' => scarto_transient_cleanup_count($transient_cleanup),
        'reservation_restriction_retained' => (bool) scarto_get_email_blocklist_entry($email),
    ]);
}

/**
 * GDPR: Export (Admin-only) - For staff dashboard
 * v8.7.1: Now requires admin authentication
 */
function scarto_api_gdpr_export_admin($request) {
    global $wpdb;

    $p = $request->get_json_params();
    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    $code = isset($p['code']) ? scarto_sanitize_text($p['code'], 10) : '';

    if (empty($email) && empty($code)) {
        return new WP_Error('bad_request', 'Fornire email o codice prenotazione', ['status' => 400]);
    }

    $where_clause = $email ? "user_email = %s" : "code = %s";
    $where_param = $email ?: $code;

    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT code, status, user_nome, user_cognome, user_email, user_indirizzo,
                user_via, user_civico, user_cap, user_citta, user_provincia, user_note_spedizione, reservation_source,
                created_at, updated_at, completed_at, expires_at, ip_address
         FROM {$wpdb->scarto_orders} WHERE $where_clause ORDER BY created_at DESC",
        $where_param
    ), ARRAY_A);

    $subject_email = $email ?: sanitize_email($orders[0]['user_email'] ?? '');
    $pending_reservations = $subject_email ? scarto_get_pending_reservation_metadata($subject_email) : [];
    $privacy_requests = $subject_email ? scarto_get_gdpr_request_metadata($subject_email) : [];
    $restriction = $subject_email ? scarto_get_email_blocklist_entry($subject_email) : null;
    $processing_restriction = $subject_email ? scarto_get_subject_processing_restriction($subject_email) : null;
    $audit_logs = $subject_email ? scarto_get_subject_audit_metadata($subject_email) : [];
    if (empty($orders) && empty($pending_reservations) && empty($privacy_requests) && !$restriction && !$processing_restriction && empty($audit_logs)) {
        return new WP_Error('not_found', 'Nessun dato trovato', ['status' => 404]);
    }

    $export_data = [
        'export_date' => wp_date('Y-m-d H:i:s'),
        'export_format' => 'GDPR Data Export (Admin)',
        'privacy_requests' => $privacy_requests,
        'pending_reservations' => $pending_reservations,
        'reservation_restriction' => $restriction,
        'processing_restriction' => $processing_restriction,
        'audit_logs' => $audit_logs,
        'orders' => []
    ];

    foreach ($orders as $order) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT book_id, titolo, autore, scatola, status, withdrawn_at
             FROM {$wpdb->scarto_order_items} WHERE order_code = %s",
            $order['code']
        ), ARRAY_A);

        $export_data['orders'][] = [
            'reservation_code' => $order['code'],
            'status' => $order['status'],
            'source' => $order['reservation_source'],
            'personal_data' => [
                'nome' => $order['user_nome'],
                'cognome' => $order['user_cognome'],
                'email' => $order['user_email'],
                'indirizzo' => $order['user_indirizzo'],
                'via' => $order['user_via'],
                'civico' => $order['user_civico'],
                'cap' => $order['user_cap'],
                'citta' => $order['user_citta'],
                'provincia' => $order['user_provincia'],
                'note_spedizione' => $order['user_note_spedizione']
            ],
            'technical_data' => [
                'ip_address' => $order['ip_address'],
                'created_at' => $order['created_at'] ? wp_date('Y-m-d H:i:s', intval($order['created_at'] / 1000)) : null,
                'completed_at' => $order['completed_at'] ? wp_date('Y-m-d H:i:s', intval($order['completed_at'] / 1000)) : null,
                'expires_at' => $order['expires_at'] ? wp_date('Y-m-d H:i:s', intval($order['expires_at'] / 1000)) : null
            ],
            'books' => $items
        ];
    }

    scarto_audit_log('gdpr_data_export_admin', null, null, [
        'email_hash' => $email ? scarto_email_fingerprint($email) : null,
        'code_scoped' => !$email && $code !== '',
        'orders_count' => count($orders)
    ]);

    return rest_ensure_response($export_data);
}

/**
 * GDPR: Delete (Admin-only) - For staff dashboard
 * v8.7.1: Now requires admin authentication
 */
function scarto_api_gdpr_delete_admin($request) {
    global $wpdb;

    $p = $request->get_json_params();
    $email = isset($p['email']) ? sanitize_email($p['email']) : '';
    $code = isset($p['code']) ? scarto_sanitize_text($p['code'], 10) : '';
    $confirm = !empty($p['confirm']);
    $operation_reference = wp_generate_uuid4();

    if (empty($email) && empty($code)) {
        return new WP_Error('bad_request', 'Fornire email o codice prenotazione', ['status' => 400]);
    }

    if (!$confirm) {
        return new WP_Error('confirmation_required', 'Conferma richiesta. Invia confirm: true', ['status' => 400]);
    }

    $where_clause = $email ? "user_email = %s" : "code = %s";
    $where_param = $email ?: $code;
    $subject_email = $email ?: sanitize_email($wpdb->get_var($wpdb->prepare(
        "SELECT user_email FROM {$wpdb->scarto_orders} WHERE code = %s LIMIT 1",
        $code
    )));

    $wpdb->query('START TRANSACTION');

    $active_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders} WHERE $where_clause AND status = 'active'",
        $where_param
    ));

    if ($active_count > 0) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('active_reservations', "Ci sono $active_count prenotazioni attive. Completale o annullale prima.", ['status' => 409]);
    }

    // Anonymize completed orders
    $anonymized = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = 'GDPR_DELETED', user_cognome = 'GDPR_DELETED',
             user_email = 'deleted@gdpr.local', user_indirizzo = 'Dati cancellati su richiesta GDPR',
             user_via = '', user_civico = '', user_cap = '', user_citta = '',
             user_provincia = '', user_note_spedizione = '',
             ip_address = NULL, user_agent = NULL
         WHERE $where_clause AND status = 'completed'",
        $where_param
    ));

    // Delete cancelled/expired orders
    $orders_to_delete = $wpdb->get_col($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders} WHERE $where_clause AND status IN ('cancelled', 'expired')",
        $where_param
    ));

    $deleted = 0;
    if (!empty($orders_to_delete)) {
        $ph = implode(',', array_fill(0, count($orders_to_delete), '%s'));
        $items_deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->scarto_order_items} WHERE order_code IN ($ph)", ...$orders_to_delete));
        $deleted = $items_deleted === false
            ? false
            : $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->scarto_orders} WHERE code IN ($ph)", ...$orders_to_delete));
    }

    $transient_cleanup = $subject_email
        ? scarto_delete_transient_personal_data($subject_email)
        : ['success' => true, 'verifications_deleted' => 0, 'gdpr_tokens_deleted' => 0];
    $audit_anonymized = $subject_email ? scarto_anonymize_audit_email($subject_email) : 0;
    if ($anonymized === false || $deleted === false || $audit_anonymized === false || empty($transient_cleanup['success'])) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Impossibile completare la cancellazione dei dati.', ['status' => 500]);
    }
    $wpdb->query('COMMIT');

    scarto_audit_log(
        'gdpr_data_deletion_admin',
        $code !== '' ? 'order' : 'privacy_operation',
        $code !== '' ? $code : $operation_reference,
        [
        'scope' => $code !== '' ? 'reservation_code' : 'email_without_identifier_retention',
        'anonymized' => $anonymized,
        'deleted' => $deleted,
        'transient_cleanup' => $transient_cleanup,
        ]
    );

    return rest_ensure_response([
        'success' => true,
        'message' => 'Dati elaborati secondo GDPR',
        'orders_anonymized' => (int) $anonymized,
        'orders_deleted' => (int) $deleted,
        'transient_data_deleted' => scarto_transient_cleanup_count($transient_cleanup),
        'reservation_restriction_retained' => (bool) ($subject_email && scarto_get_email_blocklist_entry($subject_email)),
        'operation_reference' => $operation_reference,
    ]);
}

add_filter('wp_privacy_personal_data_exporters', function($exporters) {
    $exporters['scarto-librario'] = [
        'exporter_friendly_name' => 'Gestione Scarto Librario',
        'callback' => 'scarto_wp_privacy_exporter',
    ];
    return $exporters;
});

function scarto_wp_privacy_exporter($email_address, $page = 1) {
    global $wpdb;
    $limit = 50;
    $offset = (max(1, (int) $page) - 1) * $limit;
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->scarto_orders}
         WHERE user_email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
        sanitize_email($email_address),
        $limit,
        $offset
    ), ARRAY_A);

    $data = [];
    foreach ($orders ?: [] as $order) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT titolo, autore, inventario, scatola, status
             FROM {$wpdb->scarto_order_items} WHERE order_code = %s",
            $order['code']
        ), ARRAY_A);
        $data[] = [
            'group_id' => 'scarto-librario',
            'group_label' => 'Prenotazioni Scarto Librario',
            'item_id' => 'scarto-order-' . $order['code'],
            'data' => [
                ['name' => 'Codice', 'value' => $order['code']],
                ['name' => 'Stato', 'value' => $order['status']],
                ['name' => 'Origine prenotazione', 'value' => ($order['reservation_source'] ?? 'online') === 'in_person' ? 'In sede' : 'Online'],
                ['name' => 'Nome', 'value' => trim($order['user_nome'] . ' ' . $order['user_cognome'])],
                ['name' => 'Email', 'value' => $order['user_email']],
                ['name' => 'Indirizzo', 'value' => $order['user_indirizzo']],
                ['name' => 'Via', 'value' => $order['user_via'] ?? ''],
                ['name' => 'Numero civico', 'value' => $order['user_civico'] ?? ''],
                ['name' => 'CAP', 'value' => $order['user_cap'] ?? ''],
                ['name' => 'Città', 'value' => $order['user_citta'] ?? ''],
                ['name' => 'Provincia', 'value' => $order['user_provincia'] ?? ''],
                ['name' => 'Note di spedizione', 'value' => $order['user_note_spedizione'] ?? ''],
                ['name' => 'Data creazione', 'value' => wp_date('Y-m-d H:i:s', (int) $order['created_at'] / 1000)],
                ['name' => 'Informativa accettata', 'value' => $order['privacy_version'] ?? 'non registrata'],
                ['name' => 'Indirizzo IP', 'value' => $order['ip_address'] ?? 'anonimizzato'],
                ['name' => 'Libri', 'value' => wp_json_encode($items, JSON_UNESCAPED_UNICODE)],
            ],
        ];
    }

    if ((int) $page === 1) {
        $restriction = scarto_get_email_blocklist_entry($email_address);
        if ($restriction) {
            $data[] = [
                'group_id' => 'scarto-librario-restriction',
                'group_label' => 'Restrizione prenotazioni Scarto Librario',
                'item_id' => 'scarto-restriction-' . scarto_email_fingerprint($email_address),
                'data' => [
                    ['name' => 'Email', 'value' => $restriction['email']],
                    ['name' => 'Motivo sintetico', 'value' => $restriction['reason'] ?? ($restriction['reference'] ?? '')],
                    ['name' => 'Inserita', 'value' => $restriction['created_at'] ?? 'dato non disponibile'],
                    ['name' => 'Autore', 'value' => $restriction['author'] ?? 'dato non disponibile'],
                    ['name' => 'Tipo scadenza', 'value' => $restriction['schedule_type'] ?? 'riesame'],
                    ['name' => 'Data scadenza o riesame', 'value' => $restriction['schedule_date'] ?? 'dato non disponibile'],
                ],
            ];
        }
        $processing_restriction = scarto_get_subject_processing_restriction($email_address);
        if ($processing_restriction) {
            $data[] = [
                'group_id' => 'scarto-librario-processing-restriction',
                'group_label' => 'Limitazione temporanea del trattamento',
                'item_id' => 'scarto-processing-restriction-' . scarto_email_fingerprint($email_address),
                'data' => [
                    ['name' => 'Email', 'value' => $processing_restriction['email']],
                    ['name' => 'Motivazione', 'value' => $processing_restriction['reason']],
                    ['name' => 'Valida fino al', 'value' => $processing_restriction['until']],
                    ['name' => 'Registrata il', 'value' => $processing_restriction['created_at']],
                ],
            ];
        }
        foreach (scarto_get_gdpr_request_metadata($email_address) as $index => $request) {
            $data[] = [
                'group_id' => 'scarto-librario-privacy-requests',
                'group_label' => 'Richieste privacy Scarto Librario',
                'item_id' => 'scarto-privacy-request-' . $index,
                'data' => [
                    ['name' => 'Azione', 'value' => $request['action']],
                    ['name' => 'Creata', 'value' => $request['created_at']],
                    ['name' => 'Scadenza', 'value' => $request['expires_at']],
                    ['name' => 'Utilizzata', 'value' => $request['used'] ? 'sì' : 'no'],
                ],
            ];
        }
        foreach (scarto_get_pending_reservation_metadata($email_address) as $index => $pending) {
            $data[] = [
                'group_id' => 'scarto-librario-pending',
                'group_label' => 'Richieste prenotazione non confermate',
                'item_id' => 'scarto-pending-' . $index,
                'data' => [
                    ['name' => 'Creata', 'value' => $pending['created_at']],
                    ['name' => 'Scadenza', 'value' => $pending['expires_at']],
                    ['name' => 'Dati personali', 'value' => wp_json_encode($pending['personal_data'], JSON_UNESCAPED_UNICODE)],
                    ['name' => 'Volumi richiesti', 'value' => wp_json_encode($pending['requested_books'], JSON_UNESCAPED_UNICODE)],
                ],
            ];
        }

        $audit_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, category, action, outcome, entity_type, entity_id, ip_address, user_agent, created_at
             FROM {$wpdb->scarto_audit_log}
             WHERE subject_email = %s ORDER BY id ASC LIMIT 500",
            strtolower(sanitize_email($email_address))
        ), ARRAY_A) ?: [];
        foreach ($audit_rows as $audit_row) {
            $data[] = [
                'group_id' => 'scarto-librario-audit',
                'group_label' => 'Log attività Scarto Librario',
                'item_id' => 'scarto-audit-' . $audit_row['id'],
                'data' => [
                    ['name' => 'Operazione', 'value' => $audit_row['action']],
                    ['name' => 'Esito', 'value' => $audit_row['outcome']],
                    ['name' => 'Categoria', 'value' => $audit_row['category']],
                    ['name' => 'Indirizzo IP', 'value' => $audit_row['ip_address'] ?: 'anonimizzato'],
                    ['name' => 'User-Agent', 'value' => $audit_row['user_agent'] ?: 'anonimizzato'],
                    ['name' => 'Entità', 'value' => trim(($audit_row['entity_type'] ?: '') . ' ' . ($audit_row['entity_id'] ?: ''))],
                    ['name' => 'Data', 'value' => get_date_from_gmt($audit_row['created_at'], 'Y-m-d H:i:s')],
                ],
            ];
        }
    }

    return ['data' => $data, 'done' => count($orders ?: []) < $limit];
}

add_filter('wp_privacy_personal_data_erasers', function($erasers) {
    $erasers['scarto-librario'] = [
        'eraser_friendly_name' => 'Gestione Scarto Librario',
        'callback' => 'scarto_wp_privacy_eraser',
    ];
    return $erasers;
});

function scarto_wp_privacy_eraser($email_address, $page = 1) {
    global $wpdb;
    $email = sanitize_email($email_address);
    $restriction_retained = (bool) scarto_get_email_blocklist_entry($email);
    $active = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders} WHERE user_email = %s AND status = 'active'",
        $email
    ));

    $anonymized = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->scarto_orders}
         SET user_nome = 'GDPR_DELETED', user_cognome = 'GDPR_DELETED',
             user_email = 'deleted@gdpr.local', user_indirizzo = 'Dati cancellati su richiesta GDPR',
             user_via = '', user_civico = '', user_cap = '', user_citta = '',
             user_provincia = '', user_note_spedizione = '',
             ip_address = NULL, user_agent = NULL
         WHERE user_email = %s AND status = 'completed'",
        $email
    ));

    $codes = $wpdb->get_col($wpdb->prepare(
        "SELECT code FROM {$wpdb->scarto_orders}
         WHERE user_email = %s AND status IN ('cancelled', 'expired') LIMIT 100",
        $email
    ));
    $deleted = 0;
    if ($codes) {
        $placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->scarto_order_items} WHERE order_code IN ($placeholders)",
            $codes
        ));
        $deleted = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->scarto_orders} WHERE code IN ($placeholders)",
            $codes
        ));
    }

    $remaining = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->scarto_orders}
         WHERE user_email = %s AND status IN ('completed', 'cancelled', 'expired')",
        $email
    ));
    $transient_cleanup = scarto_delete_transient_personal_data($email);
    $audit_anonymized = scarto_anonymize_audit_email($email);
    scarto_audit_log('wp_privacy_eraser', null, null, [
        'email_hash' => scarto_email_fingerprint($email),
        'anonymized' => (int) $anonymized,
        'deleted' => $deleted,
        'transient_cleanup' => $transient_cleanup,
    ]);

    return [
        'items_removed' => ((int) $anonymized + $deleted + (int) $audit_anonymized + scarto_transient_cleanup_count($transient_cleanup)) > 0,
        'items_retained' => $active > 0 || $restriction_retained,
        'messages' => array_values(array_filter([
            $active > 0 ? 'Le prenotazioni attive sono conservate fino a completamento o annullamento.' : null,
            $restriction_retained ? 'La restrizione anti-abuso è stata conservata e richiede un riesame autorizzato separato.' : null,
        ])),
        'done' => $remaining === 0,
    ];
}

// Include GDPR Privacy Policy shortcode
if (file_exists(SCARTO_PLUGIN_DIR . 'includes/gdpr-privacy-policy.php')) {
    require_once SCARTO_PLUGIN_DIR . 'includes/gdpr-privacy-policy.php';
}
