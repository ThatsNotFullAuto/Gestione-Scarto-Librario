# Gestione Scarto Librario

Plugin WordPress della Biblioteca statale Stelio Crise per pubblicare un catalogo di volumi destinati allo scarto, acquisire prenotazioni verificate tramite email e supportare il lavoro degli operatori nel pannello amministrativo.

Versione corrente: **9.4.4**. Requisiti minimi: WordPress 6.6, PHP 8.2 e HTTPS. Il frontend usa React e TypeScript; il backend usa API REST WordPress e tabelle dedicate.

## Funzioni principali

- importazione e aggiornamento del catalogo da Excel;
- prenotazioni online con codice OTP e controllo atomico della disponibilita;
- prenotazioni assistite create dagli operatori;
- stati disponibile, prenotato, consegnato e scaduto;
- email di riepilogo, PDF operativi e reinvio controllato;
- ricerca, filtri, paginazione, log, statistiche ed esportazioni;
- backup cifrati, conservazione configurabile e strumenti per i diritti degli interessati;
- whitelist istituzionale, blacklist motivata e controlli anti-abuso;
- diagnostica e test di sicurezza non distruttivi.

## Struttura

- `gestione-scarto-librario/`: plugin completo, sorgenti, bundle runtime e test.
- `docs/architecture/`: dossier tecnico e DPIA preliminare in italiano e inglese.
- `docs/operations/`: installazione, hardening, privacy e gestione operativa.
- `docs/history/`: piani e verifiche delle versioni precedenti, conservati come storico.
- `fixtures/`: dati esclusivamente fittizi per prove locali.
- `releases/`: ZIP installabili versionati e checksum SHA-256; la 9.4.4 e la release corrente.

## Installazione

Usare `releases/gestione-scarto-librario-9.4.4.zip` da **Plugin > Aggiungi plugin > Carica plugin**. Prima dell'aggiornamento eseguire un backup e leggere `docs/operations/UPGRADE-9.4.4.md`. Verificare sempre il checksum pubblicato.

## Sviluppo e verifica

```bash
cd gestione-scarto-librario
npm ci
npm run check
npm run test:security:smoke:self
npm run test:offline:release
npm run release
```

Le modifiche a `src/` richiedono la rigenerazione di `dist/`. Non usare dati personali o cataloghi reali nei test e non eseguire i test di concorrenza contro la produzione.

La procedura completa per produrre e verificare lo ZIP installabile e disponibile in [docs/CREARE-ZIP-INSTALLABILE.md](docs/CREARE-ZIP-INSTALLABILE.md). In alternativa usare `crea-zip.ps1` su Windows o `./crea-zip.sh` su Linux/macOS.

## Sicurezza e privacy

Leggere [SECURITY.md](SECURITY.md) prima di segnalare vulnerabilita. Il dossier in `docs/architecture/` descrive dati trattati, ruoli, flussi, misure tecniche e rischi residui. La configurazione finale, i tempi di conservazione e l'informativa devono essere approvati dal titolare del trattamento e, ove richiesto, dal RPD.

## Licenza

Non e ancora stata autorizzata una licenza di riuso. Consultare [LICENSE-NOTICE.md](LICENSE-NOTICE.md) prima di rendere pubblico il repository.
