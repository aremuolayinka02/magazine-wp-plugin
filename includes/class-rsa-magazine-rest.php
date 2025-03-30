<?php
if (!defined('ABSPATH')) {
    exit;
}

class RSA_Magazine_REST {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('rsa-magazines/v1', '/shortcode/(?P<name>[a-zA-Z0-9-_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_shortcode'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('rsa-magazines/v1', '/magazines/digital', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_digital_magazines'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('rsa-magazines/v1', '/magazines/hardcopy', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_hardcopy_magazines'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('rsa-magazines/v1', '/magazines/featured', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_featured_magazine'),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_shortcode($request) {
        $name = $request->get_param('name');
        
        global $wpdb;
        $shortcode = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rsa_magazine_shortcodes WHERE name = %s",
            $name
        ));
        
        if (!$shortcode) {
            return new WP_Error('shortcode_not_found', 'Shortcode not found', array('status' => 404));
        }
        
        // Get viewer page ID from settings
        $viewer_page_id = get_option('rsa_magazines_viewer_page_id', '');
        
        return array(
            'shortcode' => $shortcode,
            'viewerPageId' => $viewer_page_id
        );
    }

    public function get_digital_magazines() {
        global $wpdb;
        $magazines = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines WHERE status = 'active' ORDER BY created_at DESC"
        );
        
        return array('magazines' => $magazines);
    }

    public function get_hardcopy_magazines() {
        global $wpdb;
        $magazines = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rsa_hardcopy_magazines WHERE status = 'active' ORDER BY created_at DESC"
        );
        
        return array('magazines' => $magazines);
    }

    public function get_featured_magazine() {
        $featured_id = get_option('rsa_magazines_featured_magazine');
        
        if (!$featured_id) {
            return array('magazines' => array());
        }
        
        global $wpdb;
        $magazine = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines WHERE id = %d AND status = 'active'",
            $featured_id
        ));
        
        if (!$magazine) {
            return array('magazines' => array());
        }
        
        return array('magazines' => array($magazine));
    }
}