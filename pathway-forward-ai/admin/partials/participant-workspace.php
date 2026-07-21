<?php
if (!defined('ABSPATH')) exit;

$post_type = post_type_exists('pfs_participant') ? 'pfs_participant' : 'pfai_participant';
$participant_id = isset($_GET['participant_id']) ? absint($_GET['participant_id']) : 0;
$participant = $participant_id ? get_post($participant_id) : null;
if ($participant && $participant->post_type !== $post_type) $participant = null;

$page_title = $participant ? $participant->post_title : 'Participant Workspace';
$page_subtitle = $participant ? 'A connected view of this participant’s progress, needs, and next actions.' : 'Choose a participant to open their workspace.';
include PFAI_PLUGIN_DIR . 'admin/partials/layout-start.php';

if (!$participant) {
    $participants = get_posts(array('post_type'=>$post_type,'post_status'=>'publish','posts_per_page'=>100,'orderby'=>'title','order'=>'ASC'));
    ?>
    <section class="pfai-panel"><h2>Select a participant</h2>
    <?php if (!$participants) : ?><p class="pfai-muted">No participants are available.</p>
    <?php else : ?><div class="pfai-participant-directory">
    <?php foreach ($participants as $item) : $url=add_query_arg(array('page'=>'pfai-participant-workspace','participant_id'=>$item->ID),admin_url('admin.php')); ?>
    <article class="pfai-person-card"><div class="pfai-avatar"><?php echo esc_html(strtoupper(substr($item->post_title,0,1))); ?></div><div class="pfai-person-main"><h3><?php echo esc_html($item->post_title); ?></h3><p>Open the complete participant workspace.</p></div><a class="button button-primary" href="<?php echo esc_url($url); ?>">Open Workspace</a></article>
    <?php endforeach; ?></div><?php endif; ?></section>
    <?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; return;
}

$workspace_url = add_query_arg(array('page'=>'pfai-participant-workspace','participant_id'=>$participant_id),admin_url('admin.php'));
$notice = '';
$notice_type = 'success';

