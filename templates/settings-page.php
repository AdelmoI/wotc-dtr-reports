<?php
/**
 * Template per la pagina delle impostazioni
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wotc-dtr-settings">
    <h1><?php _e('Impostazioni WotC DTR Reports', 'wotc-dtr-reports'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wotc_dtr_settings_group');
        do_settings_sections('wotc-dtr-settings');
        submit_button();
        ?>
    </form>
    
    <div class="wotc-dtr-card wotc-dtr-help">
        <h2><?php _e('Guida Rapida', 'wotc-dtr-reports'); ?></h2>
        
        <h3><?php _e('Impostazioni Generali', 'wotc-dtr-reports'); ?></h3>
        <p><strong><?php _e('Nome Cliente:', 'wotc-dtr-reports'); ?></strong> <?php _e('Usato per generare i nomi dei file. Es. "ilcovodelnerdweb" produrrà file come "ilcovodelnerdweb_sales_wk_40.csv".', 'wotc-dtr-reports'); ?></p>
        
        <h3><?php _e('Invio Email', 'wotc-dtr-reports'); ?></h3>
        <p><?php _e('Quando abilitato, i report vengono inviati automaticamente per email dopo essere stati generati.', 'wotc-dtr-reports'); ?></p>
        <p><strong><?php _e('Segnaposto disponibili:', 'wotc-dtr-reports'); ?></strong></p>
        <ul>
            <li>{week} - <?php _e('Numero della settimana', 'wotc-dtr-reports'); ?></li>
            <li>{year} - <?php _e('Anno', 'wotc-dtr-reports'); ?></li>
        </ul>
        <p><strong><?php _e('Esempio:', 'wotc-dtr-reports'); ?></strong> <?php _e('Se nel testo usi "Settimana {week} dell\'anno {year}", verrà convertito in "Settimana 40 dell\'anno 2025".', 'wotc-dtr-reports'); ?></p>
    </div>
</div>