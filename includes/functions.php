<?php
/**
 * Funzioni di supporto per WotC DTR Reports
 *
 * @package WotCDTRReports
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifica se un utente appartiene al gruppo franchising
 *
 * @param int $user_id ID dell'utente
 * @return bool True se l'utente è un franchising
 */
function wotc_dtr_is_user_franchising($user_id) {
    if (!$user_id) return false;
    $group = get_user_meta($user_id, 'wcb2b_group', true);
    return $group == FRANCHISING_GROUP;
}

/**
 * Verifica se un utente appartiene al gruppo reseller
 *
 * @param int $user_id ID dell'utente
 * @return bool True se l'utente è un reseller
 */
function wotc_dtr_is_user_reseller($user_id) {
    if (!$user_id) return false;
    $group = get_user_meta($user_id, 'wcb2b_group', true);
    return $group == RESELLER_GROUP;
}
