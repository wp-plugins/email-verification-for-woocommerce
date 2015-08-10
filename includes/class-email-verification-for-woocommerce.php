<?php

/**
 * @version           3.2
 * @package           email verification for woocommerce
 * @author            Tonny Keuken (tonny.keuken@tidl.nl)
 */
class TK_EVF_WC {

        private static $temp_user_table = 'unvalidated_users';
        public $customer_id;

        public function __construct() {
                add_shortcode( 'email-verification-for-woocommerce', array( $this, 'add_shortcode' ) );
                add_action( 'user_register', array( $this, 'create_temp_user' ) );
                add_action( 'woocommerce_checkout_init', array( $this, 'before_checkout_process' ) );
                add_action( 'init', array( $this, 'load_textdomain' ) );
                // add scheduler action
                add_action( 'purge_unvalidated_accounts_cron', array( $this, 'purge_unvalidated_accounts' ) );
                add_action( 'woocommerce_before_my_account', array( $this, 'add_activation_message_to_my_account' ) );
                add_filter( 'woocommerce_my_account_message', array( $this, 'add_text_to_my_account' ) );
        }

        public static function get_table_name() {
                global $wpdb;
                return $wpdb->prefix . self::$temp_user_table;
        }

        public function before_checkout_process() {
                // check if user is logged in and if guest checkout is enabled
                // this only works if guest checkout is disabled,
                $guest_checkout = get_option( 'woocommerce_enable_guest_checkout' );
                if ( !is_user_logged_in() && $guest_checkout === 'no' ) {
                        $page_id = wc_get_page_id( 'myaccount' );
                        wp_redirect( home_url() . '?page_id=' . $page_id );
                        exit;
                } else {
                        return;
                }
        }

