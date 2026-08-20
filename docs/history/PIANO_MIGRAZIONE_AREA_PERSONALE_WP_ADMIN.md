# Piano tecnico di migrazione dell'area personale in WordPress

**Progetto:** Gestione Scarto Librario  
**Versione di partenza:** 8.9.3 risanata  
**Versione candidata:** 9.0.1  
**Linea di sviluppo:** pannello amministrativo WordPress  
**Area di lavoro esclusiva:** `Plugin-admin-wordpress/`  
**Backup immutato:** `Plugin-risanato/`

## 1. Obiettivo e vincoli

Trasferire integralmente le funzioni riservate al personale dalla pagina pubblica al pannello
`wp-admin`, mantenendo catalogo, prenotazioni, importazione, esportazione, PDF, impostazioni,
retention e diagnostica. Il frontend pubblico deve conservare soltanto consultazione, carrello,
verifica email e prenotazione.

La migrazione non deve modificare gli identificativi delle tabelle, perdere dati, ridurre i
controlli introdotti nella versione 8.9.3 o rendere pubblici dati personali. Il rilascio deve
essere reversibile reinstallando lo ZIP 8.9.3 e ripristinando il backup, se necessario.

## 2. Inventario e destinazione delle funzioni

| Funzione esistente | Destinazione `wp-admin` | Criterio di equivalenza |
|---|---|---|
| Elenco e ricerca prenotazioni | Scarto Librario > Prenotazioni | Stessi stati, dati, ordinamento e ricerca per codice |
| Dettaglio utente e volumi | Espansione riga prenotazione | Nessun campo personale perso o esposto fuori dall'area autorizzata |
| Consegna, annullamento, restituzione | Azioni prenotazione | Stesse transizioni atomiche e conferme |
| PDF di ritiro | Azione PDF | Stessi dati, impaginazione e download locale |
| Importazione Excel | Scarto Librario > Catalogo | Stesse colonne, limiti, validazione e transazione |
| Esportazione Excel | Scarto Librario > Catalogo | Stati calcolati dalle prenotazioni staff, non dal DTO pubblico |
| Reset totale | Catalogo, sezione pericolo | Capability dedicata, nonce e password di sicurezza |
| Impostazioni operative | Scarto Librario > Impostazioni | Email, biblioteca, prenotazioni, rate limit e retention |
| Personalizzazione grafica | Scarto Librario > Aspetto | Header (due colori e opacità), accento, sfondo, testo, font, logo e link sanitizzati |
| Credenziali e diagnostica | Scarto Librario > Privacy e sicurezza | Password di sicurezza, Site Health, tabelle, cron e hosting |
| Login/logout staff | Account WordPress | Account individuali, capability e recupero password WordPress |

## 3. Architettura prevista

1. Registrare un menu principale **Scarto Librario** con icona SVG locale e sottomenu:
   **Prenotazioni**, **Catalogo**, **Aspetto**, **Impostazioni**, **Privacy e sicurezza**.
2. Caricare JavaScript, CSS e configurazione soltanto nelle pagine amministrative del plugin.
3. Mantenere il bundle pubblico indipendente e rimuovere il pulsante `Personale`.
4. Usare autenticazione cookie WordPress, nonce `wp_rest` e capability dedicate per le API private.
5. Conservare la password di sicurezza database come step-up per importazione, reset, purge e
   cleanup; non conservarla nel browser né nei log.
6. Mantenere endpoint pubblici e privati separati, risposte private `no-store` e blocco CORS
   cross-origin.
7. Conservare schema e tabelle esistenti. Eventuali nuove opzioni devono usare autoload disattivato
   quando non necessarie in ogni richiesta.

## 4. Ruoli e autorizzazioni

Capability previste:

- `scarto_view_reservations`: visualizzare prenotazioni e dati associati;
- `scarto_manage_reservations`: consegnare, annullare e restituire;
- `scarto_manage_catalog`: importare ed esportare il catalogo;
- `scarto_manage_settings`: modificare configurazione e aspetto;
- `scarto_manage_privacy`: usare strumenti privacy e diagnostica.

Gli amministratori ricevono tutte le capability. **Operatore Scarto Librario** riceve soltanto
lettura e gestione prenotazioni; **Responsabile Scarto Librario** riceve anche catalogo,
configurazione e privacy. La password di sicurezza resta obbligatoria per le operazioni distruttive.
Ogni audit amministrativo deve includere l'ID WordPress dell'operatore senza registrare password,
token, email in chiaro o contenuti dei file importati.

## 5. Personalizzazione grafica

Le opzioni ammesse devono essere esplicite e validate:

- colore iniziale/finale e opacità dello sfondo header, sfondo pagina, testo e accento;
- famiglia font da una lista locale controllata;
- logo scelto dalla Media Library, memorizzato come attachment ID;
- URL homepage, privacy e contatto istituzionale;
- titolo e sottotitolo della pagina pubblica.

I colori devono essere salvati solo se conformi a `#RRGGBB`. Gli URL devono passare da
`esc_url_raw`; il logo deve essere un'immagine della Media Library accessibile all'operatore.
Non sono ammessi CSS, HTML, JavaScript, font remoti o URL di script inseriti liberamente. Le
variabili grafiche devono essere emesse con escape nel template pubblico e non indebolire la CSP.

L'interfaccia deve mostrare contrasto stimato e avvisare quando testo/sfondo non raggiungono il
rapporto WCAG AA 4,5:1. La configurazione predefinita deve replicare l'aspetto 8.9.3.

## 6. Protezione dei dati personali

