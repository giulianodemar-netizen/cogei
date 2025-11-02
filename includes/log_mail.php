<?php
/**
 * Utility centralizzata per logging email Albo Fornitori
 * 
 * Questa classe gestisce tutti i log delle email inviate dal sistema
 * di gestione Albo Fornitori (pannello BO e cron scadenze).
 * 
 * @version 1.0
 * @author Cogei System
 */

class AlboFornitoriMailLogger {
    
    /**
     * Path del file di log
     */
    private static $log_file = null;
    
    /**
     * Inizializza il logger
     */
    private static function init() {
        if (self::$log_file === null) {
            // Usa ABSPATH se definito (WordPress), altrimenti determina path assoluto
            if (defined('ABSPATH')) {
                $base_path = ABSPATH;
            } else {
                // Script eseguito fuori WordPress: usa directory parent del file includes
                $base_path = dirname(dirname(__FILE__)) . '/';
            }
            self::$log_file = $base_path . 'log_mail/log_mail_albo_fornitori.txt';
            
            // Crea il file di log se non esiste
            if (!file_exists(self::$log_file)) {
                $dir = dirname(self::$log_file);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $header = str_repeat("=", 100) . "\n";
                $header .= "LOG EMAIL ALBO FORNITORI - " . date('Y') . "\n";
                $header .= str_repeat("=", 100) . "\n\n";
                file_put_contents(self::$log_file, $header, LOCK_EX);
            }
        }
    }
    
