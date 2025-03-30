<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add CodeMirror
wp_enqueue_style('wp-codemirror');
wp_enqueue_script('wp-codemirror');
wp_enqueue_script('csslint');
wp_enqueue_style('wp-theme-plugin-editor');

// Get current custom CSS
$custom_css = get_option('rsa_magazines_custom_css', '');

if (isset($_POST['save_custom_css'])) {
    check_admin_referer('save_custom_css', 'css_nonce');
    
    // Update custom CSS with no caching
    update_option('rsa_magazines_custom_css', wp_kses_post($_POST['custom_css']), false);
    
    // Clear caches
    wp_cache_delete('rsa_magazines_custom_css', 'options');
    delete_transient('rsa_magazines_styles');
    
    echo '<div class="notice notice-success"><p>Custom CSS saved successfully!</p></div>';
}
?>

<div class="wrap">
    <h2>Custom CSS</h2>
    <p>Add custom CSS that will be applied to all magazine shortcodes.</p>

    <form method="post" action="">
        <?php wp_nonce_field('save_custom_css', 'css_nonce'); ?>
        
        <div class="css-editor-section">
            <textarea id="custom_css" name="custom_css" rows="20" style="width: 100%;"><?php 
                echo esc_textarea($custom_css); 
            ?></textarea>
        </div>

        <p class="submit">
            <input type="submit" name="save_custom_css" class="button button-primary" value="Save Custom CSS">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var cssEditor = wp.CodeMirror.fromTextArea(document.getElementById('custom_css'), {
        mode: 'css',
        lineNumbers: true,
        lineWrapping: true,
        viewportMargin: Infinity
    });

    setTimeout(function() {
        cssEditor.refresh();
    }, 100);
});
</script>