// Save case-management activity directly to the participant record.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pfai_workspace_action'])) {
    check_admin_referer('pfai_workspace_' . $participant_id);
    $action = sanitize_key(wp_unslash($_POST['pfai_workspace_action']));
    $now = current_time('timestamp');
    $user = wp_get_current_user();

    if ($action === 'generate_ai_summary') {
        $notes_for_ai = array();
        $existing_notes = get_post_meta($participant_id, 'pfai_workspace_notes', true);
        if (is_array($existing_notes)) {
            foreach (array_slice($existing_notes, 0, 5) as $existing_note) {
                if (!empty($existing_note['text'])) $notes_for_ai[] = $existing_note['text'];
            }
        }
        $docs_for_ai = array();
        $existing_docs = get_post_meta($participant_id, 'pfai_workspace_documents', true);
        if (is_array($existing_docs)) {
            foreach ($existing_docs as $existing_doc) if (!empty($existing_doc['name'])) $docs_for_ai[] = $existing_doc['name'];
        }
        $get_for_ai = function($pfs_key, $pfai_key = '') use ($participant_id) {
            $value = get_post_meta($participant_id, $pfs_key, true);
            if ($value === '' && $pfai_key) $value = get_post_meta($participant_id, $pfai_key, true);
            return is_scalar($value) ? trim((string)$value) : '';
        };
        $ai_result = PFAI_AI_Service::generate_participant_summary($participant->post_title, array(
            'program' => $get_for_ai('pfs_program','pfai_program'),
            'status' => $get_for_ai('pfs_status','pfai_status'),
            'career_goal' => $get_for_ai('pfs_career_goal','pfai_career_goal'),
            'employment_status' => $get_for_ai('pfs_employment_status','pfai_employment_status'),
            'profile_completion' => absint($_POST['pfai_profile_completion'] ?? 0),
            'open_tasks' => absint($_POST['pfai_open_tasks'] ?? 0),
            'follow_up' => $get_for_ai('pfs_follow_up_date','pfai_follow_up_date'),
            'recent_notes' => $notes_for_ai,
            'document_names' => $docs_for_ai,
        ));
        if (is_wp_error($ai_result)) {
            $notice = $ai_result->get_error_message();
            $notice_type = 'error';
        } else {
            update_post_meta($participant_id, 'pfai_ai_generated_summary', wp_kses_post($ai_result));
            update_post_meta($participant_id, 'pfai_ai_generated_summary_time', $now);
            $notice = 'AI participant summary generated.';
        }
    } elseif ($action === 'add_note') {
        $note_text = isset($_POST['pfai_note']) ? sanitize_textarea_field(wp_unslash($_POST['pfai_note'])) : '';
        if ($note_text !== '') {
            $items = get_post_meta($participant_id, 'pfai_workspace_notes', true);
            if (!is_array($items)) $items = array();
            array_unshift($items, array('text'=>$note_text,'created'=>$now,'author'=>$user->display_name));
            update_post_meta($participant_id, 'pfai_workspace_notes', array_slice($items, 0, 100));
            $notice = 'Case note added.';
        } else { $notice = 'Enter a note before saving.'; $notice_type = 'error'; }
    } elseif ($action === 'add_task') {
        $task_text = isset($_POST['pfai_task']) ? sanitize_text_field(wp_unslash($_POST['pfai_task'])) : '';
        $due = isset($_POST['pfai_task_due']) ? sanitize_text_field(wp_unslash($_POST['pfai_task_due'])) : '';
        if ($task_text !== '') {
            $items = get_post_meta($participant_id, 'pfai_workspace_tasks', true);
            if (!is_array($items)) $items = array();
            array_unshift($items, array('id'=>wp_generate_uuid4(),'text'=>$task_text,'due'=>$due,'done'=>false,'created'=>$now));
            update_post_meta($participant_id, 'pfai_workspace_tasks', array_slice($items, 0, 100));
            $notice = 'Task added.';
        } else { $notice = 'Enter a task before saving.'; $notice_type = 'error'; }
    } elseif ($action === 'toggle_task') {
        $task_id = isset($_POST['pfai_task_id']) ? sanitize_text_field(wp_unslash($_POST['pfai_task_id'])) : '';
        $items = get_post_meta($participant_id, 'pfai_workspace_tasks', true);
        if (!is_array($items)) $items = array();
        foreach ($items as &$item) {
            if (isset($item['id']) && hash_equals((string)$item['id'], $task_id)) {
                $item['done'] = empty($item['done']);
                $item['completed'] = $item['done'] ? $now : 0;
                break;
            }
        }
        unset($item);
        update_post_meta($participant_id, 'pfai_workspace_tasks', $items);
        $notice = 'Task updated.';
    } elseif ($action === 'add_document') {
        $name = isset($_POST['pfai_document_name']) ? sanitize_text_field(wp_unslash($_POST['pfai_document_name'])) : '';

        if (empty($_FILES['pfai_document_file']['name'])) {
            $notice = 'Choose a file from your computer before uploading.';
            $notice_type = 'error';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('pfai_document_file', $participant_id, array(), array(
                'test_form' => false,
                'mimes' => array(
                    'pdf'          => 'application/pdf',
                    'doc'          => 'application/msword',
                    'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls'          => 'application/vnd.ms-excel',
                    'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                ),
            ));

            if (is_wp_error($attachment_id)) {
                $notice = 'Upload failed: ' . $attachment_id->get_error_message();
                $notice_type = 'error';
            } else {
                $url = wp_get_attachment_url($attachment_id);
                $path = get_attached_file($attachment_id);
                $display_name = $name !== '' ? $name : get_the_title($attachment_id);
                if ($display_name === '' && $path) $display_name = wp_basename($path);
                if ($display_name === '') $display_name = 'Participant document';

                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_title' => $display_name,
                ));

                $items = get_post_meta($participant_id, 'pfai_workspace_documents', true);
                if (!is_array($items)) $items = array();
                array_unshift($items, array(
                    'name'          => $display_name,
                    'url'           => $url,
                    'attachment_id' => $attachment_id,
                    'mime'          => get_post_mime_type($attachment_id),
                    'created'       => $now,
                ));
                update_post_meta($participant_id, 'pfai_workspace_documents', array_slice($items, 0, 100));
                $notice = 'Document uploaded successfully.';
            }
        }
    }
}

