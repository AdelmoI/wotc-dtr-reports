<?php
/**
 * Template per la pagina di amministrazione
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Valori predefiniti per il form
$current_week = date('W');
$current_year = date('Y');
$date_obj = new DateTime();
$date_obj->setISODate($current_year, $current_week, 1);
$default_start_date = $date_obj->format('Y-m-d');
$date_obj->modify('+6 days');
$default_end_date = $date_obj->format('Y-m-d');

// Recupera i report esistenti
$upload_dir = wp_upload_dir();
$wotc_base_dir = $upload_dir['basedir'] . '/wotc-dtr/';
$reports = array();

// Cerca i report nell'ultimo anno
$current_year_dir = $wotc_base_dir . $current_year . '/';
if (is_dir($current_year_dir)) {
    $week_dirs = glob($current_year_dir . 'week_*', GLOB_ONLYDIR);
    
    foreach ($week_dirs as $week_dir) {
        $week_num = str_replace($current_year_dir . 'week_', '', $week_dir);
        $sales_file = $week_dir . '/' . 'ilcovodelnerdweb_sales_wk_' . $week_num . '.csv';
        $stock_file = $week_dir . '/' . 'ilcovodelnerdweb_stock_wk_' . $week_num . '.csv';
        
        if (file_exists($sales_file) || file_exists($stock_file)) {
            $reports[$week_num] = array(
                'week' => $week_num,
                'year' => $current_year,
                'date' => date('Y-m-d H:i:s', filemtime($week_dir)),
                'sales_url' => file_exists($sales_file) ? $upload_dir['baseurl'] . '/wotc-dtr/' . $current_year . '/week_' . $week_num . '/' . basename($sales_file) : false,
                'stock_url' => file_exists($stock_file) ? $upload_dir['baseurl'] . '/wotc-dtr/' . $current_year . '/week_' . $week_num . '/' . basename($stock_file) : false
            );
        }
    }
}

// Ordina i report per settimana (più recenti prima)
krsort($reports);
?>

<div class="wrap wotc-dtr-admin">
    <h1><?php _e('WotC DTR Reports', 'wotc-dtr-reports'); ?></h1>
    
    <div class="wotc-dtr-card">
        <h2><?php _e('Genera Report WotC', 'wotc-dtr-reports'); ?></h2>
        <p><?php _e('Usa questo form per generare un report per un periodo specifico.', 'wotc-dtr-reports'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('wotc_generate_report'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wotc-dtr-start-date"><?php _e('Data inizio', 'wotc-dtr-reports'); ?></label></th>
                    <td>
                        <input type="text" name="start_date" id="wotc-dtr-start-date" value="<?php echo esc_attr($default_start_date); ?>" class="regular-text wotc-dtr-datepicker">
                        <p class="description"><?php _e('Seleziona la data di inizio del periodo (inclusa)', 'wotc-dtr-reports'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wotc-dtr-end-date"><?php _e('Data fine', 'wotc-dtr-reports'); ?></label></th>
                    <td>
                        <input type="text" name="end_date" id="wotc-dtr-end-date" value="<?php echo esc_attr($default_end_date); ?>" class="regular-text wotc-dtr-datepicker">
                        <p class="description"><?php _e('Seleziona la data di fine del periodo (inclusa)', 'wotc-dtr-reports'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wotc-dtr-week-number"><?php _e('Numero settimana', 'wotc-dtr-reports'); ?></label></th>
                    <td>
                        <input type="number" name="week_number" id="wotc-dtr-week-number" value="<?php echo esc_attr($current_week); ?>" min="1" max="53" class="small-text">
                        <p class="description"><?php _e('Numero della settimana da usare nel report e nel nome file', 'wotc-dtr-reports'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wotc-dtr-year"><?php _e('Anno', 'wotc-dtr-reports'); ?></label></th>
                    <td>
                        <input type="number" name="year" id="wotc-dtr-year" value="<?php echo esc_attr($current_year); ?>" min="2020" max="2030" class="small-text">
                        <p class="description"><?php _e('Anno da usare nel report', 'wotc-dtr-reports'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wotc-dtr-send-email"><?php _e('Invia Email', 'wotc-dtr-reports'); ?></label></th>
                    <td>
                        <input type="checkbox" name="send_email" id="wotc-dtr-send-email" value="1">
                        <p class="description"><?php _e('Invia i report via email dopo la generazione', 'wotc-dtr-reports'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="generate_report" class="button button-primary" value="<?php _e('Genera Report', 'wotc-dtr-reports'); ?>">
            </p>
        </form>
    </div>
    
    
    <div class="wotc-dtr-card">
        <h2><?php _e('Report Disponibili', 'wotc-dtr-reports'); ?></h2>
        
        <?php if (empty($reports)): ?>
            <p><?php _e('Nessun report disponibile.', 'wotc-dtr-reports'); ?></p>
        <?php else: ?>
            <table class="widefat striped wotc-dtr-report-list">
                <thead>
                    <tr>
                        <th><?php _e('Settimana', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Anno', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Generato il', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Report Vendite', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Report Stock', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Azioni', 'wotc-dtr-reports'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $week_number => $report): ?>
                        <tr>
                            <td><?php echo esc_html($week_number); ?></td>
                            <td><?php echo esc_html($report['year']); ?></td>
                            <td><?php echo esc_html($report['date']); ?></td>
                            <td>
                                <?php if ($report['sales_url']): ?>
                                    <a href="<?php echo esc_url($report['sales_url']); ?>" class="button" download><?php _e('Scarica', 'wotc-dtr-reports'); ?></a>
                                <?php else: ?>
                                    <span class="wotc-dtr-not-available"><?php _e('Non disponibile', 'wotc-dtr-reports'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($report['stock_url']): ?>
                                    <a href="<?php echo esc_url($report['stock_url']); ?>" class="button" download><?php _e('Scarica', 'wotc-dtr-reports'); ?></a>
                                <?php else: ?>
                                    <span class="wotc-dtr-not-available"><?php _e('Non disponibile', 'wotc-dtr-reports'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="#" class="button wotc-dtr-email-report" data-week="<?php echo esc_attr($week_number); ?>" data-year="<?php echo esc_attr($report['year']); ?>"><?php _e('Invia via Email', 'wotc-dtr-reports'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="wotc-dtr-card">
        <h2><?php _e('Report Franchising Disponibili', 'wotc-dtr-reports'); ?></h2>
        
        <?php
        // Cerca i report franchising
        $franchise_reports = array();
        
        if (is_dir($current_year_dir)) {
            $week_dirs = glob($current_year_dir . 'week_*', GLOB_ONLYDIR);
            
            foreach ($week_dirs as $week_dir) {
                $week_num = str_replace($current_year_dir . 'week_', '', $week_dir);
                
                // Cerca i file con pattern *_sales_*_wk_*.csv (escludi il report standard)
                $files = glob($week_dir . '/' . 'ilcovodelnerdweb_sales_*_wk_' . $week_num . '.csv');
                $standard_file = $week_dir . '/' . 'ilcovodelnerdweb_sales_wk_' . $week_num . '.csv';
                
                foreach ($files as $file) {
                    // Salta il report standard
                    if ($file === $standard_file) {
                        continue;
                    }
                    
                    $filename = basename($file);
                    // Estrai il nome del franchising dal nome file
                    preg_match('/ilcovodelnerdweb_sales_(.+?)_wk_(\d+)\.csv$/', $filename, $matches);
                    
                    if (isset($matches[1]) && isset($matches[2])) {
                        $franchise_slug = $matches[1];
                        $franchise_week = $matches[2];
                        
                        // Cerca di ottenere il nome reale del franchising
                        $franchise_name = ucwords(str_replace('-', ' ', $franchise_slug));
                        
                        $franchise_reports[] = array(
                            'week' => $franchise_week,
                            'year' => $current_year,
                            'franchise_slug' => $franchise_slug,
                            'franchise_name' => $franchise_name,
                            'date' => date('Y-m-d H:i:s', filemtime($file)),
                            'url' => $upload_dir['baseurl'] . '/wotc-dtr/' . $current_year . '/week_' . $franchise_week . '/' . $filename
                        );
                    }
                }
            }
        }
        
        // Ordina i report per nome franchising e settimana
        usort($franchise_reports, function($a, $b) {
            $name_compare = strcmp($a['franchise_name'], $b['franchise_name']);
            if ($name_compare !== 0) {
                return $name_compare;
            }
            return $b['week'] - $a['week']; // Settimane più recenti prima
        });
        ?>
        
        <?php if (empty($franchise_reports)): ?>
            <p><?php _e('Nessun report franchising disponibile.', 'wotc-dtr-reports'); ?></p>
        <?php else: ?>
            <table class="widefat striped wotc-dtr-report-list">
                <thead>
                    <tr>
                        <th><?php _e('Franchising', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Settimana', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Anno', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Generato il', 'wotc-dtr-reports'); ?></th>
                        <th><?php _e('Azioni', 'wotc-dtr-reports'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($franchise_reports as $report): ?>
                        <tr>
                            <td><?php echo esc_html($report['franchise_name']); ?></td>
                            <td><?php echo esc_html($report['week']); ?></td>
                            <td><?php echo esc_html($report['year']); ?></td>
                            <td><?php echo esc_html($report['date']); ?></td>
                            <td>
                                <a href="<?php echo esc_url($report['url']); ?>" class="button" download><?php _e('Scarica', 'wotc-dtr-reports'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="wotc-dtr-card wotc-dtr-cron-info">
        <h2><?php _e('Informazioni Server Cron', 'wotc-dtr-reports'); ?></h2>
        <p><?php _e('Questo plugin può essere eseguito automaticamente tramite un cron job del server.', 'wotc-dtr-reports'); ?></p>
        <p><strong><?php _e('Comando da aggiungere al cron del server:', 'wotc-dtr-reports'); ?></strong></p>
        <code>0 3 * * 1 php <?php echo ABSPATH; ?>wp-content/plugins/wotc-dtr-reports/cron-runner.php</code>
        <p><?php _e('Questo comando eseguirà lo script ogni lunedì alle 3:00 del mattino.', 'wotc-dtr-reports'); ?></p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.wotc-dtr-email-report').click(function(e) {
        e.preventDefault();
        
        if (confirm('<?php _e("Vuoi inviare via email i report di questa settimana?", "wotc-dtr-reports"); ?>')) {
            var week = $(this).data('week');
            var year = $(this).data('year');
            
            // Mostra indicatore di caricamento
            $(this).text('Invio in corso...').prop('disabled', true);
            
            // Chiamata AJAX per inviare l'email
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wotc_send_report_email',
                    week: week,
                    year: year,
                    security: '<?php echo wp_create_nonce("wotc-email-nonce"); ?>'
                },
                success: function(response) {
                    alert(response.data.message);
                },
                error: function() {
                    alert('Errore durante l\'invio dell\'email.');
                },
                complete: function() {
                    $('.wotc-dtr-email-report').text('<?php _e("Invia via Email", "wotc-dtr-reports"); ?>').prop('disabled', false);
                }
            });
        }
    });
});
</script>