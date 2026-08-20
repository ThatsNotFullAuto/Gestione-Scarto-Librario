# Piano di intervento 9.1.0

## Obiettivi e confini di sicurezza

Il rilascio aggiunge eccezioni controllate ai limiti per email, un registro operativo consultabile, statistiche aggregate e un caricamento catalogo misurabile. Le email configurate come eccezioni restano soggette a verifica OTP, disponibilita dei volumi, limiti IP e globali, permessi WordPress e password di sicurezza. Non esistera quindi una scorciatoia che consenta di prenotare senza verifica o superare i controlli infrastrutturali.

## Dati e migrazione

La tabella audit esistente verra estesa in modo idempotente con categoria, esito, email interessata e ID dell'utente WordPress che ha eseguito l'operazione. Le email saranno normalizzate, mai accompagnate dal codice OTP e accessibili soltanto agli utenti con capacita privacy. I dettagli continueranno a escludere password, token, indirizzi e payload completi. La retention gia configurabile per gli audit log si applichera anche ai nuovi campi; l'anonimizzazione privacy rimuovera email e riferimenti tecnici quando previsto.

## Eccezioni ai limiti per email

Nelle impostazioni di sicurezza verra aggiunto un campo multilinea che accetta indirizzi separati da virgola, punto e virgola o nuova riga. Il salvataggio con nonce e capacita dedicata normalizzera, convalidera e deduplichera i valori. L'esenzione riguardera esclusivamente il limite OTP per email, il limite giornaliero per email e il numero massimo di prenotazioni attive per email. Ogni utilizzo dell'eccezione sara registrato.

## Registro operativo

Una nuova pagina **Log attivita**, limitata alla capacita privacy, mostrera data e ora WordPress, categoria, operazione, esito, email, prenotazione/entita, utente amministrativo e dettagli non sensibili. I filtri comprenderanno categoria, esito, intervallo date, email e ricerca operazione; i risultati saranno paginati e tutte le query dinamiche saranno preparate. Saranno registrati gli eventi significativi del flusso OTP e prenotazione, non le letture anonime del catalogo, per evitare raccolta sproporzionata e degrado prestazionale.

## Statistiche

Una pagina **Statistiche**, accessibile al personale autorizzato alla consultazione, presentera indicatori aggregati su catalogo, stati delle prenotazioni e andamento degli ultimi 30 giorni. I diagrammi saranno realizzati senza servizi o librerie esterne, affiancati da valori e tabelle accessibili per non comunicare informazioni soltanto tramite colore. Nessun nominativo, email, IP o codice OTP verra esposto nelle statistiche.

## Caricamento e prestazioni

Il catalogo pubblico e amministrativo continuera a usare pagine da 500 record, ma le pagine successive saranno richieste in piccoli gruppi concorrenti. Una callback aggiornera record caricati, totale e percentuale reale. La schermata mostrera indicatore animato, barra con semantica `progressbar` e testo comprensibile anche senza animazione. La pagina Prenotazioni usera un bootstrap leggero e non scarichera inutilmente migliaia di libri.

## Verifica e rilascio

Saranno eseguiti lint PHP, controllo sicurezza, TypeScript, build Vite, audit dipendenze e smoke test. I test statici verificheranno che l'allowlist non disabiliti OTP, limiti IP/globali, controlli di disponibilita o autorizzazioni. Il pacchetto ZIP includera solo file runtime, manifest e hash SHA-256. La migrazione dovra essere provata prima sul sito di test con backup ripristinabile e successivamente in produzione, controllando pagine Log/Statistiche, invio OTP, prenotazione concorrente, azioni amministrative e cleanup.
