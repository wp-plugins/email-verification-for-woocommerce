<?php

/**
 * @version           3.2
 * @package           email verification for woocommerce
 * @author            Tonny Keuken (tonny.keuken@tidl.nl)
 */
class TK_EVF_WC {
	
	protected $meta_key = 'confirm_code';

    public function __construct() {
        	add_action( 'parse_request', array($this, 'maybe_process_shortcode') );
            add_shortcode( 'email-verification-for-woocommerce', array( $this, 'add_shortcode' ) );
            add_action( 'user_register', array( $this, 'set_user_as_pending' ) );
            add_action( 'woocommerce_checkout_init', array( $this, 'before_checkout_process' ) );
            add_action( 'init', array( $this, 'load_textdomain' ) );
            // add scheduler action
            add_action( 'purge_unvalidated_accounts_cron', array( $this, 'purge_unvalidated_accounts' ) );
            add_filter( 'wp', array( $this, 'add_text_to_my_account' ) );
            add_action( 'woocommerce_checkout_order_processed', array($this, 'set_user_as_active_from_order') );
            add_action( 'authenticate', array($this, 'block_login'), 99 );
    }
    
    public function block_login( $user ) {
	    
	    if( $user instanceof WP_User ) {
		    
		    if( get_user_meta( $user->ID, $this->meta_key, true ) ) {
	            
	            return new WP_Error(
		            'unverified',
					__( '<strong>ERROR</strong>: You have not verified your email address. Please follow the instructions in the email to activate your account.' )
	            );
	            
	        }
		    
	    }
	    
	    return $user;
        
    }

    public function before_checkout_process() {
	    
        $guest_checkout = get_option( 'woocommerce_enable_signup_and_login_from_checkout' );
        
        if ( ! is_user_logged_in() && $guest_checkout === 'no' ) {
	        
            $page_id = wc_get_page_id( 'myaccount' );
            
            wp_redirect( home_url() . '?page_id=' . $page_id );
            
            exit;
                
        } else {
            
            return;
            
        }
        
    }

    public function add_text_to_my_account() { 

        if ( ! is_user_logged_in() && is_page(wc_get_page_id( 'myaccount' )) && ! empty( $_COOKIE['email_send_for_activation'] ) && $_COOKIE['email_send_for_activation'] === 'done' ) {
	        
            wc_add_notice( __( 'A confirmation link has been sent to your email address. Please follow the instructions in the email to activate your account.', 'email-verification-for-woocommerce', 'success' ) );
            
            setcookie("email_send_for_activation", "", time()-3600, '/');
                
        }
        
        return;
    }
    
    public function load_textdomain() {
	    
        load_plugin_textdomain( 'email-verification-for-woocommerce', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/language/' );
        
    }
    
    public function maybe_process_shortcode($query) {
        
        if( ! empty( $query->query_vars['pagename'] ) && $query->query_vars['pagename'] == 'account-validation' ) {
	        
	        $this->add_shortcode();
	        
        }
        
    }

    public function add_shortcode() {
	    
	    if( is_admin() ) {
		    return;
	    }
    
        global $wpdb;
        
        $hash = htmlspecialchars( filter_input( INPUT_GET, 'passkey', FILTER_SANITIZE_STRING ) );
        
        $did  = count( $hash === 64 ) ? $hash : false;
        
        if ( $did ) {
            
            $user_id   = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM `{$wpdb->usermeta}` WHERE meta_key = %s AND meta_value = '%s'", $this->meta_key, $did ) );
            
            if ( $user_id ) {
                
                //remove filter
                remove_filter( 'user_register', array( $this, 'set_user_as_pending' ) );

                $this->set_user_as_active($user_id);

                wc_set_customer_auth_cookie( $user_id );
                
            	do_action( 'woocommerce_set_cart_cookies',  true );
            	
            	$cart_session = get_user_meta( $user_id, '_woocommerce_persistent_cart', true );	
            	
                if ( $cart_session ) {
                    
                    $page_id = wc_get_page_id( 'checkout' );
                    
                    wc_add_notice( __( 'Account activation successful. You can now continue.', 'email-verification-for-woocommerce' ), 'success' );
                        
                } else {
                    
                    $page_id = wc_get_page_id( 'myaccount' );
                    
                    wc_add_notice( __( 'Account activation successful.', 'email-verification-for-woocommerce' ), 'success' );
                }
                
                wp_redirect( apply_filters( 'woocommerce_email_verification_login_redirect', home_url() . '?page_id=' . $page_id, $user_id, $cart_session )  );
                
                exit;
                    
            } else {
	            
                wp_redirect( home_url() );
                
                exit;
                
            }
                
        } else {
            
            wp_redirect( home_url() );
            
            exit;
            
        }
        
    }

    public function set_user_as_pending( $user_id ) {
        
      if ( ! current_user_can( 'manage_options' ) ) {
	      
            if ( ! $user_id ) {
               return;
            }
            
            $data = get_userdata( $user_id );
            $to   = $data->user_email;
            $un   = $data->user_login;
            $hash = $this->generate_hash( $to, $un );
            
            update_user_meta( $user_id, $this->meta_key, $hash );
            
            $this->send_verification( $to, $un, $hash );
            
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

    public function set_user_as_active_from_order( $order_id ) {
	    
        global $wpdb;
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        $this->set_user_as_active( $user_id );
        
    }
    
    public function set_user_as_active( $user_id ) {
	    
       delete_user_meta( $user_id, $this->meta_key );
       
    }

    /* Verification Email */

    public function send_verification( $to, $un, $hash ) {
        
        $page_id                                 = get_option( 'woocommerce_account_validation_page_id' );
        $activation_post                         = get_post( $page_id );
        $activation_url                          = $activation_post->post_name;
        $blogname                                = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        $subject                                 = sprintf( __( 'Activate your %s account', 'email-verification-for-woocommerce' ), $blogname );
        
        ob_start();
        
        wc_get_template( 'emails/email-header.php', array( 'email_heading' => $subject ) );
        
        echo sprintf( __( '<p>Hello %s,</p>'
                        . '<p>To activate your account and access the feature you were trying to view, click on the link below:</p>'
                        . '<a href="%s" class="button">Activate account</a>'
                        . '<p>Thank you for registering with us.</p>'
                        . '<p>Thanks, %s</p>', 'email-verification-for-woocommerce' ), $un, home_url( '/' ) . $activation_url . '?passkey=' . $hash, $blogname );
                        
        wc_get_template( 'emails/email-footer.php', array( 'site_title' => esc_html( get_bloginfo( 'name', 'display' ) ) ) );
                        
		      $message = ob_get_contents();
		
		      ob_end_clean();
                        
        wc_mail( $to, $subject, apply_filters( 'woocommerce_mail_content', $message ) );
        
        setcookie( 'email_send_for_activation', 'done', time()+20, '/' );
        
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
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->users}` u JOIN `{$wpdb->usermeta}` um ON um.user_id = u.ID AND um.meta_key = %s WHERE `u.user_registered` < NOW() - INTERVAL %d DAY", $this->meta_key, 30 ) );
        
    }
}