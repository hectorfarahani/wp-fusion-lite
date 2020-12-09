<?php

class WPF_Growmatik {

	/**
	 * Contains API params
	 */

	public $params;

	/**
	 * Contains API url
	 */

	public $url;

	/**
	 * Lets pluggable functions know which features are supported by the CRM
	 */

	public $supports;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.0
	 */

	public function __construct() {

		$this->slug     = 'growmatik';
		$this->name     = 'Growmatik';
		$this->supports = array( 'add_tags', 'add_fields' );
		$this->url      = 'https://api.stg.growmatik.ai/public/v1/'; // @todo Should be updated.

		// Set up admin options
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/admin/class-admin.php';
			new WPF_Growmatik_Admin( $this->slug, $this->name, $this );
		}

	}

	/**
	 * Sets up hooks specific to this CRM
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		// add_filter( 'wpf_format_field_value', array( $this, 'format_field_value' ), 10, 3 );
	}


	/**
	 * Gets params for API calls
	 *
	 * @access  public
	 * @return  array Params
	 */

	public function get_params( $api_secret = null, $api_key = null ) {

		// Get saved data from DB
		if ( empty( $api_secret ) || empty( $api_key ) ) {
			$api_key = wp_fusion()->settings->get( 'growmatik_api_secret' );
			$api_key = wp_fusion()->settings->get( 'growmatik_api_key' );
		}

		$this->params = array(
			'headers' => array(
				'apiSecret' => $api_secret,
				'apiKey'    => $api_key,
			),
		);

		return $this->params;
	}


	/**
	 * Initialize connection
	 *
	 * @access  public
	 * @return  bool
	 */

	public function connect( $api_secret = null, $api_key = null ) {

		if ( ! $this->params ) {
			$this->get_params( $api_secret, $api_key );
		}

		$test_endpoint = ''; // @todo Ask team for connecten validation endpoint.

		$request  = $this->url . '/' . $test_endpoint . '/';
		$response = wp_remote_get( $request, $this->params );

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 500 == $response_code ) {
			return new WP_Error( $response_code, __( 'An error has occurred in API server. [error 500]', 'wp-fusion-lite' ) );
		}

		if ( 401 == $response_code ) {
			return new WP_Error( $response_code, __( 'API key is not valid. [error 401]', 'wp-fusion-lite' ) );
		}

		$results = json_decode( wp_remote_retrieve_body( $response ) );

		if ( isset( $results->success ) && $results->success ) {
			return true;
		}

		return new WP_Error( $response_code, __( 'Unknown Error', 'wp-fusion-lite' ) );

	}


	/**
	 * Performs initial sync once connection is configured
	 *
	 * @access public
	 * @return bool
	 */

	public function sync() {

		if ( is_wp_error( $this->connect() ) ) {
			return false;
		}

		$this->sync_tags();
		$this->sync_crm_fields();

		do_action( 'wpf_sync' );

		return true;

	}


	/**
	 * Gets all available tags and saves them to options
	 *
	 * @access public
	 * @return array Lists
	 */

	public function sync_tags() {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$available_tags = array();

		// Load available tags into $available_tags like 'tag_id' => 'Tag Label'

		wp_fusion()->settings->set( 'available_tags', $available_tags );

		return $available_tags;
	}


	/**
	 * Loads all custom fields from CRM and merges with local list
	 *
	 * @access public
	 * @return array CRM Fields
	 */

	public function sync_crm_fields() {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$crm_fields = array();

		// Load available fields into $crm_fields like 'field_key' => 'Field Label'

		asort( $crm_fields );
		wp_fusion()->settings->set( 'crm_fields', $crm_fields );

		return $crm_fields;
	}


	/**
	 * Gets contact ID for a user based on email address
	 *
	 * @access public
	 * @return int Contact ID
	 */

	public function get_contact_id( $email_address ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse response for contact ID here

		return $contact_id;
	}


	/**
	 * Gets all tags currently applied to the user, also update the list of available tags
	 *
	 * @access public
	 * @return void
	 */

	public function get_tags( $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse response to create an array of tag ids. $tags = array(123, 678, 543);

		return $tags;
	}

	/**
	 * Applies tags to a contact
	 *
	 * @access public
	 * @return bool
	 */

	public function apply_tags( $tags, $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->params;
		$params['body'] = $tags;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Removes tags from a contact
	 *
	 * @access public
	 * @return bool
	 */

	public function remove_tags( $tags, $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->params;
		$params['body'] = $tags;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;

	}


	/**
	 * Adds a new contact
	 *
	 * @access public
	 * @return int Contact ID
	 */

	public function add_contact( $contact_data, $map_meta_fields = true ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		if ( $map_meta_fields == true ) {
			$contact_data = wp_fusion()->crm_base->map_meta_fields( $contact_data );
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->params;
		$params['body'] = $contact_data;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		// Get new contact ID out of response

		return $contact_id;

	}

	/**
	 * Update contact
	 *
	 * @access public
	 * @return bool
	 */

	public function update_contact( $contact_id, $contact_data, $map_meta_fields = true ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		if ( $map_meta_fields == true ) {
			$contact_data = wp_fusion()->crm_base->map_meta_fields( $contact_data );
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->params;
		$params['body'] = $contact_data;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Loads a contact and updates local user meta
	 *
	 * @access public
	 * @return array User meta data that was returned
	 */

	public function load_contact( $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$user_meta      = array();
		$contact_fields = wp_fusion()->settings->get( 'contact_fields' );
		$body_json      = json_decode( wp_remote_retrieve_body( $response ), true );

		foreach ( $contact_fields as $field_id => $field_data ) {

			if ( $field_data['active'] == true && isset( $body_json['data'][ $field_data['crm_field'] ] ) ) {
				$user_meta[ $field_id ] = $body_json['data'][ $field_data['crm_field'] ];
			}
		}

		return $user_meta;
	}


	/**
	 * Gets a list of contact IDs based on tag
	 *
	 * @access public
	 * @return array Contact IDs returned
	 */

	public function load_contacts( $tag ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contact_ids = array();

		// Iterate over the contacts returned in the response and build an array such that $contact_ids = array(1,3,5,67,890);

		return $contact_ids;

	}

}
