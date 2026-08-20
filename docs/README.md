# Indice documentazione

## Architettura e valutazione

- `architecture/DOSSIER-TECNICO-DPIA-IT.md`: dossier italiano per analisi tecnica, sicurezza e privacy.
- `architecture/TECHNICAL-SECURITY-PRELIMINARY-DPIA-EN.md`: versione inglese.
- I dossier Markdown descrivono la candidata 9.4.7; i PDF 9.4.4 restano copie storiche e non sostituiscono i sorgenti aggiornati.

## Esercizio e aggiornamento

- `CREARE-ZIP-INSTALLABILE.md`: procedura completa per build, ZIP, checksum e controllo del pacchetto.
- `operations/UPGRADE-9.4.8.md`: aggiornamento, verifica PDF e rollback della release corrente.
- `operations/RAPPORTO-VERIFICHE-OFFLINE-9.4.8.md`: test strutturali e multipagina del riepilogo PDF.
- `operations/RAPPORTO-ESECUZIONE-AUDIT-9.4.7.md`: implementazione, evidenze locali e gate esterni ancora necessari.
- `operations/PIANO-OPERATIVO-AUDIT-SICUREZZA-PRIVACY-9.4.6.md`: piano tecnico eseguito per la candidata 9.4.7.
- `operations/README.md`: procedura per il modulo site-specific opt-in e antilockout.
- `operations/HARDENING-SITO-ANTILOCKOUT-9.4.3.md`: hardening dell'hosting senza rischio di lockout.
- `operations/RAPPORTO-ESECUZIONE-HARDENING-9.4.3.md`: misure implementate e verifiche.
- `operations/DATI-PERSONALI-IP-USER-AGENT.md`: dati trattati e motivazioni operative.
- `operations/GUIDA-INTERPRETAZIONE-LOG.md`: lettura degli eventi registrati.

## Storico

`history/` conserva piani e verifiche precedenti per tracciabilita. Non sostituisce la documentazione della versione corrente e puo descrivere comportamenti ormai superati.

## Confine del repository

Consultare `PRIVATE-FILES-NOT-INCLUDED.md` prima di aggiungere nuovi file. La documentazione descrive il sistema, ma non deve contenere dati di utenti o configurazioni segrete dell'ambiente istituzionale.
