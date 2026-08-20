# Piano di adeguamento privacy e sicurezza 9.4.0

> Nota: la regola sul domicilio descritta in questo piano e' stata superata dalla versione 9.4.1. Fare riferimento a `PIANO-RECAPITI-PRENOTAZIONI-9.4.1.md`: online si usa esclusivamente l'email verificata; in sede si usa l'email oppure, se assente, il domicilio.

## Obiettivo

Allineare il plugin alle decisioni organizzative da sottoporre al Titolare e al DPO, riducendo i dati raccolti, rendendo verificabili i diritti degli interessati e proteggendo le copie complete dell'archivio. L'aggiornamento e additivo e non elimina catalogo o prenotazioni esistenti.

## Interventi implementati

- Raccolta del domicilio configurabile nella 9.4.0; comportamento sostituito dalla regola automatica per origine nella 9.4.1.
- `User-Agent` rimosso dalle prenotazioni e conservato solo nei log, con anonimizzazione contestuale all'IP.
- Informativa ed export estesi a IP, `User-Agent`, log, blacklist, backup e comunicazioni email.
- Area **Interessati** protetta da capability, nonce, password e motivazione per ricerca, export, rettifica, limitazione e cancellazione.
- Blacklist strutturata con motivo breve, autore, inserimento, scadenza o riesame; whitelist limitata agli account istituzionali.
- Backup completo cifrato con AES-256-GCM, password separata, download registrato e nessun uso della Media Library.
- Reset esteso a OTP cifrati pendenti, token privacy e contatori temporanei.
- Stato degli ultimi cleanup esposto con data e conteggi; periodi modificabili solo con piano attestato e password.

## Verifiche tecniche

- Eseguire sintassi PHP, TypeScript, controlli di sicurezza, build e smoke test.
- Provare upgrade da 9.3.2 con dati realistici e verificare migrazione dello `User-Agent`.
- Collaudare entrambi i percorsi domicilio attivo/disattivo, inclusi reinvio email e PDF.
- Verificare export completo, rettifica, limitazione, cancellazione e audit con ruoli autorizzati e non autorizzati.
- Eseguire round trip del backup cifrato e import di un backup legacy su staging.

## Approvazioni e rilascio

Prima della produzione, l'ente deve approvare finalita e periodi di conservazione, procedura blacklist, custodia delle password di backup e gestione delle richieste degli interessati. La versione va distribuita inizialmente come release candidate, con backup pre-aggiornamento e procedura di rollback documentata.
