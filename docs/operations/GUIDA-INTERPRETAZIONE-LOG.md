# Guida all'interpretazione dei log

Questa guida aiuta il personale autorizzato a leggere il registro **Scarto Librario > Log attività**. Il log documenta operazioni pubbliche, amministrative, di sicurezza e privacy. Non contiene password o codici OTP.

## Come leggere le colonne

- **Data e ora**: momento dell'evento nel fuso orario configurato in WordPress.
- **Categoria**: area funzionale interessata: Prenotazioni, Catalogo, Email, Sicurezza, Privacy, Impostazioni o Sistema.
- **Operazione**: descrizione leggibile e, sotto, codice tecnico stabile dell'evento.
- **Esito**: `Riuscita`, `Non riuscita`, `Bloccata` oppure `Informativa`.
- **Email**: casella interessata, quando necessaria per controlli e assistenza. `-` indica che l'evento non riguarda una singola email o che il dato è stato anonimizzato.
- **Entità**: tipo e identificativo dell'oggetto interessato, per esempio `order ABC123` o `verification ...`.
- **Utente WP**: amministratore/operatore che ha eseguito l'azione. “Operazione pubblica/sistema” indica un visitatore, un processo automatico o un evento non associato a un account amministrativo.
- **Dettagli**: dati tecnici contestuali in formato JSON. I valori `true` e `false` significano rispettivamente sì e no.

## Priorità di verifica

- **Normale**: evento atteso; non richiede interventi isolatamente.
- **Da verificare**: controllare configurazione o contattare l'utente se l'evento si ripete.
- **Attenzione**: possibile abuso, errore operativo o operazione distruttiva; verificare tempestivamente.

## Prenotazioni e OTP

| Codice evento | Significato | Priorità e azione |
|---|---|---|
| `orders_accessed` | Un operatore ha caricato l'elenco prenotazioni. `count` è il numero di righe restituite, `page` la pagina e `search` indica se era attiva una ricerca. Può derivare anche dall'aggiornamento periodico del pannello. | Normale. Verificare solo accessi attribuiti a utenti WP inattesi. |
| `reservation_verification_requested` | La richiesta è valida, l'OTP è stato affidato al trasporto email e la verifica è pendente. `books` indica i volumi richiesti. Non significa che la consegna email sia garantita. | Normale. Se l'utente non riceve nulla, controllare spam e trasporto SMTP. |
| `reservation_verification_email_failed` | WordPress/PHPMailer ha rifiutato l'invio dell'OTP; la richiesta temporanea viene eliminata. | Da verificare: eseguire il test email e controllare SMTP/log del server. |
| `reservation_verification_confirmed` | L'OTP corretto è stato verificato. La prenotazione è stata creata oppure riconosciuta come richiesta già elaborata. | Normale. Correlare con `reservation_created`. |
| `reservation_verification_failed` | È stato inserito un OTP errato. `remaining_attempts` indica i tentativi rimasti. | Da verificare se ripetuto; assistere l'utente o valutare tentativi impropri. |
| `reservation_verification_invalid_or_expired` | Il `requestId` non esiste, è scaduto o ha esaurito i tentativi. | Normale se occasionale; l'utente deve richiedere un nuovo OTP. |
| `reservation_verification_rate_limited` | Troppi invii OTP o tentativi dalla stessa email, connessione o dall'intero servizio. Nei dettagli `global_allowed`, `ip_allowed` ed `email_allowed` mostrano quale controllo ha bloccato. | Attenzione se frequente o globale; verificare possibili abusi e impostazioni anti-abuso. |
| `reservation_confirmation_rejected` | OTP corretto, ma la creazione finale non è riuscita. `error_code` spiega il motivo, spesso volume non più disponibile o limite raggiunto. | Da verificare. È normale in caso di prenotazioni simultanee sullo stesso volume. |
| `reservation_created` | Prenotazione salvata correttamente. L'entità contiene il codice definitivo; `books` è il numero dei volumi. | Normale. |
| `staff_reservation_created` | Un operatore WordPress ha creato una prenotazione direttamente in sede, senza OTP e senza i limiti quantitativi o temporali del percorso pubblico. L'entità contiene il codice e `books` il numero dei volumi. | Normale se coerente con una richiesta allo sportello; verificare sempre l'utente WP associato. |
| `reservation_summary_resent` | Un operatore ha richiesto un nuovo invio del riepilogo all'email registrata. `accepted: true` indica che il trasporto email ha accettato il messaggio, non che il destinatario lo abbia ricevuto. | Da verificare se l'esito è negativo o se i reinvii si ripetono. |
| `reservation_rate_limited_ip` | La connessione ha raggiunto il limite giornaliero di prenotazioni definitive. | Attenzione se ripetuto; controllare IP/proxy e possibili abusi. |
| `reservation_rate_limited_email` | L'indirizzo ha raggiunto il limite giornaliero configurato. | Normale se il limite è intenzionale; verificare con l'utente. |
| `reservation_active_limit_reached` | L'email possiede già il massimo numero di prenotazioni attive. `active_reservations` indica quante. | Normale; attendere chiusura/scadenza o modificare motivatamente il limite. |
| `reservation_email_limits_exempted` | L'email è nella whitelist e i limiti associati all'indirizzo non sono stati applicati. I limiti IP/globali restano attivi. | Normale, ma verificare che l'indirizzo sia autorizzato. |
| `reservation_blocklist_rejected` | L'email è in blacklist. La richiesta viene fermata prima dell'OTP o nuovamente prima della conferma. | Normale se la blacklist è corretta; verificare eventuali contestazioni. |
| `order_status_changed` | Un operatore ha modificato una prenotazione. `previous_status` e `status` mostrano prima e dopo; `action` riporta conferma, annullamento o riapertura. | Normale; verificare l'utente WP se la modifica non era prevista. |
| `order_expired` | Una prenotazione non ritirata è scaduta automaticamente e i volumi sono stati liberati. `auto: true` indica il processo pianificato. | Normale. |

