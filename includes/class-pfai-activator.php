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

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'pfai_employers';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            employer_name varchar(255) NOT NULL,
            organization_name varchar(255) DEFAULT '',
            contact_name varchar(255) DEFAULT '',
            email varchar(255) DEFAULT '',
            phone varchar(100) DEFAULT '',
            partnership_status varchar(50) NOT NULL DEFAULT 'prospect',
            hiring_needs longtext DEFAULT '',
            interaction_notes longtext DEFAULT '',
            follow_up_date date DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        flush_rewrite_rules();
    }
}
