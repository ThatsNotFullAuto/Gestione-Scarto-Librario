# Piano di ripristino area amministrativa e import Excel

## 1. Obiettivo e perimetro

La lavorazione avviene esclusivamente nella copia `Plugin-admin-wordpress-import-excel/`, clonata dalla versione 9.0.1 in `Plugin-admin-wordpress/`. La sorgente e gli ZIP 9.0.0/9.0.1 restano immutati. L'obiettivo e ripristinare Prenotazioni e Catalogo in `wp-admin`, rendere l'import Excel chiaramente accessibile e verificare le principali best practice WordPress senza modifiche distruttive ai dati esistenti.

## 2. Diagnosi iniziale

Entrambe le pagine mostrano il fallback PHP "Caricamento Scarto Librario..." perche condividono `dist/admin`. Il bundle generato e un modulo ES e contiene `export`, mentre `includes/admin.php` lo registra come script classico affidandosi a `wp_script_add_data(..., 'type', 'module')`. Se WordPress non produce l'attributo `type="module"`, il browser interrompe l'esecuzione prima del mount React. L'import Excel, gia presente in React e nella route REST `POST /scarto/v1/books`, resta quindi irraggiungibile.

## 3. Interventi programmati

1. Correggere il caricamento del bundle admin con l'API Script Modules disponibile nella versione minima WordPress 6.6, mantenendo una compatibilita difensiva e caricando la configurazione prima del modulo.
2. Mostrare nel contenitore PHP un fallback accessibile con indicazioni diagnostiche e collegamento diretto al Catalogo; nasconderlo automaticamente quando React parte.
3. Rendere l'area Catalogo esplicita: import `.xlsx`/`.xls`, formato colonne atteso, limite file, conferma con password di sicurezza, riepilogo esito ed errori comprensibili.
4. Validare il file nel browser prima dell'invio: estensione, dimensione, foglio presente, righe non vuote, limite massimo, intestazioni e campi obbligatori. Non accettare formule come dati eseguibili.
5. Rafforzare l'API import con capability dedicata, nonce REST, password di secondo livello, limiti di payload/righe, normalizzazione tipizzata, transazione e audit privo di dati personali.
6. Evitare ID casuali instabili quando e disponibile l'inventario; segnalare duplicati e righe non valide prima di alterare il catalogo.
7. Incrementare versione plugin/package, rigenerare `dist/public` e `dist/admin` e produrre un nuovo artefatto senza sovrascrivere le release esistenti.

## 4. Verifica best practice WordPress

La revisione coprira: guardia `ABSPATH`, ruoli e privilegio minimo, nonce separato dall'autorizzazione, schema e `permission_callback` REST, sanitizzazione input, escaping tardivo, query preparate, prefissi `scarto_`, caricamento asset limitato alle pagine del plugin, localizzazione delle stringhe, privacy/GDPR, uninstall non distruttivo per impostazione predefinita, dipendenze locali, assenza di segreti e struttura ZIP installabile. Le anomalie non risolvibili senza un WordPress reale saranno registrate come rischi residui.

## 5. Criteri di accettazione

- Prenotazioni e Catalogo sostituiscono il fallback PHP con l'interfaccia React.
- Catalogo espone sempre un'azione visibile "Importa catalogo Excel" agli utenti con `scarto_manage_catalog`.
- File errati, sovradimensionati, vuoti, con colonne mancanti o oltre 50.000 righe vengono rifiutati prima di modificare il database.
- Import valido restituisce conteggi di inseriti, aggiornati ed eliminati; un errore causa rollback.
- Utente non autorizzato, nonce mancante/errato e password errata ricevono `403`; payload eccessivo riceve errore controllato.
- `npm run check:php`, `npm run check:security`, `npm run type-check`, `npm run build` e smoke test locale terminano con esito registrato.
- Lo ZIP contiene una sola directory plugin e nessun `node_modules`, sorgente, report, segreto o source map.

## 6. Collaudi manuali obbligatori

Su staging WordPress: amministratore e Responsabile vedono Catalogo/import; Operatore non lo vede; Prenotazioni funziona per i ruoli previsti; import valido/invalido e conflitto con prenotazioni attive sono comprensibili; refresh, logout/login e cache svuotata non riproducono la schermata bloccata. Verificare inoltre tastiera, focus del dialogo, zoom 200%, mobile, email/PDF, backup e ripristino. Questi test ambientali non possono essere sostituiti dalla sola verifica statica.

## 7. Rollback e stato

Prima dell'installazione salvare database e plugin attivo. In caso di regressione disattivare la candidata, reinstallare lo ZIP 9.0.1 originale e ripristinare il database solo se un'operazione di import e stata confermata.

## 8. Stato di attuazione

Completati nella candidata 9.0.2: copia verificata (8.685 file per origine e destinazione), correzione del modulo ES condiviso, configurazione admin nel markup, fallback diagnostico, import Excel visibile e validato, ID stabili da inventario, riepilogo import, risposta REST privata, conservazione dati predefinita in uninstall, polling pubblico silenzioso e sospeso durante le sessioni attive, aggiornamento dipendenze e rigenerazione dei bundle. Superati sintassi PHP, TypeScript, build, controlli statici di sicurezza, smoke test locale e `npm audit` con zero vulnerabilita. Restano i collaudi WordPress/staging del paragrafo 6.

Aggiornamento candidata 9.0.3: il file reale `BSTS - Scarto librario 2026 - Copia (1).xlsx` contiene 3.888 righe, 273 righe con inventario ripetuto, 6 senza inventario, 2 senza titolo e 14 valori Anno oltre 20 caratteri. L'import ora valida il file prima della password, conserva tutti i record con 3.888 ID deterministici univoci, converte i campi mancanti in fallback dichiarati e mostra avvisi non bloccanti. Il campo Anno e stato esteso a 100 caratteri tramite migrazione idempotente dello schema 8.12. Suite `npm run check` superata sulla 9.0.3.
