<?php
$titles = array(
    'pfai-ai-coach' => 'AI Career Coach',
    'pfai-employers' => 'Employers',
    'pfai-ai-center' => 'AI Center',
);
$page_title = isset($titles[$page_slug]) ? $titles[$page_slug] : 'Coming Soon';
$page_subtitle = 'This module is planned for a future release.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>
<section class="pfai-panel"><h2><?php echo esc_html($page_title); ?></h2><p class="pfai-muted">The foundation is in place. Functional tools will be added in the next phase.</p></section>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
