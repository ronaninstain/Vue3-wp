<?php

/**
 * Plugin Name: Vue Admin SPA
 * Description: A WordPress plugin with Vue.js for admin menu and submenu as a Single Page Application (SPA).
 * Version: 1.0
 * Author: Shoive
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Vue.js, Vue Router, and custom admin scripts
function vue_admin_enqueue_scripts($hook)
{
    // Load scripts only on the Vue admin page
    if ($hook !== 'toplevel_page_vue-admin-page') {
        return;
    }

    // Load Vue.js from CDN
    wp_enqueue_script('vue-js', 'https://unpkg.com/vue@3/dist/vue.global.prod.js', [], '3.0', true);

    // Load Vue Router from CDN
    wp_enqueue_script('vue-router-js', 'https://unpkg.com/vue-router@4', [], '4.0', true);

    // Load the admin.js script
    wp_enqueue_script('vue-admin-js', plugin_dir_url(__FILE__) . 'assets/admin.js', ['vue-js', 'vue-router-js'], '1.0', true);

    // Localize the script to pass data from PHP to Vue.js
    wp_localize_script('vue-admin-js', 'vueAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vue_admin_nonce'),
    ]);

    // Load the admin styles
    wp_enqueue_style('vue-admin-style', plugin_dir_url(__FILE__) . 'assets/style.css');
}
add_action('admin_enqueue_scripts', 'vue_admin_enqueue_scripts');

// Add custom admin menu and submenu
function vue_admin_menu()
{
    // Main menu page
    add_menu_page(
        'Vue Admin SPA',    // Page title
        'Vue Admin',        // Menu title
        'manage_options',   // Capability
        'vue-admin-page',   // Menu slug
        'vue_admin_render_page', // Function to render the page
        'dashicons-admin-generic', // Icon
        6  // Position in the admin menu
    );

    // Submenu page
    add_submenu_page(
        'vue-admin-page',    // Parent slug
        'Add Options',      // Page title
        'Add Options',      // Menu title
        'manage_options',    // Capability
        'vue-admin-page#/add-options', // Menu slug
        'vue_admin_render_page'  // Function to render the page
    );

    // Submenu page 3
    add_submenu_page(
        'vue-admin-page',    // Parent slug
        'Blog Titles',      // Page title
        'Blog Titles',      // Menu title
        'manage_options',    // Capability
        'vue-admin-page#/blog-titles', // Menu slug
        'vue_admin_render_page'  // Function to render the page
    );
}
add_action('admin_menu', 'vue_admin_menu');

// Render the Vue.js admin page
function vue_admin_render_page()
{
    // Include the template file for the admin page
    include plugin_dir_path(__FILE__) . 'templates/admin-template.php';
}

// Handle the AJAX request to save options
function save_vue_options()
{
    // Check the nonce for security
    check_ajax_referer('vue_admin_nonce', 'nonce');

    // Get the posted text option
    $text_option = sanitize_text_field($_POST['text_option']);

    // Handle banner upload if a file was provided
    if (!empty($_FILES['banner_option'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload the file and get the file array
        $uploaded_file = wp_handle_upload($_FILES['banner_option'], ['test_form' => false]);

        // If the file was uploaded successfully
        if (isset($uploaded_file['file'])) {
            // Get the uploaded file path
            $file = $uploaded_file['file'];

            // Get the file type
            $filetype = wp_check_filetype($file);

            // Prepare an array of post data for the attachment
            $attachment_data = [
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name(basename($file)),
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];

            // Insert the attachment into the WordPress media library
            $attachment_id = wp_insert_attachment($attachment_data, $file);

            // Generate the attachment metadata and update the attachment in the media library
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);

            // Save the attachment ID in wp_options
            update_option('vue_banner_option', $attachment_id);
        }
    }

    // Save the text option in wp_options
    update_option('vue_text_option', $text_option);

    // Send a response back to Vue.js
    wp_send_json_success(['message' => 'Options saved successfully!']);
}
add_action('wp_ajax_save_vue_options', 'save_vue_options');

// Handle the AJAX request to fetch blog titles
function fetch_blog_titles()
{
    // Check the nonce for security
    check_ajax_referer('vue_admin_nonce', 'nonce');

    // Fetch the latest blog posts
    $posts = get_posts([
        'post_type' => 'post',
        'posts_per_page' => -1 // Adjust this value as needed
    ]);

    // Prepare the posts data
    $post_data = [];
    foreach ($posts as $post) {
        $post_data[] = [
            'title' => $post->post_title,
            'permalink' => get_permalink($post->ID)
        ];
    }

    // Send the response back to Vue.js
    wp_send_json_success($post_data);
}
add_action('wp_ajax_fetch_blog_titles', 'fetch_blog_titles');

