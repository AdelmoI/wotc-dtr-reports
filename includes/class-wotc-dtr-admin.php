<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Classe per l'interfaccia di amministrazione
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WotC_DTR_Admin
 */
class WotC_DTR_Admin {
    
    /**
     * Inizializza la classe
     */
    public function __construct() {
        // Aggiungi voce di menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Aggiungi script e stili
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        // Aggiungi gestione AJAX per invio email
        add_action('wp_ajax_wotc_send_report_email', array($this, 'ajax_send_report_email'));
    }
    
    /**
     * Gestisce la richiesta AJAX per inviare i report via email
     */
    public function ajax_send_report_email() {
        // Verifica nonce
        check_ajax_referer('wotc-email-nonce', 'security');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti.'));
        }
        
        $week = isset($_POST['week']) ? intval($_POST['week']) : 0;
        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        
        if (!$week || !$year) {
            wp_send_json_error(array('message' => 'Parametri mancanti.'));
        }
        
        // Istanzia la classe report
        $report_generator = new WotC_DTR_Reports();
        
        // Invia i report via email
        $result = $report_generator->send_reports_by_email($week, $year);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Report inviati con successo via email.'));
        } else {
            wp_send_json_error(array('message' => 'Errore nell\'invio dei report via email.'));
        }
    }
    /**
     * Aggiunge la voce di menu nell'admin
     */
    public function add_admin_menu() {
        // Pagina principale
        add_submenu_page(
            'woocommerce',
            __('WotC DTR Reports', 'wotc-dtr-reports'),
            __('WotC DTR Reports', 'wotc-dtr-reports'),
            'manage_options',
            'wotc-dtr-reports',
            array($this, 'render_admin_page')
        );
        
        // Pagina impostazioni
        add_submenu_page(
            'woocommerce',
            __('Impostazioni WotC DTR', 'wotc-dtr-reports'),
            __('Impostazioni WotC DTR', 'wotc-dtr-reports'),
            'manage_options',
            'wotc-dtr-settings',
            array($this, 'render_settings_page')
        );
    }
    /**
     * Renderizza la pagina delle impostazioni
     */
    public function render_settings_page() {
        // Carica il template delle impostazioni
        include WOTC_DTR_PLUGIN_DIR . 'templates/settings-page.php';
    }
    /**
     * Carica script e stili per l'admin
     *
     * @param string $hook Hook della pagina corrente
     */
    public function enqueue_admin_assets($hook) {
        if ('woocommerce_page_wotc-dtr-reports' !== $hook) {
            return;
        }
        
        // Carica jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        // Carica CSS personalizzato
        wp_enqueue_style(
            'wotc-dtr-admin-css',
            WOTC_DTR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WOTC_DTR_VERSION
        );
        
        // Carica JS personalizzato
        wp_enqueue_script(
            'wotc-dtr-admin-js',
            WOTC_DTR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            WOTC_DTR_VERSION,
            true
        );
    }
    
    /**
     * Renderizza la pagina di amministrazione
     */
    public function render_admin_page() {
        // Aggiungi questa riga per debug
        echo '<!-- Inizio render_admin_page() -->';
        
        // Gestisci la generazione manuale del report
        if (isset($_POST['generate_report']) && check_admin_referer('wotc_generate_report')) {
            // Ottieni i parametri dal form
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            $week_number = isset($_POST['week_number']) ? intval($_POST['week_number']) : 0;
            $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
            
            // Validazione input
            $start_date = !empty($start_date) ? $start_date : null;
            $end_date = !empty($end_date) ? $end_date : null;
            $week_number = ($week_number > 0 && $week_number <= 53) ? $week_number : null;
            $year = ($year >= 2020 && $year <= 2030) ? $year : null;
            
            // Istanzia la classe report
            $report_generator = new WotC_DTR_Reports();
            
            // Genera i report standard
            $sales_result = $report_generator->generate_sales_report($start_date, $end_date, $week_number, $year);
            $stock_result = $report_generator->generate_stock_report($week_number, $year);
            
            // Genera anche i report franchising
            $franchise_results = $report_generator->generate_franchising_reports($start_date, $end_date, $week_number, $year);
            
            // Mostra risultati
            echo '<div class="notice notice-success is-dismissible">';
            echo '<h3>' . __('Report generati con successo!', 'wotc-dtr-reports') . '</h3>';
            echo '<p><strong>Report vendite:</strong> ' . esc_html($sales_result['filepath']) . '</p>';
            
            if (isset($stock_result['filepath'])) {
                echo '<p><strong>Report stock:</strong> ' . esc_html($stock_result['filepath']) . '</p>';
            } else {
                echo '<p><strong>Report stock:</strong> Non generato</p>';
            }
            
            if (count($franchise_results) > 0) {
                echo '<p><strong>Report franchising generati:</strong></p>';
                echo '<ul>';
                foreach ($franchise_results as $result) {
                    echo '<li>' . esc_html($result['franchise_name']) . ': ' . esc_html($result['filepath']) . ' (' . esc_html($result['products_with_purchases']) . ' prodotti)</li>';
                }
                echo '</ul>';
            } else {
                echo '<p><strong>Report franchising:</strong> Nessun utente franchising trovato.</p>';
            }
            
            echo '</div>';
        }

        
        // Verifica che il template esista
        $template_path = WOTC_DTR_PLUGIN_DIR . 'templates/admin-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template non trovato: ' . esc_html($template_path) . '</p></div>';
        }
        
        // Aggiungi questa riga per debug
        echo '<!-- Fine render_admin_page() -->';
    }
}