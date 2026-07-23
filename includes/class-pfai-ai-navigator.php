<?php
if (!defined('ABSPATH')) {
    exit;
}

class PFAI_AI_Navigator {
    private static $assets_enqueued = false;

    public static function register_hooks() {
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade'));
        add_action('init', array(__CLASS__, 'maybe_cleanup_retention'));

        add_action('wp_ajax_pfai_ai_navigator_chat', array(__CLASS__, 'ajax_chat'));
        add_action('wp_ajax_pfai_ai_navigator_escalate', array(__CLASS__, 'ajax_escalate'));
    }

    public static function register_shortcodes() {
        add_shortcode('pfai_ai_assistant', array(__CLASS__, 'render_assistant_shortcode'));
        add_shortcode('pfai_reemployment_services', array(__CLASS__, 'render_reemployment_shortcode'));
    }

    public static function maybe_upgrade() {
        $stored = (string) get_option('pfai_ai_navigator_db_version', '');
        if (version_compare($stored, PFAI_DB_VERSION, '>=')) {
            return;
        }

        self::install_schema();
        update_option('pfai_ai_navigator_db_version', PFAI_DB_VERSION);
    }

    public static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $conversations = self::table_conversations();
        $messages = self::table_messages();
        $escalations = self::table_escalations();

        $sql_conversations = "CREATE TABLE IF NOT EXISTS $conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participant_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            service_context varchar(64) NOT NULL DEFAULT 'general',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY participant_id (participant_id),
            KEY user_id (user_id),
            KEY service_context (service_context),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        $sql_messages = "CREATE TABLE IF NOT EXISTS $messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role varchar(20) NOT NULL,
            content longtext NOT NULL,
            content_hash char(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY role (role),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_escalations = "CREATE TABLE IF NOT EXISTS $escalations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned DEFAULT NULL,
            participant_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            service_context varchar(64) NOT NULL DEFAULT 'general',
            reason varchar(120) NOT NULL DEFAULT '',
            urgency varchar(20) NOT NULL DEFAULT 'normal',
            summary text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY participant_id (participant_id),
            KEY user_id (user_id),
            KEY urgency (urgency),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_escalations);

