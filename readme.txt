=== WotC DTR Reports ===
Contributors: ilcovonerd
Tags: woocommerce, wizards of the coast, reports, dtr
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generatore di report settimanali per Wizards of the Coast secondo il formato DTR (Direct to Retail).

== Description ==

WotC DTR Reports è un plugin che permette ai rivenditori Wizards of the Coast di generare facilmente i report settimanali nel formato richiesto dal programma DTR (Direct to Retail).

Caratteristiche principali:

* Generazione automatica di report vendite settimanali
* Esclusione degli ordini di utenti franchising
* Inclusione degli ordini di utenti reseller
* Interfaccia admin per la generazione manuale dei report
* Supporto per cron job del server
* Formato file CSV compatibile con le specifiche WotC

Il plugin richiede WooCommerce e l'uso del campo personalizzato 'wotc_material_number' per i prodotti.

== Installation ==

1. Carica i file del plugin nella cartella `/wp-content/plugins/wotc-dtr-reports`
2. Attiva il plugin attraverso il menu 'Plugins' in WordPress
3. Configura i gruppi utente per franchising e reseller nelle costanti del plugin
4. Vai a WooCommerce → WotC DTR Reports per generare i report

== Frequently Asked Questions ==

= Come si differenziano gli utenti franchising? =

Il plugin utilizza il campo meta 'wcb2b_group' con valore costante FRANCHISING_GROUP (default: 2) per identificare gli utenti franchising.

= Dove vengono salvati i report generati? =

I report vengono salvati nella cartella `wp-content/uploads/wotc-dtr/` del tuo sito WordPress.

= Il plugin può essere eseguito automaticamente? =

Sì, il plugin può essere eseguito automaticamente tramite WP-Cron o tramite un cron job del server chiamando il file cron-runner.php.

== Changelog ==

= 1.0.0 =
* Prima versione pubblica