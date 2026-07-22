<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once PFAI_PLUGIN_DIR . 'admin/class-pfai-admin.php';

class PFAI_Plugin {
    public function run() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'maybe_upgrade_core'));
        PFAI_Employers::register_hooks();
        PFAI_Participants::register_hooks();
        PFAI_AI_Navigator::register_hooks();

        if (is_admin()) {
            (new PFAI_Admin())->register_hooks();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'pathway-forward-ai',
            false,
            dirname(plugin_basename(PFAI_PLUGIN_FILE)) . '/languages'
        );
    }

    public function maybe_upgrade_core() {
        $stored_version = (string) get_option('pfai_version', '');
        if (version_compare($stored_version, PFAI_VERSION, '>=')) {
            return;
        }

        update_option('pfai_version', PFAI_VERSION);
    }
}
