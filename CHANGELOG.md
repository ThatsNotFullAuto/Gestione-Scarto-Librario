# Changelog

## 9.4.8 - 2026-08-20

- Ripristinato lo stile strutturato del PDF di conferma prenotazione.
- Mantenuti codice, dati richiedente, scadenza, titoli, autori, inventari, istruzioni e footer configurabile.
- Uniformato il renderer server-side affinché download e allegato email siano identici su ogni hosting.
- Aggiunto un test multipagina in memoria con 70 volumi e validazione della struttura PDF.

## 9.4.7 - 2026-08-20

- Rimossi fingerprint email e IP ridondanti dai nuovi dettagli dei log.
- Aggiunta una bonifica storica incrementale, diagnostica e recuperabile senza modificare prenotazioni o catalogo.
- Completati gli export dell'interessato con log, IP e User-Agent entro la retention.
- Rimossi callback, sessioni e schemi del precedente login proprietario inattivo; account, capability e nonce WordPress restano invariati.
- Aggiunti limite di parsing Excel, verifica SHA-256 del vendor e test di file ostili.
- Aggiunto un modulo site-specific separato, opt-in e antilockout per REST utenti e XML-RPC; non è incluso nello ZIP principale.

## 9.4.6 - 2026-08-20

- Corretto l'export Excel che indicava come disponibili i volumi già consegnati.
- Acquisito uno snapshot globale aggiornato della disponibilità al momento dell'esportazione.
- Rimossi dall'Excel i campi tecnici `_availability`, `_reserved`, `_delivered` e `reservedUntil`.
- Aggiunto un controllo statico contro la regressione dello stato esportato.

## 9.4.5 - 2026-08-20

- Resi identici byte-per-byte il riepilogo PDF allegato all'email e quello scaricato dalla conferma pubblica.
- Uniformati i piè di pagina PDF ai recapiti configurati nel pannello WordPress.
- Assegnato all'allegato il nome leggibile `prenotazione_CODICE.pdf` in una directory temporanea privata e casuale.
- Rimossi email e fingerprint dai nuovi eventi di audit creati dopo un'anonimizzazione.
- Aggiunti controlli statici e test diretto del renderer PDF PHP.

## 9.4.4 - 2026-08-20

- Corretto il ripristino delle prenotazioni in sede prive di email e dotate di domicilio.
- Aggiunto un test PHP offline per recapiti, cifratura backup, password errata e alterazione.
- Aggiunta la verifica byte-per-byte di due build ZIP e di ogni checksum del manifesto interno.
- Aggiornati dossier tecnico, documentazione operativa e pacchetto GitHub.

## 9.4.3 - 2026-08-19

- Applicati header `no-store` a tutte le risposte amministrative delle prenotazioni.
- Limitato il reset globale dei dati personali alla capability privacy dedicata.
- Disabilitata per impostazione predefinita l'importazione di backup legacy non cifrati.
- Chiarita la distinzione tra password di sicurezza del plugin e password MySQL.
- Isolato il blocco temporaneo della password per utente WordPress e indirizzo IP.
- Resa deterministica la generazione della release e aggiunti controlli sui file vietati.

Per la cronologia precedente consultare l'intestazione di `gestione-scarto-librario/gestione-scarto-librario.php` e `docs/history/`.