$get = function($pfs_key, $pfai_key = '') use ($participant_id) {
    $value = get_post_meta($participant_id, $pfs_key, true);
    if ($value === '' && $pfai_key) $value = get_post_meta($participant_id, $pfai_key, true);
    return is_scalar($value) ? trim((string)$value) : '';
};
$status = $get('pfs_status','pfai_status') ?: 'Not set';
$program = $get('pfs_program','pfai_program') ?: 'Program not set';
$manager = $get('pfs_case_manager','pfai_case_manager') ?: 'Unassigned';
$email = $get('pfs_email','pfai_email');
$phone = $get('pfs_phone','pfai_phone');
$goal = $get('pfs_career_goal','pfai_career_goal') ?: 'Career goal has not been recorded.';
$employment = $get('pfs_employment_status','pfai_employment_status') ?: 'Not recorded';
$follow_up = $get('pfs_follow_up_date','pfai_follow_up_date');
$legacy_notes = $get('pfs_case_notes','pfai_case_notes');
$legacy_docs = get_post_meta($participant_id,'pfs_documents',true);
if (!is_array($legacy_docs)) $legacy_docs = get_post_meta($participant_id,'pfai_documents',true);
if (!is_array($legacy_docs)) $legacy_docs = array();

$case_notes = get_post_meta($participant_id, 'pfai_workspace_notes', true);
if (!is_array($case_notes)) $case_notes = array();
$tasks = get_post_meta($participant_id, 'pfai_workspace_tasks', true);
if (!is_array($tasks)) $tasks = array();
$workspace_docs = get_post_meta($participant_id, 'pfai_workspace_documents', true);
if (!is_array($workspace_docs)) $workspace_docs = array();

$open_tasks = 0;
foreach ($tasks as $task) if (empty($task['done'])) $open_tasks++;
$document_count = count($legacy_docs) + count($workspace_docs);

$completed = 0;
$checks = array($status !== 'Not set', $program !== 'Program not set', $manager !== 'Unassigned', (bool)$email, (bool)$phone, $goal !== 'Career goal has not been recorded.', $employment !== 'Not recorded', (bool)$follow_up, $document_count > 0, (bool)$legacy_notes || !empty($case_notes));
foreach ($checks as $check) if ($check) $completed++;
$progress = $completed * 10;

$needs = array();
if (!$email || !$phone) $needs[] = 'Complete contact information';
if ($goal === 'Career goal has not been recorded.') $needs[] = 'Define a career goal';
if (!$follow_up) $needs[] = 'Schedule a follow-up';
if ($document_count === 0) $needs[] = 'Add a résumé or supporting document';
if ($employment === 'Not recorded' || strtolower($employment) === 'unemployed') $needs[] = 'Review employment and placement readiness';
if ($open_tasks > 0) $needs[] = sprintf('Complete %d open case-management task%s', $open_tasks, $open_tasks === 1 ? '' : 's');
$next_action = $needs ? $needs[0] : 'Review progress and prepare the next advancement step';
$risk = (!$follow_up || $status === 'Needs Follow-Up') ? 'Attention' : ($progress < 60 ? 'Developing' : 'On Track');
$summary = sprintf('%s is enrolled in %s and is currently marked %s. The participant profile is %d%% complete, with %d open task%s. The recommended next action is to %s.', $participant->post_title, $program, $status, $progress, $open_tasks, $open_tasks === 1 ? '' : 's', strtolower($next_action));
$ai_generated_summary = get_post_meta($participant_id, 'pfai_ai_generated_summary', true);
$ai_generated_time = (int) get_post_meta($participant_id, 'pfai_ai_generated_summary_time', true);
$edit_url = get_edit_post_link($participant_id);

