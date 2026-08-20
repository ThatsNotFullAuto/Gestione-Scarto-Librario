# Contribuire

## Preparazione

Richiede Node.js 22, npm, PHP 8.2 o successivo e un'installazione WordPress di staging. Installare le dipendenze con `npm ci` dentro `gestione-scarto-librario/`.

## Flusso di lavoro

1. Aprire un issue privo di dati personali per bug o modifiche rilevanti.
2. Creare un branch breve e descrittivo.
3. Modificare i sorgenti; non intervenire direttamente sui file hashati in `dist/`.
4. Eseguire `npm run check` e i test manuali pertinenti.
5. Aggiornare documentazione, versione e asset generati quando necessario.
6. Aprire una pull request indicando impatto, test, migrazioni, privacy e rollback.

## Requisiti di sicurezza

Ogni endpoint deve avere schema, autorizzazione e risposta anti-cache adeguati. Le operazioni amministrative richiedono capability WordPress; quelle sensibili richiedono anche la password di sicurezza del plugin. Non rimuovere o indebolire il percorso anti-lockout documentato.

Non inserire mai nel repository cataloghi reali, backup, esportazioni, indirizzi personali, token, OTP, password, file `.env`, dump SQL o report prodotti contro ambienti reali.

## Verifica minima

```bash
npm run check
npm run test:security:smoke:self
```

I test di concorrenza e limite attivo creano dati: eseguirli solo su staging autorizzato con volumi fittizi.
