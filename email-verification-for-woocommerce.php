<?php
/**
 * @version           3.2
 * @package           email verification for woocommerce
 * @author            Tonny Keuken (tonny.keuken@tidl.nl)
 *
 * @wordpress-plugin
 * Plugin Name:       Email Verification for Woocommerce
 * Plugin URI:        http://tidl.nl/
 * Description:       Sends a verification link to a customer' e-mailaddress for activating their account after registration, this hooks in before proceeding to the checkout.
 * Version:           3.1.1
 * Author:            Tonny Keuken
 * Author URI:        http://tidl.nl/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       email-verification-for-woocommerce
 * Domain Path:       /language
 */

/*
 * I used and reinforced The original plugin WooCommerce Email Verification  
 * Original author: subhansanjaya
 * Original URI: http://www.backraw.com/ 
 * 
 * I revamped it and made it work
 */

if(! defined( 'ABSPATH' )) exit; // Exit if accessed directly

require('includes/class-email-verification-for-woocommerce.php');

global $TK_EVF_WC;
$TK_EVF_WC = new TK_EVF_WC();

register_activation_hook( __FILE__,  array( 'TK_EVF_WC', 'activate')  );
register_deactivation_hook( __FILE__,  array( 'TK_EVF_WC', 'deactivate')  );