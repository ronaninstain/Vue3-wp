<?php

/**
 * Plugin Name: Vue Admin SPA
 * Description: A WordPress plugin with Vue.js for admin menu and submenu as a Single Page Application (SPA).
 * Version: 1.2
 * Author: Shoive
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Vue.js, Vue Router, and custom admin scripts
function vue_admin_enqueue_scripts($hook)
{
    if ($hook !== 'toplevel_page_vue-admin-page') {
        return;
    }

    wp_enqueue_script('vue-js', 'https://unpkg.com/vue@3/dist/vue.global.prod.js', [], '3.0', true);
    wp_enqueue_script('vue-router-js', 'https://unpkg.com/vue-router@4', [], '4.0', true);
    wp_enqueue_script('vue-admin-js', plugin_dir_url(__FILE__) . 'assets/admin.js', ['vue-js', 'vue-router-js'], '1.0', true);

    wp_localize_script('vue-admin-js', 'vueAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vue_admin_nonce'),
        'uploadUrl' => admin_url('async-upload.php'),
    ]);

    wp_enqueue_style('vue-admin-style', plugin_dir_url(__FILE__) . 'assets/style.css');
}
add_action('admin_enqueue_scripts', 'vue_admin_enqueue_scripts');

// Add custom admin menu and submenu
function vue_admin_menu()
{
    add_menu_page(
        'Vue Admin SPA',
        'Vue Admin',
        'manage_options',
        'vue-admin-page',
        'vue_admin_render_page',
        'dashicons-admin-generic',
        6
    );

    add_submenu_page(
        'vue-admin-page',
        'Add Options',
        'Add Options',
        'manage_options',
        'vue-admin-page#/add-options',
        'vue_admin_render_page'
    );

    add_submenu_page(
        'vue-admin-page',
        'Blog Titles',
        'Blog Titles',
        'manage_options',
        'vue-admin-page#/blog-titles',
        'vue_admin_render_page'
    );

    add_submenu_page(
        'vue-admin-page',
        'Delete Path',
        'Delete Path',
        'manage_options',
        'vue-admin-page#/delete-path',
        'vue_admin_render_page'
    );

    add_submenu_page(
        'vue-admin-page',
        'User Notifications',
        'User Notifications',
        'manage_options',
        'vue-admin-page#/user-meta',
        'vue_admin_render_page'
    );

    add_submenu_page(
        'vue-admin-page',
        'Update Certificate Date',
        'Certificate Date',
        'manage_options',
        'vue-admin-page#/update-activity',
        'vue_admin_render_page'
    );

    add_submenu_page(
        'vue-admin-page',
        'Course Ratings',
        'Course Ratings',
        'manage_options',
        'vue-admin-page#/course-ratings',
        'vue_admin_render_page'
    );
}
add_action('admin_menu', 'vue_admin_menu');

// Render the Vue.js admin page
function vue_admin_render_page()
{
    include plugin_dir_path(__FILE__) . 'templates/admin-template.php';
}

// Handle saving options
function save_vue_options()
{
    check_ajax_referer('vue_admin_nonce', 'nonce');

    $text_option = sanitize_text_field($_POST['text_option']);
    $image_option = isset($_FILES['image_option']) ? $_FILES['image_option'] : null;

    $uploaded_image = null;

    if ($image_option && !empty($image_option['tmp_name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload($image_option, ['test_form' => false]);

        if (isset($upload['url'])) {
            $uploaded_image = $upload['url'];
        }
    }

    $saved_data = [
        'text_option' => $text_option,
        'image_option' => $uploaded_image,
    ];

    update_option('vue_saved_options', $saved_data);
    wp_send_json_success(['message' => 'Options saved successfully!', 'data' => $saved_data]);
}
add_action('wp_ajax_save_vue_options', 'save_vue_options');

// Fetch blog titles
function fetch_blog_titles()
{
    check_ajax_referer('vue_admin_nonce', 'nonce');
    $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1]);
    $post_data = array_map(function ($post) {
        return ['title' => $post->post_title, 'permalink' => get_permalink($post->ID)];
    }, $posts);
    wp_send_json_success($post_data);
}
add_action('wp_ajax_fetch_blog_titles', 'fetch_blog_titles');

// Delete a file by its path
add_action('wp_ajax_delete_file_path', 'delete_file_path_handler');
function delete_file_path_handler()
{
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vue_admin_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    // Validate file path
    $file_url = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
    if (empty($file_url)) {
        wp_send_json_error(['message' => 'File URL is required.']);
    }

    // Parse the file path to get relative path
    $upload_dir = wp_get_upload_dir();
    $base_upload_dir = $upload_dir['basedir'];
    $base_upload_url = $upload_dir['baseurl'];

    // Check if the file is within the uploads directory
    if (strpos($file_url, $base_upload_url) === 0) {
        $relative_path = str_replace($base_upload_url, '', $file_url);
        $full_path = $base_upload_dir . $relative_path;

        // Attempt to find the file in the media library
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid = %s",
            $file_url
        ));

        if ($attachment_id) {
            // If the file is in the media library, delete the attachment
            if (wp_delete_attachment($attachment_id, true)) {
                wp_send_json_success(['message' => 'File and associated media library entry deleted successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to delete file from the media library.']);
            }
        } else {
            // File exists but is not in the media library; delete it directly
            if (file_exists($full_path)) {
                if (unlink($full_path)) {
                    wp_send_json_success(['message' => 'File deleted successfully (not in media library).']);
                } else {
                    wp_send_json_error(['message' => 'Failed to delete file directly.']);
                }
            } else {
                wp_send_json_error(['message' => 'File does not exist in the uploads directory.']);
            }
        }
    } else {
        // Handle custom file paths outside the uploads directory
        $full_path = ABSPATH . $file_url; // Ensure it's an absolute path
        if (file_exists($full_path)) {
            if (unlink($full_path)) {
                wp_send_json_success(['message' => 'Custom path file deleted successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to delete custom path file.']);
            }
        } else {
            wp_send_json_error(['message' => 'File does not exist in the custom path.']);
        }
    }
}
// Update user meta
add_action('wp_ajax_update_user_meta_value', 'update_user_meta_value');
function update_user_meta_value()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vue_admin_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    $user_id = intval($_POST['user_id']);
    $meta_value = sanitize_text_field($_POST['meta_value']);
    $meta_keys = json_decode(stripslashes($_POST['meta_keys']), true);

    if (empty($user_id) || empty($meta_keys) || empty($meta_value)) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }

    foreach ($meta_keys as $meta_key) {
        update_user_meta($user_id, $meta_key, $meta_value);
    }

    wp_send_json_success(['message' => 'Notifications updated successfully.']);
}
// Update certifacte date
add_action('wp_ajax_update_activity_meta', 'update_activity_meta');
function update_activity_meta()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vue_admin_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    global $wpdb;

    $user_id = intval($_POST['user_id']);
    $course_id = intval($_POST['course_id']);
    $completion_date = sanitize_text_field($_POST['completion_date']);

    if (empty($user_id) || empty($course_id) || empty($completion_date)) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }

    $table_name = $wpdb->prefix . 'bp_activity';

    // Check for existing record
    $existing_record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND component = 'course' 
            AND type = 'submit_course' 
            AND item_id = %d
            ORDER BY date_recorded DESC
            LIMIT 1",
            $user_id,
            $course_id
        )
    );

    if ($existing_record) {
        // Update existing record
        $result = $wpdb->update(
            $table_name,
            ['date_recorded' => $completion_date],
            ['id' => $existing_record->id],
            ['%s'],
            ['%d']
        );
        if (!$result) {
            wp_send_json_error(['message' => 'Failed to update activity.']);
        }
        wp_send_json_success(['message' => 'Activity updated successfully.']);
    } else {
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'component' => 'course',
                'type' => 'submit_course',
                'item_id' => $course_id,
                'date_recorded' => $completion_date,
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );
        if (!$result) {
            wp_send_json_error(['message' => 'Failed to insert new activity.']);
        }
        wp_send_json_success(['message' => 'Activity added successfully.']);
    }
}
// Create a table to store the rating status
function create_rating_status_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'course_rating_status';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        status tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Insert default status if the table is empty
    $wpdb->insert($table_name, ['status' => 0]);
}
register_activation_hook(__FILE__, 'create_rating_status_table');

// Handle AJAX request to get the rating status
function get_rating_status()
{
    check_ajax_referer('vue_admin_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'course_rating_status';
    $status = $wpdb->get_var("SELECT status FROM $table_name WHERE id = 1");

    // Debug: Log the current status
    error_log("Current status fetched from database: " . $status);

    wp_send_json_success(['status' => $status]);
}
add_action('wp_ajax_get_rating_status', 'get_rating_status');

function toggle_rating_status() {
    check_ajax_referer('vue_admin_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'course_rating_status';
    $status = intval($_POST['status']); // Get the status from the AJAX request

    // Debug: Log the status being updated
    error_log("Status received from AJAX: " . $status);

    // Update the status in the database
    $result = $wpdb->update($table_name, ['status' => $status], ['id' => 1]);

    if ($result === false) {
        error_log("Failed to update status in the database.");
        wp_send_json_error(['message' => 'Failed to update status in the database.']);
    } else {
        // Debug: Fetch the updated status from the database
        $updated_status = $wpdb->get_var("SELECT status FROM $table_name WHERE id = 1");
        error_log("Updated status in the database: " . $updated_status);

        wp_send_json_success(['message' => 'Rating status updated successfully.', 'status' => $updated_status]);
    }
}
add_action('wp_ajax_toggle_rating_status', 'toggle_rating_status');

// Conditionally apply the metabox removal code
function sa_remove_metaboxes()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'course_rating_status';
    $status = $wpdb->get_var("SELECT status FROM $table_name WHERE id = 1");

    if ($status == 0) {
        remove_meta_box('postcustom', 'course', 'normal');
        remove_meta_box('postcustom', 'unit', 'normal');
        remove_meta_box('postcustom', 'post', 'normal');
        remove_meta_box('postcustom', 'page', 'normal');
        remove_meta_box('commentstatusdiv', 'course', 'normal');
        remove_meta_box('commentsdiv', 'course', 'normal');
        remove_meta_box('aam-access-manager', 'course', 'advanced');
        remove_meta_box('commentstatusdiv', 'unit', 'normal');
        remove_meta_box('commentsdiv', 'unit', 'normal');
        remove_meta_box('aam-access-manager', 'unit', 'advanced');
    }
}
add_action('admin_init', 'sa_remove_metaboxes');

// function check_table_contents() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'course_rating_status';

//     // Check if the table exists
//     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
//         var_dump("Table does not exist: $table_name");
//         return;
//     }

//     // Get the contents of the table
//     $results = $wpdb->get_results("SELECT * FROM $table_name");
//     var_dump($results);
// }
// add_action('init', 'check_table_contents');
