# Verifica best practice WordPress - candidata 9.0.2

## Esito sintetico

La revisione statica non ha rilevato blocchi noti nelle aree modificate. La candidata applica capability dedicate, nonce REST, password di secondo livello per import/reset, schemi REST, limiti di payload e righe, transazioni, sanitizzazione, query preparate, risposte private `no-store`, asset locali e caricamento limitato alle pagine del plugin.

## Correzioni applicate

- Bundle Vite admin caricato esplicitamente come `type="module"`; configurazione REST e nonce letti da attributo dati sottoposto a escaping.
- Fallback PHP accessibile se JavaScript o manifest non vengono caricati.
- Import limitato a `.xlsx`/`.xls`, 10 MB e 50.000 righe, con Titolo e Inventario/ID obbligatori, duplicati respinti e conteggi finali.
- Nessuna cancellazione dati in uninstall salvo consenso esplicito nelle Impostazioni e avviso di backup.
- Polling silenzioso e sospeso durante selezione, carrello, compilazione e conferma utente.
- Dipendenze aggiornate; `npm audit` finale: zero vulnerabilita.

## Evidenze automatiche

- `npm run check:php`: superato.
- `npm run type-check`: superato.
- `npm run build`: bundle pubblico e admin generati.
- `npm run check:security`: superato, incluse regressioni module/import/uninstall/polling.
- `npm run test:security:smoke:self`: superato.
- `npm audit --audit-level=moderate`: zero vulnerabilita.
- Artefatto: `gestione-scarto-librario-9.0.2.zip`, SHA-256 `e3d402e999390bc6ced6e6a3b52f3f228ccd407885fcf5489aa990c6fcbf4e36`.

## Rischi residui e verifiche richieste

Non e stato eseguito un collaudo dentro l'installazione WordPress di produzione. Prima del rilascio stabile verificare ruoli, nonce, import reale, prenotazioni attive, email, PDF, cron, GDPR, backup/ripristino, aggiornamento da 9.0.1, cache/minificazione e CSP. Il campo `Tested up to: 6.7` non va aumentato senza test sulla versione dichiarata. L'internazionalizzazione del codice storico e parziale e manca una suite di integrazione WordPress; sono debiti tecnici, non prove di malfunzionamento della correzione. Il bundle admin supera 500 KB minificati: pianificare il caricamento differito di XLSX/PDF dopo il collaudo funzionale.
