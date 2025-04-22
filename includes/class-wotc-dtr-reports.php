<?php
/**
 * Classe principale per la generazione dei report WotC DTR
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WotC_DTR_Reports
 */
class WotC_DTR_Reports {
    /**
     * Recupera il codice EAN/GTIN di un prodotto usando vari metodi
     *
     * @param int $product_id ID del prodotto
     * @return string Codice EAN/GTIN
     */
    private function get_product_ean($product_id) {
        // Prova a usare la funzione del plugin Product GTIN se disponibile
        if (function_exists('wpm_get_code_gtin_by_product')) {
            $ean = wpm_get_code_gtin_by_product($product_id);
            if (!empty($ean)) {
                return $ean;
            }
        }
        
        // Prova a leggere direttamente dal meta_key del plugin Product GTIN
        $ean = get_post_meta($product_id, '_wpm_gtin_code', true);
        if (!empty($ean)) {
            return $ean;
        }
        
        // Fallback su altri campi comuni
        $ean = get_post_meta($product_id, '_gtin', true);
        if (!empty($ean)) {
            return $ean;
        }
        
        // Altro fallback possibile
        return get_post_meta($product_id, 'ean', true) ?: '';
    }
    /**
     * Nome del cliente per i nomi dei file
     *
     * @var string
     */
    private $customer_name;
    
    /**
     * Nome della tabella di cache
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Istanza della classe impostazioni
     *
     * @var WotC_DTR_Settings
     */
    private $settings;
    
    /**
     * Inizializza la classe
     */
    public function __construct() {
        global $wpdb, $wotc_dtr_settings;
        
        // Usa l'istanza globale delle impostazioni
        $this->settings = $wotc_dtr_settings;
        $this->customer_name = $this->settings->get_setting('customer_name', 'ilcovodelnerdweb');
        
        // Imposta il nome della tabella di cache
        $this->table_name = $wpdb->prefix . 'wotc_sales_cache';
        
        // Assicurati che la tabella di cache esista
        $this->check_cache_table();
        
        // Crea la struttura base delle directory
        $this->init_directories();
        
        // Registra il cron job
        add_action('wotc_weekly_report_cron', array($this, 'generate_weekly_reports'));
        
        // Pianifica il cron job se non è già pianificato
        if (!wp_next_scheduled('wotc_weekly_report_cron')) {
            // Programma per ogni lunedì alle 2:00 AM
            wp_schedule_event(strtotime('next monday 2:00am'), 'weekly', 'wotc_weekly_report_cron');
        }
    }
    
    /**
     * Inizializza la struttura delle directory per i report
     */
    private function init_directories() {
        $upload_dir = wp_upload_dir();
        $wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
        
        // Crea la directory base se non esiste
        if (!file_exists($wotc_base_dir)) {
            wp_mkdir_p($wotc_base_dir);
            
            // Crea un file .htaccess per proteggere la directory, ma permetti l'accesso ai file CSV
            $htaccess_file = $wotc_base_dir . '.htaccess';
            $htaccess_content = "Options -Indexes\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Crea la directory per l'anno corrente se non esiste
        $current_year = date('Y');
        $year_dir = $wotc_base_dir . $current_year . '/';
        if (!file_exists($year_dir)) {
            wp_mkdir_p($year_dir);
        }
    }
    
    /**
     * Verifica e crea la tabella di cache se necessario
     */
    private function check_cache_table() {
        global $wpdb;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->table_name} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                wotc_code varchar(20) NOT NULL,
                ean varchar(20) DEFAULT '',
                ytd_sales int(11) DEFAULT 0,
                last_updated datetime DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                UNIQUE KEY product_id (product_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            error_log("WotC Report: Creata tabella cache {$this->table_name}");
        }
    }
    
