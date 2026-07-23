<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'AI Center';
$page_subtitle = 'Live AI Service Navigator for participant support and guided workforce pathways.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>

<section class="pfai-panel">
    <div class="pfai-section-heading">
        <div>
            <span class="pfai-eyebrow">AI SERVICE NAVIGATOR</span>
            <h2>Navigator Workspace</h2>
        </div>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pathway-forward-ai-settings')); ?>">Open AI Settings</a>
    </div>

    <?php if (!PFAI_AI_Service::is_configured()) : ?>
        <div class="notice notice-warning" style="margin:12px 0;">
            <p><strong>AI provider not configured.</strong> Add provider credentials in Settings to enable live responses. The navigator remains available and can still route support requests.</p>
        </div>
    <?php endif; ?>

    <p class="pfai-muted">Resume and Interview Preparation is the first live pathway in v0.9.2. Other service pathways remain available with foundational guidance.</p>

    <?php echo do_shortcode('[pfai_reemployment_services]'); ?>
</section>

<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
