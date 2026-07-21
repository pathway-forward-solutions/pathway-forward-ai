<?php
if (!defined('ABSPATH')) exit;

$participant_post_type = post_type_exists('pfs_participant') ? 'pfs_participant' : 'pfai_participant';
$total_participants = (int) wp_count_posts($participant_post_type)->publish;

$active_participants = 0;
$needs_follow_up = 0;
$placements = 0;

$participant_ids = get_posts(array(
    'post_type'      => $participant_post_type,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
));

foreach ($participant_ids as $participant_id) {
    $status = strtolower((string) get_post_meta($participant_id, 'pfs_status', true));
    if ($status === '') {
        $status = strtolower((string) get_post_meta($participant_id, 'pfai_status', true));
    }

    if (in_array($status, array('active', 'enrolled', 'in progress', 'in-progress'), true)) {
        $active_participants++;
    }
    if (in_array($status, array('placed', 'employed', 'job placement'), true)) {
        $placements++;
    }

    $follow_up = (string) get_post_meta($participant_id, 'pfs_follow_up_date', true);
    if ($follow_up === '') {
        $follow_up = (string) get_post_meta($participant_id, 'pfai_follow_up_date', true);
    }
    if ($follow_up && strtotime($follow_up) <= strtotime('+7 days')) {
        $needs_follow_up++;
    }
}

$module_cards = array(
    array(
        'title' => 'Participants',
        'description' => 'Profiles, intake, career workspaces, documents, and progress.',
        'icon' => 'dashicons-groups',
        'url' => admin_url('edit.php?post_type=' . $participant_post_type),
        'status' => post_type_exists('pfs_participant') ? 'Connected' : 'AI records',
    ),
    array(
        'title' => 'Case Management',
        'description' => 'Follow-ups, participant notes, action items, and service coordination.',
        'icon' => 'dashicons-clipboard',
        'url' => admin_url('admin.php?page=pfai-follow-ups'),
        'status' => $needs_follow_up . ' due soon',
    ),
    array(
        'title' => 'Academy',
        'description' => 'Learning paths, lessons, resources, certificates, and progress.',
        'icon' => 'dashicons-welcome-learn-more',
        'url' => post_type_exists('pfs_lesson') ? admin_url('edit.php?post_type=pfs_lesson') : admin_url('plugins.php'),
        'status' => post_type_exists('pfs_lesson') ? 'Connected' : 'Check plugin',
    ),
    array(
        'title' => 'Employers',
        'description' => 'Employer relationships, contacts, opportunities, and placements.',
        'icon' => 'dashicons-building',
        'url' => admin_url('admin.php?page=pfai-employers'),
        'status' => 'Next sprint',
    ),
    array(
        'title' => 'AI Center',
        'description' => 'Career coaching, summaries, recommendations, and assisted workflows.',
        'icon' => 'dashicons-superhero-alt',
        'url' => admin_url('admin.php?page=pfai-ai-center'),
        'status' => 'Foundation ready',
    ),
    array(
        'title' => 'Reports',
        'description' => 'Outcome tracking, program metrics, and grant-ready reporting.',
        'icon' => 'dashicons-chart-bar',
        'url' => admin_url('admin.php?page=pfai-reports'),
        'status' => 'Available',
    ),
);

$page_title = 'Mission Control 2.0';
$page_subtitle = 'One command center for participants, case management, Academy, reporting, and AI.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';
?>

<div class="pfai-release-banner">
    <div>
        <span class="pfai-eyebrow">VERSION 1.0 DEVELOPMENT</span>
        <h2>Pathway Forward Solutions is now operating as one connected platform.</h2>
        <p>Mission Control detects and connects the modules already installed on this site.</p>
    </div>
    <span class="pfai-release-chip">Sprint 1</span>
</div>

<div class="pfai-grid pfai-kpis">
    <div class="pfai-kpi"><strong><?php echo esc_html($total_participants); ?></strong><small>Total Participants</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html($active_participants); ?></strong><small>Active Participants</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html($needs_follow_up); ?></strong><small>Follow-Ups Due Soon</small></div>
    <div class="pfai-kpi"><strong><?php echo esc_html($placements); ?></strong><small>Placements Recorded</small></div>
</div>

<section class="pfai-panel">
    <div class="pfai-section-heading">
        <div>
            <span class="pfai-eyebrow">PLATFORM MODULES</span>
            <h2>Work from one dashboard</h2>
        </div>
        <div class="pfai-actions"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pfai-follow-ups')); ?>">View Follow-Ups</a><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $participant_post_type)); ?>">Add Participant</a></div>
    </div>

    <div class="pfai-module-grid">
        <?php foreach ($module_cards as $module) : ?>
            <a class="pfai-module-card" href="<?php echo esc_url($module['url']); ?>">
                <span class="dashicons <?php echo esc_attr($module['icon']); ?>"></span>
                <div class="pfai-module-copy">
                    <div class="pfai-module-title-row">
                        <h3><?php echo esc_html($module['title']); ?></h3>
                        <span class="pfai-module-status"><?php echo esc_html($module['status']); ?></span>
                    </div>
                    <p><?php echo esc_html($module['description']); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 pfai-module-arrow"></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<div class="pfai-grid pfai-two">
    <section class="pfai-panel">
        <div class="pfai-section-heading">
            <div>
                <span class="pfai-eyebrow">RECENT ACTIVITY</span>
                <h2>Recent Participants</h2>
            </div>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $participant_post_type)); ?>">View all</a>
        </div>
        <?php
        $recent = get_posts(array(
            'post_type' => $participant_post_type,
            'post_status' => 'publish',
            'numberposts' => 5,
        ));
        if (!$recent) {
            echo '<div class="pfai-empty-state"><span class="dashicons dashicons-groups"></span><p>No participants have been added yet.</p></div>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Participant</th><th>Status</th><th>Last Updated</th></tr></thead><tbody>';
            foreach ($recent as $participant) {
                $status = get_post_meta($participant->ID, 'pfs_status', true);
                if (!$status) $status = get_post_meta($participant->ID, 'pfai_status', true);
                printf(
                    '<tr><td><a href="%s"><strong>%s</strong></a></td><td>%s</td><td>%s</td></tr>',
                    esc_url(get_edit_post_link($participant->ID)),
                    esc_html($participant->post_title),
                    esc_html($status ?: 'Not set'),
                    esc_html(get_the_modified_date(get_option('date_format'), $participant))
                );
            }
            echo '</tbody></table>';
        }
        ?>
    </section>

    <section class="pfai-panel">
        <span class="pfai-eyebrow">SYSTEM READINESS</span>
        <h2>Version 1.0 Status</h2>
        <ul class="pfai-status-list">
            <li><i class="good"></i><span><strong>Participant platform</strong><small><?php echo post_type_exists('pfs_participant') ? 'Connected to existing records' : 'AI participant records active'; ?></small></span></li>
            <li><i class="<?php echo post_type_exists('pfs_lesson') ? 'good' : 'pending'; ?>"></i><span><strong>PFS Academy</strong><small><?php echo post_type_exists('pfs_lesson') ? 'Connected' : 'Plugin needs verification'; ?></small></span></li>
            <li><i class="good"></i><span><strong>Mission Control</strong><small>Version 2.0 active</small></span></li>
            <li><i class="pending"></i><span><strong>Live AI provider</strong><small>API connection comes next</small></span></li>
        </ul>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pathway-forward-ai-settings')); ?>">Open Settings</a>
    </section>
</div>

<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
