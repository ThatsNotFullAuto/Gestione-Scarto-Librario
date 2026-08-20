# Verifica import catalogo 2026

## Fixture

File verificato: `BSTS - Scarto librario 2026 - Copia (1).xlsx`, primo foglio `BSTS - Scarto 2025`.

## Profilo dati

- 3.888 righe di catalogo;
- 273 righe appartenenti a 14 valori Inventario ripetuti;
- 6 righe senza Inventario;
- 2 righe senza Titolo;
- 14 valori Anno oltre il precedente limite di 20 caratteri;
- nessun altro campo oltre i limiti REST configurati.

## Comportamento 9.0.3

Gli inventari ripetuti non sono piu trattati come ID duplicati: ogni volume riceve un ID deterministico basato su inventario e contenuto. Le righe senza inventario ricevono un ID dal contenuto; quelle senza titolo usano `Senza titolo`. Il campo Anno accetta fino a 100 caratteri mediante schema REST, sanitizzazione e migrazione database 8.12 coerenti.

La simulazione sul file ha prodotto 3.888 ID univoci su 3.888 righe, lunghezza massima 30 caratteri. Il file viene analizzato prima dell'apertura della richiesta password; gli elementi incompleti sono mostrati come avvisi e non bloccano l'import.

Artefatto verificato: `gestione-scarto-librario-9.0.3.zip`, SHA-256 `12eeebdf13ff6bf84b45021eff136761aea953c0b8d2551451b29d15962d3733`. Payload stimato del catalogo: 1.272.969 byte su un limite REST di 20.971.520 byte.

## Verifica ancora necessaria

Il test dimostra compatibilita strutturale e compilazione, non una scrittura sul database di produzione. Installare la 9.0.3 su staging con backup, importare il file, verificare conteggio 3.888, campioni con inventario ripetuto/senza inventario e pagina pubblica, quindi autorizzare la produzione.
