<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'rsa_hardcopy_magazines';
$magazines = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

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
    
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=hardcopy&message=updated'));
    exit;
}

// Handle magazine deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['magazine_id'])) {
    check_admin_referer('delete_magazine_' . $_POST['magazine_id']);
    $magazine_id = intval($_POST['magazine_id']);
    
    $wpdb->delete($table_name, ['id' => $magazine_id], ['%d']);
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=hardcopy&message=deleted'));
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
    }
}
?>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Title</th>
            <th>Issue Number</th>
            <th>Price</th>
            <th>Status</th>
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
                    <td><?php echo esc_html($magazine->price); ?></td>
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
                    <td><?php echo date('Y-m-d', strtotime($magazine->created_at)); ?></td>
                    <td>
                        <a href="?page=rsa-magazines&tab=hardcopy&action=edit&id=<?php echo $magazine->id; ?>" class="button button-small">Edit</a>
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
                <td colspan="6">No hard copy magazines found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
