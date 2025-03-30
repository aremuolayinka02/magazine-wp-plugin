<?php
if (!defined('ABSPATH')) {
    exit;
}

function rsa_render_magazine_shortcode($atts) {
    try {
        $atts = shortcode_atts(array(
            'name' => '',
            'container_class' => ''
        ), $atts);

        if (empty($atts['name'])) {
            return '<p>Error: Shortcode name is required</p>';
        }

        // Get user login status and redirect URL
        $is_user_logged_in = is_user_logged_in();
        $redirect_url = get_option('rsa_magazines_redirect_url', wp_login_url());
        
        // Add REST URL and nonce for security
        $rest_url = esc_url_raw(rest_url('rsa-magazines/v1/'));
        $nonce = wp_create_nonce('wp_rest');

        // Simple fallback styles in case JS fails
        $fallback_css = '<style>
            .magazine-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
            .magazine-scroll { display: flex; overflow-x: auto; gap: 20px; }
            .magazine-item { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .magazine-item img { max-width: 100%; height: auto; }
        </style>';

        // Enqueue the magazine loader script
        wp_enqueue_script(
            'rsa-magazine-loader',
            plugins_url('assets/js/magazine-loader.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        return $fallback_css . sprintf(
            '<div id="rsa-mag-%1$s" class="rsa-magazines-container %6$s" 
                  data-shortcode="%1$s" 
                  data-logged-in="%3$s" 
                  data-redirect="%4$s" 
                  data-rest-url="%2$s" 
                  data-nonce="%5$s">
                <div class="rsa-magazines-loading">Loading magazines...</div>
                <noscript>
                    <p>Please enable JavaScript to view the magazine content.</p>
                </noscript>
             </div>',
            esc_attr($atts['name']),
            $rest_url,
            $is_user_logged_in ? 'true' : 'false',
            esc_url($redirect_url),
            $nonce,
            esc_attr($atts['container_class'])
        );
    } catch (Exception $e) {
        // Log error but show graceful message to users
        error_log('RSA Magazine Error: ' . $e->getMessage());
        return '<div class="rsa-magazines-error">Unable to load magazines. Please try again later.</div>';
    }
}

// Add this to your existing shortcodes.php file
function rsa_magazine_viewer_shortcode($atts) {
    // Call the function from pdf-viewer-template.php
    return rsa_magazine_viewer_shortcode_function();
}
add_shortcode('rsa_magazine_viewer', 'rsa_magazine_viewer_shortcode');

// Add fallback styles globally
function rsa_magazines_add_fallback_styles() {
    if (!wp_style_is('rsa-magazines-fallback', 'enqueued')) {
        wp_enqueue_style(
            'rsa-magazines-fallback',
            plugins_url('assets/css/fallback.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'rsa_magazines_add_fallback_styles');