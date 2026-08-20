<?php
/**
 * Uninstall script for Gestione Scarto Librario
 * Version: 9.4.8
 *
 * This file runs when the plugin is deleted through the WordPress admin.
 * Data is preserved unless an administrator explicitly enabled deletion.
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$settings = get_option('scarto_settings', []);
$delete_data = is_array($settings) && !empty($settings['delete_data_on_uninstall']);

if ($delete_data) {
    $tables = [
        $wpdb->prefix . 'scarto_order_items',
        $wpdb->prefix . 'scarto_orders',
        $wpdb->prefix . 'scarto_books',
        $wpdb->prefix . 'scarto_audit_log',
        $wpdb->prefix . 'scarto_recovery_tokens',
        $wpdb->prefix . 'scarto_gdpr_tokens',
        $wpdb->prefix . 'scarto_rate_limits',
        $wpdb->prefix . 'scarto_reservation_verifications'
    ];

    foreach ($tables as $table) {
        $safe_table = esc_sql($table);
        $wpdb->query("DROP TABLE IF EXISTS `{$safe_table}`");
    }

    $options = [
        'scarto_admin_password_hash',
        'scarto_db_admin_password_hash',
        'scarto_settings',
        'scarto_rate_limit_email_exemptions',
        'scarto_reservation_email_blocklist',
        'scarto_reservation_email_blocklist_v2',
        'scarto_subject_processing_restrictions',
        'scarto_cleanup_status',
        'scarto_db_version',
        'scarto_password_must_change',
        'scarto_auth_generation',
        'scarto_credentials_setup_required',
        'scarto_appearance',
        'scarto_admin_capabilities_version',
        'scarto_last_mail_status',
        'scarto_audit_privacy_migration',
        'scarto_audit_privacy_migration_lock',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }
}

remove_role('scarto_librario_operator');
remove_role('scarto_librario_manager');
$administrator = get_role('administrator');
if ($administrator) {
    foreach (['scarto_view_reservations', 'scarto_manage_reservations', 'scarto_manage_catalog', 'scarto_manage_settings', 'scarto_manage_privacy'] as $capability) {
        $administrator->remove_cap($capability);
    }
}

// Delete all transients with scarto_ prefix
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_scarto_%'
     OR option_name LIKE '_transient_timeout_scarto_%'"
);

// Clear any scheduled cron jobs
wp_clear_scheduled_hook('scarto_check_expired_reservations');
wp_clear_scheduled_hook('scarto_cleanup_audit_logs');
wp_clear_scheduled_hook('scarto_gdpr_data_cleanup');
wp_clear_scheduled_hook('scarto_anonymize_old_ips');
wp_clear_scheduled_hook('scarto_rate_limit_cleanup');
wp_clear_scheduled_hook('scarto_cleanup_temp_files');
wp_clear_scheduled_hook('scarto_audit_privacy_cleanup');

foreach (glob(trailingslashit(get_temp_dir()) . 'scarto-reservation-*.pdf') ?: [] as $file) {
    if (is_file($file)) @unlink($file);
}