## Catalogo e dati

| Codice evento | Significato | Priorità e azione |
|---|---|---|
| `books_imported` | Importazione Excel completata. `count` è il totale elaborato, `inserted` i nuovi volumi e `updated` quelli aggiornati. | Normale; confrontare i conteggi con il file sorgente. |
| `database_reset` | Catalogo, prenotazioni e relative righe sono stati eliminati tramite reset. | Attenzione: operazione distruttiva. Verificare immediatamente autore e autorizzazione. |
| `purge_all_data` | È stata eseguita la cancellazione/anonimizzazione generale prevista dagli strumenti privacy. `anonymized_orders` riporta le prenotazioni anonimizzate. | Attenzione: verificare richiesta, backup e utente WP. |
| `manual_cleanup` | Un amministratore ha avviato manualmente una manutenzione. `job` identifica `all`, `ip`, `gdpr`, `audit` o `expired`. | Normale se pianificata; verificare l'autore. |
| `backup_restored` | Un backup completo è stato validato e ripristinato. I dettagli mostrano i conteggi di `books`, `orders`, `order_items` e `audit_log`. | Attenzione: controllare autore, data del backup e conteggi. |
| `backup_downloaded` | Un autorizzato ha scaricato un backup completo cifrato. `bytes` indica la dimensione e `encrypted: true` conferma la cifratura applicativa. | Attenzione: verificare autore e motivo operativo del download. |

## Email, sicurezza e accessi

| Codice evento | Significato | Priorità e azione |
|---|---|---|
| `mail_test` | Un amministratore ha richiesto un'email di test. `accepted: true` significa accettata da WordPress/PHPMailer, non necessariamente consegnata alla casella. | Da verificare se `Non riuscita` o se il messaggio non arriva. |
| `db_admin_auth_failed` | Password di sicurezza errata su importazione, reset o altra operazione protetta. | Attenzione se ripetuto; controllare l'operatore e non condividere la password. |
| `db_admin_auth_blocked` | Troppi tentativi di password di sicurezza dalla stessa origine. | Attenzione: attendere il termine del blocco e verificare possibili abusi. |
| `privacy_db_auth_failed` | Password di sicurezza errata durante un'operazione privacy amministrativa. | Attenzione; verificare l'utente WP associato. |
| `privacy_db_auth_blocked` | Operazioni privacy bloccate per troppi tentativi di password. | Attenzione; verificare immediatamente se non atteso. |
| `credentials_rotated` | La password aggiuntiva di sicurezza del plugin è stata modificata. `db_changed: true` indica l'avvenuta rotazione; non si tratta della password MySQL di WordPress. | Attenzione: confermare che la modifica fosse autorizzata. |
| `login_success`, `login_failed`, `logout` | Eventi storici del sistema di accesso proprietario rimosso nella 9.4.7. Non possono essere generati dal runtime corrente. | Nessuna azione sui record storici; se compare un nuovo evento, verificare versione e integrità dei file installati. |
| `password_recovery_requested`, `password_reset_invalid_token`, `password_reset_success`, `password_changed` | Eventi storici del recupero password proprietario rimosso nella 9.4.7. | Nessuna azione sui record storici; un evento nuovo richiede verifica tecnica. |

## Impostazioni e sistema

| Codice evento | Significato | Priorità e azione |
|---|---|---|
| `settings_updated` | Impostazioni operative, email, conservazione o anti-abuso modificate. `keys` elenca i campi salvati. | Normale se autorizzato; controllare modifiche inattese a limiti, whitelist o blacklist. |
| `appearance_updated` | Aspetto, logo, colori o collegamenti pubblici modificati. `keys` elenca i campi. | Normale; verificare l'anteprima pubblica. |
| `plugin_activated` | Plugin attivato. `version` indica la versione caricata. | Normale dopo installazione, aggiornamento o riattivazione manuale. |
| `audit_privacy_migration_completed` | La bonifica incrementale dei dettagli dei log storici è terminata. `examined`, `updated` ed `errors` riportano i conteggi senza identificatori personali. | Normale dopo l'aggiornamento alla 9.4.7; verificare la diagnostica se `errors` è maggiore di zero. |
| `audit_privacy_migration_retried` | Un autorizzato ha pianificato la ripresa della bonifica log dalla pagina Privacy e sicurezza. | Verificare l'utente WP e controllare poi lo stato in diagnostica. |

