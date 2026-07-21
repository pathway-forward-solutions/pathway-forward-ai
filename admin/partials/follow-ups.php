<?php
if (!defined('ABSPATH')) exit;

$post_type = post_type_exists('pfs_participant') ? 'pfs_participant' : 'pfai_participant';
$today = current_time('Y-m-d');
$cutoff = wp_date('Y-m-d', strtotime('+30 days', current_time('timestamp')));

$ids = get_posts(array(
    'post_type' => $post_type,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
));

$rows = array();
foreach ($ids as $id) {
    $date = (string) get_post_meta($id, 'pfs_follow_up_date', true);
    if ($date === '') $date = (string) get_post_meta($id, 'pfai_follow_up_date', true);
    if (!$date || $date > $cutoff) continue;

    $status = (string) get_post_meta($id, 'pfs_status', true);
    if ($status === '') $status = (string) get_post_meta($id, 'pfai_status', true);

    $case_manager = (string) get_post_meta($id, 'pfs_case_manager', true);
    if ($case_manager === '') $case_manager = (string) get_post_meta($id, 'pfai_case_manager', true);

    $rows[] = array(
        'id' => $id,
        'name' => get_the_title($id),
        'date' => $date,
        'status' => $status ?: 'Not set',
        'case_manager' => $case_manager ?: 'Unassigned',
        'overdue' => $date < $today,
        'today' => $date === $today,
    );
}

usort($rows, function($a, $b){ return strcmp($a['date'], $b['date']); });
$overdue = count(array_filter($rows, function($r){ return $r['overdue']; }));
$due_today = count(array_filter($rows, function($r){ return $r['today']; }));

$page_title = 'Participant Follow-Up Queue';
$page_subtitle = 'See overdue and upcoming participant follow-ups in one place.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>

<div class="pfai-grid pfai-kpis">
    <div class="pfai-kpi"><strong><?php echo esc_html($overdue); ?></strong><small>Overdue</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html($due_today); ?></strong><small>Due Today</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html(count($rows)); ?></strong><small>Due Within 30 Days</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html(count($ids)); ?></strong><small>Total Participants</small></div>
</div>

<section class="pfai-panel">
    <div class="pfai-section-heading">
        <div><span class="pfai-eyebrow">CASE MANAGEMENT</span><h2>Follow-Up Queue</h2></div>
        <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>">Add Participant</a>
    </div>

    <?php if (!$rows) : ?>
        <div class="pfai-empty-state"><span class="dashicons dashicons-yes-alt"></span><p>No participant follow-ups are due within the next 30 days.</p></div>
    <?php else : ?>
        <div class="pfai-table-scroll">
        <table class="widefat striped pfai-followup-table">
            <thead><tr><th>Due Date</th><th>Participant</th><th>Status</th><th>Case Manager</th><th>Priority</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row) :
                $priority = $row['overdue'] ? 'Overdue' : ($row['today'] ? 'Due today' : 'Upcoming');
                $priority_class = $row['overdue'] ? 'danger' : ($row['today'] ? 'warning' : 'neutral');
            ?>
                <tr>
                    <td><strong><?php echo esc_html(wp_date(get_option('date_format'), strtotime($row['date']))); ?></strong></td>
                    <td><a href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>"><strong><?php echo esc_html($row['name']); ?></strong></a></td>
                    <td><?php echo esc_html($row['status']); ?></td>
                    <td><?php echo esc_html($row['case_manager']); ?></td>
                    <td><span class="pfai-priority pfai-priority-<?php echo esc_attr($priority_class); ?>"><?php echo esc_html($priority); ?></span></td>
                    <td><a class="button button-small" href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>">Open Record</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
