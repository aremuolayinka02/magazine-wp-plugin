<?php
if (!defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($action === 'edit' || $action === 'add') {
    include_once plugin_dir_path(__FILE__) . 'shortcode-form.php';
    return;
}

// Show success message
if (isset($_GET['message']) && $_GET['message'] === 'success') {
    echo '<div class="notice notice-success"><p>Shortcode saved successfully!</p></div>';
}

// Get all shortcodes
global $wpdb;
$shortcodes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rsa_magazine_shortcodes ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Magazine Shortcodes</h1>
    <a href="?page=rsa-magazines&tab=shortcodes&action=add" class="page-title-action">Add New</a>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>Type</th>
                <th>Display</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($shortcodes): ?>
                <?php foreach ($shortcodes as $sc): ?>
                    <tr>
                        <td><code>[rsa name="<?php echo esc_html($sc->name); ?>"]</code></td>
                        <td><?php echo esc_html($sc->list_type); ?></td>
                        <td><?php echo esc_html($sc->display_type); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=rsa-magazines&tab=shortcodes&action=edit&id=' . $sc->id)); ?>" 
                               class="button button-small">Edit</a>
                            <button type="button" class="button button-small button-link-delete" 
                                    onclick="deleteShortcode(<?php echo $sc->id; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No shortcodes found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function deleteShortcode(id) {
    if (!confirm('Are you sure you want to delete this shortcode?')) return;
    
    jQuery.post(ajaxurl, {
        action: 'delete_magazine_shortcode',
        shortcode_id: id,
        nonce: '<?php echo wp_create_nonce("delete_shortcode"); ?>'
    }).done(function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('Error deleting shortcode');
        }
    });
}
</script>