$timeline = array();
foreach ($case_notes as $note) $timeline[] = array('time'=>isset($note['created'])?(int)$note['created']:0,'type'=>'note','title'=>'Case note added','detail'=>isset($note['text'])?$note['text']:'');
foreach ($tasks as $task) {
    $timeline[] = array('time'=>isset($task['created'])?(int)$task['created']:0,'type'=>'task','title'=>'Task created','detail'=>isset($task['text'])?$task['text']:'');
    if (!empty($task['completed'])) $timeline[] = array('time'=>(int)$task['completed'],'type'=>'complete','title'=>'Task completed','detail'=>isset($task['text'])?$task['text']:'');
}
foreach ($workspace_docs as $doc) $timeline[] = array('time'=>isset($doc['created'])?(int)$doc['created']:0,'type'=>'document','title'=>'Document added','detail'=>isset($doc['name'])?$doc['name']:'Participant document');
usort($timeline, function($a,$b){ return $b['time'] <=> $a['time']; });
$timeline = array_slice($timeline, 0, 12);
?>
<?php if ($notice) : ?><div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
<div class="pfai-workspace-header">
    <div class="pfai-avatar pfai-avatar-large"><?php echo esc_html(strtoupper(substr($participant->post_title,0,1))); ?></div>
    <div class="pfai-workspace-identity"><span class="pfai-eyebrow">PARTICIPANT WORKSPACE</span><h2><?php echo esc_html($participant->post_title); ?></h2><p><?php echo esc_html($program); ?> · Case manager: <?php echo esc_html($manager); ?></p></div>
    <span class="pfai-status-badge pfai-status-<?php echo esc_attr(sanitize_title($status)); ?>"><?php echo esc_html($status); ?></span>
    <a class="button" href="<?php echo esc_url($edit_url); ?>">Edit Record</a>
</div>
<div class="pfai-workspace-grid">
<section class="pfai-panel pfai-ai-summary">
<div class="pfai-section-heading"><div><span class="pfai-eyebrow">AI PARTICIPANT INTELLIGENCE</span><h2>Participant Snapshot</h2></div>
<form method="post" action="<?php echo esc_url($workspace_url); ?>">
<?php wp_nonce_field('pfai_workspace_' . $participant_id); ?><input type="hidden" name="pfai_workspace_action" value="generate_ai_summary"><input type="hidden" name="pfai_profile_completion" value="<?php echo esc_attr($progress); ?>"><input type="hidden" name="pfai_open_tasks" value="<?php echo esc_attr($open_tasks); ?>"><button class="button button-primary" type="submit"><?php echo $ai_generated_summary ? 'Refresh AI Summary' : 'Generate AI Summary'; ?></button>
</form></div>
<?php if ($ai_generated_summary) : ?><div class="pfai-ai-generated-output"><?php echo wpautop(esc_html($ai_generated_summary)); ?></div><?php if ($ai_generated_time) : ?><p class="pfai-muted">Generated <?php echo esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), $ai_generated_time)); ?></p><?php endif; ?>
<?php else : ?><p class="pfai-summary-copy"><?php echo esc_html($summary); ?></p><p class="pfai-muted">This is the rules-based summary. Connect OpenAI in Settings, then generate the AI version.</p><?php endif; ?>
<div class="pfai-recommendation"><span class="dashicons dashicons-lightbulb"></span><div><strong>Next Best Action</strong><p><?php echo esc_html($next_action); ?></p></div></div>
</section>
<section class="pfai-panel">
<span class="pfai-eyebrow">READINESS</span><h2><?php echo esc_html($progress); ?>% Profile Complete</h2>
<div class="pfai-progress"><span style="width:<?php echo esc_attr($progress); ?>%"></span></div>
<div class="pfai-risk-row"><span>Progress status</span><strong><?php echo esc_html($risk); ?></strong></div>
</section>
</div>
<div class="pfai-workspace-grid pfai-workspace-grid-3">
<section class="pfai-panel"><span class="pfai-eyebrow">CONTACT</span><h2>Contact Details</h2><dl class="pfai-detail-list"><dt>Email</dt><dd><?php echo esc_html($email ?: 'Not recorded'); ?></dd><dt>Phone</dt><dd><?php echo esc_html($phone ?: 'Not recorded'); ?></dd><dt>Follow-up</dt><dd><?php echo esc_html($follow_up ? wp_date(get_option('date_format'),strtotime($follow_up)) : 'Not scheduled'); ?></dd></dl></section>
<section class="pfai-panel"><span class="pfai-eyebrow">CAREER</span><h2>Career Goal</h2><p><?php echo nl2br(esc_html($goal)); ?></p><dl class="pfai-detail-list"><dt>Employment</dt><dd><?php echo esc_html($employment); ?></dd></dl></section>
<section class="pfai-panel"><span class="pfai-eyebrow">CASELOAD</span><h2><?php echo esc_html($open_tasks); ?> Open Tasks</h2><p><?php echo esc_html(count($case_notes)); ?> workspace notes · <?php echo esc_html($document_count); ?> documents</p></section>
</div>

