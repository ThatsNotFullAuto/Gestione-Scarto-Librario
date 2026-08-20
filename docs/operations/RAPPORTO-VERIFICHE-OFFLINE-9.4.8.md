# Rapporto verifiche offline 9.4.8

**Data:** 20 agosto 2026  
**Oggetto:** stile e completezza del PDF di conferma prenotazione

Il generatore server-side è stato uniformato per non dipendere dalla presenza di TCPDF o FPDF sull'hosting. Download pubblico e allegato email continuano a usare gli stessi byte.

Il test automatico genera in memoria una prenotazione con 70 volumi e verifica:

- intestazione strutturata e separatori grafici;
- codice prenotazione grande ed evidenziato;
- dati del richiedente, data e scadenza;
- presenza dell'ultimo titolo, autore e inventario;
- istruzioni per il ritiro e footer configurabile;
- creazione di più pagine e relativa numerazione;
- validità degli offset della tabella `xref` del PDF.

Sono inoltre stati superati sintassi PHP, controlli statici, backup, privacy audit, import Excel, TypeScript, `npm audit`, build Vite e verifica deterministica dello ZIP. Il controllo visivo finale con dati reali resta da effettuare sul sito dopo l'aggiornamento.
