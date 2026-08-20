<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('SCARTO_AUDIT_PRIVACY_MIGRATION_VERSION', '1');

function add_action(...$args): void {}
function scarto_sanitize_text($value, $max_length = 255): string {
    return substr(trim(strip_tags((string) $value)), 0, (int) $max_length);
}

require_once dirname(__DIR__) . '/includes/audit-privacy.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$input = [
    'count' => 3,
    'success' => true,
    'email_hash' => 'secret-fingerprint',
    'ip_hash' => 'network-fingerprint',
    'message' => 'Richiesta inviata a mario.rossi@example.org',
    'nested' => [
        'token' => 'do-not-retain',
        'status' => 'completed',
        'note' => 'Contattare utente@example.org',
    ],
];

$clean = scarto_sanitize_audit_details($input);
assert_true(($clean['count'] ?? null) === 3, 'i conteggi operativi devono essere conservati');
assert_true(($clean['success'] ?? null) === true, 'gli esiti booleani devono essere conservati');
assert_true(!array_key_exists('email_hash', $clean), 'il fingerprint email deve essere rimosso');
assert_true(!array_key_exists('ip_hash', $clean), 'il fingerprint IP deve essere rimosso');
assert_true(!array_key_exists('token', $clean['nested'] ?? []), 'i token annidati devono essere rimossi');
assert_true(($clean['message'] ?? '') === 'Richiesta inviata a [email rimossa]', 'le email nel testo devono essere oscurate');
assert_true(($clean['nested']['note'] ?? '') === 'Contattare [email rimossa]', 'le email annidate devono essere oscurate');
assert_true(scarto_sanitize_audit_entity_id('utente@example.org') === null, 'un entity_id email deve essere scartato');
assert_true(scarto_sanitize_audit_entity_id('GAJY4B') === 'GAJY4B', 'un codice operativo deve essere conservato');

fwrite(STDOUT, "OK bonifica privacy audit offline\n");
