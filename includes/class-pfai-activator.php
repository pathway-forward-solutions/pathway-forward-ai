<?php
if (!defined('ABSPATH')) exit;

class PFAI_Activator {
    public static function activate() {
        update_option('pfai_version', PFAI_VERSION);
        add_option('pfai_organization_name', 'Pathway Forward Solutions Inc.');
        add_option('pfai_support_email', get_option('admin_email'));
        PFAI_Participants::register_post_type();
        flush_rewrite_rules();
    }
}
