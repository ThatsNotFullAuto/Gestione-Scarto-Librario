# Diagnosi consegna email OTP

## Evidenza dal flusso applicativo

Il plugin passa alla schermata del codice solo quando `wp_mail()` restituisce `true`. Questo significa che WordPress/PHPMailer ha accettato il messaggio, non che il server destinatario lo abbia consegnato. In caso di `false`, la richiesta OTP viene eliminata e l'utente riceve un errore 503.

## Configurazione osservata

Il mittente configurato e `bs-scts@cultura.gov.it`, mentre il sito e ospitato sotto `divulgando.eu`. Senza SMTP autenticato autorizzato a inviare per `cultura.gov.it`, il messaggio puo essere accettato localmente ma respinto o scartato lungo la consegna per mancato allineamento SPF, DKIM o DMARC.

## Strumenti aggiunti nella 9.0.4

In `Scarto Librario > Privacy e sicurezza` e disponibile un test email protetto da capability, nonce e rate limit. La diagnostica mostra ultimo esito, data, contesto ed eventuale errore PHPMailer, senza salvare destinatario, oggetto, corpo o codice OTP. Un esito "accettato" seguito da mancata ricezione indica un problema di trasporto/deliverability esterno a WordPress.

## Configurazione richiesta

Configurare sull'hosting un relay SMTP autenticato autorizzato per il mittente scelto. Per `@cultura.gov.it` servono credenziali e parametri forniti dall'amministrazione del dominio; in alternativa usare un mittente appartenente al dominio del sito con SPF/DKIM corretti. Non inserire credenziali SMTP nel codice o nello ZIP del plugin.
