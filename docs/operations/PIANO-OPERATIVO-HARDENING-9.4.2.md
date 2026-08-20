# Piano operativo di hardening e messa in sicurezza

**Plugin:** Gestione Scarto Librario  
**Versione di riferimento:** 9.4.2  
**Data:** 19 agosto 2026  
**Stato:** completato lato plugin nella candidata 9.4.4; hardening infrastrutturale e collaudo live restano a carico del gestore del sito

## 1. Obiettivo e perimetro

Il piano riduce la superficie di attacco del plugin e dell'installazione WordPress istituzionale senza alterare catalogo, prenotazioni, impostazioni o accesso amministrativo. Comprende codice del plugin, configurazione WordPress/Apache, gestione operativa, test e rilascio.

La configurazione a due fattori non rientra in questa fase. La sua assenza resta un rischio organizzativo da rivalutare, ma non devono essere installati o attivati sistemi 2FA durante l'esecuzione del presente piano.

## 2. Regola inderogabile antilockout

Qualsiasi modifica a login, ruoli, capability, REST API, XML-RPC, `.htaccess`, `wp-config.php`, WAF, IP allowlist o permessi filesystem deve rispettare questo gate:

1. Creare backup completo di file e database e verificarne leggibilita e data.
2. Conservare il pacchetto ZIP 9.4.2 e una copia della cartella plugin funzionante.
3. Verificare prima dell'intervento un accesso alternativo con WinSCP/SFTP o pannello hosting che consenta di rinominare la cartella del plugin e ripristinare `.htaccess`/`wp-config.php`.
4. Mantenere aperta una sessione amministratore gia verificata e collaudare il nuovo accesso in una seconda finestra privata. Non disconnettere la sessione di emergenza finche il test non e concluso.
5. Applicare una sola modifica di accesso per volta. Vietati interventi simultanei su endpoint utenti, XML-RPC, WAF e ruoli.
6. Non rinominare automaticamente account, non rimuovere capability agli amministratori esistenti e non bloccare globalmente `/wp-json/` o `wp-admin`.
7. Definire prima il rollback, il responsabile e il tempo massimo di verifica. In caso di `403`, `500`, loop di login o perdita dell'area admin, ripristinare immediatamente la singola modifica.
8. Ripetere il test con amministratore, Responsabile Scarto Librario, Operatore Scarto Librario e visitatore anonimo.

## 3. Priorita P1: modifiche al plugin

### 3.1 Cache delle risposte amministrative

- Aggiungere `/scarto/v1/admin/reservations` e `/scarto/v1/admin/reservations/resend` all'elenco centralizzato delle route private in `includes/security.php`.
- Applicare `Cache-Control: no-store, no-cache, must-revalidate, private`, `Pragma: no-cache`, `Expires: 0` e `Vary: Cookie, X-WP-Nonce` anche alle risposte di errore.
- Testare tramite browser e `curl` risposte `200`, `400`, `403`, `409`, `429` e `500` simulato.

**Accettazione:** nessuna risposta amministrativa o contenente codice prenotazione risulta memorizzabile da browser, proxy o CDN.

### 3.2 Capability per cancellazione globale

- Separare l'autorizzazione di `/purge-all-data` da quella del catalogo.
- Richiedere `SCARTO_CAP_PRIVACY`, nonce REST, stessa origine, password di sicurezza del plugin e conferma esplicita.
- Mantenere `/reset` coerente con la finalita dichiarata e documentare esattamente quali dati elimina.

**Antilockout:** non modificare l'assegnazione delle capability ai ruoli esistenti nello stesso rilascio; cambiare solo il controllo della route e verificare prima che amministratore e Responsabile possiedano `SCARTO_CAP_PRIVACY`.

### 3.3 Denominazione della password aggiuntiva

- Sostituire nelle interfacce la dicitura “Password sicurezza database” con “Password di sicurezza del plugin”.
- Precisare che non e la password MySQL di WordPress e che nel database viene conservato solo un hash bcrypt.
- Non rinominare la chiave tecnica `scarto_db_admin_password_hash` in questo rilascio, evitando migrazioni inutili.

### 3.4 Backup legacy non cifrati

- Inventariare eventuali backup storici non cifrati e convertirli in copie cifrate verificate.
- Dopo approvazione e test, rifiutare in produzione gli import non racchiusi nel formato cifrato del plugin.
- Conservare un percorso di migrazione solo su staging o tramite procedura straordinaria autorizzata e registrata.

### 3.5 Artefatti sensibili e pacchetto

- Tenere fuori dalla cartella distribuibile file Excel reali, esportazioni JSON, password, log, screenshot con dati e documenti riservati.
- Estendere il controllo di rilascio affinche fallisca se trova `*.xlsx`, backup `*.json`, `*.log`, file di password, `.env`, chiavi private o dump SQL.
- Generare ZIP deterministico con una sola directory radice e pubblicare SHA-256.

## 4. Priorita P1: hardening WordPress e server

### 4.1 Profili utenti pubblici

