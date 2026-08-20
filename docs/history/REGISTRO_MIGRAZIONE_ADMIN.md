# Registro migrazione area amministrativa

## 13 luglio 2026

- Creata copia isolata in `Plugin-admin-wordpress/gestione-scarto-librario` senza `node_modules`
  e senza report generati.
- Conservata intatta la versione 8.9.3 in `Plugin-risanato/` come backup.
- Redatto `PIANO_MIGRAZIONE_AREA_PERSONALE_WP_ADMIN.md` con equivalenza funzionale,
  autorizzazioni, cybersecurity, privacy, personalizzazione, test e rollback.
- Creata la versione candidata 9.0.0 con menu `Scarto Librario`, icona proprietaria e sottomenu
  Prenotazioni, Catalogo, Aspetto, Impostazioni, Privacy e sicurezza.
- Sostituita l'autenticazione staff proprietaria con account, nonce e capability WordPress;
  rimosse dalla registrazione REST le route di login, sessione, logout e recupero staff.
- Creati i ruoli a privilegio minimo `Operatore Scarto Librario` e `Responsabile Scarto Librario`.
- Migrati prenotazioni, azioni, PDF, import/export Excel e reset nel pannello; corretto l'export
  affinché usi gli ordini privati e neutralizzi celle interpretabili come formule.
- Aggiunte personalizzazioni con colori header, opacità, accento, sfondo, testo, font locale,
  logo Media Library, titolo, sottotitolo e link con sanitizzazione e verifica contrasto WCAG.
- Aggiunti strumenti GDPR nativi per export JSON `no-store` e cancellazione con conferma,
  capability, nonce, rate limit e password database di secondo livello.
- Separate le build `dist/public` e `dist/admin`; verificata l'assenza di chiamate amministrative
  nel bundle pubblico e di route staff legacy in entrambi i bundle distribuiti.
- Aggiornata DOMPurify da 3.4.9 a 3.4.12 tramite lockfile; `npm audit` non rileva vulnerabilità.
- Superati controlli sintattici PHP, type-check TypeScript, build Vite, controlli statici di
  sicurezza e smoke test locale non distruttivo.
- Confrontate le vulnerabilità del thread Reddit indicato dall'utente con codice e bundle; creato
  `VERIFICA_THREAD_REDDIT_SICUREZZA.md`. Nessuna falla concreta del thread rilevata; registrati i
  rischi residui e le verifiche ambientali ancora necessarie.
- Esteso il controllo di regressione a entrambi i bundle distribuiti e alla copertura GDPR dei dati
  temporanei cifrati; ripetuti con esito positivo sintassi PHP, TypeScript, build, controllo statico,
  smoke test locale e `npm audit` (`0 vulnerabilities`).
- Creato `gestione-scarto-librario-9.0.0.zip` con un'unica cartella radice WordPress e senza
  sorgenti, dipendenze di sviluppo o report. SHA-256:
  `9fd5ae4d6cf6a65d1093397dfb2b6eb4fba8c5d25e3acfbb2c07b0a94681c11d`.
- Avviata la candidata 9.0.1 dopo la revisione del rischio di prenotazioni massive e DoS.
- Spostato il circuito globale prima dei contatori IP/email e limitata la crescita numerica dei
  contatori bloccati; gli indirizzi IPv6 temporanei sono aggregati per `/64`.
- Gli header `CF-Connecting-IP` e `X-Forwarded-For` sono ora accettati soltanto da indirizzi o CIDR
  presenti in `SCARTO_TRUSTED_PROXIES`; l'accesso diretto non può falsificare l'IP applicativo.
- Aggiunto il limite configurabile delle prenotazioni attive per email verificata, serializzato dal
  contatore email all'interno della transazione per coprire richieste concorrenti.
- Estesa la diagnostica a `DISALLOW_FILE_EDIT` e alla configurazione proxy; redatto
  `HARDENING_ANTI_ABUSO_E_WAF.md` con regole, monitoraggio e collaudi richiesti all'hosting.
- Aggiunto `security-tests/active-limit-test.mjs` per il collaudo concorrente su staging con la
  stessa email verificata e libri distinti.
- Superati nuovamente sintassi PHP, controllo statico post-build, TypeScript, build pubblica/admin,
  smoke test locale e `npm audit` (`0 vulnerabilities`).
- Creato `gestione-scarto-librario-9.0.1.zip`, mantenendo disponibile il precedente 9.0.0. Lo ZIP
  contiene una sola cartella radice e nessun file di sviluppo. SHA-256:
  `9da31dc3e98bede63f0b5fa1f63da41c2fc8f455ca7c5dfa9c04d248ffe0c908`.
