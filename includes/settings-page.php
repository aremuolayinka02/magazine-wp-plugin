<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get saved values
$allowed_formats = get_option('rsa_magazines_allowed_formats');
$featured_magazine = get_option('rsa_magazines_featured_magazine');
$redirect_url = get_option('rsa_magazines_redirect_url', '');
$viewer_page_id = get_option('rsa_magazines_viewer_page_id', '');

// Add repair tables handler
if (isset($_POST['repair_tables']) && check_admin_referer('repair_tables', 'repair_nonce')) {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Get existing data before repair
    $digital_magazines = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rsa_digital_magazines");
    
    // Create or repair tables without dropping
    $sql_digital = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rsa_digital_magazines (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NOT NULL,
        featured_image varchar(255),
        pdf_file varchar(255) NOT NULL,
        issue_number varchar(100),
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql_digital);

    // Restore data if table was empty
    if (empty($wpdb->get_results("SELECT * FROM {$wpdb->prefix}rsa_digital_magazines"))) {
        foreach ($digital_magazines as $magazine) {
            $wpdb->insert($wpdb->prefix . 'rsa_digital_magazines', (array)$magazine);
        }
    }

    echo '<div class="notice notice-success"><p>Database tables have been repaired successfully!</p></div>';
}

if (isset($_POST['save_settings'])) {
    check_admin_referer('save_rsa_settings', 'settings_nonce');
    
    global $wpdb;
    // Force no caching for this query
    $wpdb->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
    
    // Save formats
    $formats = array();
    foreach ($_POST['formats'] as $format => $label) {
        if (!empty($label)) {
            $formats[$format] = sanitize_text_field($label);
        }
    }
    update_option('rsa_magazines_allowed_formats', $formats);
    
    // Save featured magazine
    $featured_magazine_id = intval($_POST['featured_magazine']);
    if ($featured_magazine_id > 0) {
        $magazine = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines WHERE id = %d AND status = 'active'",
            $featured_magazine_id
        ));
        
        if ($magazine) {
            update_option('rsa_magazines_featured_magazine', $featured_magazine_id);
        }
    } else {
        update_option('rsa_magazines_featured_magazine', '');
    }

    // Save redirect URL with no cache and force DB update
    update_option('rsa_magazines_redirect_url', esc_url_raw($_POST['redirect_url']), false);
    
    // Save viewer page ID
    $viewer_page_id = isset($_POST['viewer_page_id']) ? intval($_POST['viewer_page_id']) : '';
    update_option('rsa_magazines_viewer_page_id', $viewer_page_id);
    
    // Clear all caches
    wp_cache_delete('rsa_magazines_redirect_url', 'options');
    wp_cache_delete('rsa_magazines_settings', 'options');
    delete_transient('rsa_magazines_settings');
    
    // Force database to flush changes
    $wpdb->flush();
    
    // Update REST API cache timestamp
    update_option('rsa_magazines_settings_timestamp', time(), false);
    
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

global $wpdb;
$magazines = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}rsa_digital_magazines WHERE status = 'active' ORDER BY created_at DESC");
?>

<div class="rsa-settings-container">
    <h2>Magazine Settings</h2>

    <form method="post" action="">
        <?php wp_nonce_field('save_rsa_settings', 'settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="featured_magazine">Featured Digital Magazine</label></th>
                <td>
                    <select name="featured_magazine" id="featured_magazine">
                        <option value="0">None</option>
                        <?php foreach ($magazines as $magazine): ?>
                            <option value="<?php echo esc_attr($magazine->id); ?>" <?php selected($featured_magazine, $magazine->id); ?>>
                                <?php echo esc_html($magazine->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Select one digital magazine to feature.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="viewer_page_id">Magazine Viewer Page</label></th>
                <td>
                    <select name="viewer_page_id" id="viewer_page_id">
                        <option value="">Select a page</option>
                        <?php
                        $pages = get_pages();
                        foreach ($pages as $page) {
                            echo '<option value="' . $page->ID . '" ' . selected($viewer_page_id, $page->ID, false) . '>' . $page->post_title . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">Select the page where you've added the [rsa_magazine_viewer] shortcode.</p>
                </td>
            </tr>
        </table>

        <h3>Digital Magazine Access</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="redirect_url">Redirect URL</label></th>
                <td>
                    <input type="text" name="redirect_url" id="redirect_url" class="regular-text" 
                           value="<?php echo esc_url($redirect_url); ?>">
                    <p class="description">Where to send non-logged in users when they try to access a digital magazine</p>
                </td>
            </tr>
        </table>

        <h3>Allowed Digital Magazine Formats</h3>
        <table class="form-table" id="formats-table">
            <tr>
                <th>Extension</th>
                <th>Label</th>
                <th></th>
            </tr>
            <?php foreach ($allowed_formats as $format => $label): ?>
            <tr>
                <td><input type="text" name="formats[<?php echo esc_attr($format); ?>]" value="<?php echo esc_attr($format); ?>" class="small-text"></td>
                <td><input type="text" name="formats_label[<?php echo esc_attr($format); ?>]" value="<?php echo esc_attr($label); ?>" class="regular-text"></td>
                <td><button type="button" class="button remove-format">Remove</button></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p><button type="button" class="button" id="add-format">Add Format</button></p>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button button-primary" value="Save Settings">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-format').click(function() {
        var row = $('<tr>' +
            '<td><input type="text" name="formats[]" class="small-text"></td>' +
            '<td><input type="text" name="formats_label[]" class="regular-text"></td>' +
            '<td><button type="button" class="button remove-format">Remove</button></td>' +
            '</tr>');
        $('#formats-table').append(row);
    });

    $(document).on('click', '.remove-format', function() {
        $(this).closest('tr').remove();
    });
});
</script>