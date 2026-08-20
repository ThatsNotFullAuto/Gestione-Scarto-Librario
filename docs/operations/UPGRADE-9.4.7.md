# Aggiornamento alla versione 9.4.7

La 9.4.7 non modifica lo schema database, non resetta dati e non cambia ruoli, capability o password. Catalogo, prenotazioni, impostazioni, blacklist, whitelist e log operativi sono preservati. La bonifica dei dettagli dei log storici rimuove soltanto identificatori ridondanti e procede via WP-Cron in lotti da 250 record.

## Prima dell'aggiornamento

1. Scaricare un backup cifrato dal plugin e creare un backup completo di file e database.
2. Conservare lo ZIP 9.4.6 e verificare l'accesso WinSCP/SFTP.
3. Mantenere aperta una sessione amministrativa e usare una seconda sessione per il test.
4. Verificare il checksum SHA-256 pubblicato accanto allo ZIP.

## Installazione e controlli

Caricare `gestione-scarto-librario-9.4.7.zip` da **Plugin > Aggiungi plugin > Carica plugin**. Se WordPress non riattiva automaticamente il plugin, riattivarlo manualmente: i dati restano nelle tabelle dedicate.

Verificare nell'ordine: pagina pubblica, accesso amministrativo, catalogo, prenotazioni esistenti, prenotazione online con OTP/email/PDF, prenotazione in sede, cambio stato, export privacy e backup. In **Scarto Librario > Privacy e sicurezza**, controllare che “Bonifica privacy dei log storici” passi da `pending`/`running` a `completed`.

## Rollback

In caso di errore, rinominare via WinSCP la cartella 9.4.7, ripristinare la cartella 9.4.6 e riattivarla. Non eseguire reset o disinstallazione con cancellazione dati. Il codice 9.4.6 può leggere lo stesso schema 8.15; gli identificatori già rimossi dai dettagli dei log non vengono ricreati.

Il modulo in `site-hardening/` non appartiene allo ZIP principale e non va attivato insieme all'aggiornamento. Le sue protezioni REST/XML-RPC devono essere provate separatamente su staging.
