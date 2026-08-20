import fs from 'node:fs';

const checks = [
  ['templates/app.php', /https?:\/\/(?:fonts\.googleapis|fonts\.gstatic|cdn\.sheetjs|cdnjs\.)/i, 'Risorsa runtime esterna'],
  ['templates/app.php', /wp_create_nonce\('wp_rest'\)/, 'Nonce guest usato dal frontend'],
  ['gestione-scarto-librario.php', /get_param\(['"]password['"]\)/, 'Password accettata dalla query string'],
  ['gestione-scarto-librario.php', /SELECT\s+\*\s+FROM\s+\{\$wpdb->scarto_books\}/i, 'SELECT * sul catalogo pubblico'],
  ['gestione-scarto-librario.php', /['"]scarto_auth_['"]\s*\./, 'Sessione basata su IP e User-Agent'],
  ['gestione-scarto-librario.php', /set_transient\(['"]scarto_initial_(?:db_)?password/, 'Password in chiaro nei transient'],
  ['gestione-scarto-librario.php', /TRUNCATE\s+TABLE/i, 'TRUNCATE non transazionale'],
  ['gestione-scarto-librario.php', /FOREIGN_KEY_CHECKS/i, 'Disattivazione delle foreign key'],
  ['gestione-scarto-librario.php', /wp_memory_limit/, 'Aumento globale della memoria WordPress'],
  ['gestione-scarto-librario.php', /email_hash['"]?\s*=>[^\n]*hash\(['"]sha256['"]/i, 'Fingerprint email non salato nei log'],
  ['src/index.tsx', /staffPassword|window\.(?:XLSX|jspdf)/, 'Segreto o libreria globale legacy nel client']
];

let failed = false;
for (const [file, pattern, description] of checks) {
  const content = fs.readFileSync(file, 'utf8');
  if (pattern.test(content)) {
    failed = true;
    console.error(`FAIL ${description}: ${file}`);
  }
}

if (fs.existsSync('gdpr-tool.php')) {
  failed = true;
  console.error('FAIL Il tool GDPR CLI obsoleto non deve essere distribuito.');
}

for (const requiredFile of [
  'includes/rest-schema.php',
  'includes/diagnostics.php',
  'includes/admin.php',
  'includes/audit-admin.php',
  'includes/data-tools.php',
  'tests/offline-backup-test.php',
  'tools/verify-release.mjs',
  'security-tests/smoke-test.mjs',
  'security-tests/concurrency-test.mjs',
  'security-tests/active-limit-test.mjs'
]) {
  if (!fs.existsSync(requiredFile)) {
    failed = true;
    console.error(`FAIL File di sicurezza mancante: ${requiredFile}`);
  }
}

const mainPhp = fs.readFileSync('gestione-scarto-librario.php', 'utf8');
const packageData = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const escapedVersion = packageData.version.replaceAll('.', '\\.');
if (!new RegExp(`Version:\\s*${escapedVersion}`).test(mainPhp)
    || !new RegExp(`SCARTO_VERSION',\\s*'${escapedVersion}'`).test(mainPhp)
    || !new RegExp(`privacyVersion:\\s*'${escapedVersion}'`).test(fs.readFileSync('src/index.tsx', 'utf8'))) {
  failed = true;
  console.error('FAIL Versione PHP, package e privacyVersion frontend non coerenti.');
}
if (!/scarto_rest_route_args\(/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Le route REST non usano gli schemi dichiarativi.');
}

for (const [pattern, description] of [
  [/\/reserve\/confirm/, 'endpoint di conferma prenotazione'],
  [/scarto_encrypt_reservation_payload/, 'cifratura dei dati temporanei'],
  [/scarto_reservation_verifications/, 'tabella delle verifiche prenotazione'],
  [/unavailableBooks/, 'dettaglio sicuro dei libri indisponibili'],
  [/scarto_verify_transaction_storage/, 'blocco fail-closed senza InnoDB'],
  [/scarto_has_unique_request_id_index/, 'verifica fail-closed dell’indice idempotente'],
  [/UNIQUE KEY idx_request_id \(request_id\)/, 'chiave idempotente della prenotazione'],
  [/idempotentReplay/, 'risposta idempotente della conferma'],
  [/scarto_get_pending_reservation_metadata/, 'export GDPR delle prenotazioni temporanee'],
  [/scarto_delete_transient_personal_data/, 'cancellazione GDPR dei dati personali temporanei'],
  [/scarto_ip_matches_trusted_proxy/, 'allowlist CIDR dei proxy attendibili'],
  [/scarto_get_rate_limit_ip/, 'aggregazione IPv6 per i limiti anti-abuso'],
  [/max_active_reservations_per_email/, 'limite delle prenotazioni attive per email'],
  [/active_reservation_limit/, 'errore esplicito per il limite di prenotazioni attive']
]) {
  if (!pattern.test(mainPhp)) {
    failed = true;
    console.error(`FAIL Manca ${description}.`);
  }
}
if (!/SCARTO_DB_VERSION', '8\.15'/.test(mainPhp)
    || !/MODIFY COLUMN anno VARCHAR\(100\)/.test(mainPhp)
    || !/'anno' => scarto_sanitize_text\([^\n]+, 100\)/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La migrazione del campo Anno non e coerente con la sanitizzazione.');
}
if (!/scarto_send_mail_with_status\(\$email,[\s\S]+?'reservation_otp'\)/.test(mainPhp)
    || !/wp_mail_failed/.test(mainPhp)
    || !/admin_post_scarto_test_email/.test(mainPhp)
    || !/check_admin_referer\('scarto_test_email'\)/.test(mainPhp)
    || !/\[email rimossa\]/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Diagnostica, autorizzazione o redazione dati del trasporto OTP incompleta.');
}

const gdprSchemas = [...mainPhp.matchAll(/scarto_gdpr_tokens\}\s*\([^;]+?email VARCHAR\(254\)[^;]+?idx_email_action \(email, action\)/gs)];
const reservationSchemas = [...mainPhp.matchAll(/scarto_reservation_verifications\}\s*\([^;]+?email_hash CHAR\(64\)[^;]+?idx_email_hash \(email_hash\)/gs)];
if (gdprSchemas.length < 2 || reservationSchemas.length < 2) {
  failed = true;
  console.error('FAIL Le definizioni delle tabelle GDPR o verifica prenotazione non sono coerenti.');
}

const innodbSchemas = [...mainPhp.matchAll(/\)\s+ENGINE=InnoDB\s+\$charset_collate;/g)];
if (innodbSchemas.length < 8) {
  failed = true;
  console.error('FAIL Le tabelle del plugin non dichiarano tutte InnoDB in attivazione.');
}
if (/scarto_reservation_verifications\}\s+WHERE expires_at <= UTC_TIMESTAMP\(\) OR used = 1/.test(mainPhp)) {
  failed = true;
  console.error('FAIL I token usati vengono eliminati troppo presto per il replay idempotente.');
}

const securityPhp = fs.readFileSync('includes/security.php', 'utf8');
if (!/scarto_normalize_origin/.test(securityPhp) || !/Access-Control-Allow-Origin/.test(securityPhp)) {
  failed = true;
  console.error('FAIL Mancano i controlli same-origin/CORS del namespace.');
}
if (!/LEAST\(attempts \+ 1, %d\)/.test(securityPhp)) {
  failed = true;
  console.error('FAIL I contatori rate limit possono crescere senza limite dopo il blocco.');
}
if (!/['"]\/scarto\/v1\/admin\/reservations['"]/.test(securityPhp)
    || !/['"]\/scarto\/v1\/admin\/reservations\/resend['"]/.test(securityPhp)
    || !/Cache-Control', 'no-store, no-cache, must-revalidate, private'/.test(securityPhp)) {
  failed = true;
  console.error('FAIL Le route delle prenotazioni staff non sono incluse nella protezione no-store globale.');
}

const adminPhp = fs.readFileSync('includes/admin.php', 'utf8');
for (const [pattern, description] of [
  [/add_menu_page\([\s\S]+?'Scarto Librario'/, 'menu amministrativo dedicato'],
  [/SCARTO_CAP_VIEW/, 'capability di lettura prenotazioni'],
  [/SCARTO_CAP_CATALOG/, 'capability di gestione catalogo'],
  [/SCARTO_CAP_PRIVACY/, 'capability privacy'],
  [/wp_create_nonce\('wp_rest'\)/, 'nonce REST WordPress nel back office'],
  [/scarto_sanitize_hex_color_strict/, 'convalida dei colori configurabili'],
  [/wp_attachment_is_image/, 'convalida del logo selezionato'],
  [/max_active_reservations_per_email/, 'impostazione del limite prenotazioni attive'],
  [/subject_search_code/, 'ricerca interessati senza email tramite codice'],
  [/scarto_subject_rectify[\s\S]+?name="code"/, 'rettifica interessati identificati tramite codice'],
  [/script_loader_tag[\s\S]+?type="module"/, 'caricamento ES module nel pannello WordPress'],
  [/data-scarto-settings/, 'configurazione amministrativa incorporata nel markup']
]) {
  if (!pattern.test(adminPhp)) {
    failed = true;
    console.error(`FAIL Manca ${description}.`);
  }
}

if (!/X-WP-Nonce/.test(securityPhp) || !/wp_verify_nonce\(\$nonce, 'wp_rest'\)/.test(securityPhp)) {
  failed = true;
  console.error('FAIL Le API amministrative non verificano il nonce REST WordPress.');
}
if (/register_rest_route\(\$ns, '\/(?:session|login|logout|recover-password|reset-password|change-password)'/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Sono ancora registrate route di autenticazione staff proprietarie.');
}
if (!/scarto_verify_orders_access/.test(mainPhp) || !/scarto_verify_privacy_access/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Le route private non usano capability separate.');
}
if (!/\/purge-all-data'[^\n]+scarto_api_purge_all_data[^\n]+scarto_verify_privacy_db_auth/.test(mainPhp)
    || /\/purge-all-data'[^\n]+scarto_verify_db_admin_auth/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La cancellazione globale non e confinata alla capability privacy con step-up.');
}
if (!/db_admin_auth_' \. get_current_user_id\(\) \. '_' \. \$ip/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Il lockout della password del plugin non e isolato per account WordPress.');
}
if (!/\/admin\/reservations'[^\n]+scarto_api_create_staff_reservation[^\n]+scarto_verify_staff_session/.test(mainPhp)
    || !/\/admin\/reservations\/resend'[^\n]+scarto_api_resend_reservation_email[^\n]+scarto_verify_staff_session/.test(mainPhp)
    || !/function scarto_api_create_staff_reservation[\s\S]+scarto_prepare_reservation_payload\([^\n]+true\)/.test(mainPhp)
    || !/function scarto_create_verified_reservation[\s\S]+scarto_get_email_blocklist_entry/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Creazione in sede o reinvio email senza protezione staff e blacklist.');
}
if (!/scarto_verify_privacy_db_auth/.test(mainPhp) || !/get_option\('scarto_db_admin_password_hash'\)/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La cancellazione GDPR amministrativa non richiede lo step-up database.');
}

const backupRestoreSource = fs.readFileSync('includes/data-tools.php', 'utf8');
if (!/\$reservation_source === 'online' && \$email === ''/.test(backupRestoreSource)
    || !/\$clean\['reservation_source'\] === 'in_person' && \$clean\['user_email'\] === ''/.test(backupRestoreSource)
    || !/Prenotazione in sede senza email e domicilio/.test(backupRestoreSource)) {
  failed = true;
  console.error('FAIL Il ripristino backup non preserva in sicurezza le prenotazioni in sede senza email.');
}

const reserveFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_api_reserve'),
  mainPhp.indexOf('function scarto_api_confirm_reservation')
);
const globalLimitPosition = reserveFunction.indexOf("scarto_rate_limit_consume('reserve_verify_v2_global'");
const ipLimitPosition = reserveFunction.indexOf("scarto_rate_limit_consume('reserve_verify_v2_ip_'");
const emailLimitPosition = reserveFunction.indexOf("scarto_rate_limit_consume('reserve_verify_v2_email_'");
if (globalLimitPosition < 0 || ipLimitPosition < 0 || emailLimitPosition < 0
    || globalLimitPosition > ipLimitPosition || globalLimitPosition > emailLimitPosition) {
  failed = true;
  console.error('FAIL Il circuito globale deve precedere la creazione dei contatori IP/email.');
}
if (!/scarto_is_trusted_proxy\(\$remote\)/.test(mainPhp)
    || !/SCARTO_TRUST_CLOUDFLARE[\s\S]+HTTP_CF_CONNECTING_IP/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Gli header proxy non sono subordinati a una allowlist esplicita.');
}
if (!/SELECT COUNT\(\*\)[\s\S]+status = 'active'[\s\S]+expires_at > %d/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Manca il conteggio server-side delle prenotazioni attive non scadute.');
}

const diagnosticsPhp = fs.readFileSync('includes/diagnostics.php', 'utf8');
if (!/DISALLOW_FILE_EDIT/.test(diagnosticsPhp) || !/SCARTO_TRUSTED_PROXIES/.test(diagnosticsPhp)) {
  failed = true;
  console.error('FAIL La diagnostica non controlla editor file e proxy attendibili.');
}
if (!/Dominio mittente email/.test(diagnosticsPhp) || !/SPF\/DKIM\/DMARC/.test(diagnosticsPhp)) {
  failed = true;
  console.error('FAIL La diagnostica non verifica l’allineamento del mittente email.');
}

const restSchemaPhp = fs.readFileSync('includes/rest-schema.php', 'utf8');
if (!/scarto_rest_object_arg/.test(restSchemaPhp) || !/reserve_confirm/.test(restSchemaPhp)) {
  failed = true;
  console.error('FAIL Contratti REST annidati o conferma prenotazione mancanti.');
}
if (!/case 'staff_reserve'/.test(restSchemaPhp)
    || !/\$required_user_fields\s*=\s*\$staff_reservation[\s\S]+\['nome', 'cognome'\][\s\S]+\['nome', 'cognome', 'email'\]/.test(restSchemaPhp)
    || !/'via'[^\n]+SCARTO_MAX_STREET_LENGTH/.test(restSchemaPhp)
    || !/'cap'[^\n]+\^\(\?:\[0-9\]\{5\}\)\?\$/.test(restSchemaPhp)
    || !/'provincia'[^\n]+\^\(\?:\[A-Za-z\]\{2\}\)\?\$/.test(restSchemaPhp)
    || !/'noteSpedizione'[^\n]+SCARTO_MAX_SHIPPING_NOTES_LENGTH/.test(restSchemaPhp)) {
  failed = true;
  console.error('FAIL Contratto REST del domicilio strutturato o della prenotazione in sede incompleto.');
}
if (!/function scarto_prepare_reservation_user\(\$user_data, \$staff_created = false\)/.test(mainPhp)
    || !/indirizzo email è obbligatorio per le prenotazioni online/.test(mainPhp)
    || !/if \(!\$staff_created \|\| \$user\['email'\] !== ''\)[\s\S]+\$user\['indirizzo'\] = ''/.test(mainPhp)
    || !/Inserire un indirizzo email valido oppure il domicilio completo/.test(mainPhp)
    || !/email_not_available/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La minimizzazione dei recapiti online/in sede non e applicata lato server.');
}

const frontendSource = fs.readFileSync('src/index.tsx', 'utf8');
if (!/mode="online"/.test(frontendSource)
    || !/mode="staff"/.test(frontendSource)
    || !/mode === 'staff' && userData\.email\.trim\(\) === ''/.test(frontendSource)
    || !/const onlineUserData = \{[\s\S]+?nome: userData\.nome\.trim\(\)[\s\S]+?cognome: userData\.cognome\.trim\(\)[\s\S]+?email: userData\.email\.trim\(\)[\s\S]+?userData: onlineUserData/.test(frontendSource)
    || !/payload\?\.code === 'rest_invalid_param'[\s\S]+?dati anagrafici inseriti non sono stati riconosciuti/.test(frontendSource)
    || !/disabled=\{actionPending \|\| !res\.userData\?\.email\}/.test(frontendSource)) {
  failed = true;
  console.error('FAIL Interfaccia recapiti o reinvio email senza recapito non coerente.');
}
if (!/'anno' => \['type' => 'string', 'maxLength' => 100\]/.test(restSchemaPhp)) {
  failed = true;
  console.error('FAIL Lo schema REST non accetta i valori Anno del catalogo reale.');
}
if (!/case 'orders':[\s\S]+?'page'[\s\S]+?'per_page'[\s\S]+?'search'[\s\S]+?'status'/.test(restSchemaPhp)
    || !/'\/scarto\/v1\/orders' => \['page', 'per_page', 'search', 'status'\]/.test(restSchemaPhp)) {
  failed = true;
  console.error('FAIL La paginazione delle prenotazioni non e ammessa dal contratto JSON REST.');
}

const frontend = fs.readFileSync('src/index.tsx', 'utf8');
const ordersFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_api_get_orders'),
  mainPhp.indexOf('function scarto_api_books_search')
);
if (!/LIMIT %d OFFSET %d/.test(ordersFunction)
    || !/o\.user_nome LIKE %s/.test(ordersFunction)
    || !/o\.user_cognome LIKE %s/.test(ordersFunction)
    || !/pagination/.test(ordersFunction)
    || !/StaffPagination/.test(frontend)
    || !/staffOrdersRequestSequenceRef/.test(frontend)) {
  failed = true;
  console.error('FAIL Paginazione, ricerca globale o protezione da risposte obsolete delle prenotazioni incompleta.');
}
if (!/function scarto_get_public_catalog_page[\s\S]+SELECT id, autore, titolo, editore, anno, inventario, stato/.test(mainPhp)
    || /function scarto_get_public_catalog_page[\s\S]+SELECT id, scatola, autore[\s\S]+function scarto_get_admin_catalog_page/.test(mainPhp)
    || !/\/admin\/catalog[\s\S]+scarto_verify_catalog_read/.test(mainPhp)
    || !/function scarto_get_admin_catalog_page[\s\S]+SELECT id, scatola, autore, titolo, editore, anno, inventario, stato/.test(mainPhp)
    || !/Stato di conservazione:/.test(frontend)
    || !/data-conservation-status/.test(frontend)
    || !/Leggermente deteriorato/.test(frontend)
    || !/Non indicato/.test(frontend)) {
  failed = true;
  console.error('FAIL Lo stato di conservazione non e disponibile come testo accessibile con legenda.');
}
if (/function scarto_api_books_search[\s\S]+SELECT id, titolo, autore, scatola[\s\S]+function scarto_api_login/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Il numero di scatola e esposto dalla ricerca pubblica.');
}
if (!/Dichiaro di aver letto/.test(frontend)
    || /acconsento al trattamento dei miei dati personali/.test(frontend)
    || !/Il trattamento necessario al servizio non si basa sul consenso/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La presa visione privacy o la base giuridica non sono formulate in modo coerente.');
}
if (!/requested_books' => scarto_get_pending_book_details/.test(mainPhp)
    || /\$payload\['booksDetails'\] \?\? \[\]/.test(mainPhp)) {
  failed = true;
  console.error('FAIL L export GDPR delle richieste OTP pendenti non ricostruisce i volumi da bookIds.');
}
if (!/function scarto_enrich_catalog_availability/.test(mainPhp)
    || !/reserved_until/.test(mainPhp)
    || !/_availability/.test(mainPhp)
    || !/o\.status = 'completed' AND oi\.status = 'withdrawn'/.test(mainPhp)
    || !/book\.reservedUntil/.test(frontend)
    || !/Consegnato/.test(frontend)) {
  failed = true;
  console.error('FAIL Gli stati disponibile, prenotato e consegnato non sono coerenti tra API e interfacce.');
}
if (/reserve_confirm_request_/.test(mainPhp)
    || !/reserve_verify_v2_email_/.test(mainPhp)
    || !/Tentativi rimasti/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Il rate limiting OTP conta richieste non pertinenti o non comunica i tentativi residui.');
}
if (!/needsFullAdminCatalog/.test(frontend)
    || !/include_catalog=0/.test(frontend)
    || !/CatalogLoadProgress/.test(frontend)
    || !/role="progressbar"/.test(frontend)
    || !/Promise\.all\(pageNumbers/.test(frontend)
    || !/pendingStaffActions/.test(frontend)
    || !/Caricamento prenotazioni in corso/.test(frontend)) {
  failed = true;
  console.error('FAIL Il pannello prenotazioni non evita il catalogo completo o non mostra feedback operativo.');
}
const publicConfirmationFunction = frontend.slice(
  frontend.indexOf('const confirmReservation = async ('),
  frontend.indexOf('const refreshCatalog = async')
);
if (/await loadData\(/.test(publicConfirmationFunction)
    || !/confirmedBookIds/.test(publicConfirmationFunction)
    || !/reservedUntil/.test(publicConfirmationFunction)
    || !/void refreshAvailability\(\)/.test(publicConfirmationFunction)
    || !/const \[loading, setLoading\] = useState\(needsInitialCatalog\)/.test(frontend)
    || !/Verifica del codice e creazione della prenotazione in corso/.test(frontend)) {
  failed = true;
  console.error('FAIL La conferma OTP ricarica il catalogo o non fornisce feedback operativo esplicito.');
}
const withdrawalPdfFunction = frontend.slice(
  frontend.indexOf('const generateReservationPDF'),
  frontend.indexOf('// CONFIGURAZIONE STILI FONT')
);
if (!/reservation\.booksData\?\.\[bookId\]/.test(withdrawalPdfFunction)
    || !/book\.inventario/.test(withdrawalPdfFunction)
    || !/book\.titolo/.test(withdrawalPdfFunction)
    || !/book\.autore/.test(withdrawalPdfFunction)
    || !/splitTextToSize/.test(withdrawalPdfFunction)
    || !/doc\.text\('Firma', signatureCenter/.test(withdrawalPdfFunction)) {
  failed = true;
  console.error('FAIL Il PDF di ritiro non usa la copia dei dettagli dei volumi o non gestisce testi lunghi.');
}
const phpReservationPdfFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_generate_reservation_pdf'),
  mainPhp.indexOf('function scarto_generate_pdf_content')
);
if (!/array_chunk\(\$content_lines, (?:[1-4]?\d|50)\)/.test(phpReservationPdfFunction)
    || !/\/Type \/Pages \/Kids/.test(phpReservationPdfFunction)
    || !/Pagina .* di /.test(phpReservationPdfFunction)
    || /if \(\$y < 50\) break/.test(phpReservationPdfFunction)) {
  failed = true;
  console.error('FAIL Il fallback PDF PHP puo troncare le prenotazioni con molti volumi.');
}
const reserveLimitFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_api_reserve'),
  mainPhp.indexOf('function scarto_api_confirm_reservation')
);
const createReservationFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_create_verified_reservation'),
  mainPhp.indexOf('function scarto_get_existing_reservation_response')
);
if (!/scarto_reservation_pdf_payload\(\$pdf_path, \$code\)/.test(createReservationFunction)
    || !/scarto_send_notification_email\(\$code, \$user, \$enriched_details, \$now, true, \$pdf_path\)/.test(createReservationFunction)
    || !/downloadReservationPdfPayload\(reservationPdf\)/.test(frontend)
    || !/'prenotazione_' \. sanitize_file_name\(\$code\) \. '\.pdf'/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Allegato email e download pubblico non condividono lo stesso PDF server-side.');
}
const gdprDeleteFunction = mainPhp.slice(
  mainPhp.indexOf('function scarto_api_gdpr_delete_admin'),
  mainPhp.indexOf("add_filter('wp_privacy_personal_data_exporters'")
);
const nativeDeletionHandler = adminPhp.slice(
  adminPhp.indexOf("add_action('admin_post_scarto_gdpr_delete_native'"),
  adminPhp.indexOf("add_action('admin_post_scarto_subject_rectify'")
);
if (/gdpr_data_deletion_admin[\s\S]+?email_hash/.test(gdprDeleteFunction)
    || !/email_without_identifier_retention/.test(gdprDeleteFunction)
    || !/operation_reference/.test(gdprDeleteFunction)
    || /privacy_subject_deletion_authorized[\s\S]+?subject_email/.test(nativeDeletionHandler)) {
  failed = true;
  console.error('FAIL L’audit successivo alla cancellazione puo reintrodurre un identificatore personale.');
}
if (!/scarto_is_email_rate_limit_exempt/.test(mainPhp)
    || !/rate_limit_email_exemptions/.test(mainPhp)
    || !/\$email_allowed = \$email_exempt\s*\|\|/.test(reserveLimitFunction)
    || !/\$global_allowed = scarto_rate_limit_consume/.test(reserveLimitFunction)
    || !/\$ip_allowed = scarto_rate_limit_consume/.test(reserveLimitFunction)
    || !/if \(!\$staff_created && !\$email_exempt && !scarto_rate_limit_consume\('reserve_email_'/.test(createReservationFunction)
    || !/if \(!\$staff_created && !\$email_exempt && \$active_reservations >=/.test(createReservationFunction)) {
  failed = true;
  console.error('FAIL Le eccezioni email non sono confinate ai soli contatori associati all email.');
}
const reserveBlocklistPosition = reserveLimitFunction.indexOf('scarto_get_email_blocklist_entry($email)');
const reserveMailPosition = reserveLimitFunction.indexOf('scarto_send_mail_with_status($email');
const createBlocklistPosition = createReservationFunction.indexOf('scarto_get_email_blocklist_entry($normalized_email)');
const createExemptionPosition = createReservationFunction.indexOf('scarto_is_email_rate_limit_exempt($normalized_email)');
if (!/function scarto_sanitize_email_blocklist/.test(mainPhp)
    || reserveBlocklistPosition < 0
    || reserveMailPosition < 0
    || reserveBlocklistPosition > reserveMailPosition
    || createBlocklistPosition < 0
    || createExemptionPosition < 0
    || createBlocklistPosition > createExemptionPosition
    || !/reservation_blocklist_rejected/.test(mainPhp)) {
  failed = true;
  console.error('FAIL La blacklist email non precede OTP, conferma ed eccezioni per email.');
}
if (!/function scarto_persist_email_control_settings/.test(mainPhp)
    || !/scarto_rate_limit_email_exemptions/.test(mainPhp)
    || !/scarto_reservation_email_blocklist/.test(mainPhp)
    || (mainPhp.match(/scarto_persist_email_control_settings\(/g) || []).length < 2
    || !/array_key_exists\(\$key, \$saved\)/.test(mainPhp)
    || !/scarto_persist_email_control_settings\(\$settings\)/.test(fs.readFileSync('includes/admin.php', 'utf8'))
    || !/scarto_rate_limit_email_exemptions/.test(fs.readFileSync('uninstall.php', 'utf8'))
    || !/scarto_reservation_email_blocklist/.test(fs.readFileSync('uninstall.php', 'utf8'))) {
  failed = true;
  console.error('FAIL Whitelist e blacklist non sono migrate, salvate o eliminate in modo coerente.');
}
const userMailFunction = mainPhp.slice(
  mainPhp.indexOf('// Email to user with PDF attachment'),
  mainPhp.indexOf('function scarto_generate_reservation_pdf')
);
if (!/Presentarsi presso/.test(userMailFunction)
    || !/Mostrare questo codice/.test(userMailFunction)
    || !/Ritirare i libri/.test(userMailFunction)
    || /Presentati presso|Mostra questo codice|Ritira i libri/.test(userMailFunction)) {
  failed = true;
  console.error('FAIL Le istruzioni email non usano la forma impersonale richiesta.');
}

const auditAdminPhp = fs.readFileSync('includes/audit-admin.php', 'utf8');
if (!/SCARTO_CAP_PRIVACY/.test(auditAdminPhp)
    || !/subject_email/.test(auditAdminPhp)
    || !/scarto-librario-statistiche/.test(auditAdminPhp)
    || /\bverification_code\b|\btoken_hash\b|SELECT[^;]+\bpayload\b/is.test(auditAdminPhp)) {
  failed = true;
  console.error('FAIL Il registro amministrativo espone segreti o non applica capability e statistiche previste.');
}
if (!/admin_post_scarto_export_audit_log/.test(auditAdminPhp)
    || !/check_admin_referer\('scarto_export_audit_log'\)/.test(auditAdminPhp)
    || !/scarto_audit_build_filters\(\$_POST\)/.test(auditAdminPhp)
    || !/scarto_csv_safe_cell/.test(auditAdminPhp)
    || !/admin_post_scarto_export_statistics/.test(auditAdminPhp)
    || !/Dati aggregati/.test(auditAdminPhp)) {
  failed = true;
  console.error('FAIL Esportazione filtrata dei log o statistiche aggregate non protetta/completa.');
}
if (!/\['week', '14', '30', '60', 'all'\]/.test(auditAdminPhp)
    || !/Settimana corrente/.test(auditAdminPhp)
    || !/class="scarto-combination-svg"/.test(auditAdminPhp)
    || !/'class' => 'orders-bar'/.test(auditAdminPhp)
    || !/'class' => 'deliveries-bar'/.test(auditAdminPhp)
    || !/\$delivered_top = 52/.test(auditAdminPhp)
    || !/\$reserved_top = 330/.test(auditAdminPhp)
    || !/volumi_prenotati/.test(auditAdminPhp)
    || !/volumi_consegnati/.test(auditAdminPhp)
    || !/Mostra i dati del grafico/.test(auditAdminPhp)) {
  failed = true;
  console.error('FAIL Il grafico combinato o i periodi statistici richiesti non sono completi.');
}
if (!/const ReservationCountdown/.test(frontend)
    || !/seconds: number/.test(frontend)
    || !/setInterval\(\(\) => setNow\(Date\.now\(\) \+ serverClockOffsetMs\), 1000\)/.test(frontend)
    || (frontend.match(/syncServerClock\(data\.serverTime\)/g) || []).length < 3
    || /Math\.ceil\(diff \/ \(1000 \* 60 \* 60 \* 24\)\)/.test(frontend)) {
  failed = true;
  console.error('FAIL Il countdown delle prenotazioni non e aggiornato al secondo e sincronizzato con il server.');
}
if (!/'status' => \['type' => 'string', 'default' => 'all', 'enum' => \['all', 'active'\]\]/.test(fs.readFileSync('includes/rest-schema.php', 'utf8'))
    || !/\$status === 'active'/.test(mainPhp)
    || !/Solo pendenti/.test(frontend)
    || !/staffStatusFilter/.test(frontend)
    || !/applyStaffStatusFilter/.test(frontend)
    || !/mustReloadDirectly/.test(frontend)
    || !/aria-pressed=\{staffStatusFilter/.test(frontend)) {
  failed = true;
  console.error('FAIL Il filtro server-side delle prenotazioni pendenti non e completo.');
}

const dataToolsPhp = fs.readFileSync('includes/data-tools.php', 'utf8');
const backupExportFunction = dataToolsPhp.slice(
  dataToolsPhp.indexOf('function scarto_backup_export_data'),
  dataToolsPhp.indexOf('function scarto_backup_document')
);
if (!/SCARTO_CAP_PRIVACY/.test(dataToolsPhp)
    || (dataToolsPhp.match(/check_admin_referer\('scarto_(?:export|import)_backup'\)/g) || []).length !== 2
    || !/scarto_backup_verify_step_up/.test(dataToolsPhp)
    || !/hash_equals/.test(dataToolsPhp)
    || !/is_uploaded_file/.test(dataToolsPhp)
    || !/SCARTO_BACKUP_MAX_BYTES/.test(dataToolsPhp)
    || !/START TRANSACTION/.test(dataToolsPhp)
    || !/ROLLBACK/.test(dataToolsPhp)
    || !/scarto_backup_verify_transaction_storage/.test(dataToolsPhp)
    || !/scarto_backup_sanitize_settings/.test(dataToolsPhp)
    || /db_admin_password_hash|token_hash|reservation_verifications/.test(backupExportFunction)) {
  failed = true;
  console.error('FAIL Backup/ripristino non applica capability, nonce, step-up, validazione e transazione richiesti.');
}
if (!/function scarto_backup_legacy_import_allowed/.test(dataToolsPhp)
    || !/SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS/.test(dataToolsPhp)
    || !/Sono ammessi soltanto backup cifrati/.test(dataToolsPhp)
    || !/\$legacy_import_allowed \? '' : 'required'/.test(dataToolsPhp)) {
  failed = true;
  console.error('FAIL I backup legacy non cifrati non sono disabilitati per default.');
}

const runtimeLabels = [
  mainPhp,
  fs.readFileSync('includes/admin.php', 'utf8'),
  fs.readFileSync('includes/diagnostics.php', 'utf8'),
  dataToolsPhp,
].join('\n');
if (/Password sicurezza database|Password Admin DB/i.test(runtimeLabels)
    || !/Password di sicurezza del plugin/.test(runtimeLabels)) {
  failed = true;
  console.error('FAIL La password aggiuntiva e ancora presentata come credenziale del database.');
}
if (/\b(?:localStorage|sessionStorage)\b/.test(frontend)) {
  failed = true;
  console.error('FAIL Il client usa storage browser per stato o identità.');
}
if (!/headers\['X-WP-Nonce'\]/.test(frontend) || !/if \(IS_WP_ADMIN\)/.test(frontend)) {
  failed = true;
  console.error('FAIL Il client amministrativo non invia il nonce o non è separato dal flusso pubblico.');
}

const secretPattern = /(?:sk-(?:proj-)?[A-Za-z0-9_-]{16,}|OPENAI_API_KEY\s*[:=]|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|client_secret\s*[:=]\s*["'][^"']{8,})/i;
const secretFiles = [
  'gestione-scarto-librario.php',
  'includes/admin.php',
  'includes/security.php',
  'templates/app.php',
  'src/index.tsx',
];
for (const file of secretFiles) {
  if (secretPattern.test(fs.readFileSync(file, 'utf8'))) {
    failed = true;
    console.error(`FAIL Possibile segreto incorporato in ${file}.`);
  }
}
if (!/sanitizeSpreadsheetCell/.test(frontend) || !/\^\[=\+\\-@\\t\\r\]/.test(frontend)) {
  failed = true;
  console.error('FAIL L’export Excel non neutralizza i valori simili a formule.');
}
if (!/10 \* 1024 \* 1024/.test(frontend)
    || !/rows\.length > 50000/.test(frontend)
    || !/stableRowHash/.test(frontend)
    || !/inventario ripetuto mantenute come volumi distinti/.test(frontend)
    || !/await prepareCatalogFile\(file\)[\s\S]+?setImportModalOpen\(true\)/.test(frontend)) {
  failed = true;
  console.error('FAIL L’import Excel non applica limiti, ID deterministici o validazione prima della password.');
}
if (!/getCatalogAvailability/.test(frontend)
    || !/void refreshAvailability\(\)/.test(frontend)
    || /setInterval\([\s\S]{0,500}?loadData\(false\)/.test(frontend)
    || /};\s*(?:void\s+)?tick\(\);\s*const interval = setInterval/.test(frontend)) {
  failed = true;
  console.error('FAIL Il polling puo interrompere una sessione pubblica o mostrare il caricamento in background.');
}

const uninstallPhp = fs.readFileSync('uninstall.php', 'utf8');
if (!/delete_data_on_uninstall/.test(uninstallPhp)
    || !/if \(\$delete_data\)/.test(uninstallPhp)) {
  failed = true;
  console.error('FAIL La disinstallazione non preserva i dati senza consenso esplicito.');
}

const readBuiltJavaScript = directory => fs.existsSync(directory)
  ? fs.readdirSync(directory)
    .filter(file => file.endsWith('.js'))
    .map(file => fs.readFileSync(`${directory}/${file}`, 'utf8'))
    .join('\n')
  : '';

const publicJavaScript = readBuiltJavaScript('dist/public/assets');
const adminJavaScript = readBuiltJavaScript('dist/admin/assets');
const allBuiltJavaScript = `${publicJavaScript}\n${adminJavaScript}`;
if (allBuiltJavaScript) {
  if (/\/(?:login|session|logout|change-password|recover-password)(?:["'`]|\b)/.test(allBuiltJavaScript)) {
    failed = true;
    console.error('FAIL Il bundle distribuito espone riferimenti alle route staff rimosse.');
  }
  if (secretPattern.test(allBuiltJavaScript) || /\b(?:localStorage|sessionStorage)\b/.test(allBuiltJavaScript)) {
    failed = true;
    console.error('FAIL Un bundle distribuito contiene un possibile segreto o storage browser non ammesso.');
  }
}
if (publicJavaScript) {
  if (/\/(?:orders|status|admin\/settings|books|reset|purge-all-data|run-cleanup|gdpr\/(?:export|delete))(?:["'`]|\b)/.test(publicJavaScript)) {
    failed = true;
    console.error('FAIL Il bundle pubblico contiene chiamate alle API amministrative.');
  }
}
if (!/ReservationConflictError/.test(frontend) || !/Sono stati rimossi dal carrello/.test(frontend)) {
  failed = true;
  console.error('FAIL Il frontend non gestisce in modo esplicito i libri divenuti indisponibili.');
}
if (!/refreshCatalog=\{refreshCatalog\}/.test(frontend) || !/void refreshCatalog\(\)/.test(frontend)) {
  failed = true;
  console.error('FAIL Il catalogo non viene riallineato dopo un conflitto di prenotazione.');
}
if (!/register_rest_route\(\$ns, '\/catalog\/availability'/.test(mainPhp)
    || !/function scarto_api_catalog_availability\(\)/.test(mainPhp)
    || !/getCatalogAvailability/.test(frontend)
    || !/availabilityRequestSequenceRef/.test(frontend)
    || !/}, 60000\);/.test(frontend)) {
  failed = true;
  console.error('FAIL Lo snapshot leggero della disponibilita o la protezione da risposte obsolete e assente.');
}
if (!/idx_request_id['"]?\) !== false/.test(mainPhp)) {
  failed = true;
  console.error('FAIL Il duplicato idempotente non viene distinto dalla collisione del codice ordine.');
}

const releaseBuilder = fs.readFileSync('tools/build-release.mjs', 'utf8');
if (!/forbiddenEntryPatterns/.test(releaseBuilder)
    || !/forbiddenContentPatterns/.test(releaseBuilder)
    || !/releaseDate/.test(releaseBuilder)
    || !/mode: 0o644/.test(releaseBuilder)
    || !/RELEASE-MANIFEST\.json/.test(releaseBuilder)) {
  failed = true;
  console.error('FAIL La pipeline ZIP non applica esclusioni sensibili e metadati deterministici.');
}

if (failed) process.exit(1);
console.log('OK controlli di sicurezza statici');
