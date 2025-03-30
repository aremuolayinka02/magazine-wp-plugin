<?php
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
        register_rest_route('rsa-magazines/v1', '/magazines/(?P<type>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_magazines'),
            'permission_callback' => '__return_true',
            'args' => array(
                'type' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        register_rest_route('rsa-magazines/v1', '/shortcode/(?P<name>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_shortcode_data'),
            'permission_callback' => '__return_true',
            'args' => array(
                'name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Add new route for styles
        register_rest_route('rsa-magazines/v1', '/styles', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_styles'),
            'permission_callback' => '__return_true'
        ));
    }

    public function get_magazines($request) {
        global $wpdb;
        $type = $request->get_param('type');
        
        $wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        
        $magazines = [];
        
        switch ($type) {
            case 'digital':
            case 'hardcopy':
                $table = $type === 'digital' ? 'rsa_digital_magazines' : 'rsa_hardcopy_magazines';
                $magazines = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}{$table} WHERE status = %s ORDER BY created_at DESC",
                    'active'
                ));
                break;
                
            case 'featured':
                $featured_id = get_option('rsa_magazines_featured_magazine');
                if ($featured_id) {
                    $magazine = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines WHERE id = %d AND status = %s",
                        $featured_id,
                        'active'
                    ));
                    if ($magazine) {
                        $magazines = [$magazine];
                    }
                }
                break;
        }

        return new WP_REST_Response([
            'magazines' => $magazines,
            'timestamp' => time()
        ], 200);
    }

    public function get_shortcode_data($request) {
    global $wpdb;
    
    // Force no caching
    $wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    
    $name = $request->get_param('name');
    
    $shortcode = $wpdb->get_row($wpdb->prepare(
        "SELECT SQL_NO_CACHE * FROM {$wpdb->prefix}rsa_magazine_shortcodes WHERE name = %s",
        $name
    ));

    if (!$shortcode) {
        return new WP_REST_Response([
            'error' => 'Shortcode not found: ' . $name
        ], 404);
    }

    // Add viewer page ID to the response
    $viewer_page_id = get_option('rsa_magazines_viewer_page_id', '');

    return new WP_REST_Response([
        'shortcode' => $shortcode,
        'viewerPageId' => $viewer_page_id,
        'timestamp' => time()
    ], 200);
}

    public function get_styles() {
        // Force fresh data from database
        global $wpdb;
        $wpdb->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
        
        $timestamp = get_option('rsa_magazines_settings_timestamp', time(), true);
        
        $response = new WP_REST_Response([
            'grid_style' => get_option('rsa_magazines_grid_style', '', true),
            'scroll_style' => get_option('rsa_magazines_scroll_style', '', true),
            'redirect_url' => get_option('rsa_magazines_redirect_url', '', true),
            'timestamp' => $timestamp
        ], 200);
        
        // Add cache control headers
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        
        return $response;
    }
}
