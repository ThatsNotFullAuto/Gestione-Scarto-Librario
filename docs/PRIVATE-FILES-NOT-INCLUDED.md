# File riservati non inclusi

La copia GitHub esclude intenzionalmente:

- catalogo librario reale e sue revisioni Excel;
- backup JSON del database del plugin;
- password di sicurezza, credenziali WordPress, SMTP o database;
- esportazioni di prenotazioni, log, statistiche o dati degli interessati;
- report di test eseguiti contro ambienti reali;
- corrispondenza con RPD/DPO e osservazioni preliminari non destinate alla pubblicazione;
- file temporanei, `node_modules/`, copie di lavoro e vecchie release ZIP.

Queste esclusioni non rendono incompleto il codice sorgente. I dati necessari allo sviluppo sono rappresentati da una fixture anonima; le dipendenze si ricostruiscono con `npm ci`; la sola release corrente e conservata in `releases/`.

Prima di ogni push eseguire almeno:

```bash
git status --short
git diff --cached
git grep -nEi 'password|secret|token|authorization|BEGIN .*PRIVATE KEY'
```

Ogni risultato deve essere esaminato nel contesto: i nomi tecnici e la documentazione possono contenere tali parole, ma mai valori reali.
