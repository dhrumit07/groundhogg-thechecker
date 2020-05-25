<?php

namespace GroundhoggTheChecker\Sdk;

use Groundhogg\Plugin;
use function Groundhogg\get_json_error;
use function Groundhogg\is_json_error;
use WP_Error;

class Thechecker
{
    private $secret;
    private $base_url;

    /**
     * TheChecker constructor.
     *
     * Lazy load this bad boy
     */
    public function __construct(){
        $this->init();
    }

    /**
     * init the options when ready
     */
    public function init(){
        $this->secret = get_option( 'gh_thechecker_api_key' );
        $this->base_url = 'https://api.thechecker.co';
    }

    /**
     * @return bool
     */
    public function is_connected()
    {
        return ! empty( $this->secret ) && ! empty( $this->base_url );
    }

    /**
     * @param string $endpoint the REST endpoint
     * @param array $body the body of the request
     * @param string $method The request method
     * @param array $headers optional headers to override a request
     * @return object|WP_Error
     */
    public function request( $endpoint, $body=[], $headers=[], $method='POST' )
    {

        $body [ 'api_key' ] = $this->secret;
        $method = strtoupper( $method );
        $url = sprintf( '%s/%s', $this->base_url, $endpoint );

        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'body'      => $body,
        ];

        if ( $method === 'GET' ){
            $response = wp_remote_get( $url , $args );
        } else {
            $response = wp_remote_post( $url, $args );
        }

        if ( ! $response ){
            return new WP_Error( 'unknown_error', sprintf( 'Failed to initialize remote %s.', $method ) );
        }

        if ( is_wp_error( $response ) ){
            return $response;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ) );

        if ( is_json_error( $json ) ){
            return get_json_error( $json );
        }

        return $json;
    }


    /**
     * Validate email address using the TheChecker API.
     *
     * @param array $body -  needs email and ip_address param
     * @param array $headers
     * @return object|WP_Error
     */
    public function verify_email( $body = []  , $headers = [] ){
        return $this->get( 'v2/verify' , $body , $headers );
    }

    /**
     * Get number of remaining credits from TheChecker
     *
     * @return object|WP_Error
     */
    public function get_credits(){
        return $this->get( 'credit-balance' , [] ,[]);
    }

    /**
     * GET Request Wrapper
     *
     * @param $endpoint
     * @param $body
     * @param array $headers
     * @return object|WP_Error
     */
    public function get( $endpoint, $body = [], $headers=[] )
    {
        return $this->request( $endpoint, $body, $headers, 'GET' );
    }

    /**
     * PUT Request Wrapper
     *
     * @param $endpoint
     * @param $body
     * @param array $headers
     * @return object|WP_Error
     */
    public function put( $endpoint, $body, $headers=[] )
    {
        return $this->request( $endpoint, $body, $headers, 'PUT' );
    }

    /**
     * POST Request Wrapper
     *
     * @param $endpoint
     * @param $body
     * @param array $headers
     * @return object|WP_Error
     */
    public function post( $endpoint, $body, $headers=[] )
    {
        return $this->request( $endpoint, $body, $headers, 'POST' );
    }

    /**
     * PATCH Request Wrapper
     *
     * @param $endpoint
     * @param $body
     * @param array $headers
     * @return object|WP_Error
     */
    public function patch( $endpoint, $body, $headers=[] )
    {
        return $this->request( $endpoint, $body, $headers, 'PATCH' );
    }

    /**
     * DELETE Request Wrapper
     *
     * @param $endpoint
     * @param $body
     * @param array $headers
     * @return object|WP_Error
     */
    public function delete( $endpoint, $body, $headers=[] )
    {
        return $this->request( $endpoint, $body, $headers, 'DELETE' );
    }

}