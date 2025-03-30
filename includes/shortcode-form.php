<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$shortcode = null;

// Get shortcode for editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $shortcode_id = intval($_GET['id']);
    $shortcode = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rsa_magazine_shortcodes WHERE id = %d",
        $shortcode_id
    ));
}

$page_title = $shortcode ? 'Edit Shortcode' : 'Create New Shortcode';

// Remove TinyMCE/CodeMirror and add Monaco
wp_enqueue_style('rsa-monaco-editor', 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/editor/editor.main.min.css');
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>

<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <form id="shortcode-form" method="post">
        <?php wp_nonce_field('save_shortcode', 'shortcode_nonce'); ?>
        <?php if ($shortcode): ?>
            <input type="hidden" name="shortcode_id" value="<?php echo esc_attr($shortcode->id); ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="shortcode_name">Shortcode Name</label></th>
                <td>
                    <input type="text" id="shortcode_name" name="shortcode_name" class="regular-text" 
                           value="<?php echo $shortcode ? esc_attr($shortcode->name) : ''; ?>" required>
                    <p class="description">Used as [rsa name="your-shortcode-name"]</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="list_type">List Type</label></th>
                <td>
                    <select id="list_type" name="list_type">
                        <option value="digital" <?php selected($shortcode ? $shortcode->list_type : '', 'digital'); ?>>Digital Magazines</option>
                        <option value="hardcopy" <?php selected($shortcode ? $shortcode->list_type : '', 'hardcopy'); ?>>Hard Copy Magazines</option>
                        <option value="featured" <?php selected($shortcode ? $shortcode->list_type : '', 'featured'); ?>>Featured Digital Magazine</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="display_type">Display Type</label></th>
                <td>
                    <select id="display_type" name="display_type" <?php echo ($shortcode && $shortcode->list_type === 'featured') ? 'disabled' : ''; ?>>
                        <option value="grid" <?php selected($shortcode ? $shortcode->display_type : '', 'grid'); ?>>Grid Layout</option>
                        <option value="scroll" <?php selected($shortcode ? $shortcode->display_type : '', 'scroll'); ?>>Horizontal Scroll</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="container_class">Container Class</label></th>
                <td>
                    <input type="text" id="container_class" name="container_class" class="regular-text"
                           value="<?php echo $shortcode ? esc_attr($shortcode->container_class) : ''; ?>">
                    <p class="description">Custom class for the container. Styles applied to this class will take precedence over default styles.</p>
                </td>
            </tr>
        </table>

        <h3>HTML Template</h3>
        <div class="template-editor">
            <div id="monaco-template" style="height: 300px; border: 1px solid #ddd;"></div>
            <input type="hidden" name="template" id="template">
        </div>

        <h3>CSS Style</h3>
        <div class="css-editor">
            <p class="description">Enter your CSS styles. Supports modern CSS features.</p>
            <div id="monaco-css" style="height: 400px; border: 1px solid #ddd;"></div>
            <input type="hidden" name="css" id="css">
        </div>

        <!-- Add Preview Section -->
        <div class="preview-section" style="margin-top: 20px;">
            <h3>Live Preview</h3>
            <div class="preview-wrapper">
                <div class="preview-controls" style="margin-bottom: 15px;">
                    <button type="button" class="button reload-preview">Refresh Preview</button>
                </div>
                <div id="preview_outer" class="preview-outer">
                    <div id="preview_container" class="preview-container">
                        <style id="preview_styles"></style>
                        <div id="preview_content"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($shortcode): ?>
            <!-- Load actual magazine data based on shortcode type -->
            <?php
            $preview_magazines = array();
            switch($shortcode->list_type) {
                case 'digital':
                    $preview_magazines = $wpdb->get_results(
                        "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines 
                        WHERE status = 'active' 
                        ORDER BY created_at DESC 
                        LIMIT 3"
                    );
                    break;
                case 'hardcopy':
                    $preview_magazines = $wpdb->get_results(
                        "SELECT * FROM {$wpdb->prefix}rsa_hardcopy_magazines 
                        WHERE status = 'active' 
                        ORDER BY created_at DESC 
                        LIMIT 3"
                    );
                    break;
                case 'featured':
                    $featured_id = get_option('rsa_magazines_featured_magazine');
                    if ($featured_id) {
                        $preview_magazines = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}rsa_digital_magazines 
                            WHERE id = %d AND status = 'active'",
                            $featured_id
                        ));
                    }
                    break;
            }
            ?>
            <script>
                var previewMagazines = <?php echo json_encode($preview_magazines); ?>;
                var isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
                var redirectUrl = "<?php echo esc_url(get_option('rsa_magazines_redirect_url', wp_login_url())); ?>";
            </script>
        <?php endif; ?>
        
        <p class="submit">
            <input type="submit" name="save_shortcode" class="button button-primary" value="<?php echo $shortcode ? 'Update Shortcode' : 'Create Shortcode'; ?>">
            <a href="?page=rsa-magazines&tab=shortcodes" class="button">Cancel</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var templateEditor, cssEditor;
    
    // Initialize Monaco Editor
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' }});
    require(['vs/editor/editor.main'], function() {
        // Initialize Template Editor
        templateEditor = monaco.editor.create(document.getElementById('monaco-template'), {
            value: <?php echo json_encode($shortcode ? $shortcode->template : '<div class="magazine-item">
    <img src="{featured_image}" alt="{title}">
    <h2>{title}</h2>
    <div class="description">{description}</div>
    <div class="issue">Issue: {issue_number}</div>
    <a href="{pdf_file}" class="read-button">Read Magazine</a>
</div>'); ?>,
            language: 'html',
            theme: 'vs-light',
            minimap: { enabled: false },
            lineNumbers: 'on',
            roundedSelection: true,
            scrollBeyondLastLine: false,
            automaticLayout: true
        });

        // Initialize CSS Editor
        cssEditor = monaco.editor.create(document.getElementById('monaco-css'), {
            value: <?php echo json_encode($shortcode ? $shortcode->css : '.magazine-item {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.magazine-item img {
    max-width: 100%;
    height: auto;
}

.magazine-item h2 {
    margin: 15px 0;
    font-size: 1.5em;
}

.magazine-item .description {
    margin: 10px 0;
    line-height: 1.4;
}

.magazine-item .read-button {
    display: inline-block;
    padding: 10px 20px;
    background: #007cba;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
}

.magazine-item .read-button:hover {
    background: #006ba1;
}'); ?>,
            language: 'css',
            theme: 'vs-light',
            minimap: { enabled: false },
            lineNumbers: 'on',
            roundedSelection: true,
            scrollBeyondLastLine: false,
            automaticLayout: true
        });

        // Update preview on content change
        templateEditor.onDidChangeModelContent(debounce(updatePreview, 300));
        cssEditor.onDidChangeModelContent(debounce(updatePreview, 300));
        
        // Initial preview
        setTimeout(updatePreview, 1000);
    });

    // Handle form submission
    $('#shortcode-form').on('submit', function(e) {
        e.preventDefault();
        
        // Update hidden inputs with editor content
        $('#template').val(templateEditor.getValue());
        $('#css').val(cssEditor.getValue());
        
        var formData = new FormData();
        formData.append('action', 'save_magazine_shortcode');
        formData.append('nonce', '<?php echo wp_create_nonce("save_shortcode"); ?>');
        formData.append('shortcode_name', $('#shortcode_name').val());
        formData.append('list_type', $('#list_type').val());
        formData.append('display_type', $('#display_type').val());
        formData.append('container_class', $('#container_class').val());
        formData.append('template', $('#template').val());
        formData.append('css', $('#css').val());
        
        <?php if ($shortcode): ?>
            formData.append('shortcode_id', <?php echo esc_js($shortcode->id); ?>);
        <?php endif; ?>

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    window.location.href = '?page=rsa-magazines&tab=shortcodes&message=success';
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                alert('Network error occurred. Check console for details.');
            }
        });
    });

    // Update display type availability based on list type
    $('#list_type').change(function() {
        $('#display_type').prop('disabled', $(this).val() === 'featured');
    });

    // Add preview functionality
    function updatePreview() {
        var template = templateEditor.getValue();
        var css = cssEditor.getValue();
        var listType = $('#list_type').val();
        var displayType = $('#display_type').val();
        var containerClass = $('#container_class').val();

        // Build preview HTML with scoped styles
        var html = '<style>' + css;
        
        // Add default container styles
        html += `
            .preview-outer {
                padding: 20px;
                background: #f8f9fa;
                border: 1px solid #ddd;
            }
            .preview-container {
                max-width: 1200px;
                margin: 0 auto;
                background: #fff;
                padding: 20px;
                border: 1px solid #eee;
            }
        `;
        html += '</style>';
        
        // Add container class if set
        var previewContainerClass = 'preview-content';
        if (containerClass) {
            previewContainerClass += ' ' + containerClass;
        }
        
        // Add display type class
        if (listType !== 'featured') {
            previewContainerClass += ' magazine-' + displayType;
        }
        
        html += '<div class="' + previewContainerClass + '">';

        // Use real magazine data if available
        if (typeof previewMagazines !== 'undefined' && previewMagazines.length > 0) {
            previewMagazines.forEach(function(magazine) {
                var previewTemplate = template;
                
                if (listType === 'digital' || listType === 'featured') {
                    if (!magazine.pdf_file) {
                        previewTemplate = '<div class="magazine-error">PDF file missing</div>';
                    } else {
                        previewTemplate = template.replace(/href="{pdf_file}"/g, 
                            'href="' + (isLoggedIn ? magazine.pdf_file : redirectUrl) + '"');
                    }
                } else if (listType === 'hardcopy') {
                    if (magazine.payment_page_id) {
                        previewTemplate = template.replace(/{payment_page}/g, 
                            '?page_id=' + magazine.payment_page_id);
                    }
                }

                html += previewTemplate
                    .replace(/{title}/g, magazine.title || '')
                    .replace(/{featured_image}/g, magazine.featured_image || '')
                    .replace(/{description}/g, magazine.description || '')
                    .replace(/{issue_number}/g, magazine.issue_number || '')
                    .replace(/{price}/g, magazine.price || '');
            });
        } else {
            // Fallback to sample data for new shortcodes
            var sampleMagazine = {
                title: 'Sample Magazine',
                description: 'This is a sample magazine description for preview purposes.',
                featured_image: '<?php echo plugins_url('assets/images/sample-magazine.jpg', dirname(__FILE__)); ?>',
                pdf_file: '#preview-link',
                issue_number: '2024/01',
                price: '29.99',
                payment_page_id: '1'
            };

            for (var i = 0; i < 3; i++) {
                var previewTemplate = template
                    .replace(/{title}/g, sampleMagazine.title + ' ' + (i + 1))
                    .replace(/{description}/g, sampleMagazine.description)
                    .replace(/{featured_image}/g, sampleMagazine.featured_image)
                    .replace(/{pdf_file}/g, sampleMagazine.pdf_file)
                    .replace(/{issue_number}/g, sampleMagazine.issue_number)
                    .replace(/{price}/g, sampleMagazine.price)
                    .replace(/{payment_page}/g, '?page_id=' + sampleMagazine.payment_page_id);

                html += previewTemplate;
            }
        }

        html += '</div>';
        
        $('#preview_content').html(html);
    }

    // Refresh button handler
    $('.reload-preview').click(function() {
        location.reload();
    });

    // Real-time preview updates
    var previewTimeout;
    function debouncedPreview() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(updatePreview, 300);
    }

    // Attach preview update handlers
    if (typeof tinymce !== 'undefined') {
        tinymce.get('template').on('change', debouncedPreview);
        tinymce.get('css').on('change', debouncedPreview);
    }
    $('#list_type').on('change', debouncedPreview);
    $('#display_type').on('change', debouncedPreview);
    $('#container_class').on('input', debouncedPreview);

    // Initial preview after TinyMCE is ready
    $(window).on('load', function() {
        setTimeout(updatePreview, 1000);
    });
});
</script>

<style>
/* Editor Styles */
.template-editor,
.css-editor {
    margin: 20px 0;
}

.monaco-editor {
    border-radius: 4px;
    overflow: hidden;
}

/* Preview Styles */
.preview-wrapper {
    margin: 20px 0;
}

.preview-outer {
    border-radius: 4px;
    overflow: hidden;
}

.preview-container {
    overflow: auto;
    max-height: 600px;
}

.preview-content {
    /* Default content container styles */
    width: 100%;
    box-sizing: border-box;
}

/* Default Grid Layout */
.magazine-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

/* Default Scroll Layout */
.magazine-scroll {
    display: flex;
    overflow-x: auto;
    gap: 20px;
    scroll-snap-type: x mandatory;
}

.magazine-scroll > * {
    flex: 0 0 300px;
    scroll-snap-align: start;
}
</style>
