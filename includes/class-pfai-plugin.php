<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once PFAI_PLUGIN_DIR . 'admin/class-pfai-admin.php';

class PFAI_Plugin {
    public function run() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        PFAI_Employers::register_hooks();

        if (is_admin()) {
            $admin = new PFAI_Admin();
            $admin->register_hooks();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'pathway-forward-ai',
            false,
            dirname(plugin_basename(PFAI_PLUGIN_FILE)) . '/languages'
        );
    }
}
