# Changelog

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
