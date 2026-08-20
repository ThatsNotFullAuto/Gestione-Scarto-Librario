<?php

define('ABSPATH', __DIR__ . '/');
define('SCARTO_MIN_PASSWORD_LENGTH', 12);
define('SCARTO_MAX_PASSWORD_LENGTH', 72);

function add_action() {}
function add_submenu_page() {}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function sanitize_textarea_field($value) {
    return trim(strip_tags((string) $value));
}
function sanitize_key($value) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}
function sanitize_email($value) {
    return filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL) ?: '';
}
function is_email($value) {
    return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
}
function wp_json_encode($value, $flags = 0) {
    return json_encode($value, $flags);
}
function scarto_sanitize_text($value, $max = 1000) {
    return substr(sanitize_text_field($value), 0, $max);
}

require_once dirname(__DIR__) . '/includes/data-tools.php';

function assert_true($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

function assert_throws($callback, $message) {
    try {
        $callback();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($message);
}

function order_fixture($overrides = []) {
    return array_merge([
        'id' => 1,
        'code' => 'ABC234',
        'request_id' => str_repeat('a', 32),
        'status' => 'active',
        'user_nome' => 'Nome',
        'user_cognome' => 'Esempio',
        'user_email' => 'utente@example.org',
        'user_indirizzo' => '',
        'user_via' => '',
        'user_civico' => '',
        'user_cap' => '',
        'user_citta' => '',
        'user_provincia' => '',
        'user_note_spedizione' => '',
        'reservation_source' => 'online',
        'created_at' => 1700000000000,
        'expires_at' => 1700600000000,
    ], $overrides);
}

$online = scarto_backup_clean_row('orders', order_fixture());
assert_true($online['user_email'] === 'utente@example.org', 'La prenotazione online valida non viene preservata.');

assert_throws(function() {
    scarto_backup_clean_row('orders', order_fixture(['user_email' => '']));
}, 'Una prenotazione online senza email deve essere rifiutata.');

$in_person = scarto_backup_clean_row('orders', order_fixture([
    'user_email' => '',
    'reservation_source' => 'in_person',
    'user_indirizzo' => 'Via di Esempio 10, 00100 Roma (RM)',
    'user_via' => 'Via di Esempio',
    'user_civico' => '10',
    'user_cap' => '00100',
    'user_citta' => 'Roma',
    'user_provincia' => 'RM',
]));
assert_true($in_person['user_email'] === '' && $in_person['reservation_source'] === 'in_person', 'La prenotazione in sede senza email non viene preservata.');

assert_throws(function() {
    scarto_backup_clean_row('orders', order_fixture([
        'user_email' => '',
        'reservation_source' => 'in_person',
    ]));
}, 'Una prenotazione in sede senza email e domicilio deve essere rifiutata.');

$password = 'BackupSicuro9';
$document = ['format' => 'test', 'payload' => ['value' => 'dato fittizio']];
$encrypted = scarto_backup_encrypt_document($document, $password);
$decrypted = scarto_backup_decrypt_document($encrypted, $password);
assert_true($decrypted === $document, 'Il round trip cifrato non preserva il documento.');

assert_throws(function() use ($encrypted) {
    scarto_backup_decrypt_document($encrypted, 'PasswordErrata8');
}, 'Una password errata deve essere rifiutata.');

$tampered = $encrypted;
$ciphertext = base64_decode($tampered['ciphertext'], true);
$ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
$tampered['ciphertext'] = base64_encode($ciphertext);
assert_throws(function() use ($tampered, $password) {
    scarto_backup_decrypt_document($tampered, $password);
}, 'Un backup alterato deve essere rifiutato.');

echo "OK backup offline: recapiti, cifratura, password errata e alterazione.\n";
