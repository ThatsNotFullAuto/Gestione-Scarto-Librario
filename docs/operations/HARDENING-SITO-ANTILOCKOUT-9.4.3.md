# Hardening del sito con procedura antilockout

## Vincolo

Queste operazioni riguardano l'intera installazione WordPress e non vengono applicate dallo ZIP del plugin. Devono essere eseguite dal gestore del sito, una alla volta, con backup e accesso WinSCP/SFTP. La 2FA e esclusa dalla fase corrente.

## Gate obbligatorio

Prima di ogni modifica: backup file/database, sessione amministratore aperta, seconda finestra privata, accesso SFTP verificato, copia dei file modificati, responsabile presente e rollback scritto. Non cumulare due cambi di accesso nello stesso test.

## 1. Editor file WordPress

Inserire in `wp-config.php`, prima della riga finale di WordPress:

```php
define('DISALLOW_FILE_EDIT', true);
```

Verificare login, menu Plugin e diagnostica. Rollback: rimuovere solo la riga aggiunta tramite SFTP.

## 2. Protezione file Apache

Adattare sullo staging la configurazione Apache o `.htaccess`:

```apache
<FilesMatch "^(wp-config\.php|\.env|debug\.log|error_log|.*\.(sql|bak))$">
    Require all denied
</FilesMatch>
```

Risultato atteso: `403` o `404`, mai `200`, per i file protetti. Non usare regole ricorsive sui file PHP e non modificare i permessi in massa. Rimuovere `readme.html` dopo averne conservato una copia fuori dalla document root.

## 3. Profili utenti REST

Prima verificare se archivi autore, editor, Elementor o integrazioni usano `/wp-json/wp/v2/users`. Se non serve al pubblico, applicare una regola WordPress dedicata che neghi solo le route utenti agli anonimi. Non bloccare `/wp-json/` e non modificare le route `scarto/v1`.

Collaudo: frontend, editor, Elementor, pagine autore, REST del plugin e login. Rollback: rimuovere il solo filtro. Verificare inoltre che nome visualizzato, slug pubblico e login non coincidano e sostituire il display name “admin” senza rinominare automaticamente l'account.

## 4. XML-RPC

Censire Jetpack, applicazioni WordPress, pubblicazione remota e pingback. Se inutilizzato, bloccare solo `/xmlrpc.php`; se necessario, mantenerlo e applicare rate limit/WAF. Il test deve verificare che `wp-login.php`, REST, cron e frontend restino operativi.

## 5. WAF

Avviare in monitoraggio per almeno 48 ore. Soglie iniziali:

| Endpoint | Soglia per IP | Azione iniziale |
|---|---:|---|
| `GET /scarto/v1/catalog` | 60/minuto | log |
| `GET /scarto/v1/books/search` | 30/minuto | log |
| `POST /scarto/v1/reserve` | 5/minuto, 10/ora | log/challenge |
| `POST /scarto/v1/reserve/confirm` | 10/minuto | log |
| `POST /scarto/v1/gdpr/request` | 3/ora | log/challenge |

Passare al blocco solo dopo analisi dei falsi positivi. Non registrare body, email, OTP o password. Non applicare IP allowlist globale a `wp-admin` senza canale di emergenza indipendente.

## 6. Proxy e IP

Lasciare l'uso di `REMOTE_ADDR` se non esiste un reverse proxy. In presenza di proxy, configurare `SCARTO_TRUSTED_PROXIES` soltanto con CIDR reali del fornitore e impedire accesso diretto all'origine. Non copiare reti di esempio.

## 7. Manutenzione

- inventario mensile di WordPress, tema e plugin con versioni e stato supporto;
- aggiornamenti provati prima su staging;
- scansione vulnerabilita dell'intera installazione;
- controllo mensile di cron, cleanup, `403`, `429`, code SMTP e spazio database;
- prova periodica di ripristino, non soltanto creazione del backup;
- account nominali, password uniche e rimozione tempestiva degli accessi cessati.

## Registro esecuzione

| Data | Modifica | Esecutore | Test | Esito | Rollback disponibile |
|---|---|---|---|---|---|
|  |  |  |  |  |  |

