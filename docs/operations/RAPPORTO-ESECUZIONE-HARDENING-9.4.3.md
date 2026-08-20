# Rapporto di esecuzione hardening 9.4.3

**Data:** 19 agosto 2026  
**Baseline immutabile:** 9.4.2  
**Nuova release:** 9.4.3  
**Schema database:** 8.15 invariato  
**2FA:** non introdotta, per indicazione del committente

## Interventi completati nel codice

- aggiunte le route staff di creazione e reinvio alla protezione globale `no-store`, che copre anche gli errori REST;
- assegnata `/purge-all-data` alla capability privacy con nonce, stessa origine, rate limit e password aggiuntiva;
- mantenuti invariati ruoli e capability assegnate, evitando lockout durante l'upgrade;
- isolato il contatore dei tentativi della password aggiuntiva per account WordPress e IP;
- sostituita la denominazione ambigua della password database con “Password di sicurezza del plugin”, senza cambiare hash o chiave storica;
- disabilitati per default i backup legacy non cifrati, con eccezione esplicita solo per staging;
- aggiunto controllo diagnostico sullo stato della compatibilita legacy;
- rafforzata la pipeline ZIP contro file sensibili, segreti, percorsi locali e incoerenze di versione;
- fissati timestamp e permessi dell'archivio per build ripetibili;
- aggiunti controlli statici di regressione per tutte le misure precedenti;
- predisposte procedure di upgrade, rollback e hardening server antilockout.

## Invarianti preservati

- nessuna modifica alle tabelle o ai dati esistenti;
- nessuna rotazione o cancellazione di password;
- nessuna modifica al login WordPress;
- nessuna modifica a XML-RPC, `/wp-json/`, `.htaccess`, WAF o `wp-config.php` inclusa nel plugin;
- nessuna introduzione di 2FA;
- catalogo, prenotazioni, email, PDF, statistiche, log e strumenti interessati restano funzionalmente presenti;
- rollback binario alla 9.4.2 compatibile con lo schema 8.15.

## Verifiche automatiche richieste prima della firma

- `npm run check:php`;
- `npm run check:security`;
- `npm run type-check`;
- `npm audit`;
- `npm run build`;
- `npm run test:security:smoke:self`;
- doppia esecuzione del build release con confronto SHA-256;
- ispezione contenuto ZIP e unicita della directory radice.

## Esito automatico

- sintassi PHP: superata per tutti i file runtime;
- controlli statici di sicurezza: superati;
- TypeScript: superato;
- `npm audit`: zero vulnerabilita note;
- build Vite pubblico e amministrativo: superata;
- smoke test locale: tutte le verifiche superate;
- ZIP: una sola radice, nessun documento, backup, Excel, log o sorgente di sviluppo;
- riproducibilita: due generazioni consecutive identiche;
- SHA-256 candidato: `37cee5c0c0f21bb5d2936f882c8e3fbf6a20131d8147cb583539f3781651d0e1`.

Non sono stati eseguiti contro la 9.4.3 i test autenticati di concorrenza, recapito SMTP, cache/CDN e ripristino nel WordPress reale; appartengono al collaudo staging obbligatorio.

## Attivita esterne non dichiarabili come eseguite

Le seguenti azioni richiedono credenziali e responsabilita del gestore WordPress/hosting e non possono essere applicate da una copia locale del plugin: `DISALLOW_FILE_EDIT`, regole Apache, rimozione `readme.html`, limitazione profili REST, valutazione XML-RPC, WAF, proxy, vulnerability scan autenticato e penetration test. Devono essere registrate nella tabella di `HARDENING-SITO-ANTILOCKOUT-9.4.3.md`.

Il piano puo essere considerato concluso lato plugin dopo superamento dei test e verifica dello ZIP. La messa in sicurezza dell'intero sito resta conclusa solo dopo l'esecuzione documentata delle attivita esterne applicabili.