    /**
     * Scrive una voce nel log delle email
     * 
     * @param array $params Parametri del log:
     *   - ambiente: 'DEBUG' o 'PROD'
     *   - tipo_email: tipo di email (es: 'attivazione', 'disattivazione', 'richiesta_documenti', ecc.)
     *   - destinatario: email del destinatario
     *   - oggetto: oggetto dell'email
     *   - user_id: ID dell'utente fornitore
     *   - user_name: ragione sociale o nome utente
     *   - user_email: email dell'utente fornitore
     *   - documenti: array di documenti coinvolti (opzionale)
     *   - allegati: array di file allegati (opzionale)
     *   - email_sent: true se inviata, false se fallita o simulata
     *   - note: note aggiuntive (opzionale)
     */
    public static function log($params) {
        self::init();
        
        $timestamp = date('d/m/Y H:i:s');
        $ambiente = isset($params['ambiente']) ? strtoupper($params['ambiente']) : 'PROD';
        $tipo_email = $params['tipo_email'] ?? 'non_specificato';
        $destinatario = $params['destinatario'] ?? 'N/A';
        $oggetto = $params['oggetto'] ?? 'N/A';
        $user_id = $params['user_id'] ?? 0;
        $user_name = $params['user_name'] ?? 'N/A';
        $user_email = $params['user_email'] ?? 'N/A';
        $email_sent = isset($params['email_sent']) ? $params['email_sent'] : false;
        
        // Costruisci la voce di log
        $log_entry = str_repeat("-", 100) . "\n";
        $log_entry .= "[{$timestamp}] [{$ambiente}] {$tipo_email}\n";
        $log_entry .= "Destinatario: {$destinatario}\n";
        $log_entry .= "Oggetto: {$oggetto}\n";
        $log_entry .= "Utente: ID={$user_id} | Nome={$user_name} | Email={$user_email}\n";
        
        // Aggiungi documenti se presenti
        if (!empty($params['documenti']) && is_array($params['documenti'])) {
            $log_entry .= "Documenti coinvolti:\n";
            foreach ($params['documenti'] as $doc) {
                if (is_array($doc)) {
                    $nome = $doc['nome'] ?? 'N/A';
                    $scadenza = $doc['scadenza'] ?? 'N/A';
                    $giorni = isset($doc['giorni']) ? $doc['giorni'] : 'N/A';
                    $log_entry .= "  - {$nome}: scadenza {$scadenza} (giorni: {$giorni})\n";
                } else {
                    $log_entry .= "  - {$doc}\n";
                }
            }
        }
        
        // Aggiungi allegati se presenti
        if (!empty($params['allegati']) && is_array($params['allegati'])) {
            $log_entry .= "Allegati: " . implode(', ', $params['allegati']) . "\n";
        }
        
        // Aggiungi note se presenti
        if (!empty($params['note'])) {
            $log_entry .= "Note: {$params['note']}\n";
        }
        
        // Stato invio
        if ($ambiente === 'DEBUG') {
            $log_entry .= "Stato: EMAIL SIMULATA (DEBUG MODE)\n";
        } else {
            $status = $email_sent ? 'INVIATA CON SUCCESSO' : 'INVIO FALLITO';
            $log_entry .= "Stato: {$status}\n";
        }
        
        $log_entry .= "\n";
        
        // Scrivi nel file di log
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log semplificato per attivazione utente
     */
    public static function logActivation($user_id, $user_name, $user_email, $destinatario, $email_sent, $debug_mode = false) {
        self::log([
            'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
            'tipo_email' => 'ATTIVAZIONE_UTENTE',
            'destinatario' => $destinatario,
            'oggetto' => 'Il tuo account su Cogei.net è stato attivato',
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'email_sent' => $email_sent
        ]);
    }
    
    /**
     * Log semplificato per disattivazione utente
     */
    public static function logDeactivation($user_id, $user_name, $user_email, $destinatario, $email_sent, $debug_mode = false, $documenti = []) {
        self::log([
            'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
            'tipo_email' => 'DISATTIVAZIONE_UTENTE',
            'destinatario' => $destinatario,
            'oggetto' => 'Il tuo account su Cogei.net è stato disattivato',
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'documenti' => $documenti,
            'email_sent' => $email_sent
        ]);
    }
    
    /**
     * Log semplificato per richiesta documenti
     */
    public static function logDocumentRequest($user_id, $user_name, $user_email, $destinatario, $email_sent, $note, $allegati = [], $debug_mode = false) {
        self::log([
            'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
            'tipo_email' => 'RICHIESTA_DOCUMENTI',
            'destinatario' => $destinatario,
            'oggetto' => 'Richiesta Documenti - Cogei.net',
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'note' => $note,
            'allegati' => $allegati,
            'email_sent' => $email_sent
        ]);
    }
    
    /**
     * Log per notifica admin (modifiche documenti)
     */
    public static function logAdminNotification($user_id, $user_name, $user_email, $admin_email, $email_sent, $documenti, $debug_mode = false) {
        self::log([
            'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
            'tipo_email' => 'NOTIFICA_ADMIN_MODIFICHE_DOCUMENTI',
            'destinatario' => $admin_email,
            'oggetto' => 'ALERT: Disattivazione automatica fornitore per modifica documenti',
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'documenti' => $documenti,
            'email_sent' => $email_sent
        ]);
    }
    
    /**
     * Log per notifiche cron scadenze
     */
    public static function logCronNotification($tipo, $user_id, $user_name, $user_email, $destinatario, $email_sent, $documenti, $trigger_day, $debug_mode = false) {
        $tipi_email = [
            'scadenza_15giorni' => 'AVVISO_SCADENZA_15_GIORNI',
            'scadenza_5giorni' => 'AVVISO_SCADENZA_5_GIORNI',
            'scadenza_oggi' => 'AVVISO_SCADENZA_OGGI',
            'avviso_disattivazione' => 'AVVISO_DISATTIVAZIONE_15_GIORNI',
            'disattivazione' => 'DISATTIVAZIONE_AUTOMATICA'
        ];
        
        self::log([
            'ambiente' => $debug_mode ? 'DEBUG' : 'PROD',
            'tipo_email' => $tipi_email[$tipo] ?? 'CRON_NOTIFICA',
            'destinatario' => $destinatario,
            'oggetto' => self::getSubjectForTriggerDay($trigger_day),
            'user_id' => $user_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'documenti' => $documenti,
            'email_sent' => $email_sent,
            'note' => "Trigger day: {$trigger_day}"
        ]);
    }
    
    /**
     * Ottieni l'oggetto email per trigger day
     */
    private static function getSubjectForTriggerDay($trigger_day) {
        $subjects = [
            15 => 'AVVISO: Documenti in scadenza tra 15 giorni',
            5 => 'URGENTE: Documenti in scadenza tra 5 giorni',
            0 => 'CRITICO: Documenti scaduti oggi',
            -15 => 'DISATTIVAZIONE: Account disattivato per documenti scaduti'
        ];
        
        return $subjects[$trigger_day] ?? 'Notifica scadenza documenti';
    }
}
