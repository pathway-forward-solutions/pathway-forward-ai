<?php
$counts = PFAI_Participants::counts();
$page_title = 'Reports';
$page_subtitle = 'Live participant and placement metrics.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>
<div class="pfai-grid pfai-kpis">
<div class="pfai-kpi"><strong><?php echo esc_html($counts['total']); ?></strong><small>Total Participants</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['active']); ?></strong><small>Active</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['follow_up']); ?></strong><small>Needs Follow-Up</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['placed']); ?></strong><small>Placed</small></div>
</div>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
