<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_Admin {
    public function register_hooks() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_menu() {
        add_menu_page(
            __('Pathway Forward AI', 'pathway-forward-ai'),
            __('Pathway Forward AI', 'pathway-forward-ai'),
            'manage_options',
            'pathway-forward-ai',
            array($this, 'render_dashboard'),
            'dashicons-chart-area',
            3
        );

        add_submenu_page(
            'pathway-forward-ai',
            __('Mission Control', 'pathway-forward-ai'),
            __('Mission Control', 'pathway-forward-ai'),
            'manage_options',
            'pathway-forward-ai',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'pathway-forward-ai',
            __('Settings', 'pathway-forward-ai'),
            __('Settings', 'pathway-forward-ai'),
            'manage_options',
            'pathway-forward-ai-settings',
            array($this, 'render_settings')
        );
    }

    public function enqueue_assets($hook_suffix) {
        if (strpos($hook_suffix, 'pathway-forward-ai') === false) {
            return;
        }

        wp_enqueue_style(
            'pfai-admin',
            PFAI_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            PFAI_VERSION
        );

        wp_enqueue_script(
            'pfai-admin',
            PFAI_PLUGIN_URL . 'admin/js/admin.js',
            array(),
            PFAI_VERSION,
            true
        );
    }

    public function register_settings() {
        register_setting(
            'pfai_settings_group',
            'pfai_organization_name',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'Pathway Forward Solutions Inc.',
            )
        );

        register_setting(
            'pfai_settings_group',
            'pfai_support_email',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => get_option('admin_email'),
            )
        );

        add_settings_section(
            'pfai_general_section',
            __('Organization Settings', 'pathway-forward-ai'),
            array($this, 'render_settings_section'),
            'pathway-forward-ai-settings'
        );

        add_settings_field(
            'pfai_organization_name',
            __('Organization name', 'pathway-forward-ai'),
            array($this, 'render_organization_name_field'),
            'pathway-forward-ai-settings',
            'pfai_general_section'
        );

        add_settings_field(
            'pfai_support_email',
            __('Support email', 'pathway-forward-ai'),
            array($this, 'render_support_email_field'),
            'pathway-forward-ai-settings',
            'pfai_general_section'
        );
    }

    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $organization_name = get_option('pfai_organization_name', 'Pathway Forward Solutions Inc.');
        include PFAI_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include PFAI_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function render_settings_section() {
        echo '<p>' . esc_html__('Configure the basic identity and contact information for your platform.', 'pathway-forward-ai') . '</p>';
    }

    public function render_organization_name_field() {
        $value = get_option('pfai_organization_name', 'Pathway Forward Solutions Inc.');
        printf(
            '<input type="text" class="regular-text" name="pfai_organization_name" value="%s" />',
            esc_attr($value)
        );
    }

    public function render_support_email_field() {
        $value = get_option('pfai_support_email', get_option('admin_email'));
        printf(
            '<input type="email" class="regular-text" name="pfai_support_email" value="%s" />',
            esc_attr($value)
        );
    }
}