- Verificare se il sito necessita degli archivi autore e dell'endpoint `/wp-json/wp/v2/users`.
- Se non necessari, limitare la collezione utenti ai soggetti autenticati senza bloccare l'intera REST API.
- Se necessari, usare nomi pubblici e slug che non coincidano con il login; sostituire il nome visualizzato “admin”.

**Antilockout:** prima testare in staging Elementor, editor, pagine autore e plugin dipendenti dalla REST API; non introdurre regole Apache generiche su `/wp-json/`.

### 4.2 XML-RPC

- Censire Jetpack, applicazioni WordPress, pingback e pubblicazione remota.
- Disabilitare XML-RPC solo se nessuna funzione lo usa; altrimenti applicare rate limit e monitoraggio a `/xmlrpc.php`.

**Antilockout:** il blocco deve essere reversibile con una singola modifica e non deve interessare `wp-login.php`, REST API o cron.

### 4.3 File e configurazione

- Impostare `DISALLOW_FILE_EDIT` in `wp-config.php` dopo backup e prova di accesso SFTP.
- Negare esplicitamente via Apache l'accesso a `wp-config.php`, `.env`, `.git`, log e dump; rimuovere `readme.html`.
- Verificare permessi minimi compatibili con hosting e aggiornamenti, evitando modifiche ricorsive non testate.
- Confermare `WP_DEBUG_DISPLAY=false` in produzione e che eventuali log non siano pubblici.

### 4.4 WAF e anti-abuso

- Applicare soglie dedicate agli endpoint `/scarto/v1/`, iniziando in modalita monitoraggio.
- Non usare IP allowlist per l'intero `wp-admin` senza accesso di emergenza indipendente.
- Accettare header proxy solo dagli indirizzi dei proxy realmente fidati e impedire accesso diretto all'origine quando applicabile.
- Non registrare corpi REST, OTP, password o indirizzi completi nei log WAF.

## 5. Priorita P2: verifica applicativa e privacy

- Confermare con il RPD finalita, base giuridica, informativa, necessita di DPIA, blacklist, periodi di conservazione e procedura per i diritti.
- Approvare valori di retention prima di selezionare l'attestazione nel pannello.
- Formalizzare titolare, autorizzati, amministratore di sistema, hosting, posta, protocollo e relativi accordi/responsabilita.
- Verificare che le prenotazioni online raccolgano nome, cognome ed email; il domicilio deve essere ammesso solo per prenotazioni in sede prive di email.
- Documentare custodia e trasferimento dei PDF, lettere protocollate, backup scaricati ed esportazioni dell'interessato.
- Definire una procedura di incidente: triage, conservazione evidenze, contenimento, valutazione della violazione e coinvolgimento del RPD.

## 6. Matrice minima di collaudo

| Area | Prove obbligatorie | Esito atteso |
|---|---|---|
| Accessi | anonimo, Operatore, Responsabile, amministratore | minimo privilegio, nessun lockout |
| REST | nonce mancante/errato, origine ostile, payload inatteso | `403`/`400`, nessuna modifica |
| Prenotazioni | OTP valido/errato/scaduto, concorrenza, doppio click | idempotenza e nessuna doppia assegnazione |
| Privacy | export, rettifica, limitazione, cancellazione | motivazione, audit e dati completi |
| Import | file valido, duplicati, 50.000 righe, prenotazioni attive | transazione e report comprensibile |
| Backup | export cifrato, password errata, file alterato, ripristino | rifiuto sicuro o round trip integro |
| Cache | risposte private corrette e di errore | sempre `no-store` |
| Cleanup | cron e avvio manuale autorizzato | conteggi e data aggiornati |
| Frontend | desktop/mobile, tastiera, 200% zoom | flusso completabile e feedback visibile |
| Rollback | rinomina plugin e ripristino configurazione | area admin recuperabile |

## 7. Sequenza di rilascio

1. Congelare e archiviare la 9.4.2 come baseline immutabile.
2. Implementare le modifiche P1 del plugin in una nuova versione, senza sovrascrivere lo ZIP esistente.
3. Eseguire `npm run check`, smoke test, test concorrenza e scansione dei segreti.
4. Collaudare upgrade su staging con copia anonimizzata e backup cifrato.
5. Applicare hardening server uno step alla volta seguendo il gate antilockout.
6. Registrare risultati, intestazioni HTTP, ruoli, screenshot e hash dell'artefatto.
7. Installare in finestra presidiata con WinSCP/SFTP disponibile e rollback gia preparato.
8. Monitorare per almeno 48 ore errori PHP, email, `403`, `429`, cron e tempi di risposta.

## 8. Criteri di chiusura

Il piano puo essere dichiarato completato solo quando non esistono blocchi critici, il ripristino e stato provato, le route private non sono memorizzabili, i ruoli sono verificati, i dati pubblici corrispondono all'allowlist, le decisioni privacy sono approvate e ogni intervento infrastrutturale ha un rollback documentato. L'assenza di 2FA deve rimanere nel registro dei rischi come decisione temporanea esplicita.
