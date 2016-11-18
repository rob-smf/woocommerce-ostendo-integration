<?php
/**
 * Ostendo Integration.
 *
 * @package  WC_Integration_Ostendo_Integration
 * @category Integration
 * @author   Robert Schillinger
 */
if ( ! class_exists( 'WC_Integration_Ostendo_Integration' ) ) :

class WC_Integration_Ostendo_Integration extends WC_Integration {
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		$this->id                 = 'integration-ostendo';
		$this->method_title       = __( 'Ostendo', 'woocommerce-integration-ostendo' );
		$this->method_description = __( 'Integrate with Ostendo.', 'woocommerce-integration-ostendo' );
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		// Define user set variables.
		$this->enable_ostendo_import			  = $this->get_option( 'enable_ostendo_import' );
		$this->api_endpoint			  			  = $this->get_option( 'api_endpoint' );
        $this->enable_ostendo_sales_order         = $this->get_option( 'enable_ostendo_sales_order' );
		$this->email_recipient        			  = $this->get_option( 'email_recipient' );
        $this->email_subject          			  = $this->get_option( 'email_subject' );
        $this->email_message          			  = $this->get_option( 'email_message' );
		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
	}
	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enable_ostendo_import' => array(
                'title'             => __( 'Enable Import', 'woocommerce-integration-ostendo' ),
				'type'              => 'checkbox',
				'description'       => __( 'Check this if you would like to enable importing product stock from Ostendo.', 'woocommerce-integration-ostendo' ),
				'label'				=> __( 'Check this if you would like to enable importing product stock from Ostendo.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => 'no',
            ),
			'api_endpoint' => array(
				'title'             => __( 'API Endpoint', 'woocommerce-integration-ostendo' ),
				'type'              => 'text',
				'description'       => __( 'Please provide the API endpoint.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'enable_ostendo_sales_order' => array(
                'title'             => __( 'Enable Sales Orders', 'woocommerce-integration-ostendo' ),
				'type'              => 'checkbox',
				'label'				=> __( 'Check this if you would like to enable sending an XML to Ostendo after each sale.', 'woocommerce-integration-ostendo' ),
				'description'       => __( 'Check this if you would like to enable sending an XML to Ostendo after each sale.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => 'no',
            ),
			'email_recipient' => array(
				'title'             => __( 'Email Recipient', 'woocommerce-integration-ostendo' ),
				'type'              => 'text',
				'description'       => __( 'Please enter the email address that should receive the XML file.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => ''
			),
            'email_subject' => array(
				'title'             => __( 'Email Subject', 'woocommerce-integration-ostendo' ),
				'type'              => 'text',
				'description'       => __( 'Please enter the subject of the email.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => ''
			),
            'email_message' => array(
				'title'             => __( 'Email Message', 'woocommerce-integration-ostendo' ),
				'type'              => 'text',
				'description'       => __( 'Please enter any copy that should appear in the body of the email.', 'woocommerce-integration-ostendo' ),
				'desc_tip'          => true,
				'default'           => ''
			),
		);
	}

}

endif;
