<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add CodeMirror
wp_enqueue_style('wp-codemirror');
wp_enqueue_script('wp-codemirror');
wp_enqueue_script('csslint');
wp_enqueue_style('wp-theme-plugin-editor');

// Get current styles
$grid_style = get_option('rsa_magazines_grid_style', '');
$scroll_style = get_option('rsa_magazines_scroll_style', '');

if (isset($_POST['save_styles'])) {
    check_admin_referer('save_magazine_styles', 'style_nonce');
    
    // Use SQL_NO_CACHE for immediate update
    global $wpdb;
    $wpdb->query("SELECT SQL_NO_CACHE 1");
    
    update_option('rsa_magazines_grid_style', wp_kses_post($_POST['grid_style']), false);
    update_option('rsa_magazines_scroll_style', wp_kses_post($_POST['scroll_style']), false);
    
    // Clear any cached styles
    wp_cache_delete('rsa_magazines_styles', 'options');
    
    echo '<div class="notice notice-success"><p>Styles saved successfully!</p></div>';
}
?>

<div class="wrap">
    <h2>Advanced Styling</h2>
    <p>Override the default styles for magazine layouts. These styles will be applied globally to all shortcodes.</p>

    <form method="post" action="">
        <?php wp_nonce_field('save_magazine_styles', 'style_nonce'); ?>

        <div class="style-editor-section">
            <h3>Grid Layout Style</h3>
            <p>Override the default grid layout styles. Base class: <code>.magazine-grid</code></p>
            <div class="code-editor-container">
                <textarea id="grid_style" name="grid_style" rows="10" style="width: 100%;"><?php 
                    echo esc_textarea($grid_style ?: '.magazine-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px;
}'); 
                ?></textarea>
            </div>
        </div>

        <div class="style-editor-section">
            <h3>Horizontal Scroll Style</h3>
            <p>Override the default scroll layout styles. Base class: <code>.magazine-scroll</code></p>
            <div class="code-editor-container">
                <textarea id="scroll_style" name="scroll_style" rows="10" style="width: 100%;"><?php 
                    echo esc_textarea($scroll_style ?: '.magazine-scroll {
    display: flex;
    overflow-x: auto;
    gap: 20px;
    padding: 20px;
    scroll-snap-type: x mandatory;
}

.magazine-scroll > * {
    flex: 0 0 300px;
    scroll-snap-align: start;
}'); 
                ?></textarea>
            </div>
        </div>

        <div class="preview-section">
            <h3>Live Preview</h3>
            <div class="preview-controls">
                <label>
                    <input type="radio" name="preview_type" value="grid" checked> Grid Layout
                </label>
                <label>
                    <input type="radio" name="preview_type" value="scroll"> Scroll Layout
                </label>
                <input type="number" id="preview_count" min="1" max="12" value="6" style="width: 60px;">
            </div>
            <div id="style_preview" class="preview-area"></div>
        </div>

        <p class="submit">
            <input type="submit" name="save_styles" class="button button-primary" value="Save Styles">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var gridEditor = wp.CodeMirror.fromTextArea(document.getElementById('grid_style'), {
        mode: 'css',
        lineNumbers: true,
        lineWrapping: true,
        viewportMargin: Infinity
    });

    var scrollEditor = wp.CodeMirror.fromTextArea(document.getElementById('scroll_style'), {
        mode: 'css',
        lineNumbers: true,
        lineWrapping: true,
        viewportMargin: Infinity
    });

    // Force refresh
    setTimeout(function() {
        gridEditor.refresh();
        scrollEditor.refresh();
    }, 100);

    // Live preview handler
    function updatePreview() {
        var previewType = $('input[name="preview_type"]:checked').val();
        var count = $('#preview_count').val();
        var css = previewType === 'grid' ? gridEditor.getValue() : scrollEditor.getValue();
        
        // Update preview
        var preview = '<style>' + css + '</style>';
        preview += '<div class="magazine-' + previewType + '">';
        for (var i = 0; i < count; i++) {
            preview += '<div class="magazine-item">Sample Magazine ' + (i + 1) + '</div>';
        }
        preview += '</div>';
        
        $('#style_preview').html(preview);
    }

    // Attach event handlers
    gridEditor.on('change', updatePreview);
    scrollEditor.on('change', updatePreview);
    $('input[name="preview_type"]').on('change', updatePreview);
    $('#preview_count').on('input', updatePreview);

    // Initial preview
    updatePreview();
});
</script>

<style>
.preview-section {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
}
.preview-controls {
    margin-bottom: 15px;
}
.preview-controls label {
    margin-right: 15px;
}
.preview-area {
    border: 1px solid #eee;
    padding: 20px;
    min-height: 200px;
}
.magazine-item {
    background: #f5f5f5;
    padding: 15px;
    border: 1px solid #ddd;
    text-align: center;
}
</style>
