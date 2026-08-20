# Piano operativo post-audit sicurezza e privacy

**Plugin:** Gestione Scarto Librario  
**Versione di riferimento:** 9.4.6  
**Release candidata:** 9.4.7  
**Data:** 20 agosto 2026  
**Stato:** implementazione locale completata nella 9.4.7; collaudo autenticato e attivazioni server in attesa dell'ambiente reale  
**2FA:** esclusa dalla fase corrente per decisione del committente

## 1. Obiettivo e perimetro

Il piano tratta esclusivamente le criticita confermate mediante confronto tra audit, codice 9.4.6 e verifiche in sola lettura del sito istituzionale. Gli obiettivi sono:

- eliminare dai log i fingerprint email non necessari e rendere coerenti export e cancellazione dell'interessato;
- rimuovere il codice storico di autenticazione del plugin, oggi non raggiungibile ma fuorviante;
- ridurre l'enumerazione pubblica degli utenti WordPress e la superficie XML-RPC senza interferire con amministrazione, Elementor, REST API o plugin;
- rafforzare manutenzione e test dell'importazione Excel;
- completare le decisioni organizzative su retention, log infrastrutturali e backup.

Non sono emerse vulnerabilita critiche o alte direttamente sfruttabili nel plugin. Il piano non autorizza modifiche distruttive, reset del database o perdita di catalogo, prenotazioni, impostazioni e log legittimi.

## 2. Regola inderogabile antilockout

Ogni modifica a login, capability, REST API, XML-RPC, `.htaccess`, `wp-config.php`, WAF o permessi deve rispettare questo gate:

1. Creare un backup cifrato del plugin e un backup completo di file e database; verificare data, dimensione e leggibilita.
2. Conservare ZIP 9.4.6, cartella plugin funzionante e copie originali dei file server modificati.
3. Verificare prima dell'intervento l'accesso WinSCP/SFTP o al pannello hosting e la possibilita di rinominare plugin o file di hardening.
4. Mantenere aperta una sessione amministratore gia funzionante e collaudare in una seconda finestra privata.
5. Applicare una sola modifica di accesso per volta. Non intervenire contemporaneamente su REST utenti, XML-RPC, ruoli e WAF.
6. Non bloccare globalmente `/wp-json/`, `wp-admin`, `wp-login.php` o le route `/scarto/v1/`.
7. Definire prima di ogni intervento responsabile, test, tempo massimo e rollback. In presenza di `403` imprevisti, `500`, loop di login o perdita dell'editor, ripristinare subito la singola modifica.
8. Verificare visitatore anonimo, Operatore Scarto Librario, Responsabile Scarto Librario e amministratore WordPress.

## 3. Priorita P1: cancellazione ed export dei dati di log

### 3.1 Eliminare i fingerprint ridondanti

Il campo protetto `subject_email` e sufficiente per ricerca, audit ed export fino alla cancellazione. I fingerprint HMAC presenti in `details.email_hash` sono ridondanti e mantengono una correlabilita non necessaria.

Interventi:

- rimuovere `email_hash` dai dettagli prodotti da prenotazioni, richieste GDPR, export ed eraser;
- estendere `scarto_sanitize_audit_details()` affinche rifiuti ricorsivamente chiavi come `email_hash`, `email_fingerprint`, `mail_hash` e varianti equivalenti;
- vietare email, token, OTP, password, indirizzi e hash identificativi sia come valore sia come `entity_id`;
- conservare nei log successivi alla cancellazione soltanto riferimento operativo casuale, tipo di operazione, esito e conteggi aggregati;
- non inserire un nuovo fingerprint in `scarto_perform_gdpr_delete()` o `scarto_wp_privacy_eraser()` dopo l'anonimizzazione.

**Criterio di accettazione:** cercando l'email cancellata o ricalcolandone il fingerprint non deve esistere alcun valore corrispondente nelle tabelle del plugin, fatta salva una blacklist legittimamente mantenuta e soggetta a riesame.

### 3.2 Rendere completo l'export prima della cancellazione

Gli eventi relativi a una persona devono essere collegati tramite `subject_email`, non tramite hash nei dettagli. Prima dell'anonimizzazione devono quindi risultare esportabili con IP e User-Agent ancora presenti entro la retention.

Interventi:

- passare `subject_email` al logger per richiesta/verifica GDPR, invio OTP, export e operazioni correlate;
- verificare che `scarto_get_subject_audit_metadata()` includa categoria, azione, esito, IP, User-Agent, data, entita e utente WP senza limite silenzioso che tronchi dati dovuti;
- documentare chiaramente quando IP e User-Agent risultano gia anonimizzati per scadenza;
- mantenere fuori dall'export password, OTP, token e payload cifrati non pertinenti;
- aggiungere un controllo che segnali un export parziale se il limite tecnico degli eventi viene raggiunto.

