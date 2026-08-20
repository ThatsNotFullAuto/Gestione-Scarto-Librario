# Aggiornamento sicuro alla versione 9.4.3

## Scopo

La 9.4.3 applica hardening senza modificare lo schema database 8.15, le tabelle, le impostazioni, le password esistenti o l'assegnazione delle capability. La 9.4.2 resta il rollback binario di riferimento.

## Prima dell'aggiornamento

1. Scaricare un backup cifrato dal plugin e verificare di possedere la relativa password.
2. Conservare backup completo WordPress di file e database.
3. Conservare `gestione-scarto-librario-9.4.2.zip` e il relativo SHA-256.
4. Verificare accesso WinSCP/SFTP e possibilita di rinominare `wp-content/plugins/gestione-scarto-librario`.
5. Mantenere aperta una sessione amministratore funzionante; usare una seconda finestra privata per il collaudo.
6. Se esistono backup storici non cifrati, convertirli e verificarli su staging prima dell'aggiornamento.

## Installazione

1. Installare lo ZIP 9.4.3 tramite **Plugin > Aggiungi plugin > Carica plugin**.
2. Se WordPress non riattiva automaticamente il plugin, riattivarlo manualmente. I dati restano nelle tabelle WordPress.
3. Non disinstallare la versione precedente e non selezionare la cancellazione dati.
4. Svuotare soltanto la cache applicativa/CDN della pagina dello scarto, senza eliminare database o opzioni.

## Collaudo immediato

- aprire frontend e catalogo come anonimo;
- effettuare una prenotazione di prova con OTP e poi annullarla/eliminarla secondo procedura;
- accedere come Operatore e verificare lista, ricerca, filtro pendenti e reinvio email;
- accedere come Responsabile e verificare catalogo, impostazioni, privacy, backup e diagnostica;
- verificare che la password venga chiamata “Password di sicurezza del plugin”;
- verificare nella diagnostica “Importazione backup legacy non cifrati disabilitata”;
- controllare che le risposte di `/admin/reservations` e `/admin/reservations/resend` abbiano `Cache-Control: no-store`;
- confermare che un Operatore senza capability privacy non possa eseguire `/purge-all-data`;
- controllare log PHP, cron, email e audit.

## Rollback antilockout

La 9.4.3 non introduce migrazioni database; il rollback binario alla 9.4.2 e quindi previsto come compatibile.

1. Se `wp-admin` e accessibile, disattivare la 9.4.3 e installare lo ZIP 9.4.2 senza disinstallare il plugin.
2. Se compare un errore `500`, usare WinSCP/SFTP per rinominare la cartella corrente, caricare la cartella 9.4.2 con slug esatto `gestione-scarto-librario` e riattivare.
3. Non ripristinare il database salvo corruzione dimostrata; se necessario usare il backup creato prima dell'aggiornamento.
4. Registrare orario, errore, operatore e azione eseguita.

## Backup legacy straordinario

Solo su staging e per il tempo strettamente necessario e possibile aggiungere a `wp-config.php`:

```php
define('SCARTO_ALLOW_LEGACY_UNENCRYPTED_BACKUPS', true);
```

Importare il vecchio file, generare subito un nuovo backup cifrato e rimuovere la costante. Non abilitare questa compatibilita in produzione.

