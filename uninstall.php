<?php
/**
 * @version           3.0.0
 * @package           email verification for woocommerce
 * @author            Tonny Keuken (tonny.keuken@tidl.nl)
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require('includes/class-email-verification-for-woocommerce.php');
global $wpdb;
$used_table_name = TK_EVF_WC::get_table_name();

// check for page and its settings, if we have them delete them
If ( get_option( 'woocommerce_account_validation_page_id' ) ) {    
    $used_page_id = get_option( 'woocommerce_account_validation_page_id' );
    wp_trash_post( $used_page_id );
    delete_option( 'woocommerce_account_validation_page_id' );
}        
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->prefix."options`");
//drop table
$wpdb->get_results("DROP TABLE IF EXISTS ".$used_table_name );