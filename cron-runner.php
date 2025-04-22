<?php
/**
 * Runner per cron job del server
 *
 * Questo file può essere eseguito direttamente da cron
 */

// Carica WordPress
if (file_exists(dirname(__FILE__) . '/../../../wp-load.php')) {
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
} else {
    die('WordPress not found');
}

// Verifica che il plugin sia attivo
if (!class_exists('WotC_DTR_Reports')) {
    die('WotC DTR Reports plugin not active');
}

// Esegui la generazione dei report
$report_generator = new WotC_DTR_Reports();
$result = $report_generator->generate_weekly_reports();

// Output per log del cron
echo "WotC DTR Reports - Report generati con successo\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";