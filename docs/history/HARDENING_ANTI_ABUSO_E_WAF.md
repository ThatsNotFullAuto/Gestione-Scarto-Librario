# Hardening anti-abuso e WAF

## Scopo

Queste misure sono un requisito di rilascio per la versione 9.0.1. I controlli interni al plugin
limitano prenotazioni, email e tentativi, ma una richiesta arrivata a WordPress consuma comunque
risorse PHP e MySQL. WAF, CDN o reverse proxy devono quindi applicare i primi limiti.

## Regole raccomandate

Applicare le regole al percorso `/wp-json/scarto/v1/`, lasciando invariati gli altri endpoint
WordPress. Restituire `429 Too Many Requests` e non reindirizzare le richieste bloccate.

| Endpoint | Soglia iniziale per IP | Azione |
|---|---:|---|
| `GET /init` | 30/minuto, burst 10 | limita |
| `GET /catalog` | 60/minuto, burst 20 | limita |
| `GET /books/search` | 30/minuto, burst 10 | limita |
| `POST /reserve` | 5/minuto e 10/ora | limita; challenge dopo anomalie |
| `POST /reserve/confirm` | 10/minuto | limita |
| `POST /gdpr/request` | 3/ora | limita; challenge dopo anomalie |
| API amministrative | accesso normale `wp-admin`; nessuna cache | non esporre tramite cache pubblica |

Consentire per i `POST` pubblici solo `Content-Type: application/json` e una dimensione massima
di 128 KiB. Non considerare CORS o `Origin` una protezione anti-bot: client automatici possono
omettere o simulare questi header.

## Proxy e indirizzo client

Senza proxy il plugin usa `REMOTE_ADDR`. Dietro reverse proxy, definire in `wp-config.php`
`SCARTO_TRUSTED_PROXIES` con indirizzi o CIDR esatti dei soli proxy autorizzati. Abilitare
`SCARTO_TRUST_CLOUDFLARE` soltanto insieme a tale allowlist.

```php
define('SCARTO_TRUSTED_PROXIES', ['127.0.0.1', '10.20.0.0/16']);
// define('SCARTO_TRUST_CLOUDFLARE', true); // solo con CIDR Cloudflare aggiornati
define('DISALLOW_FILE_EDIT', true);
```

Il proxy deve sovrascrivere, non concatenare ciecamente, gli header provenienti da Internet.
Se si usa Cloudflare, firewall o security group devono impedire l'accesso pubblico diretto
all'origine. Non copiare intervalli IP di esempio: usare quelli correnti del fornitore.

## Monitoraggio e risposta

- Allertare su picchi di `429`, oltre 50 richieste `/reserve` in 10 minuti o crescita anomala
  delle tabelle `scarto_rate_limits` e `scarto_reservation_verifications`.
- Controllare reputazione e code SMTP per evitare che l'invio dei codici venga usato come spam.
- Conservare log WAF senza corpo delle richieste, email, indirizzi o altri dati personali.
- Predisporre una regola di blocco temporaneo per ASN, paese o fingerprint solo dopo valutazione
  operativa e privacy; non applicare blocchi permanenti basati esclusivamente sull'IP.

## Collaudo obbligatorio

Su staging verificare richieste normali, superamento delle soglie, IPv6 con indirizzi diversi
nella stessa `/64`, header `CF-Connecting-IP` da origine non fidata e richieste concorrenti con
la stessa email. Confermare che gli utenti normali completino il flusso e che i blocchi restituiscano
`429` senza creare ordini, inviare email aggiuntive o aumentare indefinitamente i contatori.

Per il limite attivo impostare temporaneamente su staging almeno 3 prenotazioni/giorno per IP ed
email e massimo 2 attive. Preparare dall'interfaccia tre richieste della stessa email su libri
distinti, senza confermarle, quindi eseguire dalla cartella del plugin:

```bash
SCARTO_BASE_URL=https://staging.example/wp-json/scarto/v1 \
SCARTO_CONFIRMATIONS='[{"requestId":"...","verificationCode":"..."},{"requestId":"...","verificationCode":"..."},{"requestId":"...","verificationCode":"..."}]' \
SCARTO_EXPECTED_ACCEPTED=2 npm run test:security:active-limit
```

Il risultato atteso è due conferme `200` e una `429 active_reservation_limit`. Ripristinare subito
i limiti operativi ed eliminare le prenotazioni e i dati personali di prova.
