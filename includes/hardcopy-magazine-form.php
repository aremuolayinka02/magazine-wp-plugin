<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'rsa_hardcopy_magazines';
$magazine = null;

// Get magazine for editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $magazine_id = intval($_GET['id']);
    $magazine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $magazine_id));
}

// Handle form submission
if (isset($_POST['submit']) && isset($_POST['magazine_type']) && $_POST['magazine_type'] === 'hardcopy') {
    check_admin_referer('save_hardcopy_magazine', 'magazine_nonce');
    
    $data = array(
        'title' => sanitize_text_field($_POST['title']),
        'description' => wp_kses_post($_POST['description']),
        'featured_image' => sanitize_text_field($_POST['featured_image']),
        'issue_number' => sanitize_text_field($_POST['issue_number']),
        'payment_page_id' => intval($_POST['payment_page']),
        'price' => floatval($_POST['price']),
        'status' => 'active'
    );
    
    if (isset($_POST['magazine_id'])) {
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => intval($_POST['magazine_id'])],
            array_fill(0, count($data), '%s'),
            ['%d']
        );
        $message = 'updated';
    } else {
        $result = $wpdb->insert($table_name, $data);
        $message = 'created';
    }
    
    if ($result === false) {
        wp_die('Database error: ' . $wpdb->last_error);
    }

    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=hardcopy&message=' . $message));
    exit;
}

$form_title = $magazine ? 'Edit Hard Copy Magazine' : 'Add New Hard Copy Magazine';
?>

<div class="wrap">
    <h2><?php echo $form_title; ?></h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('save_hardcopy_magazine', 'magazine_nonce'); ?>
        <input type="hidden" name="magazine_type" value="hardcopy">
        <?php if ($magazine): ?>
            <input type="hidden" name="magazine_id" value="<?php echo $magazine->id; ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="title">Magazine Title</label></th>
                <td><input name="title" type="text" id="title" class="regular-text" required value="<?php echo $magazine ? esc_attr($magazine->title) : ''; ?>"></td>
            </tr>
            
            <tr>
                <th scope="row"><label for="featured_image">Featured Image</label></th>
                <td>
                    <input type="hidden" name="featured_image" id="featured_image_id" value="<?php echo $magazine ? esc_attr($magazine->featured_image) : ''; ?>">
                    <button type="button" class="button" id="upload_image_button">Upload Image</button>
                    <div id="image_preview"><?php if ($magazine && $magazine->featured_image): ?><img src="<?php echo esc_url($magazine->featured_image); ?>" style="max-width:200px;"><?php endif; ?></div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="description">Description</label></th>
                <td><?php wp_editor($magazine ? $magazine->description : '', 'description', array('textarea_rows' => 10)); ?></td>
            </tr>
            
            <tr>
                <th scope="row"><label for="issue_number">Issue Number/Year</label></th>
                <td><input name="issue_number" type="text" id="issue_number" class="regular-text" value="<?php echo $magazine ? esc_attr($magazine->issue_number) : ''; ?>"></td>
            </tr>
            
            <tr>
                <th scope="row"><label for="price">Price</label></th>
                <td>
                    <input name="price" type="number" id="price" class="regular-text" step="0.01" min="0" required value="<?php echo $magazine ? esc_attr($magazine->price) : ''; ?>">
                    <p class="description">Enter the price in your currency</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="payment_page">Order Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'payment_page',
                        'show_option_none' => 'Select a page',
                        'option_none_value' => '',
                        'post_status' => 'publish',
                        'selected' => $magazine ? $magazine->payment_page_id : ''
                    ));
                    ?>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Hard Copy Magazine">
            <a href="?page=rsa-magazines&tab=hardcopy" class="button">Cancel</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#upload_image_button').click(function(e) {
        e.preventDefault();
        var image = wp.media({ 
            title: 'Upload Magazine Image',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().url;
            $('#featured_image_id').val(image_url);
            $('#image_preview').html('<img src="' + image_url + '" style="max-width:200px;"/>');
        });
    });
});
</script>
