# Rapporto di esecuzione audit 9.4.7

**Data:** 20 agosto 2026  
**Baseline preservata:** 9.4.6  
**Esito locale:** superato  
**Esito ambiente WordPress reale:** da collaudare

## Interventi completati

- Rimossi fingerprint email/IP ridondanti dai nuovi dettagli di audit e introdotta sanitizzazione ricorsiva di email, token, OTP, password e recapiti.
- Aggiunta bonifica storica versionata in lotti da 250 record, con cursore, lock, WP-Cron, diagnostica e ripresa autorizzata tramite password del plugin.
- Completati export diretti e WordPress con tutti i log correlati, IP e User-Agent entro la retention, senza limiti silenziosi.
- Impedita la reintroduzione di email o fingerprint dopo cancellazione; i nuovi log usano un riferimento operativo casuale e conteggi.
- Rimossi callback, sessioni, cookie, schemi e allowlist del login proprietario non più attivo. Account WordPress, capability, nonce e password aggiuntiva restano invariati.
- Limitata la lettura Excel a una riga oltre il massimo consentito e registrata la provenienza del pacchetto SheetJS.
- Preparato un modulo site-specific separato e disattivato per default per REST utenti e XML-RPC. Non entra nello ZIP installabile.

Non sono state modificate tabelle, chiavi, catalogo, prenotazioni, impostazioni, ruoli o capability. L'aggiornamento non esegue reset e la migrazione dei log non gira durante l'attivazione: viene pianificata e procede a piccoli lotti.

## Evidenze locali

- `npm run check`: PHP, analisi statica, backup cifrato, bonifica audit, Excel, TypeScript, `npm audit` e build superati.
- `npm audit`: zero vulnerabilità note nelle dipendenze installate.
- `npm run test:security:smoke:self`: 17 controlli superati, incluse API pubbliche, CORS, cache privata, route staff anonime, JSON e CSP.
- Build Vite completata per bundle pubblico e amministrativo; `dist/` rigenerato dai sorgenti TypeScript.

## Gate esterno obbligatorio

Prima della produzione restano da eseguire su staging: aggiornamento sopra una copia dei dati, accessi per Operatore/Responsabile/admin, OTP-email-PDF, prenotazione online e in sede, concorrenza, export/cancellazione nei tre percorsi, backup/ripristino e rollback cartella. Le protezioni REST utenti e XML-RPC devono essere abilitate una alla volta solo dopo questi test, con WinSCP e una sessione amministrativa di emergenza disponibili.

L'assenza di tali prove esterne non viene rappresentata come garanzia assoluta: i controlli locali dimostrano coerenza del pacchetto, non il comportamento specifico dell'hosting, SMTP, cron, cache o integrazioni del sito.
