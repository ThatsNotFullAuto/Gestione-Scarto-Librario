# Dati personali, indirizzi IP e User-Agent

## Scopo e ambito

Questo documento descrive il trattamento effettuato dal plugin Gestione Scarto Librario 9.4.4. E' una scheda tecnica per il Titolare del trattamento e per il RPD/DPO: non costituisce un parere legale, una DPIA o una dichiarazione di conformita'. Hosting, server web, firewall, CDN, Microsoft 365, protocollo e backup esterni possono effettuare trattamenti ulteriori da censire separatamente.

## Dati forniti dall'interessato

Per tutte le prenotazioni vengono raccolti:

- nome e cognome;
- volumi selezionati;
- attestazione di presa visione dell'informativa, versione e data;

Il recapito dipende dall'origine della richiesta:

- **online:** l'indirizzo email e la relativa verifica OTP sono obbligatori; il domicilio non viene mostrato, accettato o conservato;
- **in sede con email:** il personale registra l'email, senza OTP; il domicilio non viene raccolto;
- **in sede senza email:** il personale registra via o piazza, numero civico, CAP, citta' e provincia; le note strettamente utili alla spedizione sono facoltative.

Nell'ultimo caso il domicilio serve a spedire la lettera contenente il documento protocollato che conferma la prenotazione e l'avvenuta consegna dei volumi in biblioteca. Non esiste piu' un'opzione globale di attivazione: il server applica automaticamente la regola e, quando e' presente l'email, scarta eventuali dati di domicilio inviati da un client modificato o non aggiornato.

I dati storici raccolti con regole precedenti non vengono cancellati automaticamente e restano soggetti ai periodi di conservazione e agli strumenti per i diritti degli interessati.

## Dati generati dal servizio

Il plugin tratta inoltre:

- codice univoco, stato, origine e date della prenotazione;
- titolo, autore, inventario e riferimenti dei volumi prenotati;
- indirizzo IP della prenotazione confermata;
- eventi di audit, esito, data, account WordPress dell'operatore e, quando pertinente, email dell'interessato;
- indirizzo IP e stringa `User-Agent` associati agli eventi di audit;
- blacklist: email, motivo sintetico, autore, data di inserimento e data di scadenza o riesame;
- limitazioni temporanee del trattamento disposte dal personale autorizzato;
- contatori anti-abuso con identificativi pseudonimizzati.

Il numero della scatola e' un dato operativo del catalogo riservato al personale. Il catalogo pubblico non espone nome, email, domicilio, IP, `User-Agent`, codice di prenotazione o numero della scatola.

Password, password di cifratura e codici OTP non sono inseriti nei log.

## Verifica OTP e dati temporanei

1. Il browser invia la richiesta alle API REST tramite HTTPS.
2. Il plugin genera un OTP e lo invia all'email indicata.
3. Il payload non ancora confermato e' cifrato con AES-256-GCM.
4. Nel database sono memorizzati temporaneamente il payload cifrato e gli hash dell'email e dell'OTP, non l'OTP in chiaro.
5. La verifica scade dopo circa 15 minuti.
6. Solo dopo la verifica vengono create la prenotazione e le associazioni con i volumi.

Le prenotazioni create in sede dal personale non richiedono OTP. Possono essere prive di email soltanto se contengono il domicilio completo; l'operazione e' attribuita all'account WordPress che l'ha eseguita.

## Indirizzi IP

### Raccolta e finalita'

L'IP e' rilevato dal server. Il plugin usa normalmente `REMOTE_ADDR`; intestazioni come `X-Forwarded-For` o `CF-Connecting-IP` sono accettate soltanto se il proxy e' stato configurato come attendibile.

L'IP in chiaro e' registrato nella prenotazione confermata e negli audit log pertinenti per prevenire abusi, applicare limiti, analizzare anomalie e ricostruire eventi operativi o di sicurezza. Non viene pubblicato ne' inserito nelle email.

### Contatori anti-abuso

Nei contatori di rate limiting l'IP non e' memorizzato direttamente. Una chiave `HMAC-SHA-256`, derivata usando un segreto WordPress, identifica il contatore; la tabella conserva hash, numero di tentativi e scadenza. Per IPv6 viene utilizzata la rete `/64` ai soli fini anti-abuso. I contatori scaduti sono eliminati dal cleanup.

## Stringa User-Agent

Il `User-Agent` e' trasmesso dal browser e puo' descrivere browser, versione, sistema operativo e tipo generale di dispositivo. Non identifica con certezza una persona e puo' essere modificato, ma, se associato a IP, email, data o prenotazione, e' un dato potenzialmente identificativo.

