# Rapporto verifiche offline 9.4.5

**Data:** 20 agosto 2026  
**Schema database:** 8.15, invariato

## Esito

- Parsing PHP completato su tutti i file runtime e di test.
- Controlli statici di sicurezza completati.
- TypeScript strict completato e bundle pubblico/amministrativo rigenerati.
- `npm audit`: zero vulnerabilità note.
- Test backup cifrato: recapiti, password errata e alterazione superati.
- Test diretto PDF fallback PHP: nome allegato, firma `%PDF-1.4`, inventario, footer configurato, identità byte-per-byte del payload e cleanup temporaneo superati.

## Limiti della verifica

Non sono stati eseguiti l'invio SMTP reale, il confronto dell'allegato ricevuto, l'aggiornamento WordPress né una cancellazione GDPR su staging. Queste verifiche restano obbligatorie prima dell'uso in produzione.
