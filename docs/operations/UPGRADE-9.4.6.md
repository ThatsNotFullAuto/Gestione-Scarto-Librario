# Aggiornamento sicuro alla versione 9.4.6

## Correzione

La versione 9.4.6 corregge esclusivamente l'esportazione Excel del catalogo. Nella 9.4.5 i volumi consegnati erano presenti correttamente nello stato interno `delivered`, ma la colonna **Stato Attuale** controllava soltanto le prenotazioni attive e li indicava come `DISPONIBILE`.

Il nuovo export acquisisce dal server uno snapshot globale aggiornato al momento del clic e produce `DISPONIBILE`, `PRENOTATO` o `CONSEGNATO`. Le colonne tecniche `_availability`, `_reserved`, `_delivered` e `reservedUntil` non vengono più incluse.

Non cambiano schema database 8.15, catalogo, prenotazioni, statistiche, impostazioni, capability o password.

## Procedura antilockout

1. Conservare la ZIP 9.4.5 e verificare l'accesso SFTP/WinSCP.
2. Eseguire un backup cifrato del plugin e il backup del sito/database.
3. Caricare `gestione-scarto-librario-9.4.6.zip` senza cancellare preventivamente la cartella attiva.
4. Riattivare manualmente il plugin se richiesto da WordPress.
5. Esportare il catalogo e verificare che i volumi già consegnati risultino `CONSEGNATO`.

Il rollback binario alla 9.4.5 è compatibile perché lo schema resta invariato.
