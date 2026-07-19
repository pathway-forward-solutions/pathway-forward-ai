<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
