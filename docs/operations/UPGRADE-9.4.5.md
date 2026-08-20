# Aggiornamento sicuro alla versione 9.4.5

## Contenuto dell'aggiornamento

La versione 9.4.5 genera sul server una sola copia del riepilogo PDF: gli stessi byte sono allegati all'email e restituiti alla finestra di conferma per il download. Il nome dell'allegato è `prenotazione_CODICE.pdf`. I PDF di prenotazione e ritiro usano nome, indirizzo, telefono ed email biblioteca configurati nelle impostazioni.

L'audit successivo a una cancellazione GDPR non reintroduce l'email o un suo fingerprint. Restano data e ora, operatore WordPress, motivazione, esito, conteggi e un riferimento operativo non personale. Non cambiano schema database 8.15, tabelle, impostazioni, ruoli, capability o password.

## Procedura antilockout

1. Eseguire un backup cifrato dal plugin e un backup del sito/database dal provider.
2. Conservare la ZIP 9.4.4 e verificare l'accesso SFTP/WinSCP prima dell'aggiornamento.
3. Caricare `gestione-scarto-librario-9.4.5.zip` da **Plugin > Aggiungi plugin > Carica plugin**.
4. Se WordPress non riattiva automaticamente il plugin, riattivarlo manualmente dalla pagina Plugin.
5. Verificare versione, diagnostica, catalogo e prenotazioni esistenti.

## Collaudo richiesto

- Effettuare una prenotazione di prova e confrontare SHA-256 o contenuto del PDF ricevuto via email con quello scaricato dalla finestra di conferma.
- Verificare nel PDF di prenotazione e in quello di ritiro i recapiti configurati.
- Eseguire una cancellazione su dati di prova e verificare i due eventi privacy nel log senza email o hash.

## Rollback

La 9.4.5 mantiene lo schema 8.15. In caso di errore, rinominare via SFTP la cartella corrente, ripristinare la 9.4.4 con slug `gestione-scarto-librario` e riattivarla. Non ripristinare il database salvo corruzione dimostrata.
