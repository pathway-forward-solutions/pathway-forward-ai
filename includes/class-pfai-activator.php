<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_Activator {
    public static function activate() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(PFAI_PLUGIN_FILE));
            wp_die(
                esc_html__('Pathway Forward AI requires PHP 7.4 or newer.', 'pathway-forward-ai'),
                esc_html__('Plugin activation error', 'pathway-forward-ai'),
                array('back_link' => true)
            );
        }

        global $wpdb;

        add_option('pfai_version', PFAI_VERSION);
        add_option('pfai_organization_name', 'Pathway Forward Solutions Inc.');
        add_option('pfai_setup_complete', '0');
        add_option('pfai_employers_db_version', PFAI_DB_VERSION);
        add_option('pfai_support_email', get_option('admin_email'));
        add_option('pfai_ai_navigator_db_version', PFAI_DB_VERSION);
        add_option('pfai_ai_retention_days', 90);
        add_option('pfai_ai_rate_limit_per_hour', 20);

        PFAI_Employers::install();
        PFAI_Participants::register_post_type();
        PFAI_AI_Navigator::install_schema();
        update_option('pfai_version', PFAI_VERSION);

        flush_rewrite_rules();
    }
}
