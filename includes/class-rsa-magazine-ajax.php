<?php
class RSA_Magazine_Ajax {
    public static function init() {
        add_action('wp_ajax_save_magazine_shortcode', array(__CLASS__, 'save_shortcode'));
        add_action('wp_ajax_delete_magazine_shortcode', array(__CLASS__, 'delete_shortcode'));
    }

    public static function save_shortcode() {
        check_ajax_referer('save_shortcode', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'rsa_magazine_shortcodes';
        
        // Get submitted data
        $shortcode_id = isset($_POST['shortcode_id']) ? intval($_POST['shortcode_id']) : 0;
        $shortcode_name = sanitize_text_field($_POST['shortcode_name']);
        $css_content = wp_kses_post($_POST['css']);
        
        // Prepare data for insert/update with explicit container_class
        $data = array(
            'name' => $shortcode_name,
            'list_type' => sanitize_text_field($_POST['list_type']),
            'template' => wp_kses_post($_POST['template']),
            'css' => $css_content,
            'container_class' => sanitize_text_field($_POST['container_class']), // Ensure this is included
            'display_type' => sanitize_text_field($_POST['display_type'])
        );

        // Check for featured shortcode limit
        if ($_POST['list_type'] === 'featured') {
            $existing_featured = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE list_type = 'featured' AND id != %d",
                $shortcode_id
            ));
            
            if ($existing_featured > 0) {
                wp_send_json_error(array(
                    'message' => 'Only one featured digital magazine shortcode can be created.'
                ));
                return;
            }
        }

        // Check for duplicate name, excluding current shortcode if editing
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE name = %s AND id != %d",
            $shortcode_name,
            $shortcode_id
        ));
        
        if ($existing > 0) {
            wp_send_json_error(array(
                'message' => 'A shortcode with this name already exists. Please choose a different name.'
            ));
            return;
        }
        
        // Update or insert
        if ($shortcode_id > 0) {
            $result = $wpdb->update($table, $data, ['id' => $shortcode_id]);
        } else {
            $result = $wpdb->insert($table, $data);
        }

        if ($result === false) {
            wp_send_json_error(array('message' => $wpdb->last_error));
        } else {
            wp_send_json_success();
        }
    }

    public static function delete_shortcode() {
        check_ajax_referer('delete_shortcode', 'nonce');
        
        if (!isset($_POST['shortcode_id'])) {
            wp_send_json_error(array('message' => 'Shortcode ID is required'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rsa_magazine_shortcodes';
        $result = $wpdb->delete(
            $table,
            ['id' => intval($_POST['shortcode_id'])],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error(array('message' => $wpdb->last_error));
        } else {
            RSA_Magazine_Cache::get_instance()->clear_cache();
            wp_send_json_success();
        }
    }
}

RSA_Magazine_Ajax::init();
