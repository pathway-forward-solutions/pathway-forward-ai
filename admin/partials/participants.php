<?php
if (!defined('ABSPATH')) exit;

$post_type = post_type_exists('pfs_participant') ? 'pfs_participant' : 'pfai_participant';
$participants = get_posts(array(
    'post_type' => $post_type,
    'post_status' => 'publish',
    'posts_per_page' => 100,
    'orderby' => 'title',
    'order' => 'ASC',
));
$counts = PFAI_Participants::counts();
$page_title = 'Participants';
$page_subtitle = 'Open a participant workspace or manage intake records.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>
<div class="pfai-grid pfai-kpis">
<div class="pfai-kpi"><strong><?php echo esc_html($counts['total']); ?></strong><small>Total</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['active']); ?></strong><small>Active</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['new_this_month']); ?></strong><small>New This Month</small></div>
<div class="pfai-kpi"><strong><?php echo esc_html($counts['placed']); ?></strong><small>Placed</small></div>
</div>
<section class="pfai-panel">
<div class="pfai-section-heading">
<div><span class="pfai-eyebrow">PARTICIPANT MODULE</span><h2>Participant Directory</h2></div>
<a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>">Add Participant</a>
</div>
<?php if (!$participants) : ?>
<div class="pfai-empty-state"><span class="dashicons dashicons-groups"></span><p>No participants have been added yet.</p></div>
<?php else : ?>
<div class="pfai-participant-directory">
<?php foreach ($participants as $participant) :
    $status = get_post_meta($participant->ID, 'pfs_status', true) ?: get_post_meta($participant->ID, 'pfai_status', true) ?: 'Not set';
    $program = get_post_meta($participant->ID, 'pfs_program', true) ?: get_post_meta($participant->ID, 'pfai_program', true) ?: 'Program not set';
    $manager = get_post_meta($participant->ID, 'pfs_case_manager', true) ?: get_post_meta($participant->ID, 'pfai_case_manager', true) ?: 'Unassigned';
    $workspace = add_query_arg(array('page'=>'pfai-participant-workspace','participant_id'=>$participant->ID), admin_url('admin.php'));
?>
<article class="pfai-person-card">
<div class="pfai-avatar"><?php echo esc_html(strtoupper(substr($participant->post_title, 0, 1))); ?></div>
<div class="pfai-person-main"><h3><?php echo esc_html($participant->post_title); ?></h3><p><?php echo esc_html($program); ?> · <?php echo esc_html($manager); ?></p></div>
<span class="pfai-status-badge pfai-status-<?php echo esc_attr(sanitize_title($status)); ?>"><?php echo esc_html($status); ?></span>
<a class="button" href="<?php echo esc_url($workspace); ?>">Open Workspace</a>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
