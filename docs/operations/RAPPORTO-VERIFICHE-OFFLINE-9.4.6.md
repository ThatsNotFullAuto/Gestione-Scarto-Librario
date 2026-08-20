# Rapporto verifiche offline 9.4.6

**Data:** 20 agosto 2026  
**Schema database:** 8.15, invariato

## Evidenze

- Il file segnalato contiene 3.886 volumi: 3.881 `available` e 5 `delivered` nei campi tecnici, ma 3.886 valori `DISPONIBILE` nella vecchia colonna **Stato Attuale**.
- La nuova implementazione usa `catalog/availability` al momento dell'esportazione e non la pagina corrente delle prenotazioni.
- Il controllo di regressione richiede la traduzione `delivered` in `CONSEGNATO` e l'esclusione dei campi tecnici.
- Parsing PHP, controlli statici di sicurezza, TypeScript strict, backup cifrato, `npm audit` e build Vite completati.

## Verifica esterna residua

Su staging occorre esportare il catalogo reale e confrontare almeno un volume disponibile, uno prenotato e uno consegnato con il pannello Prenotazioni.
