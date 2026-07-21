<?php
if (!defined('ABSPATH')) exit;

class PFAI_Admin {
    private $pages = array(
        'pathway-forward-ai' => 'dashboard',
        'pfai-participants' => 'participants',
        'pfai-participant-workspace' => 'participant-workspace',
        'pfai-ai-coach' => 'placeholder',
        'pfai-employers' => 'placeholder',
        'pfai-case-management' => 'case-management',
        'pfai-follow-ups' => 'follow-ups',
        'pfai-reports' => 'reports',
        'pfai-ai-center' => 'placeholder',
        'pathway-forward-ai-settings' => 'settings',
    );

    public function register_hooks() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_bar_menu', array($this, 'register_toolbar_link'), 90);
        add_filter('plugin_action_links_' . plugin_basename(PFAI_PLUGIN_FILE), array($this, 'plugin_action_links'));
    }

    public function register_menu() {
        $capability = 'manage_options';

        add_menu_page(
            'Pathway Forward Mission Control',
            'PFS Mission Control',
            $capability,
            'pathway-forward-ai',
            array($this, 'render_current_page'),
            'dashicons-chart-area',
            58
        );

        $items = array(
            array('Mission Control','Mission Control','pathway-forward-ai'),
            array('Participants','Participants','pfai-participants'),
            array('Participant Workspace','Participant Workspace','pfai-participant-workspace'),
            array('All Participants','All Participants','edit.php?post_type=pfai_participant'),
            array('Add Participant','Add Participant','post-new.php?post_type=pfai_participant'),
            array('Case Management','Case Management','pfai-case-management'),
            array('Follow-Up Queue','Follow-Up Queue','pfai-follow-ups'),
            array('AI Career Coach','AI Career Coach','pfai-ai-coach'),
            array('Employers','Employers','pfai-employers'),
            array('Reports','Reports','pfai-reports'),
            array('AI Center','AI Center','pfai-ai-center'),
            array('Settings','Settings','pathway-forward-ai-settings'),
        );

        foreach ($items as $item) {
            add_submenu_page(
                'pathway-forward-ai',
                $item[0],
                $item[1],
                $capability,
                $item[2],
                array($this, 'render_current_page')
            );
        }

        // WordPress.com can reorganize top-level menus. This Tools entry provides
        // a dependable second route to the same Mission Control dashboard.
        add_management_page(
            'Pathway Forward Mission Control',
            'PFS Mission Control',
            $capability,
            'pfai-mission-control',
            array($this, 'render_mission_control')
        );
    }

    public function register_toolbar_link($wp_admin_bar) {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'pfai-mission-control',
            'title' => 'PFS Mission Control',
            'href'  => admin_url('admin.php?page=pathway-forward-ai'),
            'meta'  => array('class' => 'pfai-toolbar-link'),
        ));
    }

    public function plugin_action_links($links) {
        $mission_control = sprintf(
            '<a href="%s"><strong>%s</strong></a>',
            esc_url(admin_url('admin.php?page=pathway-forward-ai')),
            esc_html__('Mission Control', 'pathway-forward-ai')
        );
        array_unshift($links, $mission_control);
        return $links;
    }

    public function render_mission_control() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $page_slug = 'pathway-forward-ai';
        include PFAI_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public function enqueue_assets($hook_suffix) {
        if (
            strpos($hook_suffix, 'pathway-forward-ai') === false &&
            strpos($hook_suffix, 'pfai-') === false &&
            get_post_type() !== 'pfai_participant'
        ) return;

        wp_enqueue_style('pfai-admin', PFAI_PLUGIN_URL . 'admin/css/admin.css', array(), PFAI_VERSION);
    }

    public function register_settings() {
        register_setting('pfai_settings_group','pfai_organization_name',array(
            'type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'Pathway Forward Solutions Inc.'
        ));
        register_setting('pfai_settings_group','pfai_support_email',array(
            'type'=>'string','sanitize_callback'=>'sanitize_email','default'=>get_option('admin_email')
        ));
        register_setting('pfai_settings_group','pfai_openai_api_key',array(
            'type'=>'string','sanitize_callback'=>array($this,'sanitize_api_key'),'default'=>''
        ));
        register_setting('pfai_settings_group','pfai_openai_model',array(
            'type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'gpt-5-mini'
        ));

        add_settings_section('pfai_general','Organization Settings',function(){
            echo '<p>Configure your organization identity and support contact.</p>';
        },'pathway-forward-ai-settings');

        add_settings_field('pfai_organization_name','Organization name',function(){
            printf('<input type="text" class="regular-text" name="pfai_organization_name" value="%s">',esc_attr(get_option('pfai_organization_name','Pathway Forward Solutions Inc.')));
        },'pathway-forward-ai-settings','pfai_general');

        add_settings_field('pfai_support_email','Support email',function(){
            printf('<input type="email" class="regular-text" name="pfai_support_email" value="%s">',esc_attr(get_option('pfai_support_email',get_option('admin_email'))));
        },'pathway-forward-ai-settings','pfai_general');

        add_settings_section('pfai_ai','AI Connection',function(){
            echo '<p>Connect the platform to the OpenAI API. For stronger security, the key can instead be defined as <code>PFAI_OPENAI_API_KEY</code> in wp-config.php.</p>';
        },'pathway-forward-ai-settings');

        add_settings_field('pfai_openai_api_key','OpenAI API key',function(){
            $configured = PFAI_AI_Service::is_configured();
            printf('<input type="password" class="regular-text" name="pfai_openai_api_key" value="" autocomplete="new-password" placeholder="%s"><p class="description">%s Leave blank to keep the existing saved key.</p>', $configured ? 'Key configured' : 'sk-...', $configured ? 'AI connection is currently configured.' : 'No API key is configured.');
        },'pathway-forward-ai-settings','pfai_ai');

        add_settings_field('pfai_openai_model','AI model',function(){
            printf('<input type="text" class="regular-text" name="pfai_openai_model" value="%s"><p class="description">Recommended starting value: gpt-5-mini.</p>',esc_attr(get_option('pfai_openai_model','gpt-5-mini')));
        },'pathway-forward-ai-settings','pfai_ai');
    }


    public function sanitize_api_key($value) {
        $value = trim((string) $value);
        if ($value === '') return get_option('pfai_openai_api_key', '');
        return preg_replace('/[^A-Za-z0-9_\-\.]/', '', $value);
    }

    public function render_current_page() {
        if (!current_user_can('manage_options')) return;

        $slug = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'pathway-forward-ai';
        $template = isset($this->pages[$slug]) ? $this->pages[$slug] : 'dashboard';
        $page_slug = $slug;

        include PFAI_PLUGIN_DIR . 'admin/partials/' . $template . '.php';
    }
}
