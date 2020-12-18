<?php

class WPF_Growmatik {

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
		$this->url      = 'https://api.stg.growmatik.ai/public/v1';

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

	public function get_params( $get = true, $api_secret = null, $api_key = null ) {

		// Get saved data from DB
		if ( empty( $api_secret ) || empty( $api_key ) ) {
			$api_secret = wp_fusion()->settings->get( 'growmatik_api_secret' );
			$api_key    = wp_fusion()->settings->get( 'growmatik_api_key' );
		}

		$params = array(
			'headers' => array(
				'apiKey' => $api_key,
			),
		);

		if ( ! $get ) {
			$params['body'] = array(
				'apiSecret' => $api_secret,
			);
		}

		return $params;
	}


	/**
	 * Try dummy post request to make sure API credentials are valid.
	 *
	 * @access  public
	 * @return  bool|object true or WP_Error object with custom error message if connection fails.
	 */
	public function connect( $api_secret = null, $api_key = null ) {

		$params = $this->get_params( false, $api_secret, $api_key );

		$request = $this->url . '/contacts';

		$params['body']['users'] = array();

		$response = wp_remote_post( $request, $params );

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			return true;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 500 == $response_code ) {
			return new WP_Error( $response_code, __( 'An error has occurred in API server. [error 500]', 'wp-fusion-lite' ) );
		}

		if ( 401 == $response_code ) {
			return new WP_Error( $response_code, __( 'Invalid API credentials. [error 401]', 'wp-fusion-lite' ) );
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

		$params = $this->get_params( false );

		$request  = $this->url . '/site/tags/';
		$response = wp_remote_get( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tags = json_decode( wp_remote_retrieve_body( $response ) );

		$available_tags = array();

		foreach ( $tags->data as $tag ) {
			$available_tags[ strval( $tag->id ) ] = $tag->name;
		}

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

		$params = $this->get_params( false );

		$request  = $this->url . '/site/attributes/';
		$response = wp_remote_get( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$fields = json_decode( wp_remote_retrieve_body( $response ) );

		$crm_fields = array();

		foreach ( $fields->data as $field ) {
			$crm_fields[ $field->id ] = $field->name;
		}

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

		$params = $this->get_params( false );

		$params['body']['email'] = $email_address;

		$request  = $this->url . '/contact/email/';
		$response = wp_remote_get( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$user = json_decode( wp_remote_retrieve_body( $response ) );

		return $user->data->userId;
	}


	/**
	 * Gets all tags currently applied to the user, also update the list of available tags
	 *
	 * @access public
	 * @return void
	 */
	public function get_tags( $contact_id ) {

		$params = $this->get_params( false );

		$params['body']['id'] = $contact_id;

		$request  = $this->url . '/contact/tags/id/';
		$response = wp_remote_get( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tags = json_decode( wp_remote_retrieve_body( $response ) );

		$user_tags = array();

		foreach ( $tags->data as $tag ) {
			$user_tags[ strval( $tag->id ) ] = $tag->name;
		}

		return $user_tags;
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
