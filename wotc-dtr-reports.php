<?php
/**
 * Plugin Name: WotC DTR Reports
 * Plugin URI: https://ilcovonerd.it/
 * Description: Generatore di report settimanali per Wizards of the Coast secondo il formato DTR. Gestisce vendite, stock e traffico escludendo gli ordini franchising.
 * Version: 1.0.0
 * Author: Il Covo del Nerd
 * Author URI: https://ilcovonerd.it/
 * Text Domain: wotc-dtr-reports
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 7.9
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// IMPORTANTE: Prima definisci tutte le costanti
define('WOTC_DTR_VERSION', '1.0.0');
define('WOTC_DTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOTC_DTR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOTC_DTR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Costanti per i gruppi utente
if (!defined('FRANCHISING_GROUP')) {
    define('FRANCHISING_GROUP', 2); // Modifica con il valore corretto
}
if (!defined('RESELLER_GROUP')) {
    define('RESELLER_GROUP', 3); // Modifica con il valore corretto
}

// Solo DOPO aver definito le costanti, carica la classe Settings
require_once WOTC_DTR_PLUGIN_DIR . 'includes/class-wotc-dtr-settings.php';
global $wotc_dtr_settings;
$wotc_dtr_settings = new WotC_DTR_Settings();

// Verifica se WooCommerce è attivo
function wotc_dtr_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wotc_dtr_woocommerce_notice');
        return false;
    }
    return true;
}

// Avviso WooCommerce mancante
function wotc_dtr_woocommerce_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WotC DTR Reports richiede WooCommerce. Attiva WooCommerce prima di utilizzare questo plugin.', 'wotc-dtr-reports'); ?></p>
    </div>
    <?php
}

// Carica i file necessari
function wotc_dtr_load_files() {
    if (!wotc_dtr_check_woocommerce()) {
        return;
    }

    // Carica le funzioni di supporto
    require_once WOTC_DTR_PLUGIN_DIR . 'includes/functions.php';
    
    // La classe Settings è già caricata all'inizio del file, non ricaricarla
    
    // Carica la classe principale per la generazione dei report
    require_once WOTC_DTR_PLUGIN_DIR . 'includes/class-wotc-dtr-reports.php';
    
    // Carica la classe per l'interfaccia di amministrazione
    if (is_admin()) {
        require_once WOTC_DTR_PLUGIN_DIR . 'includes/class-wotc-dtr-admin.php';
        new WotC_DTR_Admin();
    }
}
add_action('plugins_loaded', 'wotc_dtr_load_files');

// Attivazione del plugin
register_activation_hook(__FILE__, 'wotc_dtr_activate');

function wotc_dtr_activate() {
    // Crea la tabella di cache se non esiste
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wotc_sales_cache';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
    }
    
    // Crea la directory per i report se non esiste
    $upload_dir = wp_upload_dir();
    $wotc_dir = $upload_dir['basedir'] . '/wotc-dtr/';
    
    if (!file_exists($wotc_dir)) {
        wp_mkdir_p($wotc_dir);
    }
    
    // Crea un file .htaccess che permetta l'accesso ai file CSV
    $htaccess_file = $wotc_dir . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "Options -Indexes\n";
        // Rimuovi le righe che bloccano l'accesso ai file CSV
        // $htaccess_content .= "<Files \"*.csv\">\n";
        // $htaccess_content .= "  Order allow,deny\n";
        // $htaccess_content .= "  Deny from all\n";
        // $htaccess_content .= "</Files>\n";
        
        file_put_contents($htaccess_file, $htaccess_content);
    }
}

// Disattivazione del plugin
register_deactivation_hook(__FILE__, 'wotc_dtr_deactivate');

function wotc_dtr_deactivate() {
    // Rimuovi i job cron
    wp_clear_scheduled_hook('wotc_weekly_report_cron');
}

// Disinstallazione del plugin
register_uninstall_hook(__FILE__, 'wotc_dtr_uninstall');

function wotc_dtr_uninstall() {
    // Rimuovi la tabella di cache
    global $wpdb;
    $table_name = $wpdb->prefix . 'wotc_sales_cache';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Rimuovi le impostazioni
    delete_option('wotc_dtr_settings');
    
    // Opzionale: rimuovi i report generati
    // $upload_dir = wp_upload_dir();
    // $wotc_dir = $upload_dir['basedir'] . '/wotc-dtr/';
    // if (file_exists($wotc_dir)) {
    //     // Rimuovi i file nella directory
    //     $files = glob($wotc_dir . '*');
    //     foreach ($files as $file) {
    //         if (is_file($file)) {
    //             unlink($file);
    //         }
    //     }
    //     // Rimuovi la directory
    //     rmdir($wotc_dir);
    // }
}

// Non ricaricare la classe Settings, è già caricata e inizializzata all'inizio del file