<?php
/**
 * GDPR / Privacy Policy content for "Gestione Scarto Librario"
 *
 * - Adds suggested text to WordPress Privacy Policy guide (Settings > Privacy), when available.
 * - Provides shortcode [scarto_privacy_policy] to print the same content on a public page.
 *
 * Contact email: bs-scts@cultura.gov.it
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('scarto_privacy_contact_email')) {
    /**
     * Returns the contact email to be shown in the privacy policy.
     * Can be overridden via the 'scarto_privacy_contact_email' filter.
     */
    function scarto_privacy_contact_email() {
        $email = apply_filters('scarto_privacy_contact_email', 'bs-scts@cultura.gov.it');
        $email = sanitize_email($email);
        return $email ? $email : 'bs-scts@cultura.gov.it';
    }
}

if (!function_exists('scarto_privacy_get_controller_info')) {
    /**
     * Returns controller/contact info used in the policy.
     */
    function scarto_privacy_get_controller_info() {
        $settings = function_exists('scarto_get_settings') ? scarto_get_settings() : [];

        $name = !empty($settings['library_name']) ? (string) $settings['library_name'] : get_bloginfo('name');
        $address = !empty($settings['library_address']) ? (string) $settings['library_address'] : '';
        $phone = !empty($settings['library_phone']) ? (string) $settings['library_phone'] : '';
        $email = scarto_privacy_contact_email();
        $pec = !empty($settings['contact_pec']) ? sanitize_email($settings['contact_pec']) : '';

        return [
            'name'    => $name,
            'address' => $address,
            'phone'   => $phone,
            'email'   => $email,
            'pec'     => $pec,
        ];
    }
}

if (!function_exists('scarto_privacy_get_dpo_info')) {
    /**
     * Returns DPO (Data Protection Officer) contact info.
     * Can be overridden via the 'scarto_privacy_dpo_info' filter.
     */
    function scarto_privacy_get_dpo_info() {
        $settings = function_exists('scarto_get_settings') ? scarto_get_settings() : [];

        $defaults = [
            'name'  => !empty($settings['dpo_name']) ? (string) $settings['dpo_name'] : '',
            'email' => !empty($settings['dpo_email']) ? sanitize_email($settings['dpo_email']) : '',
            'phone' => !empty($settings['dpo_phone']) ? (string) $settings['dpo_phone'] : '',
        ];

        return apply_filters('scarto_privacy_dpo_info', $defaults);
    }
}

