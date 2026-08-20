# Test automatici di sicurezza

La suite `smoke-test.mjs` esegue esclusivamente controlli non distruttivi:

- legge gli endpoint pubblici e cerca campi riservati;
- verifica che gli ordini non siano accessibili anonimamente;
- controlla gli header anti-cache delle API amministrative;
- verifica assenza di CORS cross-origin e rifiuto delle origini esterne sugli endpoint privati;
- prova gli schemi della richiesta e della conferma prenotazione con payload volutamente invalidi;
- invia un solo JSON malformato e un solo payload con campo inatteso;
- controlla CSP e dipendenze runtime esterne, se viene indicata la pagina.

Non effettua login, prenotazioni reali, invii email, brute force, import, reset, purge o scansioni attive.

Il flusso email deve essere verificato manualmente con dati di test: richiesta del codice, codice
errato, codice corretto, scadenza dopo 15 minuti e conferma che il libro non risulti prenotato
prima della verifica.

## Test controllato di concorrenza

Questo test crea una prenotazione reale e va eseguito solo su staging o con volumi fittizi
autorizzati. Aprire due browser separati, selezionare almeno un libro identico, richiedere
entrambi i codici email senza confermarli e usare i due `requestId` visibili nella risposta
REST insieme ai codici ricevuti:

```bash
SCARTO_BASE_URL="https://esempio.it/wp-json/scarto/v1" \
SCARTO_REQUEST_A="..." SCARTO_CODE_A="123456" \
SCARTO_REQUEST_B="..." SCARTO_CODE_B="654321" \
npm run test:security:concurrency
```

Il test invia le conferme nello stesso istante. Supera solo se una richiesta ottiene `200`,
l'altra `409` con l'elenco dei libri indisponibili e il replay della vincente restituisce lo
stesso codice ordine senza duplicazioni.

## Esecuzione

```bash
npm run test:security:smoke -- \
  --base-url "https://esempio.it/wp-json/scarto/v1" \
  --page-url "https://esempio.it/pagina-scarto/"
```

In alternativa:

```bash
SCARTO_BASE_URL="https://esempio.it/wp-json/scarto/v1" \
SCARTO_PAGE_URL="https://esempio.it/pagina-scarto/" \
npm run test:security:smoke
```

I report vengono scritti in:

```text
security-tests/reports/smoke-report.md
security-tests/reports/smoke-report.json
```

Un codice di uscita `1` indica almeno un controllo fallito. Gli avvisi non bloccano l'esecuzione.
