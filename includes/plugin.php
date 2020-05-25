<?php

namespace GroundhoggTheChecker;

use Groundhogg\Admin\Admin_Menu;
use Groundhogg\DB\Manager;
use Groundhogg\Extension;
use GroundhoggTheChecker\Bulk_Jobs\Validate_Emails_Thechecker;
use GroundhoggTheChecker\Sdk\Thechecker;

class Plugin extends Extension{


    /**
     * Override the parent instance.
     *
     * @var Plugin
     */
    public static $instance;

	/**
	 * @var Thechecker
	 */
    public $thechecker ;

    /**
     * Include any files.
     *
     * @return void
     */
    public function includes()
    {
        require  GROUNDHOGG_THECHECKER_PATH . '/includes/functions.php';
    }

    /**
     * Init any components that need to be added.
     *
     * @return void
     */
    public function init_components()
    {

    	$this->thechecker = new Thechecker();
    }

	/**
	 * @param \Groundhogg\Bulk_Jobs\Manager $manager
	 */
	public function register_bulk_jobs( $manager )
	{
		$manager->thechecker = new Validate_Emails_Thechecker();
	}

    /**
     * Get the ID number for the download in EDD Store
     *
     * @return int
     */
    public function get_download_id()
    {
    	return  50568;
    }



	/**
	 * Register the TheChecker settings
	 *
	 * @param array[] $settings
	 * @return array[]|array[]
	 */
	public function register_settings($settings)
	{

		$settings['gh_enable_automatic_thechecker'] = [
			'id' => 'gh_enable_automatic_thechecker',
			'section' => 'thechecker-setting',
			'label' => __('Enable Automatic Validation', 'groundhogg-thechecker'),
			'desc' => __('Automatically validate email addresses when contacts are created.', 'groundhogg-thechecker'),
			'type' => 'checkbox',
			'atts' => [
				'label' => __( 'Enable', 'groundhogg' ),
				'name' => 'gh_enable_automatic_thechecker',
				'id' => 'gh_enable_automatic_thechecker',
			]
		];

		$settings['gh_thechecker_api_key'] = [
			'id' => 'gh_thechecker_api_key',
			'section' => 'thechecker-setting',
			'label' => __('API key', 'groundhogg-thechecker'),
			'desc' => __('Enter your TheChecker API key from your account. Don\'t have an API key? <b><a href="https://app.thechecker.co/api" target="_blank">Get one now!</a></b>', 'groundhogg-thechecker'),
			'type' => 'input',
			'atts' => [
				'type' => 'password',
				'name' => 'gh_thechecker_api_key',
				'id' => 'gh_thechecker_api_key',
			]
		];

		return $settings;
	}

	/**
	 * Add ZB tab
	 *
	 * @param array[] $tabs
	 * @return array[]|array[]
	 */
	public function register_settings_tabs($tabs)
	{
		$tabs['thechecker'] = [
			'id' => 'thechecker',
			'title' => _x('TheChecker', 'settings_tabs', 'groundhogg-thechecker')
		];

		return $tabs;
	}

	/**
	 * Add ZB Setting
	 *
	 * @param array[] $sections
	 * @return array[]|array[]
	 */
	public function register_settings_sections($sections)
	{
		$sections['thechecker-setting'] = [
			'id' => 'thechecker-setting',
			'title' => _x('TheChecker Settings', 'settings_sections', 'groundhogg-thechecker'),
			'tab' => 'thechecker',
		];

		return $sections;
	}



    /**
     * Get the version #
     *
     * @return mixed
     */
    public function get_version()
    {
        return GROUNDHOGG_THECHECKER_VERSION;
    }

    /**
     * @return string
     */
    public function get_plugin_file()
    {
        return GROUNDHOGG_THECHECKER__FILE__;
    }

    /**
     * Register autoloader.
     *
     * Groundhogg autoloader loads all the classes needed to run the plugin.
     *
     * @since 1.6.0
     * @access private
     */
    protected function register_autoloader()
    {
        require GROUNDHOGG_THECHECKER_PATH . 'includes/autoloader.php';
        Autoloader::run();
    }
}

Plugin::instance();