        public function add_text_to_my_account() {
                // check if user is logged in and if guest checkout is enabled
                // this only works if guest checkout is disabled,
                $guest_checkout = get_option( 'woocommerce_enable_guest_checkout' );
                //do messages        
                if ( isset( $_SESSION[ 'email_send_for_activation' ] ) && $_SESSION[ 'email_send_for_activation' ] === 'done' ) {
                        wc_add_notice( __( 'A confirmation link has been sent to your email address. Please follow the instructions in the email to activate your account.', 'email-verification-for-woocommerce', 'success' ) );
                        unset( $_SESSION[ 'email_send_for_activation' ] );
                } else if ( isset( $_SESSION[ 'email_send_for_activation' ] ) && $_SESSION[ 'email_send_for_activation' ] === 'verified' ) {
                        $this->add_activation_message_to_my_account();
                } else if ( !is_user_logged_in() && $guest_checkout === 'no' ) {
                        if ( !isset( $_GET[ 'action' ] ) || filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING ) === '' ) {
                                wc_add_notice( __( 'You will need an account with a validated emailaddress before you can proceed the checkout. <br /> Please login or create an account to checkout.', 'email-verification-for-woocommerce', 'notice' ) );
                        }
                }
                return;
        }

        public function add_activation_message_to_my_account() {
                if ( isset( $_SESSION[ 'email_send_for_activation' ] ) && $_SESSION[ 'email_send_for_activation' ] === 'verified' ) {
                        $page_id = wc_get_page_id( 'myaccount' );
                        wc_add_notice( __( 'Account activation successful.', 'email-verification-for-woocommerce' ), 'success' );
                        unset( $_SESSION[ 'email_send_for_activation' ] ); //break loop
                        wp_redirect( home_url() . '?page_id=' . $page_id );
                        exit;
                }
        }

        public function load_textdomain() {
                load_plugin_textdomain( 'email-verification-for-woocommerce', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/language/' );
        }

        public function add_shortcode() {
                global $wpdb;
                $hash = htmlspecialchars( filter_input( INPUT_GET, 'passkey', FILTER_SANITIZE_STRING ) );
                $did  = count( $hash === 64 ) ? $hash : false;
                if ( $did ) {
                        $table  = self::get_table_name();
                        $sSql   = $wpdb->prepare( "SELECT * FROM `{$table}` WHERE confirm_code = '%s'", $did );
                        $result = $wpdb->get_row( $sSql, ARRAY_A );

                        if ( isset( $result[ 'confirm_code' ] ) && $result[ 'confirm_code' ] === $did ) {
                                //remove filter
                                remove_filter( 'user_register', array( $this, 'create_temp_user' ) );

                                $create_user = $this->create_new_customer( $result[ 'user_email' ], $result[ 'user_login' ], $result[ 'user_pass' ], $result[ 'date_registered' ] );

                                if ( is_int( $create_user ) ) {
                                        $this->remove_temp_user( $result[ 'user_id' ] );
                                        if ( WC()->cart->get_cart_contents_count() > 0 ) {
                                                $page_id = wc_get_page_id( 'checkout' );
                                                wc_set_customer_auth_cookie( $create_user );
                                                wc_add_notice( __( 'Account activation successful. You can checkout now.', 'email-verification-for-woocommerce' ), 'success' );
                                                wp_redirect( home_url() . '?page_id=' . $page_id );
                                                exit;
                                        } else {
                                                $page_id = wc_get_page_id( 'myaccount' );
                                                wc_set_customer_auth_cookie( $create_user );
                                                $_SESSION[ 'email_send_for_activation' ] = 'verified';
                                                wp_redirect( home_url() . '?page_id=' . $page_id );
                                                exit;
                                        }
                                } else {
                                        wp_redirect( home_url() );
                                        exit;
                                }
                        } else {
                                wp_redirect( home_url() );
                                exit;
                        }
                }
        }

        public function create_new_customer( $email, $username, $password, $date_registered ) {                
                $check_pw_setting = get_option( 'woocommerce_registration_generate_password' );                
                if ( is_null( username_exists( $username ) ) ) {
                        if ( $check_pw_setting === 'yes' ) {
                                $password_generated = true;
                                $password = wp_generate_password();
                        } else {
                                $password_generated = false;
                        }
                        
                        $new_customer_data = apply_filters( 'woocommerce_new_customer_data', array(
                                'user_login'            => $username,
                                'user_pass'             => $password,
                                'user_email'            => $email,
                                'role'                  => 'customer',
                                'user_registered'       => $date_registered
                                ) );
                        
                        $uid = wp_insert_user( $new_customer_data );
                        
                        do_action( 'woocommerce_created_customer', $uid, $new_customer_data, $password_generated );                        
                        return $uid;
                } else {
                        return false;
                }
        }

        public function set_customer_id( $user_id ) {
                $this->customer_id = $user_id;
        }

        public function create_temp_user( $user_id ) {
                if ( !current_user_can( 'manage_options' ) ) {
                        if ( !$user_id ) {
                                return;
                        }
                        // avoiding  a 'call to undefined function' error while using wp_delete_user
                        require_once( ABSPATH . 'wp-admin/includes/user.php' );
                        global $wpdb;
                        $data = get_userdata( $user_id );
                        $to   = $data->user_email;
                        $un   = $data->user_login;
                        $pw   = $data->user_pass;
                        $dt   = current_time( 'mysql' );

                        $hash = $this->generate_hash( $to, $un );
                        $this->send_verification( $to, $un, $hash );

                        $sql = $wpdb->prepare( "INSERT INTO `" . self::get_table_name() . "` (`user_login`, `user_pass`, `user_email`, `confirm_code`, `date_registered`) VALUES(%s, %s, %s, %s, %s)", array( $un, $pw, $to, $hash, $dt ) );
                        $wpdb->query( $sql );

                        //removing user from wordpress
                        wp_delete_user( $user_id );
                }
        }

        public function generate_hash( $to, $un ) {
                $chars     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@()[]{}<>?';
                $generated = '';
                for ( $i = 0; $i < 12; $i++ ) {
                        $generated .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
                }
                return hash( 'sha256', $to . $generated . $un );
        }

        public function remove_temp_user( $user_id ) {
                global $wpdb;
                $table = self::get_table_name();
                $sSql  = $wpdb->prepare( "DELETE FROM `{$table}`
                    WHERE `user_id` = %d
                    LIMIT 1", $user_id );
                $wpdb->query( $sSql );
        }

        /* Verification Email */

        public function send_verification( $to, $un, $hash ) {
                if ( session_status() === PHP_SESSION_NONE ) {
                        session_start();
                }
                $page_id                                 = get_option( 'woocommerce_account_validation_page_id' );
                $activation_post                         = get_post( $page_id );
                $activation_url                          = $activation_post->post_name;
                $blogname                                = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                $subject                                 = sprintf( __( 'Activate your %s account', 'email-verification-for-woocommerce' ), $blogname );
                $message                                 = sprintf( __( 'Hello %s,<br/><br/>'
                                . 'To activate your account and access the feature you were trying to view, '
                                . 'copy and paste the following link into your web browser:'
                                . '<br/><a href="%s">%s</a><br/><br/>'
                                . 'Thank you for registering with us.'
                                . '<br/><br/>Yours sincerely,<br/>%s', 'email-verification-for-woocommerce' ), $un, home_url( '/' ) . $activation_url . '?passkey=' . $hash, home_url( '/' ) . $activation_url . '?passkey=' . $hash, $blogname );
                wc_mail( $to, $subject, $message );
                $_SESSION[ 'email_send_for_activation' ] = 'done';
                return;
        }

        public static function activate() {
                global $wp_version;
                // check the version of PHP running on the server                        
                if ( version_compare( phpversion(), '5.4.0' ) < 0 ) {
                        deactivate_plugins( plugin_basename( __FILE__ ) );
                        add_action( 'admin_notices', array( 'TK_EVF_WC', 'add_install_php_error' ) );
                }
                // check the version of WP running                    
                if ( version_compare( $wp_version, '4.0' ) < 0 ) {
                        deactivate_plugins( plugin_basename( __FILE__ ) );
                        add_action( 'admin_notices', array( 'TK_EVF_WC', 'add_install_wp_error' ) );
                }
                // check the version of WP running                    
                if ( !is_object( WC() ) || version_compare( WC()->version, '2.3', '<' ) ) {
                        deactivate_plugins( plugin_basename( __FILE__ ) );
                        add_action( 'admin_notices', array( 'TK_EVF_WC', 'add_install_wc_error' ) );
                }

                global $wpdb;
                // set DB table
                if ( $wpdb->get_var( "SHOW TABLES LIKE '" . self::get_table_name() . "'" ) != self::get_table_name() ) {
                        $sSql = "CREATE TABLE IF NOT EXISTS `" . self::get_table_name() . "` (";
                        $sSql .= "`user_id` INT NOT NULL AUTO_INCREMENT ,";
                        $sSql .= "`user_login` TEXT NOT NULL,";
                        $sSql .= "`user_pass` TEXT NOT NULL,";
                        $sSql .= "`user_email` TEXT NOT NULL,";
                        $sSql .= "`confirm_code` TEXT NOT NULL,";
                        $sSql .= "`date_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',";
                        $sSql .= "PRIMARY KEY (`user_id`)";
                        $sSql .= ")";
                        $wpdb->query( $sSql );
                }

                // create page
                $page_id = wc_get_page_id( 'woocommerce_account_validation_page_id' );

                if ( $page_id == - 1 ) {
                        // add page and assign
                        $page    = array(
                                'menu_order'     => 0,
                                'comment_status' => 'closed',
                                'ping_status'    => 'closed',
                                'post_author'    => 1,
                                'post_content'   => '[email-verification-for-woocommerce]',
                                'post_name'      => __( 'account-validation', 'email-verification-for-woocommerce' ),
                                'post_title'     => __( 'Account validation', 'email-verification-for-woocommerce' ),
                                'post_type'      => 'page',
                                'post_status'    => 'publish',
                                'post_category'  => array( 1 )
                        );
                        $page_id = wp_insert_post( $page );
                        update_option( 'woocommerce_account_validation_page_id', $page_id );

                        // schedule the purge_transients_cron
                        wp_schedule_event( time(), 'daily', 'purge_unvalidated_accounts_cron' );
                }
        }

        public static function add_install_php_error() {
                global $pagenow;
                if ( $pagenow == 'plugins.php' ) {
                        $class   = "error";
                        $message = sprint( __( 'This plugin requires PHP Version 5.4 or newer.  You are using version %s sorry about that.', 'email-verification-for-woocommerce' ), PHP_VERSION );
                        echo '<div class="' . $class . '"> <p> ' . $message . ' </p></div>';
                }
        }

        public static function add_install_wp_error() {
                global $pagenow, $wp_version;
                if ( $pagenow == 'plugins.php' ) {
                        $class   = "error";
                        $message = sprintf( __( 'This plugin requires WordPress Version 4.0 or newer.  You are using version %s, please upgrade.', 'email-verification-for-woocommerce' ), $wp_version );
                        echo '<div class="' . $class . '"> <p> ' . $message . ' </p></div>';
                }
        }

        public static function add_install_wc_error() {
                global $pagenow;
                if ( $pagenow == 'plugins.php' ) {
                        $class   = "error";
                        $message = sprintf( __( 'This plugin requires WooCommerce Version 2.3 or newer.  You are using version %s, please upgrade.', 'email-verification-for-woocommerce' ), WC()->version );
                        echo '<div class="' . $class . '"> <p> ' . $message . ' </p></div>';
                }
        }

        public static function deactivate() {
                add_action( 'admin_notices', array( 'TK_EVF_WC', 'add_dectivate_message' ) );
                // clear schedule the purge_transients_cron
                wp_clear_scheduled_hook( 'purge_unvalidated_accounts_cron' );
        }

        public static function add_dectivate_message() {
                global $pagenow;
                if ( $pagenow == 'plugins.php' ) {
                        $class   = "success";
                        $message = __( 'The Email Verification for Woocommerce plugin is now <strong>deactivated</strong>.<br /><br />Some database tables and settings are NOT removed yet, those will be removed on uninstalling the plugin.', 'email-verification-for-woocommerce' );
                        echo '<div class="' . $class . '"> <p> ' . $message . ' </p></div>';
                }
        }

        public function purge_unvalidated_accounts() {
                global $wpdb;
                $table      = self::get_table_name();
                $older_than = 30; //in days
                $sSql       = $wpdb->prepare( "DELETE FROM `{$table}` WHERE `date_registered` < NOW() - INTERVAL %d DAY", $older_than );
                $wpdb->query( $sSql );
        }
}