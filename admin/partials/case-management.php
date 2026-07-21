<?php
$page_title = 'Case Management';
$page_subtitle = 'Review participant status, follow-up dates, and assigned case managers.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';

$participants = get_posts(array(
    'post_type'=>'pfai_participant',
    'post_status'=>'publish',
    'numberposts'=>-1,
    'meta_key'=>'pfai_follow_up_date',
    'orderby'=>'meta_value',
    'order'=>'ASC'
));
?>
<section class="pfai-panel">
<h2>Follow-Up Queue</h2>
<?php if (!$participants) : ?>
<p class="pfai-muted">No participant follow-ups are scheduled.</p>
<?php else : ?>
<table class="widefat striped">
<thead><tr><th>Participant</th><th>Case Manager</th><th>Status</th><th>Follow-Up Date</th></tr></thead>
<tbody>
<?php foreach ($participants as $participant) : ?>
<tr>
<td><a href="<?php echo esc_url(get_edit_post_link($participant->ID)); ?>"><?php echo esc_html($participant->post_title); ?></a></td>
<td><?php echo esc_html(get_post_meta($participant->ID,'pfai_case_manager',true) ?: '—'); ?></td>
<td><?php echo esc_html(get_post_meta($participant->ID,'pfai_status',true) ?: '—'); ?></td>
<td><?php echo esc_html(get_post_meta($participant->ID,'pfai_follow_up_date',true) ?: '—'); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</section>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
