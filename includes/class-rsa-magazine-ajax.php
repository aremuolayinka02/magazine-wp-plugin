<?php
if (!defined('ABSPATH')) {
    exit;
}

class RSA_Magazine_AJAX {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('wp_ajax_rsa_get_magazine_pdf', array($this, 'get_magazine_pdf'));
        add_action('wp_ajax_nopriv_rsa_get_magazine_pdf', array($this, 'handle_not_logged_in'));
    }

    public function get_magazine_pdf() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rsa_magazine_view_pdf')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            $redirect_url = get_option('rsa_magazines_login_redirect', wp_login_url());
            wp_send_json_error(array(
                'message' => 'You must be logged in to view this content',
                'redirect' => $redirect_url
            ));
            return;
        }

        // Get magazine ID
        $magazine_id = isset($_POST['magazine_id']) ? intval($_POST['magazine_id']) : 0;
        if (empty($magazine_id)) {
            wp_send_json_error(array('message' => 'Invalid magazine ID'));
            return;
        }

        // Get magazine details
        global $wpdb;
        $table_name = $wpdb->prefix . 'rsa_digital_magazines';
        $magazine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $magazine_id));

        if (!$magazine) {
            wp_send_json_error(array('message' => 'Magazine not found'));
            return;
        }

        // Return PDF URL
        wp_send_json_success(array('pdf_url' => $magazine->pdf_file));
    }

    public function handle_not_logged_in() {
        $redirect_url = get_option('rsa_magazines_login_redirect', wp_login_url());
        wp_send_json_error(array(
            'message' => 'You must be logged in to view this content',
            'redirect' => $redirect_url
        ));
    }
}