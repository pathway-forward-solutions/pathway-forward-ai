<?php
if (!defined('ABSPATH')) exit;
$page_title = 'AI Settings';
$page_subtitle = 'Secure server-side AI provider configuration and organization settings.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>
<section class="pfai-panel">
<form method="post" action="options.php">
<?php
settings_fields('pfai_settings_group');
do_settings_sections('pathway-forward-ai-settings');
submit_button('Save Settings');
?>
</form>
</section>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
