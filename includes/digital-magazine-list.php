<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'rsa_digital_magazines';

// Debug output
error_log('Fetching digital magazines from table: ' . $table_name);

$magazines = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

// Debug output
error_log('Found magazines: ' . print_r($magazines, true));
error_log('Last Database Error: ' . $wpdb->last_error);

// Handle status toggle
if (isset($_POST['toggle_status']) && isset($_POST['magazine_id'])) {
    check_admin_referer('toggle_status');
    $magazine_id = intval($_POST['magazine_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    
    $wpdb->update(
        $table_name,
        ['status' => $new_status],
        ['id' => $magazine_id],
        ['%s'],
        ['%d']
    );
    
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=digital&message=updated'));
    exit;
}

// Add featured toggle handler
if (isset($_POST['toggle_featured']) && isset($_POST['magazine_id'])) {
    check_admin_referer('toggle_featured');
    $magazine_id = intval($_POST['magazine_id']);
    
    // Clear old featured magazine
    update_option('rsa_magazines_featured_magazine', $magazine_id);
    
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=digital&message=featured'));
    exit;
}

// Handle magazine deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['magazine_id'])) {
    check_admin_referer('delete_magazine_' . $_POST['magazine_id']);
    $magazine_id = intval($_POST['magazine_id']);
    
    $wpdb->delete($table_name, ['id' => $magazine_id], ['%d']);
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=digital&message=deleted'));
    exit;
}

if (isset($_GET['message'])) {
    $message = sanitize_text_field($_GET['message']);
    if ($message === 'created') {
        echo '<div class="notice notice-success"><p>Magazine created successfully!</p></div>';
    } elseif ($message === 'updated') {
        echo '<div class="notice notice-success"><p>Magazine updated successfully!</p></div>';
    } elseif ($message === 'deleted') {
        echo '<div class="notice notice-success"><p>Magazine deleted successfully!</p></div>';
    } elseif ($message === 'featured') {
        echo '<div class="notice notice-success"><p>Magazine featured successfully!</p></div>';
    }
}

// Add this to your digital-magazine-list.php file or wherever the magazine links are generated
function rsa_get_magazine_viewer_url($magazine_id) {
    $viewer_page_id = get_option('rsa_magazines_viewer_page_id', '');
    if (!$viewer_page_id) {
        return '#';
    }
    
    return add_query_arg('id', $magazine_id, get_permalink($viewer_page_id));
}
?>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Title</th>
            <th>Issue Number</th>
            <th>Status</th>
            <th>Featured</th> <!-- New column -->
            <th>Publication Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($magazines): ?>
            <?php foreach ($magazines as $magazine): ?>
                <tr>
                    <td><?php echo esc_html($magazine->title); ?></td>
                    <td><?php echo esc_html($magazine->issue_number); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('toggle_status'); ?>
                            <input type="hidden" name="magazine_id" value="<?php echo $magazine->id; ?>">
                            <select name="new_status" onchange="this.form.submit()">
                                <option value="active" <?php selected($magazine->status, 'active'); ?>>Active</option>
                                <option value="on_hold" <?php selected($magazine->status, 'on_hold'); ?>>On Hold</option>
                                <option value="sold_out" <?php selected($magazine->status, 'sold_out'); ?>>Sold Out</option>
                            </select>
                            <input type="hidden" name="toggle_status" value="1">
                        </form>
                    </td>
                    <td>
                        <?php $featured_id = get_option('rsa_magazines_featured_magazine'); ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('toggle_featured'); ?>
                            <input type="hidden" name="magazine_id" value="<?php echo $magazine->id; ?>">
                            <input type="checkbox" name="toggle_featured" 
                                   onchange="this.form.submit()" 
                                   <?php checked($featured_id, $magazine->id); ?>>
                        </form>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($magazine->created_at)); ?></td>
                    <td>
                        <a href="?page=rsa-magazines&tab=digital&action=edit&id=<?php echo $magazine->id; ?>" class="button button-small">Edit</a>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('delete_magazine_' . $magazine->id); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="magazine_id" value="<?php echo $magazine->id; ?>">
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this magazine?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No digital magazines found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
