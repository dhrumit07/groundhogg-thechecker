<?php

namespace GroundhoggTheChecker;

use Groundhogg\Contact;
use Groundhogg\Plugin;
use Groundhogg\Preferences;
use function Groundhogg\admin_page_url;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_request_var;
use function Groundhogg\is_option_enabled;
use function Groundhogg\key_to_words;
use function Groundhogg\notices;

/**
 * Validate contact when any form gets submitted
 *
 * @param $obj mixed
 */
function validate_on_creation($obj)
{
    if ( ! $obj || ! is_option_enabled('gh_enable_automatic_thechecker') ) {
        return;
    }

    $contact_id = false;

    // Contact ID or Email Address
    if (is_string($obj) || is_numeric($obj)) {
        $contact = get_contactdata($obj);

        if (!$contact || !$contact->exists()) {
            return;
        }

        $contact_id = $contact->get_id();
    } else if (is_a($obj, '\Groundhogg\Contact')) {
        $contact_id = $obj->get_id();
    } else if (is_a($obj, '\Groundhogg\Submission')) {
        $contact_id = $obj->get_contact_id();
    }

    if (!$contact_id) {
        return;
    }

    validate_contact($contact_id);
}

add_action('groundhogg/after_form_submit', __NAMESPACE__ . '\validate_on_creation', 10);
add_action('groundhogg/admin/contacts/add/after', __NAMESPACE__ . '\validate_on_creation', 10);
add_action('groundhogg/form/submission_handler/after', __NAMESPACE__ . '\validate_on_creation', 10);

/**
 * Add the validate email action in contact tab.
 */
function add_validate_contact_action()
{
    ?>
    <table class="form-table">
        <tr>
            <th><?php _ex('TheChecker', 'contact_record', 'groundhogg-thechecker'); ?></th>
            <td>
                <div style="max-width: 400px;">
                    <div class="">
                        <?php

                        printf(
                            '<a href="%s" class="button" aria-label="%s">%s</a>',
                            wp_nonce_url(admin_url('admin.php?page=gh_contacts&contact=' . get_request_var('contact') . '&action=thechecker_validate_emails')),
                            esc_attr(sprintf(_x('Validate Email', 'action', 'groundhogg-thechecker'))),
                            _x('Validate Email', 'action', 'groundhogg-thechecker')
                        );

                        ?>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

add_action("groundhogg/admin/contact/record/tab/actions", __NAMESPACE__ . '\add_validate_contact_action', 20);

/**
 * Display the email validation status
 *
 * @param $contact Contact
 */
function add_validate_contact_action_in_email_section($contact)
{

    $status = $contact->get_meta('the_checker_status');
    $last_validated = absint($contact->get_meta('the_checker_status_last_validated'));

    if ($status && $last_validated) {

        $last_validated = Plugin::$instance->utils->date_time->convert_to_local_time($last_validated);

        ?>
        <p><?php printf(__("TheChecker marked this email address as <b>%s</b> on <i>%s</i>. %s?", 'groundhogg-thechecker'),
            esc_html(ucwords(key_to_words($status))),
            date_i18n(get_date_time_format(), $last_validated),
            sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                wp_nonce_url(admin_url('admin.php?page=gh_contacts&contact=' . get_request_var('contact') . '&action=thechecker_validate_emails')),
                esc_attr(sprintf(_x('Re-validate email', 'action', 'groundhogg-thechecker'))),
                _x('Re-validate email', 'action', 'groundhogg-thechecker')
            )
        ); ?></p><?php
    }
}

add_action("groundhogg/contact/record/email_status/after", __NAMESPACE__ . '\add_validate_contact_action_in_email_section');

/**
 * Process the validation action
 *
 * @param $exitcode mixed
 * @return string|void
 */
function contact_page_email_validation($exitcode)
{

    $contact = get_contactdata(absint(get_request_var('contact')));

    $contact->delete_meta('the_checker_status');
    $contact->delete_meta('the_checker_status_last_validated');

    $status = validate_contact($contact->get_id());

    // Warning Message
    if (in_array($status, get_invalid_email_statuses())) {
        notices()->add('invalid', sprintf( __('TheChecker identified this email address as %s and is not marketable.', 'groundhogg-thechecker'), esc_html(ucwords(key_to_words($status))) ), 'error' );
    } else if ($status) {
        // Yay! Message
        notices()->add('success', __('This email address is marketable.', 'groundhogg-thechecker'));
    }

    $exitcode = admin_url('admin.php?page=gh_contacts&action=edit&contact=' . $contact->get_id());

    return $exitcode;
}

add_filter('groundhogg/admin/gh_contacts/process/thechecker_validate_emails', __NAMESPACE__ . '\contact_page_email_validation', 10, 1);

/**
 * List of invalid email status
 *
 * @return array
 */
function get_invalid_email_statuses()
{
    return [
        'undeliverable',
    ];
}

/**
 * Validate a contact via the TheChecker API
 *
 * @param $contact_id
 * @return bool|mixed
 */
