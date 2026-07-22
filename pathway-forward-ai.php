<?php
/**
 * Plugin Name: Pathway Forward AI
 * Plugin URI: https://pathwayforwardsolutions.org
 * Description: Workforce-development and participant case-management tools for Pathway Forward Solutions.
 * Version: 0.8.1
 * Author: Pathway Forward Solutions Inc.
 * Text Domain: pathway-forward-ai
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PFAI_VERSION', '0.8.1');
define('PFAI_DB_VERSION', '0.8.1');
define('PFAI_PLUGIN_FILE', __FILE__);
define('PFAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PFAI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-activator.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-deactivator.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-employers.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-participants.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-ai-service.php';
require_once PFAI_PLUGIN_DIR . 'includes/class-pfai-plugin.php';

register_activation_hook(__FILE__, array('PFAI_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('PFAI_Deactivator', 'deactivate'));

(new PFAI_Plugin())->run();
