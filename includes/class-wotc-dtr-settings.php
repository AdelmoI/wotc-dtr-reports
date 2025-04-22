<?php
/**
 * Gestione delle impostazioni del plugin
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WotC_DTR_Settings
 */
class WotC_DTR_Settings {
    /**
     * Nome opzione per le impostazioni
     *
     * @var string
     */
    private $option_name = 'wotc_dtr_settings';
    
    /**
     * Impostazioni attuali
     *
     * @var array
     */
    private $settings;
    
    /**
     * Inizializza la classe
     */
    public function __construct() {
        // Carica le impostazioni dal database
        $this->load_settings();
        
        // Aggiungi sezione impostazioni all'interfaccia admin
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Carica le impostazioni dal database
     */
    private function load_settings() {
        $default_settings = array(
            'customer_name' => 'ilcovodelnerdweb',
            'email_recipients' => 'adelmoinfante1992@gmail.com',
            'email_subject' => 'WotC DTR Reports - Settimana {week}/{year}',
            'email_message' => "Gentile Amministratore,\n\nIn allegato troverai i report WotC DTR per la settimana {week} dell'anno {year}.\n\nI report sono stati generati automaticamente dal sistema.\n\nCordiali saluti,\nPlugin WotC DTR Reports",
            'cc_email' => '',
            'bcc_email' => '',
            'send_email' => 'yes'
        );
        
        $this->settings = get_option($this->option_name, $default_settings);
        
        // Assicurati che tutte le impostazioni di default esistano
        $this->settings = wp_parse_args($this->settings, $default_settings);
    }
    
    /**
     * Registra le impostazioni e i campi
     */
    public function register_settings() {
        // Registra il gruppo di impostazioni
        register_setting(
            'wotc_dtr_settings_group',  // Option group
            $this->option_name,         // Option name
            array($this, 'sanitize_settings')  // Sanitize callback
        );
        
        add_settings_section(
            'wotc_dtr_general_section',
            __('Impostazioni Generali', 'wotc-dtr-reports'),
            array($this, 'render_general_section'),
            'wotc-dtr-settings'
        );
        
        add_settings_field(
            'customer_name',
            __('Nome Cliente', 'wotc-dtr-reports'),
            array($this, 'render_customer_name_field'),
            'wotc-dtr-settings',
            'wotc_dtr_general_section'
        );
        
        add_settings_section(
            'wotc_dtr_email_section',
            __('Impostazioni Email', 'wotc-dtr-reports'),
            array($this, 'render_email_section'),
            'wotc-dtr-settings'
        );
        
        add_settings_field(
            'send_email',
            __('Invia Email', 'wotc-dtr-reports'),
            array($this, 'render_send_email_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
        
        add_settings_field(
            'email_recipients',
            __('Destinatari', 'wotc-dtr-reports'),
            array($this, 'render_email_recipients_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
        
        add_settings_field(
            'cc_email',
            __('CC', 'wotc-dtr-reports'),
            array($this, 'render_cc_email_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
        
        add_settings_field(
            'bcc_email',
            __('BCC', 'wotc-dtr-reports'),
            array($this, 'render_bcc_email_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
        
        add_settings_field(
            'email_subject',
            __('Oggetto Email', 'wotc-dtr-reports'),
            array($this, 'render_email_subject_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
        
        add_settings_field(
            'email_message',
            __('Messaggio Email', 'wotc-dtr-reports'),
            array($this, 'render_email_message_field'),
            'wotc-dtr-settings',
            'wotc_dtr_email_section'
        );
    }
    
    /**
     * Sanitizza le impostazioni prima di salvarle
     *
     * @param array $input Dati inviati dal form
     * @return array Dati sanitizzati
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['customer_name'])) {
            $sanitized['customer_name'] = sanitize_text_field($input['customer_name']);
        }
        
        if (isset($input['email_recipients'])) {
            $sanitized['email_recipients'] = sanitize_textarea_field($input['email_recipients']);
        }
        
        if (isset($input['cc_email'])) {
            $sanitized['cc_email'] = sanitize_textarea_field($input['cc_email']);
        }
        
        if (isset($input['bcc_email'])) {
            $sanitized['bcc_email'] = sanitize_textarea_field($input['bcc_email']);
        }
        
        if (isset($input['email_subject'])) {
            $sanitized['email_subject'] = sanitize_text_field($input['email_subject']);
        }
        
        if (isset($input['email_message'])) {
            $sanitized['email_message'] = sanitize_textarea_field($input['email_message']);
        }
        
        if (isset($input['send_email'])) {
            $sanitized['send_email'] = 'yes';
        } else {
            $sanitized['send_email'] = 'no';
        }
        
        return $sanitized;
    }
    
    /**
     * Renderizza la sezione generale
     */
    public function render_general_section() {
        echo '<p>' . __('Configura i parametri generali per la generazione dei report.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo del nome cliente
     */
    public function render_customer_name_field() {
        $value = isset($this->settings['customer_name']) ? $this->settings['customer_name'] : '';
        echo '<input type="text" name="' . $this->option_name . '[customer_name]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Nome del cliente da utilizzare nei nomi dei file dei report (es. ilcovodelnerdweb).', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza la sezione email
     */
    public function render_email_section() {
        echo '<p>' . __('Configura le impostazioni per l\'invio automatico dei report via email.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per abilitare l'invio email
     */
    public function render_send_email_field() {
        $checked = isset($this->settings['send_email']) && $this->settings['send_email'] === 'yes' ? 'checked' : '';
        echo '<input type="checkbox" name="' . $this->option_name . '[send_email]" value="yes" ' . $checked . '>';
        echo '<p class="description">' . __('Abilita l\'invio automatico dei report via email.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per i destinatari dell'email
     */
    public function render_email_recipients_field() {
        $value = isset($this->settings['email_recipients']) ? $this->settings['email_recipients'] : '';
        echo '<textarea name="' . $this->option_name . '[email_recipients]" rows="2" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Inserisci gli indirizzi email dei destinatari, separati da virgola.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per gli indirizzi CC
     */
    public function render_cc_email_field() {
        $value = isset($this->settings['cc_email']) ? $this->settings['cc_email'] : '';
        echo '<textarea name="' . $this->option_name . '[cc_email]" rows="2" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Inserisci gli indirizzi email in copia (CC), separati da virgola.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per gli indirizzi BCC
     */
    public function render_bcc_email_field() {
        $value = isset($this->settings['bcc_email']) ? $this->settings['bcc_email'] : '';
        echo '<textarea name="' . $this->option_name . '[bcc_email]" rows="2" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Inserisci gli indirizzi email in copia nascosta (BCC), separati da virgola.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per l'oggetto dell'email
     */
    public function render_email_subject_field() {
        $value = isset($this->settings['email_subject']) ? $this->settings['email_subject'] : '';
        echo '<input type="text" name="' . $this->option_name . '[email_subject]" value="' . esc_attr($value) . '" class="large-text">';
        echo '<p class="description">' . __('Oggetto dell\'email. Puoi usare {week} e {year} come segnaposto.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Renderizza il campo per il messaggio dell'email
     */
    public function render_email_message_field() {
        $value = isset($this->settings['email_message']) ? $this->settings['email_message'] : '';
        echo '<textarea name="' . $this->option_name . '[email_message]" rows="6" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Testo dell\'email. Puoi usare {week} e {year} come segnaposto.', 'wotc-dtr-reports') . '</p>';
    }
    
    /**
     * Ottiene un'impostazione specifica
     *
     * @param string $key Nome dell'impostazione
     * @param mixed $default Valore di default se l'impostazione non esiste
     * @return mixed Valore dell'impostazione
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Ottiene tutte le impostazioni
     *
     * @return array Tutte le impostazioni
     */
    public function get_all_settings() {
        return $this->settings;
    }
}