**Criterio di accettazione:** un account di prova deve ottenere prenotazioni, richieste temporanee, log correlati, IP e User-Agent; dopo cancellazione gli stessi identificatori non devono piu essere ricercabili.

### 3.3 Bonificare i log storici senza cancellare informazioni operative

La bonifica deve rimuovere soltanto chiavi identificative ridondanti dai JSON in `details`, preservando azione, esito, conteggi e riferimenti non personali.

Procedura tecnica:

- introdurre una migrazione versionata e ripetibile, elaborata in lotti limitati, ad esempio 250 righe per esecuzione;
- decodificare `details`, rimuovere ricorsivamente le chiavi bloccate e aggiornare solo le righe realmente cambiate;
- memorizzare cursore, stato, righe esaminate, righe corrette ed errori senza registrare dati personali;
- mostrare in diagnostica stato `non avviata`, `in corso`, `completata` o `errore`;
- non eseguire una scansione di 50.000 righe durante attivazione o login amministratore;
- consentire ripresa sicura tramite WP-Cron e comando manuale autorizzato con password del plugin.

**Rollback:** il backup cifrato pre-migrazione consente il recupero. Il rollback binario alla 9.4.6 resta compatibile, ma non deve reintrodurre volontariamente fingerprint rimossi.

## 4. Priorita P1: rimozione dell'autenticazione storica

La versione attuale usa esclusivamente account WordPress, capability dedicate e nonce REST. Le vecchie funzioni di login, sessione cookie e recupero password non hanno route registrate, ma aumentano la superficie futura e generano audit errati.

Interventi:

- eliminare callback non raggiungibili di login, sessione, logout, recupero e reset della vecchia password amministrativa;
- rimuovere `SCARTO_SESSION_COOKIE`, creazione/lettura/distruzione delle sessioni transient e header `X-Scarto-CSRF` non utilizzati;
- eliminare schemi e allowlist relativi alle route storiche non registrate;
- rimuovere chiamate a invalidazione di sessioni custom senza modificare le sessioni WordPress;
- non modificare `scarto_db_admin_password_hash`, che resta la password aggiuntiva attiva per import, reset, backup e privacy;
- mantenere temporaneamente vuota la tabella legacy dei recovery token per compatibilita di rollback; valutarne la rimozione solo in una futura migrazione esplicita;
- aggiornare documentazione e diagrammi affinche descrivano account WordPress, capability, nonce e password aggiuntiva, non un login autonomo.

**Antilockout:** ruoli e capability non devono cambiare in questa release. Prima della rimozione verificare che ogni route amministrativa attiva passi da `scarto_verify_wp_admin_capability()` o da una sua specializzazione.

**Criterio di accettazione:** nessuna route login del namespace Scarto deve essere registrata; amministratore, Responsabile e Operatore devono mantenere esattamente gli accessi previsti.

## 5. Priorita P1: hardening del sito WordPress

Queste attivita non devono essere inserite nello ZIP del plugin. Devono essere isolate in configurazione Apache o in un piccolo componente site-specific reversibile.

### 5.1 Limitare l'enumerazione utenti anonima

Situazione verificata: `/wp-json/wp/v2/users` espone ID, nome pubblico e slug di tre profili.

Sequenza:

1. Censire Elementor, editor a blocchi, archivi autore e integrazioni che usano `wp/v2/users`.
2. In staging negare soltanto agli anonimi le route `/wp/v2/users` e `/wp/v2/users/<id>`.
3. Non rimuovere le route per utenti autenticati e non bloccare l'intera REST API.
4. Verificare anche pagine autore, feed e dati autore incorporati negli articoli, perche il blocco della sola collezione REST non elimina ogni nome pubblico.
5. Evitare che display name e slug pubblico coincidano con credenziali di login; non rinominare automaticamente gli account.

**Accettazione:** anonimo `401`/`403`; editor e amministratore autenticati funzionanti; frontend, Elementor e `/scarto/v1/` invariati.

**Rollback:** disattivare o rinominare via SFTP il solo componente site-specific.

### 5.2 Disabilitare XML-RPC se inutilizzato

Situazione verificata: XML-RPC espone, tra gli altri, `system.multicall` e `pingback.ping`.

Sequenza:

1. Verificare uso di Jetpack, app WordPress, pubblicazione remota, pingback o sistemi esterni.
2. Se non utilizzato, negare l'accesso al solo `/xmlrpc.php` tramite Apache o configurazione hosting.
3. Se necessario, mantenere XML-RPC e disabilitare almeno pingback/metodi inutili, applicando rate limit e monitoraggio.
4. Verificare che REST, cron, email, login, Elementor e frontend non siano coinvolti.

