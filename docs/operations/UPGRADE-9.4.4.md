# Aggiornamento sicuro alla versione 9.4.4

## Scopo

La 9.4.4 corregge il ripristino dei backup contenenti prenotazioni create in sede senza email e con domicilio. La 9.4.3 poteva esportare tali record ma li rifiutava durante la validazione del ripristino. Non cambiano schema database 8.15, tabelle, impostazioni, ruoli, capability o password.

## Prima dell'aggiornamento

1. Conservare lo ZIP 9.4.3 e il relativo SHA-256.
2. Eseguire un backup completo WordPress di file e database.
3. Esportare un backup cifrato del plugin e custodire separatamente la password.
4. Verificare accesso WinSCP/SFTP e possibilita di rinominare la cartella del plugin.
5. Mantenere aperta una sessione amministratore e collaudare da una seconda finestra privata.

## Installazione

1. Caricare `gestione-scarto-librario-9.4.4.zip` da **Plugin > Aggiungi plugin > Carica plugin**.
2. Se WordPress non riattiva il plugin, riattivarlo manualmente; non disinstallare la versione precedente.
3. Verificare che la versione visualizzata sia 9.4.4 e che la diagnostica non segnali tabelle o indici mancanti.

## Collaudo richiesto in staging

- prenotazione online con OTP, email e nessun domicilio;
- prenotazione in sede con email e nessun domicilio;
- prenotazione in sede senza email e con domicilio completo;
- export cifrato e ripristino, verificando che tutti e tre i record e i relativi volumi siano preservati;
- password di cifratura errata e file alterato, entrambi rifiutati;
- lista, ricerca, PDF, reinvio email e stato disponibilita;
- ruoli Operatore, Responsabile e Amministratore;
- rollback alla 9.4.3 senza modifica database, se necessario.

## Rollback antilockout

La 9.4.4 mantiene lo schema 8.15, quindi il rollback binario alla 9.4.3 e previsto come compatibile. In caso di errore `500`, rinominare tramite WinSCP/SFTP la cartella corrente, caricare la 9.4.3 con slug esatto `gestione-scarto-librario` e riattivarla. Non ripristinare il database salvo corruzione dimostrata.

## Evidenze offline

La pipeline 9.4.4 esegue parsing PHP, controlli statici, test della cifratura e dei recapiti del backup, TypeScript, audit npm, build Vite e verifica deterministica del pacchetto. Questi controlli non sostituiscono il round trip su un database WordPress reale.
