<?php
if (!defined('ABSPATH')) exit;

class PFAI_Participants {
    private static $fields = array(
        'pfai_first_name','pfai_middle_name','pfai_last_name','pfai_preferred_name',
        'pfai_dob','pfai_gender','pfai_pronouns','pfai_phone','pfai_alt_phone',
        'pfai_email','pfai_contact_method','pfai_street','pfai_unit','pfai_city',
        'pfai_state','pfai_zip','pfai_county','pfai_emergency_name',
        'pfai_emergency_phone','pfai_emergency_relationship','pfai_intake_date',
        'pfai_referral_source','pfai_program','pfai_funding_source',
        'pfai_eligibility','pfai_case_manager','pfai_status',
        'pfai_employment_status','pfai_employer','pfai_job_title',
        'pfai_hourly_wage','pfai_hours_week','pfai_start_date','pfai_career_goal',
        'pfai_follow_up_date'
    );

    public static function register_hooks() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_pfai_participant', array(__CLASS__, 'save_participant'));
        add_filter('manage_pfai_participant_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_pfai_participant_posts_custom_column', array(__CLASS__, 'column_content'), 10, 2);
    }

    public static function register_post_type() {
        register_post_type('pfai_participant', array(
            'labels' => array(
                'name' => 'Participants',
                'singular_name' => 'Participant',
                'add_new_item' => 'Add New Participant',
                'edit_item' => 'Edit Participant',
                'search_items' => 'Search Participants',
                'not_found' => 'No participants found',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => array('title'),
            'map_meta_cap' => true,
        ));
    }

    public static function add_meta_boxes() {
        add_meta_box('pfai_personal', 'Personal Information', array(__CLASS__, 'personal_box'), 'pfai_participant', 'normal', 'high');
        add_meta_box('pfai_contact', 'Contact and Address', array(__CLASS__, 'contact_box'), 'pfai_participant', 'normal', 'high');
        add_meta_box('pfai_intake', 'Intake and Program', array(__CLASS__, 'intake_box'), 'pfai_participant', 'normal', 'default');
        add_meta_box('pfai_employment', 'Employment and Career Goals', array(__CLASS__, 'employment_box'), 'pfai_participant', 'normal', 'default');
        add_meta_box('pfai_documents', 'Document Center', array(__CLASS__, 'documents_box'), 'pfai_participant', 'normal', 'default');
        add_meta_box('pfai_notes', 'Case Notes and Follow-Up', array(__CLASS__, 'notes_box'), 'pfai_participant', 'normal', 'default');
    }

    private static function value($post_id, $key) {
        return get_post_meta($post_id, $key, true);
    }

    private static function input($post_id, $name, $label, $type = 'text') {
        printf(
            '<p><label>%s</label><input type="%s" name="%s" value="%s"></p>',
            esc_html($label),
            esc_attr($type),
            esc_attr($name),
            esc_attr(self::value($post_id, $name))
        );
    }

    private static function select($post_id, $name, $label, $options) {
        $value = self::value($post_id, $name);
        echo '<p><label>' . esc_html($label) . '</label><select name="' . esc_attr($name) . '">';
        echo '<option value="">Select</option>';
        foreach ($options as $option) {
            printf('<option value="%s" %s>%s</option>', esc_attr($option), selected($value, $option, false), esc_html($option));
        }
        echo '</select></p>';
    }

    public static function personal_box($post) {
        wp_nonce_field('pfai_save_participant', 'pfai_participant_nonce');
        echo '<div class="pfai-form-grid">';
        self::input($post->ID,'pfai_first_name','First name');
        self::input($post->ID,'pfai_middle_name','Middle name');
        self::input($post->ID,'pfai_last_name','Last name');
        self::input($post->ID,'pfai_preferred_name','Preferred name');
        self::input($post->ID,'pfai_dob','Date of birth','date');
        self::select($post->ID,'pfai_gender','Gender',array('Female','Male','Nonbinary','Prefer not to say','Other'));
        self::input($post->ID,'pfai_pronouns','Preferred pronouns');
        echo '</div>';
    }

    public static function contact_box($post) {
        echo '<div class="pfai-form-grid">';
        self::input($post->ID,'pfai_phone','Mobile phone','tel');
        self::input($post->ID,'pfai_alt_phone','Alternate phone','tel');
        self::input($post->ID,'pfai_email','Email','email');
        self::select($post->ID,'pfai_contact_method','Preferred contact method',array('Phone','Text','Email'));
        self::input($post->ID,'pfai_street','Street address');
        self::input($post->ID,'pfai_unit','Apartment or unit');
        self::input($post->ID,'pfai_city','City');
        self::input($post->ID,'pfai_state','State');
        self::input($post->ID,'pfai_zip','ZIP code');
        self::input($post->ID,'pfai_county','County');
        self::input($post->ID,'pfai_emergency_name','Emergency contact name');
        self::input($post->ID,'pfai_emergency_phone','Emergency contact phone','tel');
        self::input($post->ID,'pfai_emergency_relationship','Emergency contact relationship');
        echo '</div>';
    }

    public static function intake_box($post) {
        echo '<div class="pfai-form-grid">';
        self::input($post->ID,'pfai_intake_date','Intake date','date');
        self::input($post->ID,'pfai_referral_source','Referral source');
        self::input($post->ID,'pfai_program','Program');
        self::input($post->ID,'pfai_funding_source','Funding source');
        self::select($post->ID,'pfai_eligibility','Eligibility status',array('Pending','Eligible','Not Eligible'));
        self::input($post->ID,'pfai_case_manager','Assigned case manager');
        self::select($post->ID,'pfai_status','Participant status',array('Active','Inactive','Placed','Completed','Needs Follow-Up'));
        echo '</div>';
    }

    public static function employment_box($post) {
        echo '<div class="pfai-form-grid">';
        self::select($post->ID,'pfai_employment_status','Employment status',array('Unemployed','Part-Time','Full-Time','Self-Employed','Student'));
        self::input($post->ID,'pfai_employer','Employer');
        self::input($post->ID,'pfai_job_title','Job title');
        self::input($post->ID,'pfai_hourly_wage','Hourly wage','number');
        self::input($post->ID,'pfai_hours_week','Hours per week','number');
        self::input($post->ID,'pfai_start_date','Employment start date','date');
        echo '<p class="pfai-full"><label>Career goal</label><textarea name="pfai_career_goal" rows="4">' . esc_textarea(self::value($post->ID,'pfai_career_goal')) . '</textarea></p>';
        echo '</div>';
    }

    public static function documents_box($post) {
        $docs = get_post_meta($post->ID, 'pfai_documents', true);
        if (!is_array($docs)) $docs = array();

        echo '<p>Attach documents using the WordPress Media Library. Add one document URL per line.</p>';
        echo '<textarea name="pfai_documents_text" rows="6" style="width:100%;" placeholder="Resume URL&#10;ID URL&#10;Certification URL">' .
            esc_textarea(implode("\n", $docs)) . '</textarea>';
        echo '<p class="description">Use Add Media on another screen to upload files, then paste each file URL here. A dedicated uploader will be added later.</p>';
    }

    public static function notes_box($post) {
        self::input($post->ID,'pfai_follow_up_date','Follow-up date','date');
        echo '<p><label>Case notes</label><textarea name="pfai_case_notes" rows="10" style="width:100%;">' .
            esc_textarea(self::value($post->ID,'pfai_case_notes')) . '</textarea></p>';
    }

    public static function save_participant($post_id) {
        if (!isset($_POST['pfai_participant_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pfai_participant_nonce'])), 'pfai_save_participant')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach (self::$fields as $field) {
            if (!isset($_POST[$field])) continue;
            $value = wp_unslash($_POST[$field]);
            $value = ($field === 'pfai_email') ? sanitize_email($value) : sanitize_text_field($value);
            update_post_meta($post_id, $field, $value);
        }

        if (isset($_POST['pfai_case_notes'])) {
            update_post_meta($post_id, 'pfai_case_notes', sanitize_textarea_field(wp_unslash($_POST['pfai_case_notes'])));
        }

        if (isset($_POST['pfai_documents_text'])) {
            $lines = preg_split('/\r\n|\r|\n/', wp_unslash($_POST['pfai_documents_text']));
            $urls = array();
            foreach ($lines as $line) {
                $url = esc_url_raw(trim($line));
                if ($url) $urls[] = $url;
            }
            update_post_meta($post_id, 'pfai_documents', $urls);
        }

        $name = trim(self::value($post_id,'pfai_first_name') . ' ' . self::value($post_id,'pfai_last_name'));
        if ($name && get_post_field('post_title', $post_id) !== $name) {
            remove_action('save_post_pfai_participant', array(__CLASS__, 'save_participant'));
            wp_update_post(array('ID' => $post_id, 'post_title' => $name, 'post_status' => 'publish'));
            add_action('save_post_pfai_participant', array(__CLASS__, 'save_participant'));
        }
    }

    public static function columns($columns) {
        return array(
            'cb' => $columns['cb'],
            'title' => 'Participant',
            'pfai_program' => 'Program',
            'pfai_case_manager' => 'Case Manager',
            'pfai_status' => 'Status',
            'pfai_follow_up_date' => 'Follow-Up',
            'date' => 'Added',
        );
    }

    public static function column_content($column, $post_id) {
        $value = get_post_meta($post_id, $column, true);
        if ($column === 'pfai_status' && $value) {
            echo '<span class="pfai-status-badge pfai-status-' . esc_attr(sanitize_title($value)) . '">' . esc_html($value) . '</span>';
            return;
        }
        echo esc_html($value ?: '—');
    }

    private static function count_by_meta($key, $value) {
        $query = new WP_Query(array(
            'post_type' => 'pfai_participant',
            'post_status' => 'publish',
            'meta_key' => $key,
            'meta_value' => $value,
            'fields' => 'ids',
            'posts_per_page' => -1,
        ));
        return (int) $query->found_posts;
    }

    public static function counts() {
        $all = wp_count_posts('pfai_participant');
        $total = isset($all->publish) ? (int) $all->publish : 0;
        $active = self::count_by_meta('pfai_status','Active');
        $placed = self::count_by_meta('pfai_status','Placed');
        $follow_up = self::count_by_meta('pfai_status','Needs Follow-Up');

        $month_start = wp_date('Y-m-01 00:00:00');
        $q = new WP_Query(array(
            'post_type' => 'pfai_participant',
            'post_status' => 'publish',
            'date_query' => array(array('after' => $month_start, 'inclusive' => true)),
            'fields' => 'ids',
            'posts_per_page' => -1,
        ));
        $new_this_month = (int) $q->found_posts;

        return compact('total','active','placed','follow_up','new_this_month');
    }
}
