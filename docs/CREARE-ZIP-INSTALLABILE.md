# Creare lo ZIP installabile

Questa procedura genera un pacchetto WordPress riproducibile a partire dai sorgenti. Non comprimere manualmente l'intero repository: WordPress deve trovare una sola cartella radice `gestione-scarto-librario/` e, immediatamente al suo interno, il file `gestione-scarto-librario.php` con un header plugin valido.

## 1. Prerequisiti

Installare:

- Node.js 22 LTS o versione compatibile con `package-lock.json`;
- npm incluso con Node.js;
- PHP 8.2 o successivo per un controllo nativo facoltativo;
- almeno 500 MB liberi per dipendenze e build.

Clonare o scaricare il repository in un percorso locale. Non aggiungere cataloghi reali, backup, password o esportazioni alla cartella del plugin.

## 2. Verificare la versione

La stessa versione deve apparire in:

- `gestione-scarto-librario/package.json`, campo `version`;
- header `Version:` di `gestione-scarto-librario.php`;
- costante `SCARTO_VERSION` nello stesso file;
- header di `uninstall.php`.

`package.json` deve contenere anche una `releaseDate` ISO stabile. Questo valore rende deterministici data e contenuto dello ZIP.

## 3. Procedura automatica consigliata

### Windows PowerShell

Dalla radice del repository:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\crea-zip.ps1
```

### Linux o macOS

```bash
chmod +x crea-zip.sh
./crea-zip.sh
```

Gli script installano esattamente le dipendenze bloccate, eseguono tutti i controlli, compilano il frontend, generano lo ZIP e spostano ZIP e checksum in `releases/`.

## 4. Procedura manuale equivalente

```bash
cd gestione-scarto-librario
npm ci
npm run check
npm run test:security:smoke:self
npm run release
cd ..
```

`npm run release` esegue nuovamente `npm run check` e crea nella radice:

```text
gestione-scarto-librario-9.4.6.zip
gestione-scarto-librario-9.4.6.zip.sha256
```

Spostare entrambi in `releases/`. `node_modules/` serve solo localmente ed e ignorato da Git.

## 5. Cosa include il generatore

`tools/build-release.mjs` usa una allowlist e inserisce soltanto:

- file PHP principale e `uninstall.php`;
- moduli in `includes/` e template in `templates/`;
- asset runtime compilati in `dist/`;
- font runtime in `assets/fonts/`;
- `RELEASE-MANIFEST.json` con versione e SHA-256 di ogni file.

Non include `src/`, test, strumenti, documentazione, dipendenze npm, Excel, backup o configurazioni locali. Questi restano nel repository per sviluppo e revisione, ma non sono necessari all'esecuzione su WordPress.

Il generatore blocca inoltre estensioni riservate, percorsi locali, chiavi private e pattern tipici di credenziali. La sua allowlist non sostituisce comunque la revisione umana.

## 6. Verificare il checksum

Linux o macOS:

```bash
cd releases
sha256sum -c gestione-scarto-librario-9.4.6.zip.sha256
```

Windows PowerShell:

```powershell
$expected = (Get-Content .\releases\gestione-scarto-librario-9.4.6.zip.sha256).Split()[0]
$actual = (Get-FileHash .\releases\gestione-scarto-librario-9.4.6.zip -Algorithm SHA256).Hash.ToLower()
if ($actual -ne $expected) { throw "Checksum non valido" }
```

Per la release candidata 9.4.6 il valore atteso è:

```text
56952e52d74002857a1a84dd2039a717377555e400635da51a0af89ffe6d71a0
```

Una modifica lecita ai file runtime produce un checksum diverso e richiede una nuova versione. Ricreare due volte lo ZIP senza modifiche deve invece produrre lo stesso hash.

## 7. Ispezionare la struttura

Aprire lo ZIP senza estrarlo e verificare:

```text
gestione-scarto-librario/
  gestione-scarto-librario.php
  uninstall.php
  RELEASE-MANIFEST.json
  includes/
  templates/
  dist/
  assets/fonts/
```

Non devono esistere una seconda cartella annidata con lo stesso nome, file del repository alla radice dello ZIP, `node_modules/`, file sorgente non runtime o dati reali. Una struttura errata puo causare l'errore WordPress "Il plugin non ha un header valido".

## 8. Collaudo prima del rilascio

Su staging:

1. eseguire backup di file e database;
2. aggiornare il plugin usando lo ZIP, senza cancellarne le tabelle;
3. riattivarlo manualmente se WordPress non lo riattiva dopo la sostituzione;
4. verificare versione, diagnostica e assenza di errori PHP;
5. provare catalogo, prenotazione OTP, email, PDF e operazioni amministrative;
6. verificare permessi REST, header `no-store`, privacy, backup e ripristino;
7. mantenere accesso SFTP/SSH per applicare la procedura anti-lockout.

Solo dopo il collaudo caricare lo stesso file, con lo stesso checksum, in produzione. Non ricomprimerlo e non modificarlo tra staging e produzione.