<div class="pfai-workspace-grid">
<section class="pfai-panel">
<div class="pfai-section-heading"><div><span class="pfai-eyebrow">TASKS</span><h2>Case Management Tasks</h2></div><span class="pfai-count-chip"><?php echo esc_html($open_tasks); ?> open</span></div>
<form method="post" action="<?php echo esc_url($workspace_url); ?>" class="pfai-inline-form">
<?php wp_nonce_field('pfai_workspace_' . $participant_id); ?><input type="hidden" name="pfai_workspace_action" value="add_task">
<input type="text" name="pfai_task" placeholder="Add a task, such as call participant" required><input type="date" name="pfai_task_due"><button class="button button-primary" type="submit">Add Task</button>
</form>
<?php if (!$tasks) : ?><p class="pfai-muted">No tasks have been added.</p><?php else : ?><div class="pfai-task-list">
<?php foreach ($tasks as $task) : ?>
<form method="post" action="<?php echo esc_url($workspace_url); ?>" class="pfai-task-row <?php echo !empty($task['done']) ? 'is-done' : ''; ?>">
<?php wp_nonce_field('pfai_workspace_' . $participant_id); ?><input type="hidden" name="pfai_workspace_action" value="toggle_task"><input type="hidden" name="pfai_task_id" value="<?php echo esc_attr(isset($task['id'])?$task['id']:''); ?>">
<button type="submit" class="pfai-task-toggle" aria-label="Toggle task"><span class="dashicons <?php echo !empty($task['done']) ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span></button>
<div><strong><?php echo esc_html(isset($task['text'])?$task['text']:'Task'); ?></strong><?php if (!empty($task['due'])) : ?><small>Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($task['due']))); ?></small><?php endif; ?></div>
</form>
<?php endforeach; ?></div><?php endif; ?>
</section>

<section class="pfai-panel">
<span class="pfai-eyebrow">CASE NOTES</span><h2>Add a Note</h2>
<form method="post" action="<?php echo esc_url($workspace_url); ?>">
<?php wp_nonce_field('pfai_workspace_' . $participant_id); ?><input type="hidden" name="pfai_workspace_action" value="add_note">
<textarea name="pfai_note" rows="4" class="large-text" placeholder="Record participant contact, progress, barriers, or follow-up details" required></textarea><p><button class="button button-primary" type="submit">Save Case Note</button></p>
</form>
<?php if ($legacy_notes) : ?><div class="pfai-note-card"><strong>Existing participant notes</strong><p><?php echo nl2br(esc_html($legacy_notes)); ?></p></div><?php endif; ?>
<?php foreach (array_slice($case_notes,0,5) as $note) : ?><article class="pfai-note-card"><div class="pfai-note-meta"><?php echo esc_html(!empty($note['author'])?$note['author']:'Staff'); ?> · <?php echo esc_html(!empty($note['created'])?wp_date(get_option('date_format').' '.get_option('time_format'),(int)$note['created']):''); ?></div><p><?php echo nl2br(esc_html(isset($note['text'])?$note['text']:'')); ?></p></article><?php endforeach; ?>
</section>
</div>

