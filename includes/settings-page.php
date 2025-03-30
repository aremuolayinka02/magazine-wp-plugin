<?php
if (!defined('ABSPATH')) {
    exit;
}

function rsa_magazines_settings_page() {
    // Save settings if form is submitted
    if (isset($_POST['rsa_magazines_save_settings'])) {
        check_admin_referer('rsa_magazines_settings');
        
        // Save viewer page ID
        if (isset($_POST['rsa_magazines_viewer_page_id'])) {
            update_option('rsa_magazines_viewer_page_id', sanitize_text_field($_POST['rsa_magazines_viewer_page_id']));
        }
        
        // Save login redirect URL
        if (isset($_POST['rsa_magazines_login_redirect'])) {
            update_option('rsa_magazines_login_redirect', esc_url_raw($_POST['rsa_magazines_login_redirect']));
        }
        
        // Save featured magazine
        if (isset($_POST['rsa_magazines_featured_magazine'])) {
            update_option('rsa_magazines_featured_magazine', intval($_POST['rsa_magazines_featured_magazine']));
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $viewer_page_id = get_option('rsa_magazines_viewer_page_id', '');
    $login_redirect = get_option('rsa_magazines_login_redirect', wp_login_url());
    $featured_magazine = get_option('rsa_magazines_featured_magazine', '');
    
    // Get all pages for dropdown
    $pages = get_pages();
    
    // Get all digital magazines for featured dropdown
    global $wpdb;
    $digital_magazines = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}rsa_digital_magazines ORDER BY title ASC");
    
    ?>
    <div class="wrap">
        <h2>Magazine Settings</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('rsa_magazines_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="rsa_magazines_viewer_page_id">PDF Viewer Page</label></th>
                    <td>
                        <select name="rsa_magazines_viewer_page_id" id="rsa_magazines_viewer_page_id">
                            <option value="">Select a page</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo $page->ID; ?>" <?php selected($viewer_page_id, $page->ID); ?>>
                                    <?php echo $page->post_title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the page where you've added the [rsa_magazine_viewer] shortcode.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="rsa_magazines_login_redirect">Login Redirect URL</label></th>
                    <td>
                        <input type="url" name="rsa_magazines_login_redirect" id="rsa_magazines_login_redirect" 
                               value="<?php echo esc_url($login_redirect); ?>" class="regular-text">
                        <p class="description">URL to redirect non-logged in users when they try to access protected content.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="rsa_magazines_featured_magazine">Featured Magazine</label></th>
                    <td>
                        <select name="rsa_magazines_featured_magazine" id="rsa_magazines_featured_magazine">
                            <option value="">None</option>
                            <?php foreach ($digital_magazines as $magazine): ?>
                                <option value="<?php echo $magazine->id; ?>" <?php selected($featured_magazine, $magazine->id); ?>>
                                    <?php echo $magazine->title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select a magazine to feature on your site.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Database Maintenance</h3>
            <p>If you're experiencing database issues, you can repair the tables below.</p>
            
            <div class="rsa-magazines-db-repair">
                <button type="button" id="rsa-repair-tables" class="button">Repair Database Tables</button>
                <span class="spinner" style="float: none; visibility: hidden;"></span>
                <div id="rsa-repair-result"></div>
            </div>
            
            <p class="submit">
                <input type="submit" name="rsa_magazines_save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#rsa-repair-tables').on('click', function() {
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $result = $('#rsa-repair-result');
            
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $result.html('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rsa_repair_magazine_tables',
                    nonce: '<?php echo wp_create_nonce('rsa_repair_tables'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                }
            });
        });
    });
    </script>
    <?php
}

// Add AJAX handler for database repair
add_action('wp_ajax_rsa_repair_magazine_tables', 'rsa_repair_magazine_tables');
function rsa_repair_magazine_tables() {
    check_ajax_referer('rsa_repair_tables', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'rsa_digital_magazines',
        $wpdb->prefix . 'rsa_hardcopy_magazines',
        $wpdb->prefix . 'rsa_magazine_shortcodes'
    );
    
    $repaired = 0;
    $errors = array();
    
    foreach ($tables as $table) {
        $result = $wpdb->query("REPAIR TABLE $table");
        if ($result === false) {
            $errors[] = "Failed to repair table: $table";
        } else {
            $repaired++;
        }
    }
    
    if (empty($errors)) {
        wp_send_json_success(array('message' => "Successfully repaired $repaired tables."));
    } else {
        wp_send_json_error(array(
            'message' => "Repaired $repaired tables with errors: " . implode(', ', $errors)
        ));
    }
}