if (!function_exists('scarto_privacy_policy_last_updated')) {
    /**
     * Returns a stable "last updated" date for the policy content.
     * Defaults to this file's last modification time.
     */
    function scarto_privacy_policy_last_updated() {
        $ts = @filemtime(__FILE__);
        if (!$ts) {
            $ts = time();
        }
        if (function_exists('date_i18n')) {
            return date_i18n('d/m/Y', $ts);
        }
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('scarto_privacy_retention_days')) {
    /**
     * Safe wrapper for retention settings.
     */
    function scarto_privacy_retention_days($type, $fallback) {
        if (function_exists('scarto_get_retention_days')) {
            return (int) scarto_get_retention_days($type);
        }
        return (int) $fallback;
    }
}

if (!function_exists('scarto_privacy_policy_content_html')) {
    /**
     * Builds the privacy policy HTML content (without outer wrapper).
     */
    function scarto_privacy_policy_content_html() {
        $info = scarto_privacy_get_controller_info();
        $dpo = scarto_privacy_get_dpo_info();
        $has_dpo = !empty($dpo['name']) || !empty($dpo['email']);
        $ret_completed = scarto_privacy_retention_days('completed', defined('SCARTO_DEFAULT_RETENTION_COMPLETED') ? SCARTO_DEFAULT_RETENTION_COMPLETED : 365);
        $ret_cancelled = scarto_privacy_retention_days('cancelled', defined('SCARTO_DEFAULT_RETENTION_CANCELLED') ? SCARTO_DEFAULT_RETENTION_CANCELLED : 90);
        $ret_expired   = scarto_privacy_retention_days('expired', defined('SCARTO_DEFAULT_RETENTION_EXPIRED') ? SCARTO_DEFAULT_RETENTION_EXPIRED : 90);
        $ret_audit     = scarto_privacy_retention_days('audit_logs', defined('SCARTO_DEFAULT_AUDIT_LOG_RETENTION') ? SCARTO_DEFAULT_AUDIT_LOG_RETENTION : 90);
        $ret_ip        = scarto_privacy_retention_days('ip', defined('SCARTO_DEFAULT_IP_RETENTION') ? SCARTO_DEFAULT_IP_RETENTION : 30);

        $gdpr_token_minutes = defined('SCARTO_GDPR_TOKEN_EXPIRY_MINUTES') ? (int) SCARTO_GDPR_TOKEN_EXPIRY_MINUTES : 30;

        $email_display = function_exists('antispambot') ? antispambot($info['email']) : $info['email'];
        $email_attr = esc_attr($info['email']);

        $section = 0;

        ob_start();
        ?>
        <header style="text-align: center; margin-bottom: 2.5em;">
            <h2 style="margin-bottom: 0.25em;">Informativa sulla Privacy</h2>
            <p style="margin: 0; font-size: 1.35em; font-weight: 600;">Sistema Prenotazione Scarto Librario</p>
        </header>

        <h3><?php echo ++$section; ?>. Titolare del trattamento</h3>
        <p>
            <strong><?php echo esc_html($info['name']); ?></strong><br>
            <?php if (!empty($info['address'])): ?>
                <?php echo esc_html($info['address']); ?><br>
            <?php endif; ?>
            <?php if (!empty($info['phone'])): ?>
                Tel.: <?php echo esc_html($info['phone']); ?><br>
            <?php endif; ?>
            <?php if (!empty($info['pec'])): ?>
                <?php
                $pec_display = function_exists('antispambot') ? antispambot($info['pec']) : $info['pec'];
                $pec_attr = esc_attr($info['pec']);
                ?>
                PEC: <a href="mailto:<?php echo $pec_attr; ?>"><?php echo esc_html($pec_display); ?></a><br>
            <?php endif; ?>
            Email: <a href="mailto:<?php echo $email_attr; ?>"><?php echo esc_html($email_display); ?></a>
        </p>

        <?php if ($has_dpo): ?>
        <h3><?php echo ++$section; ?>. Responsabile della Protezione dei Dati (DPO)</h3>
        <p>
            <?php if (!empty($dpo['name'])): ?>
                <strong><?php echo esc_html($dpo['name']); ?></strong><br>
            <?php endif; ?>
            <?php if (!empty($dpo['email'])): ?>
                <?php
                $dpo_email_display = function_exists('antispambot') ? antispambot($dpo['email']) : $dpo['email'];
                $dpo_email_attr = esc_attr($dpo['email']);
                ?>
                Email: <a href="mailto:<?php echo $dpo_email_attr; ?>"><?php echo esc_html($dpo_email_display); ?></a><br>
            <?php endif; ?>
            <?php if (!empty($dpo['phone'])): ?>
                Tel.: <?php echo esc_html($dpo['phone']); ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <h3><?php echo ++$section; ?>. Dati personali trattati</h3>
        <p>Il sistema di prenotazione tratta esclusivamente i dati strettamente necessari all'erogazione del servizio (principio di minimizzazione). In particolare:</p>
        <ul>
            <li><strong>Dati identificativi:</strong> nome e cognome.</li>
            <li><strong>Recapito per le prenotazioni online:</strong> indirizzo email obbligatorio, verificato mediante OTP. Il domicilio non viene richiesto né conservato.</li>
            <li><strong>Recapito per le prenotazioni raccolte in sede:</strong> indirizzo email oppure, soltanto se l'interessato non dispone o non intende avvalersi dell'email, via o piazza, numero civico, CAP, città e provincia del domicilio. In questo secondo caso possono essere aggiunte facoltativamente indicazioni strettamente utili al recapito postale.</li>
            <li><strong>Dati relativi alla prenotazione:</strong> codice prenotazione e dati dei volumi selezionati (es. titolo, autore, identificativi interni).</li>
            <li><strong>Dati tecnici e di sicurezza:</strong> indirizzo IP associato alla prenotazione e ai controlli anti-abuso; User-Agent del browser conservato esclusivamente nei log di sicurezza e attività. I log possono riportare l'indirizzo email utilizzato, data e ora, operazione, esito e soggetto amministrativo che l'ha eseguita, ma non conservano mai il codice OTP.</li>
            <li><strong>Dati per l'esercizio dei diritti:</strong> token temporanei utilizzati per verificare la titolarità dell'email nelle richieste GDPR.</li>
            <li><strong>Dati per la prevenzione degli abusi:</strong> eventuale indirizzo email inserito in blacklist, motivazione sintetica, autore, data di inserimento e scadenza o data di riesame.</li>
            <li><strong>Copie di sicurezza:</strong> i backup amministrativi possono contenere l'intero archivio personale, comprese prenotazioni, log e liste di controllo; sono cifrati e accessibili esclusivamente al personale autorizzato.</li>
        </ul>

        <h3><?php echo ++$section; ?>. Finalità del trattamento</h3>
        <p>I dati sono trattati per:</p>
        <ul>
            <li>gestire la prenotazione e il ritiro dei libri in scarto, comprese le richieste raccolte direttamente in sede dal personale autorizzato;</li>
            <li>per le prenotazioni online e per quelle raccolte in sede con email, inviare comunicazioni operative, inclusi OTP ove previsto, conferme, riepiloghi, annullamenti e scadenze;</li>
            <li>per le sole prenotazioni raccolte in sede senza email, predisporre, protocollare e spedire al domicilio il documento che conferma la prenotazione e l'avvenuta consegna dei volumi presso la biblioteca;</li>
            <li>garantire la sicurezza del servizio, ricostruire le operazioni rilevanti, prevenire abusi/frodi e tutelare l'integrità dei sistemi;</li>
            <li>applicare, riesaminare e documentare eventuali restrizioni individuali all'uso del servizio;</li>
            <li>adempiere ad obblighi di legge e/o richieste delle autorità competenti;</li>
            <li>gestire richieste degli interessati (accesso, esportazione, cancellazione, ecc.).</li>
        </ul>

        <h3><?php echo ++$section; ?>. Base giuridica</h3>
        <p>Il trattamento necessario all'erogazione del servizio non si basa sul consenso. In particolare:</p>
        <ul>
            <li><strong>Art. 6(1)(e) GDPR</strong> (esecuzione di un compito di interesse pubblico o connesso all'esercizio di pubblici poteri) per l'erogazione del servizio di prenotazione e gestione dello scarto librario.</li>
            <li><strong>Art. 6(1)(c) GDPR</strong> (adempimento di un obbligo legale), ove applicabile agli adempimenti amministrativi, di protocollazione e di conservazione documentale.</li>
        </ul>
        <p>La casella presente nel modulo attesta esclusivamente la presa visione della presente informativa e non costituisce consenso al trattamento.</p>

        <h3><?php echo ++$section; ?>. Modalità del trattamento</h3>
        <p>I dati sono trattati con strumenti informatici e conservati nel database del sito WordPress, nei log riservati, nelle copie di sicurezza cifrate generate dal personale e nei sistemi tecnici necessari all'erogazione del servizio, inclusa la posta elettronica usata per le comunicazioni operative. I backup non sono pubblicati nella Media Library.</p>

        <h3><?php echo ++$section; ?>. Periodo di conservazione</h3>
        <p>I dati sono conservati per il tempo strettamente necessario alle finalità indicate e secondo le impostazioni di conservazione configurate nel sistema. In via generale:</p>

        <table style="border-collapse: collapse; width: 100%; margin: 1em 0;">
            <thead>
            <tr style="background: #f5f5f5;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Categoria</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Conservazione</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Prenotazioni attive</td>
                <td style="border: 1px solid #ddd; padding: 8px;">fino a completamento, annullamento o scadenza</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Prenotazioni completate</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($ret_completed); ?> giorni (poi anonimizzazione/eliminazione secondo configurazione)</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Prenotazioni annullate</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($ret_cancelled); ?> giorni (poi eliminazione)</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Prenotazioni scadute</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($ret_expired); ?> giorni (poi eliminazione)</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Indirizzi IP e User-Agent nei log</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($ret_ip); ?> giorni (poi anonimizzazione; lo User-Agent non è conservato nella prenotazione)</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Log di sistema / audit log</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($ret_audit); ?> giorni</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Restrizioni anti-abuso</td>
                <td style="border: 1px solid #ddd; padding: 8px;">fino al riesame o alla revoca da parte del personale autorizzato, secondo la procedura e i tempi approvati dall'ente</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Backup amministrativi</td>
                <td style="border: 1px solid #ddd; padding: 8px;">per il tempo previsto dal piano di backup dell'ente; le copie esportate devono essere custodite e cancellate secondo tale piano</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">Token per richieste GDPR</td>
                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($gdpr_token_minutes); ?> minuti (poi scadenza automatica)</td>
            </tr>
            </tbody>
        </table>

        <h3><?php echo ++$section; ?>. Destinatari dei dati</h3>
        <p>I dati possono essere trattati da soggetti che forniscono servizi tecnici necessari al funzionamento del sito (es. hosting, manutenzione, posta elettronica), nominati – ove previsto – quali responsabili del trattamento o autorizzati al trattamento. I dati non sono diffusi.</p>

        <h3><?php echo ++$section; ?>. Trasferimenti verso paesi terzi</h3>
        <p>In linea generale, i dati sono trattati su infrastrutture tecniche utilizzate dal sito. Qualora fossero coinvolti fornitori con sedi extra SEE, il trasferimento avverrà nel rispetto del GDPR tramite garanzie adeguate (es. clausole contrattuali standard, decisioni di adeguatezza, o altre misure previste dalla normativa).</p>

        <h3><?php echo ++$section; ?>. Sicurezza</h3>
        <p>Adottiamo misure tecniche e organizzative per proteggere i dati, tra cui (a titolo esemplificativo):</p>
        <ul>
            <li>comunicazioni cifrate (HTTPS) ove disponibile;</li>
            <li>controlli di accesso e limitazione delle operazioni al personale autorizzato;</li>
            <li>registri di audit per attività amministrative e di sicurezza;</li>
            <li>misure anti-abuso (es. rate limiting) e protezioni contro accessi non autorizzati.</li>
        </ul>

        <h3><?php echo ++$section; ?>. Processi decisionali automatizzati e profilazione</h3>
        <p>Il sistema <strong>non</strong> effettua processi decisionali automatizzati né attività di profilazione ai sensi dell'art. 22 del GDPR. I dati raccolti sono utilizzati esclusivamente per le finalità descritte nella presente informativa e non vengono sottoposti ad alcuna forma di analisi automatica volta a valutare aspetti personali dell'interessato.</p>

        <h3><?php echo ++$section; ?>. Diritti dell'interessato</h3>
        <p>Ai sensi del Regolamento (UE) 2016/679, l'interessato può esercitare, nei limiti e alle condizioni previste dalla normativa, i seguenti diritti:</p>
        <ul>
            <li><strong>Diritto di accesso</strong> (art. 15): ottenere conferma del trattamento e copia dei dati;</li>
            <li><strong>Diritto di rettifica</strong> (art. 16): correggere dati inesatti o incompleti;</li>
            <li><strong>Diritto alla cancellazione</strong> (art. 17): richiedere la cancellazione dei dati, ove sussistano i presupposti;</li>
            <li><strong>Diritto di limitazione</strong> (art. 18): limitare il trattamento in determinati casi;</li>
            <li><strong>Diritto alla portabilità</strong> (art. 20): ricevere i dati in formato strutturato e interoperabile;</li>
            <li><strong>Diritto di opposizione</strong> (art. 21): opporsi al trattamento per motivi legittimi;</li>
            <li><strong>Diritto di reclamo</strong>: proporre reclamo all'Autorità Garante per la protezione dei dati personali.</li>
        </ul>

        <h3><?php echo ++$section; ?>. Come esercitare i diritti</h3>
        <p>Per esercitare i diritti è possibile:</p>
        <ul>
            <li>utilizzare le funzioni GDPR integrate nel sistema (quando disponibili);</li>
            <?php if (!empty($info['pec'])): ?>
            <li>scrivere via <strong>PEC</strong> (consigliato) a: <strong><a href="mailto:<?php echo $pec_attr; ?>"><?php echo esc_html($pec_display); ?></a></strong>, indicando l'email utilizzata, se presente, e/o il codice prenotazione;</li>
            <li>in alternativa, scrivere a: <a href="mailto:<?php echo $email_attr; ?>"><?php echo esc_html($email_display); ?></a>.</li>
            <?php else: ?>
            <li>scrivere a: <strong><a href="mailto:<?php echo $email_attr; ?>"><?php echo esc_html($email_display); ?></a></strong>, indicando l'email utilizzata, se presente, e/o il codice prenotazione.</li>
            <?php endif; ?>
        </ul>
        <?php if (!empty($info['pec'])): ?>
        <p><em>Si raccomanda l'utilizzo della PEC per le richieste formali, in quanto garantisce valore legale alla comunicazione.</em></p>
        <?php endif; ?>
        <p>Per motivi di sicurezza, le richieste presentate tramite le funzioni online possono richiedere la verifica del possesso dell'indirizzo email mediante token temporaneo (validità tipica: <?php echo esc_html($gdpr_token_minutes); ?> minuti). Per le prenotazioni raccolte senza email, la Biblioteca verifica l'identità e il codice prenotazione secondo la procedura interna.</p>

        <h3><?php echo ++$section; ?>. Autorità di controllo</h3>
        <p>L'interessato ha il diritto di proporre reclamo all'Autorità Garante per la protezione dei dati personali:</p>
        <p>
            <strong>Garante per la protezione dei dati personali</strong><br>
            Piazza Venezia, 11 – 00187 Roma<br>
            Email: <a href="mailto:garante@gpdp.it">garante@gpdp.it</a><br>
            PEC: <a href="mailto:protocollo@pec.gpdp.it">protocollo@pec.gpdp.it</a><br>
            Sito web: <a href="https://www.garanteprivacy.it" target="_blank" rel="noopener noreferrer">www.garanteprivacy.it</a><br>
            Telefono: (+39) 06.696771
        </p>

        <h3><?php echo ++$section; ?>. Aggiornamenti</h3>
        <p>La presente informativa può essere aggiornata. La versione più recente è sempre disponibile su questa pagina.</p>

        <p style="margin-top: 2em; font-size: 0.95em; color: #666;">
            <strong>Ultimo aggiornamento:</strong> <?php echo esc_html(scarto_privacy_policy_last_updated()); ?>
        </p>

        <p style="font-size: 0.95em; color: #666;">
            <em>Informativa resa ai sensi del Regolamento (UE) 2016/679 (GDPR) e del D.Lgs. 196/2003 come modificato dal D.Lgs. 101/2018.</em>
        </p>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('scarto_register_privacy_policy_content')) {
    /**
     * Registers suggested privacy policy text in WP core (when available).
     */
    function scarto_register_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = scarto_privacy_policy_content_html();
        $content = wp_kses_post($content);

        wp_add_privacy_policy_content('Gestione Scarto Librario', $content);
    }
}
add_action('admin_init', 'scarto_register_privacy_policy_content');

if (!function_exists('scarto_render_privacy_policy')) {
    /**
     * Shortcode renderer: [scarto_privacy_policy]
     */
    function scarto_render_privacy_policy() {
        $content = scarto_privacy_policy_content_html();
        return '<div class="scarto-privacy-policy">' . $content . '</div>';
    }
}
add_shortcode('scarto_privacy_policy', 'scarto_render_privacy_policy');
