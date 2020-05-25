<?php
namespace GroundhoggTheChecker\Bulk_Jobs;

use Groundhogg\Bulk_Jobs\Bulk_Job;
use Groundhogg\Contact_Query;
use Groundhogg\Preferences;
use function GroundhoggTheChecker\validate_contact;

if ( ! defined( 'ABSPATH' ) ) exit;

class Validate_Emails_Thechecker extends Bulk_Job{

    /**
     * Get the action reference.
     *
     * @return string
     */
    function get_action(){
        return 'thechecker_validate_emails';
    }

    /**
     * Get an array of items someway somehow
     *
     * @param $items array
     * @return array
     */
    public function query( $items )
    {
        if ( ! current_user_can( 'edit_contacts' ) ){
            return $items;
        }

        $query = new Contact_Query();

        $args = [
            'optin_status' => [
                Preferences::UNCONFIRMED,
                Preferences::CONFIRMED,
            ]
        ];

        $contacts = $query->query( $args );

        $ids = wp_list_pluck( $contacts, 'ID' );

        return $ids;
    }

    /**
     * Get the maximum number of items which can be processed at a time.
     *
     * @param $max int
     * @param $items array
     * @return int
     */
    public function max_items($max, $items)
    {
        if ( ! current_user_can( 'edit_contacts' ) ){
            return $max;
        }

        return min( 10, intval( ini_get( 'max_input_vars' ) ) ) ;
    }

    /**
     * Process an item
     *
     * @param $item mixed
     * @return void
     */
    protected function process_item( $item )
    {
        if ( ! current_user_can( 'edit_contacts' ) ){
            return;
        }

        validate_contact($item);

    }

    /**
     * Do stuff before the loop
     *
     * @return void
     */
    protected function pre_loop(){}

    /**
     * do stuff after the loop
     *
     * @return void
     */
    protected function post_loop(){}

    /**
     * Cleanup any options/transients/notices after the bulk job has been processed.
     *
     * @return void
     */
    protected function clean_up(){}


    /**
     * Get the return URL
     *
     * @return string
     */
    protected function get_return_url()
    {
        $url = admin_url( 'admin.php?page=gh_tools&tab=thechecker' );
        return $url;
    }
}