    /**
     * Aggiorna la cache dei prodotti WotC
     *
     * @return int Numero di prodotti aggiunti
     */
    private function update_products_cache() {
        global $wpdb;
        
        $products_added = 0;
        
        // Query per trovare prodotti WotC non ancora in cache
        $new_products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID as product_id, pm.meta_value as wotc_code
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
            LEFT JOIN {$this->table_name} c ON p.ID = c.product_id
            WHERE p.post_type = 'product'
            AND pm.meta_value != ''
            AND c.id IS NULL",
            'wotc_material_number'
        ));
        
        // Aggiungi nuovi prodotti alla cache
        // All'interno del ciclo foreach che elabora i prodotti
        foreach ($new_products as $product) {
            // Cerca prima il codice dal plugin Product GTIN (EAN, UPC, ISBN) for WooCommerce
            // Nel metodo update_products_cache()
            $ean = $this->get_product_ean($product->product_id);

            // Se ancora vuoto, usa una stringa vuota
            if (empty($ean)) {
                $ean = '';
            }
            
            $wpdb->insert(
                $this->table_name,
                array(
                    'product_id' => $product->product_id,
                    'wotc_code' => $product->wotc_code,
                    'ean' => $ean,
                    'ytd_sales' => 0,
                    'last_updated' => date('Y-m-d H:i:s', strtotime('first day of january this year'))
                )
            );
            
            $products_added++;
        }
        
        if ($products_added > 0) {
            error_log("WotC Report: Aggiunti $products_added nuovi prodotti alla cache");
        }
        
        return $products_added;
    }
    
    /**
     * Verifica se un utente appartiene al gruppo franchising
     *
     * @param int $user_id ID dell'utente
     * @return bool True se l'utente è un franchising
     */
    private function is_user_franchising($user_id) {
        if (!$user_id) return false;
        $group = get_user_meta($user_id, 'wcb2b_group', true);
        return $group == FRANCHISING_GROUP;
    }
    
    /**
     * Genera tutti i report settimanali
     */
    public function generate_weekly_reports() {
        // Genera il report vendite
        $sales_report = $this->generate_sales_report();
        
        // Genera report stock
        $stock_report = $this->generate_stock_report($sales_report['week'], $sales_report['year']);
        
        // Genera report franchising
        $franchise_reports = $this->generate_franchising_reports(null, null, $sales_report['week'], $sales_report['year']);
        
        // Aggiungi log per debug
        error_log("WotC Report: Report vendite generato: " . print_r($sales_report, true));
        error_log("WotC Report: Report stock generato: " . print_r($stock_report, true));
        error_log("WotC Report: Report franchising generati: " . count($franchise_reports));
        
        // Invia i report via email se necessario
        if ($this->settings->get_setting('send_email', 'yes') === 'yes') {
            $this->send_reports_by_email($sales_report['week'], $sales_report['year']);
        }
        
        return array(
            'sales' => $sales_report,
            'stock' => $stock_report,
            'franchise' => $franchise_reports
        );
    }
    
    /**
     * Crea un archivio ZIP della cartella settimanale e lo invia via email
     * 
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return bool Esito dell'invio
     */
    public function send_reports_by_email($week_number, $year) {
        // Verifica se l'invio email è abilitato
        if ($this->settings->get_setting('send_email', 'yes') !== 'yes') {
            error_log("WotC Report: Invio email disabilitato nelle impostazioni");
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
        $year_dir = $wotc_base_dir . $year . '/';
        $week_dir = $year_dir . 'week_' . $week_number . '/';
        
        // Verifica che la directory esista
        if (!file_exists($week_dir)) {
            error_log("WotC Report: Directory $week_dir non trovata per inviare l'email");
            return false;
        }
        
        // Crea il file ZIP
        $zip_filename = $this->customer_name . '_reports_wk_' . $week_number . '.zip';
        $zip_filepath = $wotc_base_dir . $zip_filename;
        
        // Crea l'archivio ZIP
        $result = $this->create_zip_archive($week_dir, $zip_filepath);
        
        if (!$result) {
            error_log("WotC Report: Impossibile creare l'archivio ZIP");
            return false;
        }
        
        // Ottieni le impostazioni dell'email
        $to = $this->settings->get_setting('email_recipients', 'adelmoinfante1992@gmail.com');
        $subject = $this->settings->get_setting('email_subject', 'WotC DTR Reports - Settimana {week}/{year}');
        $message = $this->settings->get_setting('email_message', "Gentile Amministratore,\n\nIn allegato troverai i report WotC DTR per la settimana {week} dell'anno {year}.\n\nI report sono stati generati automaticamente dal sistema.\n\nCordiali saluti,\nPlugin WotC DTR Reports");
        
        // Sostituisci i segnaposto
        $subject = str_replace(
            array('{week}', '{year}'),
            array($week_number, $year),
            $subject
        );
        
        $message = str_replace(
            array('{week}', '{year}'),
            array($week_number, $year),
            $message
        );
        
        // Prepara gli header
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        // Aggiungi CC se configurato
        $cc = $this->settings->get_setting('cc_email', '');
        if (!empty($cc)) {
            $headers[] = 'Cc: ' . $cc;
        }
        
        // Aggiungi BCC se configurato
        $bcc = $this->settings->get_setting('bcc_email', '');
        if (!empty($bcc)) {
            $headers[] = 'Bcc: ' . $bcc;
        }
        
        $attachments = array($zip_filepath);
        
        $email_sent = wp_mail($to, $subject, $message, $headers, $attachments);
        
        // Log del risultato
        if ($email_sent) {
            error_log("WotC Report: Email inviata con successo a $to");
        } else {
            error_log("WotC Report: Errore nell'invio dell'email a $to");
        }
        
        // Elimina il file ZIP temporaneo
        @unlink($zip_filepath);
        
        return $email_sent;
    }
    
    /**
     * Crea un archivio ZIP di una directory
     * 
     * @param string $source_dir Directory da comprimere
     * @param string $destination_zip Percorso del file ZIP di destinazione
     * @return bool Esito dell'operazione
     */
    private function create_zip_archive($source_dir, $destination_zip) {
        if (!class_exists('ZipArchive')) {
            error_log("WotC Report: La classe ZipArchive non è disponibile in questo server");
            return false;
        }
        
        $zip = new ZipArchive();
        
        if (!$zip->open($destination_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            error_log("WotC Report: Impossibile creare il file ZIP");
            return false;
        }
        
        $source_dir = str_replace('\\', '/', realpath($source_dir));
        
        if (is_dir($source_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $file) {
                // Salta directory
                if ($file->isDir()) {
                    continue;
                }
                
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($source_dir) + 1);
                
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        return file_exists($destination_zip);
    }
    
    /**
     * Genera il report delle vendite
     *
     * @param string $start_date Data inizio (formato Y-m-d)
     * @param string $end_date Data fine (formato Y-m-d)
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return array Informazioni sul report generato
     */
    public function generate_sales_report($start_date = null, $end_date = null, $week_number = null, $year = null) {
        global $wpdb;
        
        // Se non si specifica un periodo, usa la settimana corrente
        if ($week_number === null) {
            $week_number = date('W');
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        // Se non si specificano date, calcola il periodo della settimana
        if ($start_date === null || $end_date === null) {
            $date_obj = new DateTime();
            $date_obj->setISODate($year, $week_number, 1); // 1 = lunedì
            $start_date = $date_obj->format('Y-m-d');
            $date_obj->modify('+6 days'); // fino a domenica
            $end_date = $date_obj->format('Y-m-d');
        }
        
        // Log dell'inizio elaborazione
        error_log("WotC Report: Iniziata generazione report per periodo $start_date - $end_date (settimana $week_number/$year)");
        
        // Aggiorna la cache dei prodotti
        $this->update_products_cache();
        
        // 1. Ottieni tutti i prodotti WotC dalla cache
        $wotc_products = $wpdb->get_results("SELECT product_id, wotc_code, ean FROM {$this->table_name}");
        $product_map = array();
        
        foreach ($wotc_products as $product) {
            $product_map[$product->product_id] = array(
                'wotc_code' => $product->wotc_code,
                'ean' => $product->ean,
                'period_sales' => 0,     // Vendite nel periodo selezionato
                'ytd_sales' => 0         // Vendite dall'inizio dell'anno
            );
        }
        
        // 2. Calcola l'inizio dell'anno per YTD
        $year_start = $year . '-01-01';
        
        // 3. Query per gli ordini dall'inizio dell'anno alla data di fine
        $ytd_args = array(
            'limit' => -1,
            'date_created' => $year_start . '...' . $end_date,
            'status' => array('processing', 'completed', 'on-hold'),
            'return' => 'ids',
        );
        
        $ytd_order_ids = wc_get_orders($ytd_args);
        $ytd_orders_count = count($ytd_order_ids);
        error_log("WotC Report: Trovati $ytd_orders_count ordini dall'inizio dell'anno");
        
        // 4. Elabora ordini YTD a batch
        $processed_ytd = 0;
        $skipped_franchise_ytd = 0;
        $batch_size = 50; // Elabora 50 ordini alla volta
        
        for ($i = 0; $i < $ytd_orders_count; $i += $batch_size) {
            $batch = array_slice($ytd_order_ids, $i, $batch_size);
            
            foreach ($batch as $order_id) {
                $order = wc_get_order($order_id);
                
                // Salta se l'ordine è annullato o rimborsato
                if ($order->get_status() === 'cancelled' || $order->get_status() === 'refunded') {
                    continue;
                }
                
                // Verifica se l'ordine è di un utente franchising
                $user_id = $order->get_user_id();
                if ($user_id && $this->is_user_franchising($user_id)) {
                    $skipped_franchise_ytd++;
                    continue; // Salta gli ordini franchising
                }
                
                // Controlla se l'ordine è nel periodo specifico
                $order_date = $order->get_date_created()->format('Y-m-d');
                $is_in_period = ($order_date >= $start_date && $order_date <= $end_date);
                
                // Per ogni prodotto nell'ordine
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
                    
                    // Se è un prodotto WotC
                    if (isset($product_map[$product_id])) {
                        // Aggiorna vendite YTD
                        $product_map[$product_id]['ytd_sales'] += $quantity;
                        
                        // Se l'ordine è nel periodo specifico, aggiorna anche le vendite del periodo
                        if ($is_in_period) {
                            $product_map[$product_id]['period_sales'] += $quantity;
                        }
                    }
                }
                
                $processed_ytd++;
            }
            
            // Log per monitorare l'avanzamento
            if ($processed_ytd % 200 === 0 || $processed_ytd === $ytd_orders_count) {
                error_log("WotC Report: Elaborati $processed_ytd/$ytd_orders_count ordini YTD (esclusi $skipped_franchise_ytd ordini franchising)");
            }
        }
        
        // 5. Conteggio ordini nel periodo specifico (per statistiche)
        $period_args = array(
            'limit' => -1,
            'date_created' => $start_date . '...' . $end_date,
            'status' => array('processing', 'completed', 'on-hold'),
            'return' => 'ids',
        );
        
        $period_order_ids = wc_get_orders($period_args);
        $period_orders_count = count($period_order_ids);
        
        // 6. Prepara i dati del report nel formato richiesto
        $report_data = array();
        $products_with_sales = 0; // Inizializzazione
        
     // Modifica alle intestazioni del CSV
        $report_data[] = array(
            'Wotc', 'EAN', 'Country', 'Week', 'Year', 'Date', 'YTDSales', 'Sales', 'ProductID', 'ProductName'
        );
        
        // Modifica all'aggiunta dei dati per ogni prodotto
        foreach ($product_map as $product_id => $data) {
            // Includi solo prodotti con vendite YTD o nel periodo
            if ($data['ytd_sales'] > 0 || $data['period_sales'] > 0) {
                // Ottieni il nome del prodotto
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : '';
                
                $report_data[] = array(
                    $data['wotc_code'],       // Wotc
                    $data['ean'],             // EAN
                    'IT',                     // Country (sempre IT)
                    $week_number,             // Week
                    $year,                    // Year
                    '',                       // Date (vuoto)
                    $data['ytd_sales'],       // YTDSales
                    $data['period_sales'],    // Sales (nel periodo selezionato)
                    $product_id,              // ProductID (nuovo)
                    $product_name             // ProductName (nuovo)
                );
                $products_with_sales++;
            }
        }
        
        // 7. Salva il report in CSV
        $upload_dir = wp_upload_dir();
        $wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
        
        // Crea le cartelle anno/settimana
        $year_dir = $wotc_base_dir . $year . '/';
        $week_dir = $year_dir . 'week_' . $week_number . '/';
        
        // Crea le directory se non esistono
        if (!file_exists($wotc_base_dir)) {
            wp_mkdir_p($wotc_base_dir);
        }
        if (!file_exists($year_dir)) {
            wp_mkdir_p($year_dir);
        }
        if (!file_exists($week_dir)) {
            wp_mkdir_p($week_dir);
        }
        
        $filename = $this->customer_name . '_sales_wk_' . $week_number . '.csv';
        $filepath = $week_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        foreach ($report_data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        error_log("WotC Report: Report vendite salvato in $filepath con $products_with_sales prodotti");
        
        // 8. Aggiorna la tabella di cache con i dati YTD calcolati
        foreach ($product_map as $product_id => $data) {
            if ($data['ytd_sales'] > 0) {
                $wpdb->update(
                    $this->table_name,
                    array(
                        'ytd_sales' => $data['ytd_sales'],
                        'last_updated' => date('Y-m-d H:i:s')
                    ),
                    array('product_id' => $product_id)
                );
            }
        }
        
        // 9. Restituisci i dati del risultato
        return array(
            'filepath' => $filepath,
            'url' => $upload_dir['baseurl'] . '/wotc-dtr/' . $year . '/week_' . $week_number . '/' . $filename,
            'week' => $week_number,
            'year' => $year,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'products_total' => count($wotc_products),
            'products_with_sales' => $products_with_sales,
            'period_orders' => $period_orders_count,
            'ytd_orders' => $ytd_orders_count,
            'processed_orders' => $processed_ytd,
            'franchising_skipped' => $skipped_franchise_ytd
        );
    }
    
    /**
     * Genera il report degli stock
     * 
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return array Informazioni sul report generato
     */
    public function generate_stock_report($week_number = null, $year = null) {
        global $wpdb;
        
        // Se non si specifica una settimana, usa la settimana corrente
        if ($week_number === null) {
            $week_number = date('W');
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        // Log dell'inizio elaborazione
        error_log("WotC Report: Iniziata generazione report stock per settimana $week_number/$year");
        
        // 1. Ottieni tutti i prodotti WotC dalla cache
        $wotc_products = $wpdb->get_results("SELECT product_id, wotc_code, ean FROM {$this->table_name}");
        
        // 2. Prepara i dati del report
        $report_data = array();
        
        // Intestazioni del CSV per lo stock modificate secondo il formato WotC
        $report_data[] = array(
            'Wotc',             // Codice WotC a 9 cifre (prima colonna)
            'EAN',              // EAN
            'Country',          // Paese
            'Week',             // Settimana
            'Year',             // Anno
            'Stock in eaches'   // Quantità disponibile
        );
        
        $products_with_stock = 0;
        $current_date = date('Y-m-d');
        
        // Elabora ciascun prodotto
        foreach ($wotc_products as $product) {
            // Verifica che il prodotto abbia un codice WotC valido
            if (empty($product->wotc_code)) {
                continue; // Salta prodotti senza codice WotC
            }
            
            $wc_product = wc_get_product($product->product_id);
            
            if (!$wc_product || $wc_product->get_status() !== 'publish') {
                continue;
            }
            
            // Ottieni il livello di stock
            $stock = $wc_product->get_stock_quantity();
            
            // Per prodotti variabili, somma lo stock di tutte le varianti
            if ($wc_product->is_type('variable')) {
                $stock = 0;
                $variations = $wc_product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $stock += $variation->get_stock_quantity() ?: 0;
                    }
                }
            }
            
            // Verifica data di preordine
            $pre_order_date = $wc_product->get_meta('_pre_order_date');
            $is_available = true;
            
            if (!empty($pre_order_date) && strtotime($pre_order_date) > strtotime($current_date)) {
                $is_available = false;
            }
            
            // Se il prodotto ha stock > 0, è disponibile e non è in preordine, aggiungilo al report
            if ($stock > 0 && $wc_product->is_in_stock() && $is_available) {
                // Aggiungi al report con le colonne nel formato WotC
                $report_data[] = array(
                    $product->wotc_code,    // Wotc (prima colonna)
                    $product->ean,          // EAN
                    'IT',                   // Country (fisso IT per Italia)
                    $week_number,           // Week
                    $year,                  // Year
                    $stock                  // Stock in eaches
                );
                
                $products_with_stock++;
            }
        }
        
        // 3. Salva il report in CSV
        $upload_dir = wp_upload_dir();
        $wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
        
        // Crea le cartelle anno/settimana
        $year_dir = $wotc_base_dir . $year . '/';
        $week_dir = $year_dir . 'week_' . $week_number . '/';
        
        // Crea le directory se non esistono
        if (!file_exists($wotc_base_dir)) {
            wp_mkdir_p($wotc_base_dir);
        }
        if (!file_exists($year_dir)) {
            wp_mkdir_p($year_dir);
        }
        if (!file_exists($week_dir)) {
            wp_mkdir_p($week_dir);
        }
        
        $filename = $this->customer_name . '_stock_wk_' . $week_number . '.csv';
        $filepath = $week_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        foreach ($report_data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        error_log("WotC Report: Report stock salvato in $filepath con $products_with_stock prodotti");
        
        // 4. Restituisci i dati del risultato
        return array(
            'filepath' => $filepath,
            'url' => $upload_dir['baseurl'] . '/wotc-dtr/' . $year . '/week_' . $week_number . '/' . $filename,
            'week' => $week_number,
            'year' => $year,
            'products_total' => count($wotc_products),
            'products_with_stock' => $products_with_stock
        );
    }
    
    /**
     * Genera il report di traffico (stub per implementazione futura)
     * 
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return array Informazioni sul report generato
     */
    public function generate_traffic_report($week_number = null, $year = null) {
        // Implementazione da completare in futuro con integrazione GA4
        
        // Se non si specifica una settimana, usa la settimana corrente
        if ($week_number === null) {
            $week_number = date('W');
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        // Log
        error_log("WotC Report: Funzione report traffico da implementare");
        
        // Restituisci dati di esempio
        return array(
            'week' => $week_number,
            'year' => $year,
            'status' => 'not_implemented',
            'message' => 'La funzione di report traffico non è ancora implementata.'
        );
    }
    
    /**
     * Genera report di acquisto per utenti franchising
     *
     * @param string $start_date Data inizio (formato Y-m-d)
     * @param string $end_date Data fine (formato Y-m-d)
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return array Informazioni sui report generati
     */
    public function generate_franchising_reports($start_date = null, $end_date = null, $week_number = null, $year = null) {
        global $wpdb;
        
        // Se non si specifica un periodo, usa la settimana corrente
        if ($week_number === null) {
            $week_number = date('W');
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        // Se non si specificano date, calcola il periodo della settimana
        if ($start_date === null || $end_date === null) {
            $date_obj = new DateTime();
            $date_obj->setISODate($year, $week_number, 1); // 1 = lunedì
            $start_date = $date_obj->format('Y-m-d');
            $date_obj->modify('+6 days'); // fino a domenica
            $end_date = $date_obj->format('Y-m-d');
        }
        
        // Ottieni tutti gli utenti franchising
        $franchising_users = get_users(array(
            'meta_key' => 'wcb2b_group',
            'meta_value' => FRANCHISING_GROUP
        ));
        
        $results = array();
        
        // Per ogni utente franchising
        foreach ($franchising_users as $user) {
            // Genera un report specifico per questo franchising
            $franchise_result = $this->generate_franchise_sales_report($user->ID, $user->display_name, $start_date, $end_date, $week_number, $year);
            $results[] = $franchise_result;
        }
        
        return $results;
    }
    
    /**
     * Genera il report delle vendite per un singolo franchising
     *
     * @param int $user_id ID dell'utente franchising
     * @param string $franchise_name Nome del franchising
     * @param string $start_date Data inizio (formato Y-m-d)
     * @param string $end_date Data fine (formato Y-m-d)
     * @param int $week_number Numero settimana per il report
     * @param int $year Anno per il report
     * @return array Informazioni sul report generato
     */
    private function generate_franchise_sales_report($user_id, $franchise_name, $start_date, $end_date, $week_number, $year) {
        global $wpdb;
        
        // Log dell'inizio elaborazione
        error_log("WotC Report: Iniziata generazione report per franchising $franchise_name (ID: $user_id), periodo $start_date - $end_date");
        
        // Aggiorna la cache dei prodotti
        $this->update_products_cache();
        
        // 1. Ottieni tutti i prodotti WotC dalla cache
        $wotc_products = $wpdb->get_results("SELECT product_id, wotc_code, ean FROM {$this->table_name}");
        $product_map = array();
        
        foreach ($wotc_products as $product) {
            $product_map[$product->product_id] = array(
                'wotc_code' => $product->wotc_code,
                'ean' => $product->ean,
                'period_purchases' => 0,     // Acquisti nel periodo selezionato
                'ytd_purchases' => 0         // Acquisti dall'inizio dell'anno
            );
        }
        
        // 2. Calcola l'inizio dell'anno per YTD
        $year_start = $year . '-01-01';
        
        // 3. Query per gli ordini dall'inizio dell'anno alla data di fine solo per questo utente
        $ytd_args = array(
            'limit' => -1,
            'date_created' => $year_start . '...' . $end_date,
            'status' => array('processing', 'completed', 'on-hold'),
            'customer_id' => $user_id,
            'return' => 'ids',
        );
        
        $ytd_order_ids = wc_get_orders($ytd_args);
        $ytd_orders_count = count($ytd_order_ids);
        
        // 4. Elabora ordini YTD
        $processed_ytd = 0;
        $batch_size = 50; // Elabora 50 ordini alla volta
        
        for ($i = 0; $i < $ytd_orders_count; $i += $batch_size) {
            $batch = array_slice($ytd_order_ids, $i, $batch_size);
            
            foreach ($batch as $order_id) {
                $order = wc_get_order($order_id);
                
                // Salta se l'ordine è annullato o rimborsato
                if ($order->get_status() === 'cancelled' || $order->get_status() === 'refunded') {
                    continue;
                }
                
                // Controlla se l'ordine è nel periodo specifico
                $order_date = $order->get_date_created()->format('Y-m-d');
                $is_in_period = ($order_date >= $start_date && $order_date <= $end_date);
                
                // Per ogni prodotto nell'ordine
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
                    
                    // Se è un prodotto WotC
                    if (isset($product_map[$product_id])) {
                        // Aggiorna acquisti YTD
                        $product_map[$product_id]['ytd_purchases'] += $quantity;
                        
                        // Se l'ordine è nel periodo specifico, aggiorna anche gli acquisti del periodo
                        if ($is_in_period) {
                            $product_map[$product_id]['period_purchases'] += $quantity;
                        }
                    }
                }
                
                $processed_ytd++;
            }
        }
        
        // 5. Prepara i dati del report nel formato richiesto
        $report_data = array();
        $products_with_purchases = 0;
        
        // Intestazioni del CSV - IDENTICHE al report vendite standard
        $report_data[] = array(
            'Wotc', 'EAN', 'Country', 'Week', 'Year', 'Date', 'YTDSales', 'Sales', 'ProductID', 'ProductName'
        );
        
        // Aggiungi dati per ogni prodotto
        foreach ($product_map as $product_id => $data) {
            // Includi solo prodotti con acquisti YTD o nel periodo
            if ($data['ytd_purchases'] > 0 || $data['period_purchases'] > 0) {
                // Ottieni il nome del prodotto
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : '';
                
                $report_data[] = array(
                    $data['wotc_code'],       // Wotc
                    $data['ean'],             // EAN
                    'IT',                     // Country (sempre IT)
                    $week_number,             // Week
                    $year,                    // Year
                    '',                       // Date (vuoto)
                    $data['ytd_purchases'],   // YTDSales (acquisti YTD)
                    $data['period_purchases'],// Sales (acquisti nel periodo)
                    $product_id,              // ProductID
                    $product_name             // ProductName
                );
                $products_with_purchases++;
            }
        }
        
        // 6. Salva il report in CSV
        $upload_dir = wp_upload_dir();
        $wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
        
        // Crea le cartelle anno/settimana
        $year_dir = $wotc_base_dir . $year . '/';
        $week_dir = $year_dir . 'week_' . $week_number . '/';
        
        // Crea le directory se non esistono
        if (!file_exists($week_dir)) {
            wp_mkdir_p($week_dir);
        }
        
        // Crea un nome file sanitizzato per il franchising
        $franchise_slug = sanitize_title($franchise_name);
        $filename = $this->customer_name . '_sales_' . $franchise_slug . '_wk_' . $week_number . '.csv';
        $filepath = $week_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        foreach ($report_data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        error_log("WotC Report: Report franchising salvato in $filepath con $products_with_purchases prodotti");
        
        // 7. Restituisci i dati del risultato
        return array(
            'user_id' => $user_id,
            'franchise_name' => $franchise_name,
            'filepath' => $filepath,
            'url' => $upload_dir['baseurl'] . '/wotc-dtr/' . $year . '/week_' . $week_number . '/' . $filename,
            'week' => $week_number,
            'year' => $year,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'products_total' => count($wotc_products),
            'products_with_purchases' => $products_with_purchases,
            'orders_count' => $ytd_orders_count
        );
    }
}