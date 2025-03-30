<?php
/*
Plugin Name: RSA Magazines
Description: Manage Digital and Hard Copy Magazines
Version: 1.0
Author: Olayinka Aremu
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize plugin after WordPress is fully loaded
function rsa_magazines_init() {
    require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-rsa-magazine-ajax.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-rsa-magazine-rest.php';
    require_once plugin_dir_path(__FILE__) . 'includes/pdf-viewer-template.php';

    // Initialize REST API
    $rsa_magazine_rest = RSA_Magazine_REST::get_instance();
    $rsa_magazine_rest->init();
    
    // Initialize AJAX handlers
    $rsa_magazine_ajax = RSA_Magazine_AJAX::get_instance();
    $rsa_magazine_ajax->init();
}
add_action('plugins_loaded', 'rsa_magazines_init');

// Place activation hook and related functions at top level
register_activation_hook(__FILE__, 'rsa_magazines_activate');

// Add this to ensure scripts load in the footer
function rsa_magazine_viewer_scripts() {
    if (is_page('magazine-viewer')) {
        wp_enqueue_script('pdf-js', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js', array(), '3.4.120', true);
        wp_enqueue_script('three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', array(), 'r128', true);
    }
}
add_action('wp_enqueue_scripts', 'rsa_magazine_viewer_scripts');

function rsa_magazines_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Create digital magazines table
    $sql_digital = "DROP TABLE IF EXISTS {$wpdb->prefix}rsa_digital_magazines;";
    $wpdb->query($sql_digital);

    $sql_digital = "CREATE TABLE {$wpdb->prefix}rsa_digital_magazines (
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

    // Create hard copy magazines table
    $sql_hardcopy = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rsa_hardcopy_magazines (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NOT NULL,
        featured_image varchar(255),
        issue_number varchar(100),
        payment_page_id bigint(20),
        price decimal(10,2) NOT NULL,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Update shortcodes table with container_class column
    $sql_shortcodes = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rsa_magazine_shortcodes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        list_type varchar(50) NOT NULL,
        template text NOT NULL,
        css text NOT NULL,
        container_class varchar(255) DEFAULT '',
        display_type varchar(20) DEFAULT 'grid',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_digital);
    dbDelta($sql_hardcopy);
    dbDelta($sql_shortcodes);

    // Add default formats
    $default_formats = array(
        'pdf' => 'PDF Document (.pdf)',
        'doc' => 'Word Document (.doc/.docx)',
        'epub' => 'EPUB eBook (.epub)'
    );
    
    add_option('rsa_magazines_allowed_formats', $default_formats);
    add_option('rsa_magazines_featured_magazine', ''); // Add this line
    add_option('rsa_magazines_login_redirect', wp_login_url()); // Add login redirect option

    // Add default templates
    $default_template = '<div class="restricted-content">
    <h2>Access Restricted</h2>
    <p>The magazine "{magazineTitle}" is only available for logged-in users.</p>
    <p>Please <a href="{loginUrl}">login</a> or <a href="{registerUrl}">register</a> to continue reading.</p>
</div>';
    
    $on_hold_template = '<div class="on-hold-content">
    <h2>Magazine Currently On Hold</h2>
    <p>The magazine "{magazineTitle}" is temporarily unavailable.</p>
    <p>Please check back later.</p>
</div>';

    $sold_out_template = '<div class="sold-out-content">
    <h2>Magazine Sold Out</h2>
    <p>Sorry, "{magazineTitle}" is currently sold out.</p>
    <p>Please check back later for restocking updates.</p>
</div>';
    
    add_option('rsa_magazines_restricted_template', $default_template);
    add_option('rsa_magazines_onhold_template', $on_hold_template);
    add_option('rsa_magazines_soldout_template', $sold_out_template);

    // Add version option to track database changes
    add_option('rsa_magazines_db_version', '1.0');
}

// Add database upgrade routine
function rsa_magazines_upgrade_check() {
    $current_version = get_option('rsa_magazines_db_version', '1.0');
    
    if (version_compare($current_version, '1.1', '<')) {
        global $wpdb;
        
        // Add container_class column if it doesn't exist
        $table = $wpdb->prefix . 'rsa_magazine_shortcodes';
        $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'container_class'");
        
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$table} ADD container_class varchar(255) DEFAULT '' AFTER css");
        }
        
        update_option('rsa_magazines_db_version', '1.1');
    }
}
add_action('plugins_loaded', 'rsa_magazines_upgrade_check');

// Add admin menu
function rsa_magazines_admin_menu() {
    // Change from manage_options to edit_posts capability
    add_menu_page(
        'RSA Magazines',
        'RSA Magazines',
        'edit_posts', // This capability is available to all roles except subscriber
        'rsa-magazines',
        'rsa_magazines_main_page',
        'dashicons-book-alt',
        30
    );
}
add_action('admin_menu', 'rsa_magazines_admin_menu');

// Main page display function
function rsa_magazines_main_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'digital';
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    
    ?>
    <div class="wrap">
        <h1>RSA Magazines</h1>
        
        <?php if ($action === 'list'): ?>
            <nav class="nav-tab-wrapper">
                <?php
                $tabs = array(
                    'digital' => 'Digital Magazine',
                    'hardcopy' => 'Hard Copy Magazine',
                    'settings' => 'Settings',
                    'shortcodes' => 'Shortcode Builder',
                    'styling' => 'Advanced Styling'
                );
                
                foreach ($tabs as $tab_id => $tab_label): ?>
                    <a href="<?php echo esc_url(add_query_arg(array(
                        'page' => 'rsa-magazines',
                        'tab' => $tab_id
                    ), admin_url('admin.php'))); ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_label; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tabbed-content">
                <?php if ($current_tab !== 'settings' && $current_tab !== 'shortcodes' && $current_tab !== 'styling'): ?>
                    <div class="add-new-wrapper" style="margin: 20px 0;">
                        <a href="?page=rsa-magazines&tab=<?php echo $current_tab; ?>&action=add" class="page-title-action">Add New</a>
                    </div>
                <?php endif; ?>

                <?php
                switch ($current_tab) {
                    case 'digital':
                        include_once plugin_dir_path(__FILE__) . 'includes/digital-magazine-list.php';
                        break;
                    case 'hardcopy':
                        include_once plugin_dir_path(__FILE__) . 'includes/hardcopy-magazine-list.php';
                        break;
                    case 'settings':
                        include_once plugin_dir_path(__FILE__) . 'includes/settings-page.php';
                        break;
                    case 'shortcodes':
                        include_once plugin_dir_path(__FILE__) . 'includes/shortcode-builder.php';
                        break;
                    case 'styling':
                        include_once plugin_dir_path(__FILE__) . 'includes/advanced-styling.php';
                        break;
                }
                ?>
            </div>
        <?php else: ?>
            <?php 
            if ($current_tab === 'digital' || $current_tab === 'hardcopy') {
                include_once plugin_dir_path(__FILE__) . 'includes/'. $current_tab .'-magazine-form.php';
            } elseif ($current_tab === 'shortcodes') {
                include_once plugin_dir_path(__FILE__) . 'includes/shortcode-form.php';
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
}

// Register shortcodes and enqueue styles
function rsa_magazines_register_shortcodes() {   
    // Register single shortcode handler for all magazine displays
    add_shortcode('rsa', 'rsa_render_magazine_shortcode');    
    // Register style handler
    add_action('wp_enqueue_scripts', 'rsa_magazines_enqueue_styles');
}
add_action('init', 'rsa_magazines_register_shortcodes');

// Enqueue styles for shortcodes
function rsa_magazines_enqueue_styles() {
    if (!is_singular() || !has_shortcode($GLOBALS['post']->post_content, 'rsa')) {
        return;
    }

    global $wpdb;
    // Get styles directly from database
    $shortcode_styles = $wpdb->get_col("SELECT css FROM {$wpdb->prefix}rsa_magazine_shortcodes");
    $grid_style = get_option('rsa_magazines_grid_style', '');
    $scroll_style = get_option('rsa_magazines_scroll_style', '');
    
    $combined_css = implode("\n", array_filter($shortcode_styles)) . "\n" . $grid_style . "\n" . $scroll_style;
    
    wp_register_style('rsa-magazines-styles', false);
    wp_enqueue_style('rsa-magazines-styles');
    wp_add_inline_style('rsa-magazines-styles', $combined_css);
}

// Enqueue required scripts
function rsa_magazines_enqueue_scripts() {
    if (is_singular() && has_shortcode($GLOBALS['post']->post_content, 'rsa')) {
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'rsa_magazines_enqueue_scripts');

// Add AJAX handlers
function rsa_magazines_ajax_handlers() {
    add_action('wp_ajax_save_magazine_shortcode', 'rsa_save_magazine_shortcode');
    add_action('wp_ajax_delete_magazine_shortcode', 'rsa_delete_magazine_shortcode');
}
add_action('admin_init', 'rsa_magazines_ajax_handlers');

function rsa_save_magazine_shortcode() {
    check_ajax_referer('save_shortcode', 'nonce');
    
    global $wpdb;
    $table = $wpdb->prefix . 'rsa_magazine_shortcodes';
    
    $data = array(
        'name' => sanitize_text_field($_POST['shortcode_name']),
        'list_type' => sanitize_text_field($_POST['list_type']),
        'template' => wp_kses_post($_POST['template']),
        'css' => wp_kses_post($_POST['css']),
        'display_type' => isset($_POST['display_type']) ? sanitize_text_field($_POST['display_type']) : 'grid'
    );
    
    if (isset($_POST['shortcode_id'])) {
        $wpdb->update($table, $data, ['id' => intval($_POST['shortcode_id'])]);
    } else {
        $wpdb->insert($table, $data);
    }
    
    wp_send_json_success();
}

function rsa_delete_magazine_shortcode() {
    check_ajax_referer('delete_shortcode', 'nonce');
    
    global $wpdb;
    $wpdb->delete(
        $wpdb->prefix . 'rsa_magazine_shortcodes',
        ['id' => intval($_POST['shortcode_id'])],
        ['%d']
    );
    
    wp_send_json_success();
}