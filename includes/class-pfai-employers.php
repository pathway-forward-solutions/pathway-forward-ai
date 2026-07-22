<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_Employers {
    public static function register_hooks() {
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'));
        add_action('admin_init', array(__CLASS__, 'handle_request'));
    }

    public static function maybe_upgrade() {
        $stored = get_option('pfai_employers_db_version');
        if ($stored !== false && version_compare((string) $stored, (string) PFAI_DB_VERSION, '>=')) {
            return;
        }

        self::install();
    }

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            employer_name varchar(255) NOT NULL,
            organization_name varchar(255) NOT NULL DEFAULT '',
            contact_name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            partnership_status varchar(32) NOT NULL DEFAULT 'prospect',
            hiring_needs longtext NOT NULL DEFAULT '',
            follow_up_date date DEFAULT NULL,
            interaction_notes longtext NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY partnership_status (partnership_status),
            KEY follow_up_date (follow_up_date),
            KEY employer_name (employer_name)
        ) $charset_collate;";

        dbDelta($sql);

        update_option('pfai_employers_db_version', PFAI_DB_VERSION);
    }

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'pfai_employers';
    }

    public static function handle_request() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['pfai_employer_action']) && 'save' === sanitize_key(wp_unslash($_POST['pfai_employer_action']))) {
            self::save_employer();
            return;
        }

        if (isset($_GET['page']) && 'pfai-employers' === sanitize_key(wp_unslash($_GET['page'])) && isset($_GET['delete'])) {
            self::delete_employer();
        }
    }

    public static function save_employer() {
        if (!isset($_POST['pfai_employer_crm_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pfai_employer_crm_nonce'])), 'pfai_save_employer')) {
            wp_die(esc_html__('Security check failed.', 'pathway-forward-ai'));
        }

        $id = isset($_POST['pfai_employer_id']) ? absint(wp_unslash($_POST['pfai_employer_id'])) : 0;
        $payload = array();
        $errors = array();

        $employer_name = sanitize_text_field(wp_unslash($_POST['employer_name'] ?? ''));
        if ($employer_name === '') {
            $errors[] = __('Employer name is required.', 'pathway-forward-ai');
        }

        $organization_name = sanitize_text_field(wp_unslash($_POST['organization_name'] ?? ''));
        $contact_name = sanitize_text_field(wp_unslash($_POST['contact_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $partnership_status = sanitize_key(wp_unslash($_POST['partnership_status'] ?? 'prospect'));
        $allowed_statuses = array('prospect', 'active', 'paused', 'inactive');
        if (!in_array($partnership_status, $allowed_statuses, true)) {
            $partnership_status = 'prospect';
        }

        $hiring_needs = sanitize_textarea_field(wp_unslash($_POST['hiring_needs'] ?? ''));
        $interaction_notes = sanitize_textarea_field(wp_unslash($_POST['interaction_notes'] ?? ''));
        $follow_up_date = '';
        if (!empty($_POST['follow_up_date'])) {
            $follow_up_date = sanitize_text_field(wp_unslash($_POST['follow_up_date']));
            $date = date_create_immutable_from_format('Y-m-d', $follow_up_date);
            if (!$date || $date->format('Y-m-d') !== $follow_up_date) {
                $errors[] = __('Follow-up date must be a valid date.', 'pathway-forward-ai');
            }
        }

        if (!empty($errors)) {
            set_transient('pfai_employer_errors', $errors, 30);
            wp_safe_redirect(add_query_arg(array('page' => 'pfai-employers', 'edit' => $id), admin_url('admin.php')));
            exit;
        }

        $payload = array(
            'employer_name' => $employer_name,
            'organization_name' => $organization_name,
            'contact_name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'partnership_status' => $partnership_status,
            'hiring_needs' => $hiring_needs,
            'follow_up_date' => $follow_up_date,
            'interaction_notes' => $interaction_notes,
        );

        global $wpdb;
        $table_name = self::get_table_name();
        $now = current_time('mysql');

        if ($id > 0) {
            $payload['updated_at'] = $now;
            $wpdb->update(
                $table_name,
                $payload,
                array('id' => $id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            $wpdb->insert($table_name, $payload, array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
        }

        wp_safe_redirect(add_query_arg(array('page' => 'pfai-employers', 'saved' => 1), admin_url('admin.php')));
        exit;
    }

    public static function delete_employer() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'pfai_delete_employer_' . absint(wp_unslash($_GET['delete'])))) {
            wp_die(esc_html__('Security check failed.', 'pathway-forward-ai'));
        }

        $id = absint(wp_unslash($_GET['delete']));
        if ($id > 0) {
            global $wpdb;
            $wpdb->delete(self::get_table_name(), array('id' => $id), array('%d'));
        }

        wp_safe_redirect(add_query_arg(array('page' => 'pfai-employers', 'deleted' => 1), admin_url('admin.php')));
        exit;
    }

    public static function get_employer($id) {
        global $wpdb;
        $table_name = self::get_table_name();
        $prepared = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", absint($id));

        return $wpdb->get_row($prepared);
    }

    public static function get_employers($search = '', $status = '', $follow_up = '') {
        global $wpdb;
        $table_name = self::get_table_name();
        $where = array('1=1');
        $values = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(employer_name LIKE %s OR organization_name LIKE %s OR contact_name LIKE %s OR email LIKE %s OR hiring_needs LIKE %s)';
            $values = array_merge($values, array_fill(0, 5, $like));
        }

        if ($status !== '') {
            $where[] = 'partnership_status = %s';
            $values[] = $status;
        }

        if ('due' === $follow_up) {
            $where[] = 'follow_up_date IS NOT NULL AND follow_up_date <= %s';
            $values[] = current_time('Y-m-d');
        }

        $sql = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where) . ' ORDER BY follow_up_date IS NULL, follow_up_date ASC, employer_name ASC';
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    public static function stats() {
        global $wpdb;
        $table_name = self::get_table_name();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE partnership_status = %s", 'active'));
        $due = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE follow_up_date IS NOT NULL AND follow_up_date <= %s", current_time('Y-m-d')));

        return compact('total', 'active', 'due');
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $follow_up = isset($_GET['follow_up']) ? sanitize_key(wp_unslash($_GET['follow_up'])) : '';
        $edit_employer = null;

        if (!empty($_GET['edit'])) {
            $edit_employer = self::get_employer(absint(wp_unslash($_GET['edit'])));
        }

        $employers = self::get_employers($search, $status, $follow_up);
        $statuses = array(
            'prospect' => __('Prospect', 'pathway-forward-ai'),
            'active' => __('Active Partnership', 'pathway-forward-ai'),
            'paused' => __('Paused', 'pathway-forward-ai'),
            'inactive' => __('Inactive', 'pathway-forward-ai'),
        );

        include PFAI_PLUGIN_DIR . 'admin/partials/employer-crm.php';
    }
}
