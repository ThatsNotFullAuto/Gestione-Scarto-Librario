# Piano recapiti prenotazioni 9.4.1

## Obiettivo

Applicare il principio di minimizzazione distinguendo il canale online dalla prenotazione raccolta al front office. L'aggiornamento non modifica ne' elimina prenotazioni esistenti e non richiede nuove colonne.

## Regole funzionali

- **Prenotazione online:** nome, cognome ed email sono obbligatori; l'email e' verificata tramite OTP. Il domicilio non viene mostrato, accettato o conservato.
- **Prenotazione in sede con email:** nome, cognome ed email valida sono registrati. Il domicilio non viene raccolto perche' il riepilogo puo' essere inviato elettronicamente.
- **Prenotazione in sede senza email:** nome, cognome e domicilio strutturato completo sono obbligatori; le note di spedizione restano facoltative. Il documento protocollato puo' essere spedito al domicilio.
- Per ogni nuova prenotazione in sede viene quindi conservato un solo recapito: email oppure domicilio.

## Interventi

1. Separare gli schemi e le validazioni REST per richieste pubbliche e amministrative.
2. Rendere il domicilio impossibile nel percorso online anche in presenza di payload manipolati.
3. Rendere email e conferma email facoltative nel front office; mostrare il domicilio solo in assenza dell'email.
4. Disabilitare il reinvio email per prenotazioni prive di email e fornire un messaggio esplicito all'operatore.
5. Mostrare in email, PDF ed export soltanto i dati effettivamente presenti.
6. Rimuovere dalle impostazioni la scelta globale sul domicilio. La vecchia chiave resta leggibile nei backup per compatibilita', ma non governa piu' il comportamento.
7. Aggiornare informativa dinamica, scheda tecnica e comunicazione al RPD/DPO.

## Sicurezza e compatibilita'

- Capability, nonce, transazioni, blocchi di disponibilita', OTP e limiti anti-abuso restano invariati.
- Blacklist e limiti per email si applicano quando un'email e' presente; la prenotazione senza email resta possibile soltanto a personale autenticato.
- I dati storici con email e domicilio restano consultabili, esportabili, rettificabili e cancellabili secondo le procedure gia' disponibili.
- La ricerca per codice consente di gestire anche interessati senza email.

## Verifiche richieste

- Rifiuto server-side del domicilio online e dell'email online mancante o non valida.
- Creazione in sede con email, senza domicilio e con invio del riepilogo.
- Creazione in sede senza email, con domicilio completo e senza tentativo di invio all'interessato.
- Rifiuto in sede quando mancano sia email sia domicilio o quando il recapito scelto e' incompleto.
- PDF, pannello prenotazioni, export privacy e backup con record di entrambi i tipi.
- Sintassi PHP, TypeScript, controlli di sicurezza, build, smoke test e struttura dello ZIP.

## Rilascio

Distribuire inizialmente come candidato `9.4.1`, dopo backup del sito e collaudo su staging. Prima dell'apertura il Titolare deve approvare l'informativa e la procedura di spedizione cartacea per le prenotazioni raccolte senza email.