        if (get_option('pfai_ai_retention_days', null) === null) {
            add_option('pfai_ai_retention_days', 90);
        }
        if (get_option('pfai_ai_rate_limit_per_hour', null) === null) {
            add_option('pfai_ai_rate_limit_per_hour', 20);
        }
    }

    public static function maybe_cleanup_retention() {
        if (!is_user_logged_in() && !wp_doing_cron()) {
            return;
        }

        $last = (int) get_option('pfai_ai_retention_cleanup_last', 0);
        if ($last > 0 && (time() - $last) < DAY_IN_SECONDS) {
            return;
        }

        $days = max(1, absint(get_option('pfai_ai_retention_days', 90)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        global $wpdb;
        $conversations = self::table_conversations();
        $messages = self::table_messages();
        $escalations = self::table_escalations();

        $old_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $conversations WHERE updated_at < %s", $cutoff));
        if (!empty($old_ids)) {
            $placeholders = implode(',', array_fill(0, count($old_ids), '%d'));

            // Preserve support-case history by detaching old conversation links instead of deleting escalations.
            $prepared = $wpdb->prepare("UPDATE $escalations SET conversation_id = NULL, updated_at = %s WHERE conversation_id IN ($placeholders)", array_merge(array(current_time('mysql')), $old_ids));
            $wpdb->query($prepared);

            $prepared = $wpdb->prepare("DELETE FROM $messages WHERE conversation_id IN ($placeholders)", $old_ids);
            $wpdb->query($prepared);

            $prepared = $wpdb->prepare("DELETE FROM $conversations WHERE id IN ($placeholders)", $old_ids);
            $wpdb->query($prepared);
        }

        update_option('pfai_ai_retention_cleanup_last', time());
    }

    public static function render_assistant_shortcode($atts) {
        $atts = shortcode_atts(array(
            'service_context' => 'general',
            'title' => 'AI Service Navigator',
            'welcome' => 'Welcome to Pathway Forward AI. Tell me how I can support your next step.',
            'suggested_prompts' => 'I need resume help|Help me prepare for an interview|How should I organize my job search?',
        ), $atts, 'pfai_ai_assistant');

        $service_context = sanitize_key($atts['service_context']);
        $title = sanitize_text_field($atts['title']);
        $welcome = sanitize_text_field($atts['welcome']);
        $prompts = array_filter(array_map('sanitize_text_field', explode('|', (string) $atts['suggested_prompts'])));

        self::enqueue_assets();

        $payload = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pfai_ai_navigator'),
            'configured' => PFAI_AI_Service::is_configured(),
            'isAuthenticated' => is_user_logged_in(),
            'supportEmail' => sanitize_email(get_option('pfai_support_email', get_option('admin_email'))),
            'supportUrl' => esc_url_raw(wp_lostpassword_url()),
            'retentionDays' => max(1, absint(get_option('pfai_ai_retention_days', 90))),
            'serviceContext' => $service_context,
        );

        wp_localize_script('pfai-public', 'PFAINavigator', $payload);

        $input_id = 'pfai-ai-input-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <section class="pfai-ai-navigator" data-service-context="<?php echo esc_attr($service_context); ?>" aria-label="<?php echo esc_attr($title); ?>">
            <header class="pfai-ai-header">
                <h2><?php echo esc_html($title); ?></h2>
                <p class="pfai-ai-subtitle"><?php echo esc_html($welcome); ?></p>
                <p class="pfai-ai-safety" role="note">This assistant provides workforce guidance only and cannot make legal, medical, financial, eligibility, hiring, or emergency decisions.</p>
            </header>
            <div class="pfai-ai-status" aria-live="polite"></div>
            <div class="pfai-ai-conversation" role="log" aria-live="polite" aria-relevant="additions text" tabindex="0">
                <article class="pfai-ai-message pfai-ai-message-assistant">
                    <p>Hello. I can help with service navigation and next-step planning. You can request human support at any time.</p>
                </article>
            </div>
            <?php if (!empty($prompts)) : ?>
                <div class="pfai-ai-prompts" aria-label="Suggested prompts">
                    <?php foreach ($prompts as $prompt) : ?>
                        <button type="button" class="pfai-ai-prompt"><?php echo esc_html($prompt); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form class="pfai-ai-form" novalidate>
                <label for="<?php echo esc_attr($input_id); ?>" class="screen-reader-text">Ask the AI Service Navigator</label>
                <textarea id="<?php echo esc_attr($input_id); ?>" class="pfai-ai-input" rows="3" maxlength="2500" placeholder="Type your question" required></textarea>
                <div class="pfai-ai-actions">
                    <button type="submit" class="pfai-ai-send button button-primary">Send</button>
                    <button type="button" class="pfai-ai-escalate button">Contact Support</button>
                </div>
            </form>
            <div class="pfai-ai-support" hidden>
                <p>Support is available. Use Contact Support to create a coordinator case. You can also email <a href="mailto:<?php echo esc_attr($payload['supportEmail']); ?>"><?php echo esc_html($payload['supportEmail']); ?></a>.</p>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_reemployment_shortcode($atts) {
        $contexts = array(
            'career-assessment-planning' => array(
                'label' => 'Career assessment and planning',
                'status' => 'Available with guided pathways',
            ),
            'resume-interview' => array(
                'label' => 'Resume and interview preparation',
                'status' => 'Pilot fully enabled in v0.9.1',
            ),
            'job-search-assistance' => array(
                'label' => 'Job search assistance',
                'status' => 'Available with foundational guidance',
            ),
            'retention-advancement' => array(
                'label' => 'Retention and advancement support',
                'status' => 'Available with foundational guidance',
            ),
        );

        self::enqueue_assets();

        $fallback_enabled = !PFAI_AI_Service::is_configured();
        $support_email = sanitize_email(get_option('pfai_support_email', get_option('admin_email')));
        $fallback_shortcode = '';
        foreach (array('pfai_reemployment_request_form', 'pfs_reemployment_request_form', 'pfs_request_services_form') as $candidate) {
            if (shortcode_exists($candidate)) {
                $fallback_shortcode = $candidate;
                break;
            }
        }
        $fallback_notice = self::handle_fallback_request_submission($contexts, $fallback_shortcode);

        ob_start();
        ?>
        <section class="pfai-reemployment-wrapper" aria-label="Reemployment Services Navigator">
            <header class="pfai-reemployment-header">
                <h2>Reemployment Services Navigator</h2>
                <p>Select a service area to launch AI guidance tailored to your request.</p>
            </header>
            <div class="pfai-service-cards" role="tablist" aria-label="Reemployment service options">
                <?php foreach ($contexts as $key => $context) : ?>
                    <button type="button" class="pfai-service-card<?php echo $key === 'resume-interview' ? ' is-active' : ''; ?>" data-service-context="<?php echo esc_attr($key); ?>" role="tab" aria-selected="<?php echo $key === 'resume-interview' ? 'true' : 'false'; ?>">
                        <strong><?php echo esc_html($context['label']); ?></strong>
                        <span><?php echo esc_html($context['status']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php echo do_shortcode('[pfai_ai_assistant service_context="resume-interview" title="Reemployment AI Navigator" welcome="Tell me whether you need resume, cover letter, job application, or interview help."]'); ?>

            <section class="pfai-request-fallback<?php echo $fallback_enabled ? '' : ' is-hidden'; ?>" aria-label="Fallback request form">
                <h3>Request Services (Fallback Form)</h3>
                <p>The AI assistant is currently unavailable. Submit this form and a coordinator will follow up.</p>
                <?php if (!empty($fallback_notice['message'])) : ?>
                    <p class="pfai-fallback-notice <?php echo esc_attr(!empty($fallback_notice['success']) ? 'is-success' : 'is-error'); ?>"><?php echo esc_html($fallback_notice['message']); ?></p>
                <?php endif; ?>
                <?php if ($fallback_shortcode) : ?>
                    <?php echo do_shortcode('[' . $fallback_shortcode . ']'); ?>
                <?php else : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('pfai_reemployment_fallback_submit', 'pfai_reemployment_fallback_nonce'); ?>
                    <input type="hidden" name="pfai_reemployment_fallback_action" value="submit">
                    <p>
                        <label for="pfai-request-name">Name</label>
                        <input id="pfai-request-name" type="text" name="pfai_request_name" autocomplete="name" required>
                    </p>
                    <p>
                        <label for="pfai-request-email">Email</label>
                        <input id="pfai-request-email" type="email" name="pfai_request_email" autocomplete="email" required>
                    </p>
                    <p>
                        <label for="pfai-request-service">Service Needed</label>
                        <select id="pfai-request-service" name="pfai_request_service">
                            <?php foreach ($contexts as $key => $context) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($context['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="pfai-request-details">Details</label>
                        <textarea id="pfai-request-details" name="pfai_request_details" rows="4" required></textarea>
                    </p>
                    <p><button type="submit" class="button button-primary">Submit Request</button></p>
                </form>
                <?php endif; ?>
                <p>Immediate support: <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a></p>
            </section>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function enqueue_assets() {
        if (self::$assets_enqueued) {
            return;
        }

        wp_enqueue_style('pfai-public', PFAI_PLUGIN_URL . 'public/css/public.css', array(), PFAI_VERSION);
        wp_enqueue_script('pfai-public', PFAI_PLUGIN_URL . 'public/js/public.js', array(), PFAI_VERSION, true);

        self::$assets_enqueued = true;
    }

    public static function ajax_chat() {
        if (!check_ajax_referer('pfai_ai_navigator', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Security validation failed. Refresh and try again.',
                'support' => true,
            ), 403);
        }

        if (!PFAI_AI_Service::is_configured()) {
            wp_send_json_error(array(
                'message' => 'AI is currently unavailable. Please use Contact Support.',
                'support' => true,
            ), 503);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'Please sign in to use AI navigation and preserve conversation privacy.',
                'support' => true,
            ), 401);
        }

        $user_id = get_current_user_id();
        if (!self::rate_limit_allows($user_id)) {
            wp_send_json_error(array(
                'message' => 'You reached the hourly message limit. Please try again later or contact support.',
                'support' => true,
            ), 429);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $service_context = sanitize_key(wp_unslash($_POST['service_context'] ?? 'general'));
        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));

        if ($message === '') {
            wp_send_json_error(array('message' => 'Enter a message before sending.'), 400);
        }

        if (self::contains_emergency_language($message)) {
            wp_send_json_success(array(
                'conversation_id' => $conversation_id,
                'response' => 'If you are in immediate danger or experiencing an emergency, call local emergency services right now. I can still help with workforce guidance after you are safe.',
                'support' => true,
                'safety' => true,
                'next_prompts' => array('I need resume feedback', 'I need interview help'),
            ));
        }

        $participant_id = self::resolve_participant_id_for_current_user();
        if ($participant_id <= 0) {
            wp_send_json_error(array(
                'message' => 'Your participant profile is not linked to this account yet. Please contact support.',
                'support' => true,
            ), 403);
        }

        if ($conversation_id > 0 && !self::owns_conversation($conversation_id, $participant_id, $user_id)) {
            wp_send_json_error(array(
                'message' => 'You do not have permission to access this conversation.',
                'support' => true,
            ), 403);
        }

        if ($conversation_id <= 0) {
            $conversation_id = self::create_conversation($participant_id, $user_id, $service_context);
        }

        if ($conversation_id <= 0) {
            wp_send_json_error(array(
                'message' => 'Unable to start a conversation right now. Please contact support.',
                'support' => true,
            ), 500);
        }

        self::append_message($conversation_id, 'user', $message);

        $history = self::get_messages($conversation_id, 20);
        $response = PFAI_AI_Service::generate_navigator_response($history, array(
            'service_context' => $service_context,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
                'support' => true,
                'conversation_id' => $conversation_id,
            ), 502);
        }

        $assistant_message = self::normalize_pilot_response($service_context, (string) $response, $history);
        self::append_message($conversation_id, 'assistant', $assistant_message);

        $next_prompts = self::build_next_prompts($service_context, $assistant_message);

        wp_send_json_success(array(
            'conversation_id' => $conversation_id,
            'response' => $assistant_message,
            'support' => true,
            'next_prompts' => $next_prompts,
        ));
    }

    public static function ajax_escalate() {
        if (!check_ajax_referer('pfai_ai_navigator', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security validation failed.'), 403);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in before requesting support escalation.'), 401);
        }

        $user_id = get_current_user_id();
        $participant_id = self::resolve_participant_id_for_current_user();
        if ($participant_id <= 0) {
            wp_send_json_error(array('message' => 'Your participant profile is not linked. Please contact support directly.'), 403);
        }

        $service_context = sanitize_key(wp_unslash($_POST['service_context'] ?? 'general'));
        $reason = sanitize_text_field(wp_unslash($_POST['reason'] ?? 'Participant requested human support'));
        $urgency = sanitize_key(wp_unslash($_POST['urgency'] ?? 'normal'));
        if (!in_array($urgency, array('low', 'normal', 'high'), true)) {
            $urgency = 'normal';
        }

        $conversation_id = absint(wp_unslash($_POST['conversation_id'] ?? 0));
        if ($conversation_id > 0 && !self::owns_conversation($conversation_id, $participant_id, $user_id)) {
            wp_send_json_error(array('message' => 'You do not have permission to escalate this conversation.'), 403);
        }

        $summary = self::build_conversation_summary($conversation_id);
        $escalation_id = self::create_escalation($conversation_id, $participant_id, $user_id, $service_context, $reason, $urgency, $summary);

        if ($escalation_id <= 0) {
            wp_send_json_error(array('message' => 'Support could not be contacted due to a system error. Please try again or email support.'), 500);
        }

        self::append_case_note($participant_id, $service_context, $reason, $urgency, $escalation_id);

        wp_send_json_success(array(
            'message' => 'Support request submitted successfully. A coordinator case was created.',
            'escalation_id' => $escalation_id,
        ));
    }

    private static function resolve_participant_id_for_current_user() {
        if (!is_user_logged_in()) {
            return 0;
        }

        $user = wp_get_current_user();
        if (!$user || empty($user->ID)) {
            return 0;
        }

        $linked = absint(get_user_meta($user->ID, 'pfai_participant_id', true));
        if ($linked > 0) {
            return $linked;
        }

        $email = sanitize_email($user->user_email);
        if ($email === '') {
            return 0;
        }

        $post_type = post_type_exists('pfs_participant') ? 'pfs_participant' : 'pfai_participant';
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'pfs_email',
                    'value' => $email,
                    'compare' => '=',
                ),
                array(
                    'key' => 'pfai_email',
                    'value' => $email,
                    'compare' => '=',
                ),
            ),
        ));

        $participant_id = !empty($query->posts) ? absint($query->posts[0]) : 0;
        if ($participant_id > 0) {
            update_user_meta($user->ID, 'pfai_participant_id', $participant_id);
        }

        return $participant_id;
    }

    private static function owns_conversation($conversation_id, $participant_id, $user_id) {
        global $wpdb;
        $table = self::table_conversations();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, participant_id, user_id FROM $table WHERE id = %d", $conversation_id));
        if (!$row) {
            return false;
        }

        return (int) $row->participant_id === (int) $participant_id && (int) $row->user_id === (int) $user_id;
    }

    private static function current_user_can_view_participant_case($participant_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user_id = get_current_user_id();
        if ((int) self::resolve_participant_id_for_current_user() === (int) $participant_id) {
            return true;
        }

        if ($participant_id <= 0) {
            return false;
        }

        $assigned = self::is_participant_assigned_to_user($participant_id, $current_user_id);
        return (bool) apply_filters('pfai_can_view_participant_ai_case', $assigned, $current_user_id, $participant_id);
    }

    private static function is_participant_assigned_to_user($participant_id, $user_id) {
        if ($participant_id <= 0 || $user_id <= 0) {
            return false;
        }

        $meta_user_ids = array('pfs_case_manager_user_id', 'pfai_case_manager_user_id');
        foreach ($meta_user_ids as $meta_key) {
            $assigned_user_id = absint(get_post_meta($participant_id, $meta_key, true));
            if ($assigned_user_id > 0) {
                return $assigned_user_id === $user_id;
            }
        }

        $manager_fields = array('pfs_case_manager', 'pfai_case_manager');
        $current_user = get_userdata($user_id);
        if (!$current_user) {
            return false;
        }

        $tokens = array_filter(array(
            sanitize_text_field((string) $current_user->display_name),
            sanitize_text_field((string) $current_user->user_login),
            sanitize_email((string) $current_user->user_email),
        ));

        foreach ($manager_fields as $meta_key) {
            $assigned = sanitize_text_field((string) get_post_meta($participant_id, $meta_key, true));
            if ($assigned === '') {
                continue;
            }
            foreach ($tokens as $token) {
                if ($token !== '' && strcasecmp($assigned, $token) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function create_conversation($participant_id, $user_id, $service_context) {
        global $wpdb;
        $table = self::table_conversations();
        $now = current_time('mysql');

        $ok = $wpdb->insert(
            $table,
            array(
                'participant_id' => absint($participant_id),
                'user_id' => absint($user_id),
                'service_context' => $service_context ?: 'general',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );

        return $ok ? (int) $wpdb->insert_id : 0;
    }

    private static function append_message($conversation_id, $role, $content) {
        global $wpdb;
        $messages = self::table_messages();
        $conversations = self::table_conversations();
        $now = current_time('mysql');
        $safe_content = sanitize_textarea_field($content);

        $wpdb->insert(
            $messages,
            array(
                'conversation_id' => absint($conversation_id),
                'role' => in_array($role, array('assistant', 'system'), true) ? $role : 'user',
                'content' => $safe_content,
                'content_hash' => hash('sha256', $safe_content),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        $wpdb->update(
            $conversations,
            array('updated_at' => $now),
            array('id' => absint($conversation_id)),
            array('%s'),
            array('%d')
        );
    }

    private static function get_messages($conversation_id, $limit = 20) {
        global $wpdb;
        $messages = self::table_messages();
        $limit = max(1, absint($limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content FROM (
                    SELECT role, content, id
                    FROM $messages
                    WHERE conversation_id = %d
                    ORDER BY id DESC
                    LIMIT %d
                ) AS selected_messages
                ORDER BY id ASC",
                absint($conversation_id),
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    private static function build_next_prompts($service_context, $assistant_message) {
        if ($service_context !== 'resume-interview') {
            return array(
                'What should I do first?',
                'Can you break this into small steps?',
                'I want to contact support.',
            );
        }

        $prompts = array(
            'Help me with resume bullet points.',
            'I need a cover letter structure.',
            'Can we practice interview answers?',
            'What are my next steps this week?',
        );

        if (stripos($assistant_message, 'application') !== false) {
            $prompts[] = 'Help me complete a job application checklist.';
        }

        return array_slice(array_values(array_unique($prompts)), 0, 4);
    }

    private static function normalize_pilot_response($service_context, $response, array $history) {
        $trimmed = trim($response);
        if ($service_context !== 'resume-interview') {
            return $trimmed . "\n\nThis service area is in foundational mode for v0.9.1. I can still provide guidance and connect you to support.";
        }

        $latest_user = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                $latest_user = strtolower((string) $history[$i]['content']);
                break;
            }
        }

        $needs_type_prompt = !preg_match('/resume|cover letter|application|interview/', $latest_user);
        if ($needs_type_prompt) {
            $trimmed .= "\n\nBefore we continue, do you need help with your resume, cover letter, job application, or interview preparation?";
        }

        if (stripos($trimmed, 'next steps') === false) {
            $trimmed .= "\n\nNext steps:\n1. Share only the details needed for this request.\n2. Review the draft guidance and edit it in your own words.\n3. Request Contact Support any time for human review.";
        }

        return $trimmed;
    }

    private static function contains_emergency_language($message) {
        $patterns = array('suicide', 'kill myself', 'harm myself', 'overdose', 'emergency', '911', 'danger right now');
        $text = strtolower($message);
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function rate_limit_allows($user_id) {
        $key = 'pfai_ai_rate_' . absint($user_id) . '_' . gmdate('YmdH');
        $count = (int) get_transient($key);
        $limit = max(5, absint(get_option('pfai_ai_rate_limit_per_hour', 20)));

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS + 60);
        return true;
    }

    private static function build_conversation_summary($conversation_id) {
        if ($conversation_id <= 0) {
            return 'Participant requested human support without a persisted conversation.';
        }

        $messages = self::get_messages($conversation_id, 10);
        if (empty($messages)) {
            return 'Participant requested human support; no conversation content is available.';
        }

        $summary_parts = array();
        foreach ($messages as $entry) {
            $role = ($entry['role'] ?? 'user') === 'assistant' ? 'Assistant' : 'Participant';
            $text = trim((string) ($entry['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $short_text = function_exists('mb_substr') ? mb_substr($text, 0, 280) : substr($text, 0, 280);
            $summary_parts[] = $role . ': ' . $short_text;
        }

        return implode("\n", array_slice($summary_parts, -6));
    }

    private static function create_escalation($conversation_id, $participant_id, $user_id, $service_context, $reason, $urgency, $summary) {
        global $wpdb;
        $table = self::table_escalations();
        $now = current_time('mysql');

        $ok = $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id > 0 ? absint($conversation_id) : null,
                'participant_id' => absint($participant_id),
                'user_id' => absint($user_id),
                'service_context' => sanitize_key($service_context ?: 'general'),
                'reason' => sanitize_text_field($reason),
                'urgency' => sanitize_key($urgency),
                'summary' => sanitize_textarea_field($summary),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $ok ? (int) $wpdb->insert_id : 0;
    }

    private static function append_case_note($participant_id, $service_context, $reason, $urgency, $escalation_id) {
        $timestamp = current_time('mysql');
        $line = sprintf(
            '[%s] AI escalation #%d created. Service: %s. Reason: %s. Urgency: %s.',
            $timestamp,
            $escalation_id,
            sanitize_text_field($service_context),
            sanitize_text_field($reason),
            sanitize_text_field($urgency)
        );

        $existing = (string) get_post_meta($participant_id, 'pfai_case_notes', true);
        $updated = trim($line . "\n" . $existing);
        update_post_meta($participant_id, 'pfai_case_notes', $updated);
    }

    public static function get_recent_escalations($limit = 25) {
        if (!is_user_logged_in()) {
            return array();
        }

        if (!current_user_can('manage_options') && !current_user_can('edit_others_posts')) {
            return array();
        }

        global $wpdb;
        $table = self::table_escalations();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d", absint($limit)),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $filtered = array();
        foreach ($rows as $row) {
            $participant_id = absint($row['participant_id'] ?? 0);
            if (self::current_user_can_view_participant_case($participant_id)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    private static function table_conversations() {
        global $wpdb;
        return $wpdb->prefix . 'pfai_ai_conversations';
    }

    private static function table_messages() {
        global $wpdb;
        return $wpdb->prefix . 'pfai_ai_messages';
    }

    private static function table_escalations() {
        global $wpdb;
        return $wpdb->prefix . 'pfai_ai_escalations';
    }

    private static function handle_fallback_request_submission($contexts, $fallback_shortcode) {
        $notice = array(
            'success' => false,
            'message' => '',
        );

        if (!empty($fallback_shortcode)) {
            return $notice;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $notice;
        }

        $action = isset($_POST['pfai_reemployment_fallback_action']) ? sanitize_key(wp_unslash($_POST['pfai_reemployment_fallback_action'])) : '';
        if ($action !== 'submit') {
            return $notice;
        }

        if (!isset($_POST['pfai_reemployment_fallback_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pfai_reemployment_fallback_nonce'])), 'pfai_reemployment_fallback_submit')) {
            $notice['message'] = 'Security validation failed. Please refresh and try again.';
            return $notice;
        }

        $name = sanitize_text_field(wp_unslash($_POST['pfai_request_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['pfai_request_email'] ?? ''));
        $service = sanitize_key(wp_unslash($_POST['pfai_request_service'] ?? 'resume-interview'));
        $details = sanitize_textarea_field(wp_unslash($_POST['pfai_request_details'] ?? ''));

        if ($name === '' || $email === '' || !is_email($email) || $details === '') {
            $notice['message'] = 'Please provide your name, a valid email, and request details.';
            return $notice;
        }

        $allowed_services = array_keys($contexts);
        if (!in_array($service, $allowed_services, true)) {
            $service = 'resume-interview';
        }

        $participant_id = 0;
        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $participant_id = self::resolve_participant_id_for_current_user();
        }

        $summary_lines = array(
            'Fallback request submitted from Reemployment Services form.',
            'Name: ' . $name,
            'Email: ' . $email,
            'Service: ' . $service,
            'Details: ' . $details,
        );
        $summary = implode("\n", $summary_lines);

        $escalation_id = self::create_escalation(0, $participant_id, $user_id, $service, 'Fallback request form submission', 'normal', $summary);
        if ($escalation_id > 0) {
            if ($participant_id > 0) {
                self::append_case_note($participant_id, $service, 'Fallback request form submission', 'normal', $escalation_id);
            }
            $notice['success'] = true;
            $notice['message'] = 'Your request was submitted successfully and routed to support.';
            return $notice;
        }

        $support_email = sanitize_email(get_option('pfai_support_email', get_option('admin_email')));
        if ($support_email && is_email($support_email)) {
            $subject = 'Pathway Forward AI fallback request: ' . $service;
            $mail_sent = wp_mail($support_email, $subject, $summary);
            if ($mail_sent) {
                $notice['success'] = true;
                $notice['message'] = 'Your request was sent successfully to support.';
                return $notice;
            }
        }

        $notice['message'] = 'We could not submit your request right now. Please contact support directly.';
        return $notice;
    }
}
