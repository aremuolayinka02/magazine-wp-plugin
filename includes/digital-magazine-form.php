<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'rsa_digital_magazines';
$magazine = null;

// Get magazine for editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $magazine_id = intval($_GET['id']);
    $magazine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $magazine_id));
}

// Handle form submission
if (isset($_POST['submit']) && isset($_POST['magazine_type']) && $_POST['magazine_type'] === 'digital') {
    check_admin_referer('save_digital_magazine', 'magazine_nonce');
    
    // Debug output
    error_log('Digital Magazine Form Submission: ' . print_r($_POST, true));
    
    $data = array(
        'title' => sanitize_text_field($_POST['title']),
        'description' => wp_kses_post($_POST['description']),
        'featured_image' => sanitize_text_field($_POST['featured_image']),
        'pdf_file' => sanitize_text_field($_POST['pdf_file']),
        'issue_number' => sanitize_text_field($_POST['issue_number']),
        'status' => 'active'
    );
    
    // Debug output
    error_log('Prepared Data: ' . print_r($data, true));
    
    if (isset($_POST['magazine_id'])) {
        // Update existing magazine
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => intval($_POST['magazine_id'])],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        $message = 'updated';
    } else {
        // Insert new magazine
        $result = $wpdb->insert($table_name, $data, ['%s', '%s', '%s', '%s', '%s', '%s']);
        $message = 'created';
    }
    
    // Debug output
    error_log('Database Operation Result: ' . print_r($result, true));
    error_log('Last Database Error: ' . $wpdb->last_error);
    
    if ($result === false) {
        // Handle error
        wp_die('Database error: ' . $wpdb->last_error);
    }
    
    if ($result !== false) {
        // Remove cache-related code, just redirect
        wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=digital&message=' . $message));
        exit;
    }
    
    wp_redirect(admin_url('admin.php?page=rsa-magazines&tab=digital&message=' . $message));
    exit;
}

$form_title = $magazine ? 'Edit Digital Magazine' : 'Add New Digital Magazine';
?>

<div class="wrap">
    <h2><?php echo esc_html($form_title); ?></h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('save_digital_magazine', 'magazine_nonce'); ?>
        <input type="hidden" name="magazine_type" value="digital">
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
                    <div id="image_preview">
                        <?php if ($magazine && $magazine->featured_image): ?>
                            <img src="<?php echo esc_url($magazine->featured_image); ?>" style="max-width:200px;"/>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="pdf_file">PDF Magazine</label></th>
                <td>
                    <input type="hidden" name="pdf_file" id="pdf_file_id" value="<?php echo $magazine ? esc_attr($magazine->pdf_file) : ''; ?>">
                    <button type="button" class="button" id="upload_pdf_button">Upload PDF</button>
                    <div id="pdf_preview">
                        <?php if ($magazine && $magazine->pdf_file): ?>
                            <p>Current PDF: <a href="<?php echo esc_url($magazine->pdf_file); ?>" target="_blank"><?php echo basename($magazine->pdf_file); ?></a></p>
                        <?php endif; ?>
                    </div>
                    <p class="description">Upload or select the PDF version of the magazine</p>
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
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $magazine ? 'Update Digital Magazine' : 'Save Digital Magazine'; ?>">
            <a href="?page=rsa-magazines&tab=digital" class="button">Cancel</a>
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
    
    $('#upload_pdf_button').click(function(e) {
        e.preventDefault();
        var pdf_uploader = wp.media({
            title: 'Select Digital Magazine File',
            button: {
                text: 'Use this file'
            },
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_pdf = pdf_uploader.state().get('selection').first();
            var pdf_url = uploaded_pdf.toJSON().url;
            var pdf_filename = uploaded_pdf.toJSON().filename;
            $('#pdf_file_id').val(pdf_url);
            $('#pdf_preview').html('<p>Selected file: <a href="' + pdf_url + '" target="_blank">' + pdf_filename + '</a></p>');
        });
    });
});
</script>
