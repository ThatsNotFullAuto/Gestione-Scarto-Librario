# Rapporto verifiche offline 9.4.4

**Data:** 20 agosto 2026  
**Baseline preservata:** 9.4.3  
**Candidata verificata:** 9.4.4  
**Schema database:** 8.15 invariato

## Ambito

Il rapporto documenta esclusivamente controlli riproducibili senza accesso al sito, al database WordPress, al server SMTP, al WAF o alla CDN. Non costituisce collaudo di produzione o penetration test.

## Difetto individuato e corretto

La validazione del ripristino richiedeva sempre un'email per ogni prenotazione. Le prenotazioni valide create in sede senza email e con domicilio potevano quindi essere esportate ma non reimportate. La 9.4.4 applica queste regole:

- prenotazione online: email valida obbligatoria;
- prenotazione in sede: email valida oppure domicilio;
- prenotazione in sede senza entrambi: rifiuto fail-closed;
- request ID, codice, stato, date e campi strutturati restano validati.

## Controlli eseguiti

| Controllo | Comando o metodo | Esito |
|---|---|---|
| Parsing PHP repository | `npm run check:php` | superato |
| Lint PHP CLI 8.3.6 | `php -l` su tutti i PHP escluso `node_modules` | superato |
| Regole statiche sicurezza | `npm run check:security` | superato |
| Backup e recapiti | `npm run test:offline:backup` | superato |
| TypeScript rigoroso | `npm run type-check` | superato |
| Dipendenze npm | `npm audit` | zero vulnerabilita note |
| Bundle pubblico/admin | `npm run build` | superato |
| API simulate e no-store | `npm run test:security:smoke:self` | tutte le prove superate |
| Pacchetto e manifesto | `npm run test:offline:release` | 40 file validi |
| Riproducibilita ZIP | due build isolate in directory temporanee | byte identici |
| Segreti e artefatti | allowlist e scansione del generatore | nessun file vietato nello ZIP |

Il test backup esegue un round trip AES-256-GCM, verifica il rifiuto della password errata e rileva la modifica di un byte del ciphertext. Verifica inoltre i record online e in sede, compreso il caso senza email.

## Artefatto

- file: `gestione-scarto-librario-9.4.4.zip`;
- SHA-256: `353f9cda20d369fc6a8d9188b7cb1d994e56b120cd7b8f0de49f21157e24001f`;
- struttura: una sola radice `gestione-scarto-librario/`;
- contenuto: soli PHP runtime, template, bundle, font e manifesto;
- esclusi: sorgenti di sviluppo, test, documenti, Excel, backup, log e credenziali.

## Controlli non eseguibili offline

- attivazione e upgrade dentro WordPress;
- round trip su tabelle InnoDB e opzioni reali;
- concorrenza autenticata su prenotazioni reali;
- matrice capability per ruoli effettivamente assegnati;
- consegna SMTP, OTP e reinvio riepilogo;
- cron e cleanup programmati da WP-Cron o cron di sistema;
- cache browser/CDN/proxy e regole WAF;
- compatibilita con tema, Elementor e plugin terzi;
- rollback tramite WinSCP/SFTP;
- vulnerability scan autenticato e penetration test.

Questi punti restano nel verbale di staging. Non devono essere dichiarati superati sulla base delle sole verifiche qui riportate.
