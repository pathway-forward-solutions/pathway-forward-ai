<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_Admin {
    public function register_hooks() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_employer_form'));
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
            __('Employer CRM', 'pathway-forward-ai'),
            __('Employer CRM', 'pathway-forward-ai'),
            'manage_options',
            'pathway-forward-ai-employers',
            array($this, 'render_employer_crm')
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

    public function handle_employer_form() {
        if (!isset($_POST['pfai_employer_action']) || 'save' !== $_POST['pfai_employer_action']) {
            return;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pfai_employer_crm_nonce'] ?? '')), 'pfai_save_employer')) {
            wp_die(esc_html__('Unauthorized request.', 'pathway-forward-ai'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pfai_employers';
        $data = array(
            'employer_name' => sanitize_text_field(wp_unslash($_POST['employer_name'] ?? '')),
            'organization_name' => sanitize_text_field(wp_unslash($_POST['organization_name'] ?? '')),
            'contact_name' => sanitize_text_field(wp_unslash($_POST['contact_name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'partnership_status' => sanitize_key(wp_unslash($_POST['partnership_status'] ?? 'prospect')),
            'hiring_needs' => sanitize_textarea_field(wp_unslash($_POST['hiring_needs'] ?? '')),
            'interaction_notes' => sanitize_textarea_field(wp_unslash($_POST['interaction_notes'] ?? '')),
            'follow_up_date' => !empty($_POST['follow_up_date']) ? sanitize_text_field(wp_unslash($_POST['follow_up_date'])) : null,
        );

        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
        $id = isset($_POST['pfai_employer_id']) ? absint($_POST['pfai_employer_id']) : 0;

        if ($id) {
            $wpdb->update($table_name, $data, array('id' => $id), $format, array('%d'));
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $wpdb->insert($table_name, $data, $format + array('%s', '%s'));
        }

        wp_safe_redirect(add_query_arg(array('page' => 'pathway-forward-ai-employers', 'saved' => 1), admin_url('admin.php')));
        exit;
    }

    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pfai_employers';
        $organization_name = get_option('pfai_organization_name', 'Pathway Forward Solutions Inc.');
        $stats = array(
            'employers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'active' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE partnership_status = %s", 'active')),
            'follow_up' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE follow_up_date IS NOT NULL AND follow_up_date <= %s", current_time('Y-m-d'))),
            'prospects' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE partnership_status = %s", 'prospect')),
        );
        include PFAI_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public function render_employer_crm() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pfai_employers';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $follow_up = isset($_GET['follow_up']) ? sanitize_key(wp_unslash($_GET['follow_up'])) : '';
        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $delete_id = isset($_GET['delete']) ? absint($_GET['delete']) : 0;

        if ($delete_id && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'pfai_delete_employer_' . $delete_id)) {
            $wpdb->delete($table_name, array('id' => $delete_id), array('%d'));
            wp_safe_redirect(add_query_arg(array('page' => 'pathway-forward-ai-employers', 'deleted' => 1), admin_url('admin.php')));
            exit;
        }

        $edit_employer = null;
        if ($edit_id) {
            $edit_employer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
        }

        $query = "SELECT * FROM $table_name WHERE 1=1";
        if ($search !== '') {
            $query .= ' AND (employer_name LIKE %s OR organization_name LIKE %s OR contact_name LIKE %s OR email LIKE %s OR hiring_needs LIKE %s OR interaction_notes LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $query = $wpdb->prepare($query, $like, $like, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $query .= ' AND partnership_status = %s';
            $query = $wpdb->prepare($query, $status);
        }
        if ($follow_up === 'due') {
            $query .= ' AND follow_up_date IS NOT NULL AND follow_up_date <= %s';
            $query = $wpdb->prepare($query, current_time('Y-m-d'));
        }

        $query .= ' ORDER BY follow_up_date IS NULL, follow_up_date ASC, employer_name ASC';
        $employers = $wpdb->get_results($query);

        include PFAI_PLUGIN_DIR . 'admin/partials/employer-crm.php';
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