Il plugin ne conserva al massimo 500 caratteri esclusivamente negli audit log. Non lo salva nella prenotazione e non esegue fingerprinting, profilazione, pubblicita' comportamentale o correlazioni con altri siti.

## Conservazione e cleanup

I valori `365`, `90` e `30` giorni sono fallback tecnici, non periodi gia' approvati dall'ente:

- prenotazioni completate: anonimizzazione dopo 365 giorni;
- prenotazioni annullate o scadute: eliminazione dopo 90 giorni;
- audit log: eliminazione dopo 90 giorni;
- IP nelle prenotazioni e IP/User-Agent nei log: anonimizzazione dopo 30 giorni;
- richieste OTP cifrate: circa 15 minuti;
- token per richieste privacy: circa 30 minuti;
- blacklist: fino alla scadenza oppure al riesame e alla rimozione autorizzata.

Un audit log puo' essere eliminato prima della scadenza IP/User-Agent se il periodo dei log e' piu' breve. La modifica dei periodi richiede la dichiarazione che il piano e' stato approvato e la password di sicurezza del plugin. Questa password aggiuntiva non coincide con la credenziale MySQL di WordPress. Il pannello mostra data dell'ultimo cleanup e numero di record anonimizzati o eliminati.

Il Titolare, tenuto conto del parere del RPD/DPO, deve approvare e documentare finalita', base giuridica, tempi e criteri di conservazione. I dati eventualmente trasferiti a protocollo, posta, documenti cartacei o sistemi esterni seguono regole proprie.

## Comunicazioni, PDF e backup

I dati della prenotazione possono essere comunicati alla casella della biblioteca e ai fornitori tecnici necessari. Se e' presente un'email, il riepilogo e' inviato anche all'interessato. Senza email non viene effettuato alcun tentativo di invio all'interessato e il domicilio e' utilizzato per la procedura di spedizione cartacea. Il PDF e' creato temporaneamente e rimosso dopo il tentativo di invio; una pulizia elimina eventuali residui temporanei.

Il backup esportato contiene l'intero archivio del plugin, compresi catalogo, prenotazioni, log, blacklist, limitazioni e impostazioni. E' cifrato prima del download con AES-256-GCM e chiave derivata dalla password separata tramite PBKDF2-HMAC-SHA256; non viene inserito nella Media Library e il download e' registrato. Dopo il download, custodia, accessi, copie e cancellazione del file sono responsabilita' organizzative esterne al plugin.

## Ricerca ed esercizio dei diritti

Il pannello `Interessati`, riservato al personale autorizzato, consente di cercare per email o codice di prenotazione e di:

- esportare i dati in JSON, inclusi IP e `User-Agent` ancora presenti nei log correlati;
- rettificare i dati presenti, compresi email o domicilio quando pertinenti;
- registrare una limitazione temporanea del trattamento;
- cancellare o anonimizzare i dati quando ne ricorrono i presupposti.

Le operazioni richiedono autorizzazione WordPress, nonce, password di sicurezza e motivazione e sono registrate. Una prenotazione attiva impedisce la cancellazione automatica. La blacklist e' mantenuta separatamente e richiede un riesame autorizzato: non deve essere rimossa automaticamente se serve ancora alla prevenzione di abusi.

Gli strumenti tecnici non decidono se una richiesta debba essere accolta. Identificazione del richiedente, presupposti, eccezioni, sistemi esterni da consultare e contenuto del riscontro restano responsabilita' del Titolare secondo la procedura interna.

## Reset del servizio

Il reset elimina catalogo, prenotazioni, associazioni con i volumi, richieste OTP cifrate pendenti, token privacy e contatori temporanei. Mantiene impostazioni e audit log necessari a documentare l'operazione. Prima della messa in produzione e' opportuno eseguire un reset dei dati di prova e verificarne l'esito.

## Punti da sottoporre al RPD/DPO

Occorre confermare almeno:

- base giuridica e riferimenti normativi del servizio;
- necessita', proporzionalita' e procedura di utilizzo del domicilio per le sole prenotazioni in sede senza email;
- periodi di conservazione per prenotazioni, IP, log, email, protocollo e backup;
- criteri, durata e riesame della blacklist;
- procedura per i diritti degli interessati e canali di contatto;
- ruoli, responsabili del trattamento, trasferimenti e misure dei fornitori;
- aggiornamento del registro dei trattamenti e necessita' di una DPIA.