function validate_contact($contact_id)
{
    $contact = get_contactdata( $contact_id );

    $thechecker = \GroundhoggTheChecker\Plugin::$instance->thechecker;

    // Continue if not API key
    if ( ! $thechecker->is_connected() ){

        // only show to admins who can edit contacts.
        if (is_admin() && current_user_can('edit_contacts')) {
            notices()->add("no_checker_api_key", sprintf( __("You have not set your TheChecker API key! <a href='%s' target='_blank'>Set it now!</a>", 'groundhogg-thechecker' ) , esc_url( admin_page_url( 'gh_settings', [ 'tab' => 'thechecker' ] ) ) ), "warning" );
        }

        return false;
    }

    $status = $contact->get_meta('the_checker_status');
    $last_validated = absint($contact->get_meta('the_checker_status_last_validated'));

    // If no status is available or the email has not been validated in over 1 day
    if (!$status || (time() - $last_validated) > DAY_IN_SECONDS) {
        if (absint($thechecker->get_credits()->credit_balance) > 0) {

            $response = $thechecker->verify_email([
                'email' => $contact->get_email(),
            ]);

            $contact->update_meta('the_checker_status', sanitize_text_field($response->result));
            $contact->update_meta('the_checker_status_last_validated', time());

            $status = $response->result;

        } else {

            // only show to admins who can edit contacts.
            if (is_admin() && current_user_can('edit_contacts')) {
                notices()->add("not_enough_checker_credits", __("You are out of TheCheker credits! <a href='https://thechecker.co/' target='_blank'>Purchase more credits now!</a>", 'groundhogg-thechecker'), "error");
            }

            return false;
        }
    }

    if ($status) {
        if (in_array($status, get_invalid_email_statuses())) {
            $contact->change_marketing_preference(Preferences::SPAM);
        }
    }

    return $status;

}

/**
 * Adds new tab inside Groundhogg Tools page
 *
 * @param $tags
 * @return array
 */
function tools_tab($tags)
{
    $tags [] = [
        'name' => __('TheChecker'),
        'slug' => 'thechecker'
    ];

    return $tags;
}

add_filter('groundhogg/admin/tools/tabs', __NAMESPACE__ . '\tools_tab', 10);
//

/**
 * Start's the bulk from the tools page
 *
 * @return mixed
 */
function validate_email_bulkjob($exitcode){
    Plugin::$instance->bulk_jobs->thechecker->start();
    return $exitcode;
}

add_filter('groundhogg/admin/gh_tools/process/thechecker_thechecker_validate_emails', __NAMESPACE__ . '\validate_email_bulkjob', 10);

/**
 * Displays Validate email button inside tools page of Groundhogg
 *
 * @param $page
 */
function display_settings($page)
{
    ?>
    <div class="show-upload-view">
        <div class="upload-plugin-wrap">
            <div class="upload-plugin">
                <p class="install-help"><?php _e('TheChecker Email Validation', 'groundhogg-thechecker'); ?></p>
                <form method="post" class="gh-tools-box">
                    <?php wp_nonce_field(); ?>
                    <?php echo Plugin::$instance->utils->html->input([
                        'type' => 'hidden',
                        'name' => 'action',
                        'value' => 'thechecker_validate_emails',
                    ]); ?>
                    <p><?php _e('Validate existing contacts with the TheChecker API. It does not validate any contacts that are already marked as <b>Non-Marketable</b>.', 'groundhogg-thechecker' ); ?></p>
                    <p class="submit" style="text-align: center;padding-bottom: 0;margin: 0;">
                        <button class="button-primary big-button" name="validate_contacts"
                                value="sync"><?php _ex('Start Validation Process', 'action', 'groundhogg-thechecker'); ?></button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
}

add_action('groundhogg/admin/gh_tools/display/thechecker_view', __NAMESPACE__ . '\display_settings', 10);

/**
 * Show a notice to buy credits when running low
 */
function show_low_credits_notice()
{
    if (! \GroundhoggTheChecker\Plugin::$instance->thechecker->is_connected() ){
        return;
    }

    $remaining_credits = get_transient( 'gh_remaining_thechecker_credits' );

    if ( ! $remaining_credits ){

        $remaining_credits = ( \GroundhoggTheChecker\Plugin::$instance->thechecker->get_credits()->credit_balance );
        set_transient( 'gh_remaining_thechecker_credits', $remaining_credits, HOUR_IN_SECONDS );
    }

    $low_on_credits = $remaining_credits < 10;

    if ( $low_on_credits ){
        notices()->add("low_thechecker_credits", sprintf( __("You are almost out of TheChecker credits, only %d left! <a href='https://groundho.gg/thechecker' target='_blank'>Purchase more credits now!</a>", 'groundhogg-thechecker'), $remaining_credits ), "error", 'edit_contacts', true );
    }
}

add_action( 'groundhogg/notices/before', __NAMESPACE__ . '\show_low_credits_notice' );