<div class="pfai-workspace-grid">
<section class="pfai-panel">
<div class="pfai-section-heading"><div><span class="pfai-eyebrow">DOCUMENTS</span><h2>Participant Documents</h2></div><span class="pfai-count-chip"><?php echo esc_html($document_count); ?> files</span></div>
<form method="post" action="<?php echo esc_url($workspace_url); ?>" class="pfai-document-form" enctype="multipart/form-data">
<?php wp_nonce_field('pfai_workspace_' . $participant_id); ?><input type="hidden" name="pfai_workspace_action" value="add_document">
<input type="text" name="pfai_document_name" placeholder="Document name, such as Resume"><input type="file" name="pfai_document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required><button class="button button-primary" type="submit">Upload File</button>
</form>
<p class="pfai-muted pfai-upload-help">Allowed: PDF, Word, Excel, JPG, and PNG. Files are stored in the WordPress Media Library.</p>
<ul class="pfai-document-list">
<?php foreach($workspace_docs as $doc): ?><li><span class="dashicons dashicons-media-document"></span><a href="<?php echo esc_url(isset($doc['url'])?$doc['url']:''); ?>" target="_blank" rel="noopener"><?php echo esc_html(isset($doc['name'])?$doc['name']:'Participant document'); ?></a></li><?php endforeach; ?>
<?php foreach($legacy_docs as $i=>$url): ?><li><span class="dashicons dashicons-media-document"></span><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Document <?php echo esc_html($i+1); ?></a></li><?php endforeach; ?>
</ul><?php if ($document_count === 0): ?><p class="pfai-muted">No documents have been added.</p><?php endif; ?>
</section>

<section class="pfai-panel">
<span class="pfai-eyebrow">TIMELINE</span><h2>Recent Activity</h2>
<?php if (!$timeline) : ?><p class="pfai-muted">Activity will appear as staff add tasks, notes, and documents.</p><?php else : ?><div class="pfai-timeline">
<?php foreach ($timeline as $event) : ?><article class="pfai-timeline-item"><span class="pfai-timeline-dot"></span><div><strong><?php echo esc_html($event['title']); ?></strong><p><?php echo esc_html($event['detail']); ?></p><small><?php echo esc_html($event['time'] ? wp_date(get_option('date_format').' '.get_option('time_format'), $event['time']) : ''); ?></small></div></article><?php endforeach; ?>
</div><?php endif; ?>
</section>
</div>

<section class="pfai-panel"><div class="pfai-section-heading"><div><span class="pfai-eyebrow">ACTION PLAN</span><h2>Needs and Next Steps</h2></div><a class="button button-primary" href="<?php echo esc_url($edit_url); ?>">Update Participant</a></div>
<?php if (!$needs): ?><div class="pfai-empty-state"><span class="dashicons dashicons-yes-alt"></span><p>The core participant profile is complete. Review outcomes and advancement opportunities.</p></div><?php else: ?><div class="pfai-needs-grid"><?php foreach($needs as $need): ?><div class="pfai-need-card"><span class="dashicons dashicons-arrow-right-alt2"></span><strong><?php echo esc_html($need); ?></strong></div><?php endforeach; ?></div><?php endif; ?>
</section>
<?php include PFAI_PLUGIN_DIR . 'admin/partials/layout-end.php'; ?>