## Privacy e GDPR

| Codice evento | Significato | Priorità e azione |
|---|---|---|
| `gdpr_verification_requested` | Inviato il collegamento/codice per verificare una richiesta privacy dell'interessato. `action` distingue esportazione e cancellazione. | Normale. |
| `gdpr_verification_failed` | Verifica privacy non valida, scaduta o già usata. | Da verificare se ripetuto. |
| `gdpr_data_export_verified` | L'interessato verificato ha esportato i propri dati. `orders_count` indica le prenotazioni incluse. | Normale; evento rilevante ai fini privacy. |
| `gdpr_data_deletion_verified` | Richiesta verificata di cancellazione eseguita. `anonymized` e `deleted` indicano i record trattati. | Normale se richiesta; conservare la tracciabilità prevista. |
| `gdpr_data_export_admin` | Un autorizzato ha esportato dati personali dal pannello. | Attenzione: verificare finalità e utente WP. |
| `gdpr_data_deletion_admin` | Un autorizzato ha cancellato o anonimizzato dati dal pannello. Per una richiesta basata sul codice, l’entità è la prenotazione; per una richiesta basata sull’email è usato un riferimento casuale non riconducibile all’indirizzo. `scope`, `anonymized`, `deleted` e `transient_cleanup` descrivono l’esecuzione. | Attenzione: operazione irreversibile; verificare autorizzazione, esito e utente WP. |
| `gdpr_auto_cleanup` | Il processo pianificato ha eliminato/anonimizzato dati oltre i termini di conservazione. | Normale; controllare i conteggi se inattesi. |
| `ip_anonymization` | Indirizzi IP più vecchi del periodo configurato sono stati anonimizzati. | Normale. |
| `wp_privacy_eraser` | È stato usato lo strumento privacy nativo di WordPress. I dettagli indicano record anonimizzati, eliminati e dati temporanei rimossi. | Attenzione: verificare la richiesta WordPress associata. |
| `privacy_subject_searched` | Un autorizzato ha cercato tutti i dati collegati a un’email. `reason` riporta la motivazione e `results` le prenotazioni trovate. | Attenzione: verificare che esista una richiesta o pratica valida. |
| `privacy_subject_export_downloaded` | È stato scaricato l’export completo di un interessato, inclusi i log tecnici correlati. | Attenzione: il file contiene dati personali; verificarne trasmissione e cancellazione. |
| `privacy_subject_rectified` | Nome, cognome, email ed eventuale domicilio sono stati rettificati; le richieste temporanee precedenti sono state invalidate. | Normale se richiesto dall’interessato; controllare motivazione e autore. |
| `privacy_subject_restricted` | È stata registrata una limitazione temporanea del trattamento fino alla data indicata. | Attenzione: riesaminare alla scadenza e non confonderla con la blacklist anti-abuso. |
| `privacy_subject_deletion_authorized` | Un operatore ha autorizzato e motivato la cancellazione/anonimizzazione amministrativa. Dopo l’operazione non vengono reinseriti email o fingerprint: la correlazione usa il codice prenotazione oppure lo stesso riferimento casuale dell’operazione. | Attenzione: correlare con l’esito `gdpr_data_deletion_admin`, l’orario e l’utente WP. |

## Chiavi frequenti nei dettagli

| Chiave | Interpretazione |
|---|---|
| `count`, `books`, `orders_count` | Numero di elementi interessati o restituiti. |
| `inserted`, `updated`, `deleted`, `anonymized` | Quantità inserite, aggiornate, eliminate o anonimizzate. |
| `scope` | Ambito dell’operazione privacy: `reservation_code` oppure `email_without_identifier_retention`. |
| `page` | Pagina amministrativa caricata. |
| `search` | `true` se era attivo un filtro di ricerca. |
| `accepted` | Il trasporto email ha accettato il messaggio; non prova la consegna finale. |
| `email_hash` | Campo storico rimosso dalla 9.4.7. La bonifica automatica lo elimina dai dettagli mantenendo i dati operativi. |
| `email_exempt` | Indica l'applicazione della whitelist per i limiti legati all'email. |
| `remaining_attempts` | Tentativi OTP ancora disponibili. |
| `error_code` | Codice tecnico dell'errore da comunicare all'assistenza. |
| `previous_status`, `status` | Stato precedente e nuovo della prenotazione. |
| `keys` | Nomi delle impostazioni salvate, non i loro valori. |

## Quando segnalare un evento

Esportare il CSV con l'intervallo di date interessato e contattare l'amministratore tecnico quando si verificano blocchi globali ripetuti, errori email continuativi, accessi attribuiti a utenti WP inattesi, ripristini/reset non autorizzati o numerosi tentativi falliti. Non inviare tramite canali non protetti backup, indirizzi, IP o esportazioni contenenti dati personali.
