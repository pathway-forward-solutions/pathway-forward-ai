<?php
if (!defined('ABSPATH')) {
    exit;
}

$statuses = array(
    'prospect' => __('Prospect', 'pathway-forward-ai'),
    'active' => __('Active Partnership', 'pathway-forward-ai'),
    'paused' => __('Paused', 'pathway-forward-ai'),
    'inactive' => __('Inactive', 'pathway-forward-ai'),
);
?>
<div class="wrap pfai-wrap">
    <div class="pfai-hero pfai-hero-small">
        <div>
            <p class="pfai-kicker"><?php echo esc_html__('Employer CRM', 'pathway-forward-ai'); ?></p>
            <h1><?php echo esc_html__('Employer Relationship Management', 'pathway-forward-ai'); ?></h1>
            <p><?php echo esc_html__('Track employer profiles, hiring needs, communication history, and follow-up dates from one place.', 'pathway-forward-ai'); ?></p>
        </div>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pathway-forward-ai')); ?>">
            <?php echo esc_html__('Back to Mission Control', 'pathway-forward-ai'); ?>
        </a>
    </div>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success inline">
            <p><?php echo esc_html__('Employer saved successfully.', 'pathway-forward-ai'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['deleted'])) : ?>
        <div class="notice notice-info inline">
            <p><?php echo esc_html__('Employer deleted.', 'pathway-forward-ai'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])) : ?>
        <div class="notice notice-error inline">
            <p><?php echo esc_html__('The employer form could not be saved. Please review the values and try again.', 'pathway-forward-ai'); ?></p>
        </div>
    <?php endif; ?>

    <div class="pfai-grid pfai-main-grid">
        <section class="pfai-panel">
            <h2><?php echo $edit_employer ? esc_html__('Edit Employer', 'pathway-forward-ai') : esc_html__('Add Employer', 'pathway-forward-ai'); ?></h2>
            <form method="post" class="pfai-entity-form">
                <?php wp_nonce_field('pfai_save_employer', 'pfai_employer_crm_nonce'); ?>
                <input type="hidden" name="pfai_employer_action" value="save" />
                <input type="hidden" name="pfai_employer_id" value="<?php echo esc_attr($edit_employer ? (int) $edit_employer->id : 0); ?>" />

                <div class="pfai-form-row">
                    <label for="pfai_employer_name"><?php echo esc_html__('Employer name', 'pathway-forward-ai'); ?></label>
                    <input type="text" id="pfai_employer_name" name="employer_name" value="<?php echo esc_attr($edit_employer ? $edit_employer->employer_name : ''); ?>" required />
                </div>

                <div class="pfai-form-row">
                    <label for="pfai_organization_name"><?php echo esc_html__('Organization name', 'pathway-forward-ai'); ?></label>
                    <input type="text" id="pfai_organization_name" name="organization_name" value="<?php echo esc_attr($edit_employer ? $edit_employer->organization_name : ''); ?>" />
                </div>

                <div class="pfai-form-row pfai-form-grid">
                    <div>
                        <label for="pfai_contact_name"><?php echo esc_html__('Contact name', 'pathway-forward-ai'); ?></label>
                        <input type="text" id="pfai_contact_name" name="contact_name" value="<?php echo esc_attr($edit_employer ? $edit_employer->contact_name : ''); ?>" />
                    </div>
                    <div>
                        <label for="pfai_email"><?php echo esc_html__('Email', 'pathway-forward-ai'); ?></label>
                        <input type="email" id="pfai_email" name="email" value="<?php echo esc_attr($edit_employer ? $edit_employer->email : ''); ?>" />
                    </div>
                </div>

                <div class="pfai-form-row pfai-form-grid">
                    <div>
                        <label for="pfai_phone"><?php echo esc_html__('Phone', 'pathway-forward-ai'); ?></label>
                        <input type="text" id="pfai_phone" name="phone" value="<?php echo esc_attr($edit_employer ? $edit_employer->phone : ''); ?>" />
                    </div>
                    <div>
                        <label for="pfai_partnership_status"><?php echo esc_html__('Partnership status', 'pathway-forward-ai'); ?></label>
                        <select id="pfai_partnership_status" name="partnership_status">
                            <?php foreach ($statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($edit_employer ? $edit_employer->partnership_status : 'prospect', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pfai-form-row">
                    <label for="pfai_follow_up_date"><?php echo esc_html__('Follow-up date', 'pathway-forward-ai'); ?></label>
                    <input type="date" id="pfai_follow_up_date" name="follow_up_date" value="<?php echo esc_attr($edit_employer ? $edit_employer->follow_up_date : ''); ?>" />
                </div>

                <div class="pfai-form-row">
                    <label for="pfai_hiring_needs"><?php echo esc_html__('Hiring needs', 'pathway-forward-ai'); ?></label>
                    <textarea id="pfai_hiring_needs" name="hiring_needs" rows="4"><?php echo esc_textarea($edit_employer ? $edit_employer->hiring_needs : ''); ?></textarea>
                </div>

                <div class="pfai-form-row">
                    <label for="pfai_interaction_notes"><?php echo esc_html__('Interaction notes', 'pathway-forward-ai'); ?></label>
                    <textarea id="pfai_interaction_notes" name="interaction_notes" rows="5"><?php echo esc_textarea($edit_employer ? $edit_employer->interaction_notes : ''); ?></textarea>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Employer', 'pathway-forward-ai'); ?></button>
                    <?php if ($edit_employer) : ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pfai-employers')); ?>"><?php echo esc_html__('Cancel', 'pathway-forward-ai'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </section>

        <section class="pfai-panel">
            <h2><?php echo esc_html__('Employer Directory', 'pathway-forward-ai'); ?></h2>
            <form method="get" class="pfai-filter-form">
                <input type="hidden" name="page" value="pfai-employers" />
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Search employers', 'pathway-forward-ai'); ?>" />
                <select name="status">
                    <option value=""><?php echo esc_html__('All statuses', 'pathway-forward-ai'); ?></option>
                    <?php foreach ($statuses as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="follow_up">
                    <option value=""><?php echo esc_html__('All follow-up dates', 'pathway-forward-ai'); ?></option>
                    <option value="due" <?php selected($follow_up, 'due'); ?>><?php echo esc_html__('Due now or overdue', 'pathway-forward-ai'); ?></option>
                </select>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Filter', 'pathway-forward-ai'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pfai-employers')); ?>"><?php echo esc_html__('Reset', 'pathway-forward-ai'); ?></a>
            </form>

            <?php if (empty($employers)) : ?>
                <p class="description"><?php echo esc_html__('No employers found yet. Add the first employer to start building the CRM.', 'pathway-forward-ai'); ?></p>
            <?php else : ?>
                <div class="pfai-employer-list">
                    <?php foreach ($employers as $employer) : ?>
                        <article class="pfai-employer-card">
                            <div class="pfai-employer-topline">
                                <div>
                                    <h3><?php echo esc_html($employer->employer_name); ?></h3>
                                    <p><?php echo esc_html($employer->organization_name); ?></p>
                                </div>
                                <span class="pfai-status pfai-status-<?php echo esc_attr($employer->partnership_status); ?>"><?php echo esc_html($statuses[$employer->partnership_status] ?? $employer->partnership_status); ?></span>
                            </div>
                            <p><strong><?php echo esc_html__('Contact:', 'pathway-forward-ai'); ?></strong> <?php echo esc_html($employer->contact_name); ?> · <?php echo esc_html($employer->email); ?> · <?php echo esc_html($employer->phone); ?></p>
                            <p><strong><?php echo esc_html__('Hiring needs:', 'pathway-forward-ai'); ?></strong> <?php echo esc_html($employer->hiring_needs ?: __('No hiring needs noted yet.', 'pathway-forward-ai')); ?></p>
                            <p><strong><?php echo esc_html__('Next follow-up:', 'pathway-forward-ai'); ?></strong> <?php echo esc_html(!empty($employer->follow_up_date) ? $employer->follow_up_date : __('No follow-up date set', 'pathway-forward-ai')); ?></p>
                            <p><strong><?php echo esc_html__('Notes:', 'pathway-forward-ai'); ?></strong> <?php echo esc_html($employer->interaction_notes ?: __('No interaction notes documented yet.', 'pathway-forward-ai')); ?></p>
                            <div class="pfai-card-actions">
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'pfai-employers', 'edit' => (int) $employer->id), admin_url('admin.php'))); ?>"><?php echo esc_html__('Edit', 'pathway-forward-ai'); ?></a>
                                <a class="button button-link-delete button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'pfai-employers', 'delete' => (int) $employer->id), admin_url('admin.php')), 'pfai_delete_employer_' . (int) $employer->id)); ?>" onclick="return confirm('<?php echo esc_attr__('Delete this employer record?', 'pathway-forward-ai'); ?>');"><?php echo esc_html__('Delete', 'pathway-forward-ai'); ?></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
