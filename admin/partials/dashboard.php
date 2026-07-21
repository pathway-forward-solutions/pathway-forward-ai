<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pfai-wrap">
    <div class="pfai-hero">
        <div>
            <p class="pfai-kicker"><?php echo esc_html__('Pathway Forward AI', 'pathway-forward-ai'); ?></p>
            <h1><?php echo esc_html__('Mission Control', 'pathway-forward-ai'); ?></h1>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        __('Welcome to the workforce-development platform for %s.', 'pathway-forward-ai'),
                        $organization_name
                    )
                );
                ?>
            </p>
        </div>
        <span class="pfai-version"><?php echo esc_html('v' . PFAI_VERSION); ?></span>
    </div>

    <div class="pfai-grid pfai-stats">
        <div class="pfai-card">
            <span class="dashicons dashicons-groups"></span>
            <div><strong>0</strong><span><?php echo esc_html__('Participants', 'pathway-forward-ai'); ?></span></div>
        </div>
        <div class="pfai-card">
            <span class="dashicons dashicons-businessperson"></span>
            <div><strong>0</strong><span><?php echo esc_html__('Case Managers', 'pathway-forward-ai'); ?></span></div>
        </div>
        <div class="pfai-card">
            <span class="dashicons dashicons-building"></span>
            <div><strong><?php echo esc_html((int) $stats['employers']); ?></strong><span><?php echo esc_html__('Employers', 'pathway-forward-ai'); ?></span></div>
        </div>
        <div class="pfai-card">
            <span class="dashicons dashicons-portfolio"></span>
            <div><strong><?php echo esc_html((int) $stats['active']); ?></strong><span><?php echo esc_html__('Active Partnerships', 'pathway-forward-ai'); ?></span></div>
        </div>
    </div>

    <div class="pfai-grid pfai-main-grid">
        <section class="pfai-panel">
            <h2><?php echo esc_html__('Quick Actions', 'pathway-forward-ai'); ?></h2>
            <div class="pfai-actions">
                <button class="button button-primary" disabled><?php echo esc_html__('Add Participant', 'pathway-forward-ai'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pathway-forward-ai-employers')); ?>"><?php echo esc_html__('Manage Employers', 'pathway-forward-ai'); ?></a>
                <button class="button" disabled><?php echo esc_html__('Open AI Career Coach', 'pathway-forward-ai'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pathway-forward-ai-settings')); ?>">
                    <?php echo esc_html__('Settings', 'pathway-forward-ai'); ?>
                </a>
            </div>
            <p class="description"><?php echo esc_html__('Employer CRM records now power the dashboard with live partnership and follow-up statistics.', 'pathway-forward-ai'); ?></p>
        </section>

        <section class="pfai-panel">
            <h2><?php echo esc_html__('System Status', 'pathway-forward-ai'); ?></h2>
            <ul class="pfai-status-list">
                <li><span class="pfai-dot is-good"></span><?php echo esc_html__('Plugin active', 'pathway-forward-ai'); ?></li>
                <li><span class="pfai-dot is-good"></span><?php echo esc_html__('WordPress connection ready', 'pathway-forward-ai'); ?></li>
                <li><span class="pfai-dot is-good"></span><?php echo esc_html(sprintf(__('Employer CRM records: %d', 'pathway-forward-ai'), (int) $stats['employers'])); ?></li>
                <li><span class="pfai-dot is-pending"></span><?php echo esc_html__('AI provider not connected', 'pathway-forward-ai'); ?></li>
            </ul>
        </section>
    </div>

    <section class="pfai-panel">
        <h2><?php echo esc_html__('Today’s Setup Checklist', 'pathway-forward-ai'); ?></h2>
        <label class="pfai-check"><input type="checkbox" checked disabled> <?php echo esc_html__('Install and activate Pathway Forward AI', 'pathway-forward-ai'); ?></label>
        <label class="pfai-check"><input type="checkbox" disabled> <?php echo esc_html__('Review organization settings', 'pathway-forward-ai'); ?></label>
        <label class="pfai-check"><input type="checkbox" disabled> <?php echo esc_html__('Add the first participant', 'pathway-forward-ai'); ?></label>
    </section>
</div>