**Accettazione:** XML-RPC non disponibile o limitato secondo decisione documentata; nessun effetto su `wp-login.php` e `/wp-json/`.

### 5.3 File e header

- rimuovere o negare `readme.html`, attualmente pubblico, come riduzione del fingerprinting;
- mantenere la protezione gia efficace di `.git`, `debug.log` e configurazione PHP;
- non considerare il `200` con corpo vuoto di `wp-config.php` come esposizione del file sorgente;
- mantenere HSTS a livello server, gia presente, evitando duplicazioni nel plugin;
- verificare periodicamente CSP, `nosniff`, frame policy e assenza di cache sulle risposte private.

## 6. Priorita P2: importazione Excel e dipendenze

Il file Excel viene analizzato nel browser e il server riceve JSON validato: non esiste esecuzione PHP del foglio. Il rischio residuo riguarda parser JavaScript, consumo di memoria e file malformati.

Interventi:

- registrare provenienza, versione, licenza e SHA-256 di `vendor/xlsx-0.20.3.tgz`;
- verificare periodicamente advisory del produttore e aggiornare solo dopo test di regressione;
- mantenere il limite client di 10 MB, il limite server di 20 MB e il massimo di 50.000 righe;
- provare file vuoto, estensione falsa, archivio corrotto, ZIP ad alta espansione, shared strings molto grandi, colonne estreme, formule e caratteri non validi;
- assicurare che formule o valori che iniziano con `=`, `+`, `-` o `@` non siano eseguiti nelle successive esportazioni CSV;
- misurare memoria e tempo con cataloghi da 3.900 e 50.000 righe;
- mantenere transazione, validazione server e rollback dell'importazione.

**Accettazione:** i file ostili vengono rifiutati o provocano un errore controllato nel browser senza `500`, modifica parziale del catalogo o blocco persistente dell'amministrazione.

## 7. Priorita P2: governance, retention e backup

### 7.1 Piano di conservazione e diritti

- sottoporre al Titolare, con supporto del RPD, i periodi 365/90/90/90/30 giorni e registrarne approvazione o modifica;
- non descrivere il parere RPD come autorizzazione tecnica automatica: responsabilita e decisione restano al Titolare;
- valutare formalmente se il trattamento richieda DPIA sulla base del rischio effettivo;
- applicare il termine generale di risposta alle richieste entro un mese, con eventuale proroga motivata secondo normativa, eliminando il riferimento non fondato a 45 giorni;
- documentare l'eventuale conservazione di una blacklist dopo richiesta di cancellazione, con base, motivazione sintetica, scadenza/riesame e accesso limitato.

### 7.2 Log infrastrutturali

Censire separatamente log Apache, PHP, WordPress, WAF/CDN, SMTP, database e hosting. Per ciascuno registrare titolare tecnico, campi raccolti, accessi, ubicazione, retention, backup e procedura incidente. Vietare nei log applicativi e WAF body REST, password, OTP, token, domicilio e allegati PDF.

### 7.3 Backup e ripristino dopo cancellazioni

- conservare i backup cifrati in archivio autorizzato, con password separata e accessi nominali;
- definire retention e cancellazione delle copie locali scaricate;
- mantenere audit di download e ripristino gia previsto dal plugin;
- predisporre una procedura per riapplicare le cancellazioni avvenute dopo la data del backup prima di riaprire il servizio;
- conservare il registro delle richieste presso il Titolare con riferimenti operativi e accesso ristretto, senza aggiungere nel plugin un nuovo archivio permanente di hash email;
- provare almeno annualmente un ripristino su staging con dati anonimizzati.

## 8. Attivita espressamente escluse o da non applicare

- Nessuna 2FA in questa fase; l'assenza resta una decisione di rischio documentata.
- Nessun blocco globale di REST API o `wp-admin`.
- Nessuna allowlist IP globale per gli amministratori senza canale di emergenza indipendente.
- Nessuna rimozione di `CREATE`, `ALTER` o `DROP` all'utente WordPress ordinario senza un'architettura separata e collaudata per le migrazioni.
- Nessuna trasmissione di email di utenti o blacklist a Have I Been Pwned o altri servizi terzi.
- Nessun obbligo generico di WAF o monitoraggio 24/7: applicarli solo se proporzionati e gestiti.
- Nessun reset di catalogo, prenotazioni o impostazioni per completare il piano.

## 9. Matrice di verifica