- Le API pubbliche non devono restituire nome, email, indirizzo, IP, user-agent o codici ordine.
- I dati personali sono visibili solo con capability `scarto_view_reservations`.
- Le schermate amministrative e le API private devono inviare `Cache-Control: no-store`.
- Export e PDF devono essere generati su richiesta, senza file persistenti nel webroot.
- Le retention per completati, annullati, scaduti, IP e audit devono restare applicate da cron.
- Exporter ed eraser WordPress devono continuare a funzionare sullo schema esistente.
- Logo e impostazioni grafiche non devono contenere dati personali o riferimenti a file privati.
- La pagina privacy deve riflettere realmente campi raccolti, finalità e periodi configurati.

## 7. Cybersecurity

Controlli obbligatori:

- capability verificata server-side su ogni endpoint privato;
- nonce REST verificato su tutte le modifiche;
- secondo fattore applicativo tramite password di sicurezza per azioni ad alto impatto;
- schemi REST, limiti di body, rate limit e query preparate invariati;
- importazione con validazione completa prima della transazione;
- protezione contro CSV/Excel formula injection in esportazione;
- nessun asset remoto, source map, segreto o file di sviluppo nello ZIP;
- audit con operatore, azione, esito e oggetto, senza PII non necessaria;
- invalidazione di tutte le vecchie sessioni personalizzate al completamento della migrazione;
- separazione del bundle pubblico per ridurre superficie esposta e dimensione del download.
- circuito globale applicato prima dei contatori IP/email per limitare la crescita del database;
- aggregazione IPv6 `/64` e header client accettati solo da proxy presenti in allowlist;
- limite concorrente alle prenotazioni attive associate alla stessa email verificata;
- rate limiting WAF/reverse proxy sugli endpoint pubblici prima dell'esecuzione PHP.

## 8. Stato di implementazione

Completato nella copia isolata 9.0.1: menu e icona, ruoli/capability, nonce WordPress, rimozione delle
route login staff, prenotazioni/PDF, catalogo/import/export/reset, form impostazioni, aspetto,
strumenti GDPR, bundle pubblico/admin separati, audit dipendenze e test statici. Il bundle pubblico
non contiene chiamate alle API amministrative. Sono inoltre implementati limite delle prenotazioni
attive per email, protezione degli header proxy e regressioni anti-abuso. La versione 8.9.3 di backup
non è stata modificata.

Restano obbligatori prima della produzione: collaudo su WordPress reale, verifica email/PDF/Excel,
test concorrente controllato, test responsive e accessibilità, backup/ripristino e revisione
indipendente. Questi controlli non possono essere sostituiti dai test statici locali.

## 9. Sequenza operativa

1. Creare copia isolata e congelare hash dello ZIP 8.9.3.
2. Registrare ruoli, capability, menu e icona.
3. Aggiungere renderer e caricamento asset limitato alle pagine del plugin.
4. Adattare le API private all'autenticazione WordPress mantenendo same-origin e `no-store`.
5. Migrare prenotazioni, azioni, PDF, Excel e impostazioni nel pannello.
6. Implementare Aspetto con allowlist di opzioni e Media Library.
7. Rimuovere accesso staff e codice di login dalla pagina pubblica.
8. Correggere l'esportazione affinché usi gli stati delle prenotazioni private.
9. Aggiornare diagnostica, uninstall, build, manifest e documentazione.
10. Eseguire collaudo comparativo e produrre ZIP con nome/versione distinti.

## 10. Test di accettazione

### Funzionali

- confronto 1:1 di elenco, ricerca, dettagli e transizioni su un database di test;
- PDF confrontato per codice, utente, volumi, date e paginazione;
- import Excel valido, invalido, massimo e rollback simulato;
- export Excel con stati disponibile, prenotato, ritirato e scaduto;
- salvataggio e ripristino di ogni impostazione grafica e operativa;
- pagina pubblica verificata senza pulsante o componenti staff.

### Autorizzazione e privacy

- anonimo, sottoscrittore e ruolo non autorizzato ricevono `401/403`;
- operatore può usare solo capability assegnate;
- nonce assente, errato o scaduto viene respinto;
- nessuna risposta anonima contiene PII o impostazioni private;
- exporter, eraser, retention e anonimizzazione IP superano il test;
- cache browser/proxy non conserva le risposte amministrative.
- email con limite attivo raggiunto riceve `429` senza nuovo ordine o blocco di altri volumi;
- rotazione di indirizzi IPv6 nella stessa `/64` non aggira i limiti;
- header IP inviati da un'origine non presente in `SCARTO_TRUSTED_PROXIES` vengono ignorati;
- WAF blocca i flood prima di PHP secondo `HARDENING_ANTI_ABUSO_E_WAF.md`.

### UI e accessibilità

- desktop, tablet e mobile nel pannello WordPress;
- navigazione tastiera, focus, etichette e messaggi di errore;
- contrasto WCAG AA delle configurazioni ammesse;
- compatibilità con menu laterale WordPress aperto e compresso;
- nessuna collisione CSS con altre pagine amministrative.

## 11. Rollback e rilascio

La versione precedente resta immutata in `Plugin-risanato/`. Prima dell'aggiornamento devono
essere salvati database, file e hash dello ZIP installato. La nuova versione deve poter leggere
le opzioni e le tabelle 8.9.3 senza conversioni distruttive. In caso di errore, disattivare la
nuova versione, reinstallare lo ZIP 8.9.3 e ripristinare il database solo se una migrazione ha
modificato dati o schema.

Il rilascio è autorizzabile solo dopo build riproducibile, audit dipendenze pulito, test statici,
regole WAF attive, test WordPress reali, revisione indipendente e approvazione del responsabile privacy.
