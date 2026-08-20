# Security Policy

## Versioni supportate

La manutenzione di sicurezza riguarda la versione corrente 9.4.4. Le versioni precedenti devono essere considerate superate e aggiornate dopo backup e verifica in staging.

## Segnalazione responsabile

Non pubblicare issue contenenti vulnerabilita sfruttabili, dati personali, URL amministrativi, log completi o credenziali. Usare una GitHub Private Vulnerability Report, se disponibile, oppure contattare privatamente la Biblioteca statale Stelio Crise tramite `bs-scts@cultura.gov.it`, indicando nell'oggetto "Segnalazione sicurezza plugin Scarto Librario".

La segnalazione dovrebbe includere versione, prerequisiti, passaggi minimi per riprodurre, impatto osservato e proposta di mitigazione. Usare esclusivamente dati fittizi. Non effettuare test distruttivi, brute force, esfiltrazione o prove sul sito istituzionale senza autorizzazione scritta.

## Distribuzione sicura

Verificare il checksum SHA-256 della release, eseguire un backup e provare l'aggiornamento in staging. Conservare la password di sicurezza del plugin fuori dal repository e dal browser. Applicare il piano in `docs/operations/HARDENING-SITO-ANTILOCKOUT-9.4.3.md`, mantenendo sempre un accesso SFTP/SSH o equivalente per il ripristino.

Le impostazioni di privacy, retention, SMTP, proxy e WAF dipendono dall'ambiente WordPress e non possono essere garantite dal solo codice del plugin.
