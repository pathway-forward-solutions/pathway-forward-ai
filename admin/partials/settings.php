<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pfai-wrap">
    <div class="pfai-hero pfai-hero-small">
        <div>
            <p class="pfai-kicker"><?php echo esc_html__('Pathway Forward AI', 'pathway-forward-ai'); ?></p>
            <h1><?php echo esc_html__('Settings', 'pathway-forward-ai'); ?></h1>
            <p><?php echo esc_html__('Configure your organization’s basic platform details.', 'pathway-forward-ai'); ?></p>
        </div>
    </div>

    <section class="pfai-panel">
        <form method="post" action="options.php">
            <?php
            settings_fields('pfai_settings_group');
            do_settings_sections('pathway-forward-ai-settings');
            submit_button();
            ?>
        </form>
    </section>
</div>
