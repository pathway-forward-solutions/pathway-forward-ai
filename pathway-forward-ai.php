<?php
/**
 * Plugin Name: Pathway Forward AI
 * Plugin URI: https://pathwayforwardsolutions.org
 * Description: Mission Control and workforce-development tools for Pathway Forward Solutions.
 * Version: 0.1.0
 * Author: Pathway Forward Solutions Inc.
 * Text Domain: pathway-forward-ai
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PFAI_VERSION', '0.1.0');
define('PFAI_PLUGIN_FILE', __FILE__);
define('PFAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PFAI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-activator.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-deactivator.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-plugin.php';

register_activation_hook(__FILE__, array('PFAI_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('PFAI_Deactivator', 'deactivate'));

function pfai_run_plugin() {
    $plugin = new PFAI_Plugin();
    $plugin->run();
}
pfai_run_plugin();
