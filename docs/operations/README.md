# Hardening del sito con procedura antilockout

Questo modulo è deliberatamente separato dallo ZIP di Gestione Scarto Librario. Non modifica il database del plugin e, appena installato, non cambia alcun comportamento: entrambe le costanti valgono `false`.

## Prerequisiti

1. Eseguire un backup e verificare accesso SFTP/WinSCP e pannello hosting.
2. Provare prima su staging con una seconda sessione amministrativa già aperta.
3. Verificare login, REST del plugin, Elementor, form, integrazioni e applicazioni che usano XML-RPC.
4. Attivare una sola protezione alla volta e ripetere i controlli.

## Installazione controllata

Creare una cartella `bibliocrise-hardening` in `wp-content/plugins/`, caricare `bibliocrise-hardening.php` e attivarla da WordPress. In `wp-config.php`, prima della riga finale di stop, aggiungere inizialmente:

```php
define('BIBLIOCRISE_RESTRICT_PUBLIC_USER_REST', false);
define('BIBLIOCRISE_DISABLE_XMLRPC', false);
```

Impostare a `true` soltanto `BIBLIOCRISE_RESTRICT_PUBLIC_USER_REST`, verificare che gli utenti anonimi ricevano `403` da `/wp-json/wp/v2/users`, quindi collaudare il sito. Solo dopo, se nessun servizio usa XML-RPC, impostare a `true` `BIBLIOCRISE_DISABLE_XMLRPC` e verificare nuovamente.

## Rollback immediato

Riportare la costante interessata a `false`. Se il pannello non è accessibile, rinominare via WinSCP `wp-content/plugins/bibliocrise-hardening` in `bibliocrise-hardening.off`. Il plugin Scarto Librario e i suoi dati non vengono toccati.

Queste misure non sostituiscono aggiornamenti, WAF, backup, log del server, least privilege e revisione degli account WordPress. Non abilitano 2FA.
