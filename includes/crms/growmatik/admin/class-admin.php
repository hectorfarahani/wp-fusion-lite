<?php

class WPF_Growmatik_Admin {

	private $slug;
	private $name;
	private $crm;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function __construct( $slug, $name, $crm ) {

		$this->slug = $slug;
		$this->name = $name;
		$this->crm  = $crm;

		add_filter( 'wpf_configure_settings', array( $this, 'register_connection_settings' ), 15, 2 );
		add_action( 'show_field_growmatik_header_begin', array( $this, 'show_field_growmatik_header_begin' ), 10, 2 );
		add_action( 'show_field_growmatik_key_end', array( $this, 'show_field_growmatik_key_end' ), 10, 2 );

		// AJAX
		add_action( 'wp_ajax_wpf_test_connection_' . $this->slug, array( $this, 'test_connection' ) );

		if ( wp_fusion()->settings->get( 'crm' ) == $this->slug ) {
			$this->init();
		}

	}

	/**
	 * Hooks to run when this CRM is selected as active
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function init() {

		// Hooks in init() will run on the admin screen when this CRM is active

	}


	/**
	 * Loads Gromatik connection information on settings page
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function register_connection_settings( $settings, $options ) {

		$new_settings = array();

		$new_settings['growmatik_header'] = array(
			'title'   => __( 'Growmatik CRM Configuration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'setup'
		);

		$new_settings['growmatik_site_id'] = array(
			'title'       => __( 'Site ID', 'wp-fusion-lite' ),
			'desc'        => __( 'Enter your Site ID. You can get it from the <em>Site settings > Integrations > API</em>.', 'wp-fusion-lite' ),
			'std'         => '',
			'type'        => 'text',
			'section'     => 'setup'
		);

		$new_settings['growmatik_api_secret'] = array(
			'title'       => __( 'API Secret', 'wp-fusion-lite' ),
			'desc'        => __( 'Enter your Growmatik API Secret. You can generate one in the <em>Site settings > Integrations > API</em>.', 'wp-fusion-lite' ),
			'std'         => '',
			'type'        => 'text',
			'section'     => 'setup',
		);
		
		$new_settings['growmatik_api_key'] = array(
			'title'       => __( 'API Key', 'wp-fusion-lite' ),
			'desc'        => __( 'Enter your Growmatik API key. You can generate one in the <em>Site settings > Integrations > API</em>.', 'wp-fusion-lite' ),
			'type'        => 'api_validate',
			'section'     => 'setup',
			'class'       => 'api_key',
			'post_fields' => array( 'growmatik_site_id', 'growmatik_api_key' )
		);

		$settings = wp_fusion()->settings->insert_setting_after( 'crm', $settings, $new_settings );

		return $settings;

	}


	/**
	 * Puts a div around the CRM configuration section so it can be toggled
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function show_field_growmatik_header_begin( $id, $field ) {

		echo '</table>';
		$crm = wp_fusion()->settings->get( 'crm' );
		echo '<div id="' . $this->slug . '" class="crm-config ' . ( $crm == false || $crm != $this->slug ? 'hidden' : 'crm-active' ) . '" data-name="' . $this->name . '" data-crm="' . $this->slug . '">';

	}

	/**
	 * Close out Growmatik section
	 *
	 * @access  public
	 * @since   1.0
	 */


	public function show_field_growmatik_key_end( $id, $field ) {

		if ( $field['desc'] != '' ) {
			echo '<span class="description">' . $field['desc'] . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		echo '</table><div id="connection-output"></div>';
		echo '</div>'; // close #growmatik div
		echo '<table class="form-table">';

	}


	/**
	 * Verify connection credentials
	 *
	 * @access public
	 * @return bool
	 */

	public function test_connection() {

		$api_key = sanitize_text_field( $_POST['growmatik_key'] );

		$connection = $this->crm->connect( $api_key, true );

		if ( is_wp_error( $connection ) ) {

			wp_send_json_error( $connection->get_error_message() );

		} else {

			$options 						  = wp_fusion()->settings->get_all();
			$options['growmatik_api_key'] 	  = $api_key;
			$options['crm'] 				  = $this->slug;
			$options['connection_configured'] = true;

			wp_fusion()->settings->set_all( $options );

			wp_send_json_success();

		}

		die();

	}


}