| Area | Prova | Esito atteso |
|---|---|---|
| Audit | creare eventi con email, IP e User-Agent | export completo prima della cancellazione |
| Cancellazione | public GDPR, gestione interessati e WP eraser | nessuna email/fingerprint residuo, salvo blacklist motivata |
| Migrazione | log storici con JSON annidato | rimozione sole chiavi vietate, dati operativi preservati |
| Autenticazione | inventario route REST | nessun login custom; capability e nonce su tutte le route staff |
| Ruoli | anonimo, Operatore, Responsabile, admin | minimo privilegio e nessun lockout |
| REST utenti | anonimo e autenticato | anonimo negato; editor e integrazioni funzionanti |
| XML-RPC | GET e `system.listMethods` | bloccato o limitato come deciso |
| Prenotazioni | OTP valido/errato/scaduto, doppio click, concorrenza | nessuna regressione o doppia assegnazione |
| Excel | corpus valido e malevolo | errore controllato o import atomico |
| Backup | export, alterazione, password errata, restore | cifratura valida e ripristino coerente |
| Cache | route private, errori inclusi | sempre `no-store, private` |
| Rollback | ripristino ZIP e regola server | pannello e frontend recuperabili |

## 10. Controlli automatici e rilascio

Prima di generare la release:

1. Aggiungere test statici che falliscano se ricompaiono route login custom o chiavi `email_hash` nei dettagli di audit.
2. Aggiungere test della bonifica ricorsiva e dell'idempotenza della migrazione.
3. Eseguire `npm run check:php`, `npm run check:security`, `npm run test:offline:backup`, `npm run type-check`, `npm audit` e `npm run build`.
4. Eseguire smoke test locale e test autenticati su staging per ruoli, cancellazione, export e concorrenza.
5. Aggiornare versione in bootstrap, `package.json`, `package-lock.json`, sorgenti frontend e documentazione.
6. Rigenerare `dist/`; non modificare manualmente gli asset con hash.
7. Generare ZIP deterministico con una sola cartella radice, verificare contenuto e pubblicare SHA-256.
8. Aggiornare sorgenti e release nella cartella `Scarto Librario GitHub` soltanto dopo esito positivo.

## 11. Sequenza di distribuzione

1. Congelare 9.4.6 come baseline e preparare 9.4.7 su copia di sviluppo.
2. Implementare prima log/export/cancellazione e relativi test.
3. Rimuovere il codice storico senza cambiare ruoli, capability o password attiva.
4. Eseguire migrazione e collaudo su staging con copia anonimizzata.
5. Installare il plugin in produzione con WinSCP/SFTP disponibile e sessione di emergenza aperta.
6. Verificare prenotazione online, prenotazione in sede, OTP, email, PDF, consegna, cancellazione e backup.
7. Applicare separatamente la limitazione REST utenti e collaudarla.
8. Applicare separatamente la decisione XML-RPC e collaudarla.
9. Monitorare per almeno 48 ore errori PHP, email, `403`, `409`, `429`, cron e prestazioni.
10. Registrare esecutore, data, prove, esito e rollback di ogni fase.

## 12. Criteri di chiusura

Il piano puo essere dichiarato completato quando:

- nessun log contiene email hash o altri identificatori vietati nei dettagli;
- export e cancellazione dell'interessato sono completi e verificati su tutti e tre i percorsi;
- il codice di autenticazione storico non e piu presente nel runtime;
- utenti REST e XML-RPC rispettano la decisione documentata senza regressioni;
- corpus Excel, concorrenza, backup e rollback sono stati provati;
- retention, blacklist, log infrastrutturali e restore post-cancellazione hanno responsabile e procedura approvati;
- release, hash, verbale di test e registro delle modifiche sono archiviati.

## 13. Registro di esecuzione

| Data | Fase | Esecutore | Ambiente | Test eseguiti | Esito | Rollback verificato | Note |
|---|---|---|---|---|---|---|---|
| 20/08/2026 | Log, export e cancellazione | Codex | sviluppo locale | PHP, test bonifica, static security | Superato | Non distruttivo; nessuna variazione schema | Migrazione incrementale e recuperabile |
| 20/08/2026 | Rimozione autenticazione storica | Codex | sviluppo locale | inventario route, analisi statica, smoke anonimo | Superato | Account, ruoli e capability invariati | Password aggiuntiva attiva preservata |
| 20/08/2026 | Import Excel e dipendenze | Codex | sviluppo locale | SHA vendor, 50.000 righe, formula testuale, file malformato | Superato | Import server transazionale invariato | Limite parser applicato a 50.001 righe |
| 20/08/2026 | Build e release | Codex | sviluppo locale | `npm run check`, smoke self, release deterministica | Superato | ZIP 9.4.6 conservato | Vedere rapporto 9.4.7 |
| Da pianificare | Ruoli, concorrenza, GDPR e rollback | Responsabile tecnico | staging WordPress | test autenticati e ripristino | Non eseguito localmente | SFTP e seconda sessione obbligatori | Gate prima della produzione |
| Da pianificare | REST utenti e XML-RPC | Responsabile tecnico | staging/sito | attivazione separata del modulo opt-in | Non attivato | Costanti `false` e rollback WinSCP | Escluso dallo ZIP principale |
