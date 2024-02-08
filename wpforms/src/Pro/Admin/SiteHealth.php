<?php


namespace WPForms\Pro\Admin;

/**
 * Site Health in WPForms PRO Info.
 *
 * @since 1.6.3
 */
class SiteHealth extends \WPForms\Admin\SiteHealth {

	/**
	 * Load an integration.
	 *
	 * @since 1.6.3
	 */
	protected function hooks() {

		add_filter( 'debug_information', [ $this, 'add_info_section' ] );

		add_filter( 'site_status_tests', [ $this, 'license_check_register' ] );
	}

	/**
	 * Add or modify which site status tests are run on a site.
	 *
	 * @since 1.6.3
	 *
	 * @param array $tests Site health tests registered.
	 *
	 * @return array
	 */
	public function license_check_register( $tests ) {

		$tests['direct']['wpforms'] = [
			'label' => esc_html__( 'WPForms', 'wpforms' ),
			'test'  => [ $this, 'license_check' ],
		];

		return $tests;
	}

	/**
	 * License checker.
	 *
	 * @since 1.6.3
	 */
	public function license_check() {

		$license = wpforms()->get( 'license' );

		if ( empty( $license ) || ! $license->get() ) {
			$status = __( 'not detected', 'wpforms' );
		} else {
			$status = $license->validate_key( $license->get(), false, false, true );
		}

		$result = [
			'label'       => sprintf( /* translators: %s - license status. */
				esc_html__( 'Your WPForms license is %s', 'wpforms' ),
				$status
			),
			'status'      => $status === 'valid' ? 'good' : 'critical',
			'badge'       => [
				'label' => esc_html__( 'Security', 'wpforms' ),
				'color' => $status === 'valid' ? 'blue' : 'red',
			],
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'You have access to updates, addons, new features, and more.', 'wpforms' )
			),
			'actions'     => '',
			'test'        => 'wpforms',
		];

		if ( $status === 'valid' ) {
			return $result;
		}

		$result['description'] = sprintf(
			'<p>%1$s</p>
				<ul>
					<li>❌ %2$s</li>
					<li>❌ %3$s</li>
					<li>❌ %4$s</li>
					<li>❌ %5$s</li>
					<li>❌ %6$s</li>
					<li>❌ %7$s</li>
					<li>❌ %8$s</li>
					<li>❌ %9$s</li>
				</ul>',
			esc_html__( 'A valid license is required for following benefits. Please read carefully.', 'wpforms' ),
			esc_html__( 'Plugin and Addon Updates', 'wpforms' ),
			esc_html__( 'New Features', 'wpforms' ),
			esc_html__( 'New Addons and Integrations', 'wpforms' ),
			esc_html__( 'WordPress Compatibility Updates', 'wpforms' ),
			esc_html__( 'Marketing and Payment Integration Compatibility Updates', 'wpforms' ),
			esc_html__( 'Security Improvements', 'wpforms' ),
			esc_html__( 'World Class Support', 'wpforms' ),
			esc_html__( 'Plugin and Addon Access', 'wpforms' )
		);
		$result['actions']     = sprintf(
			'<p><a href="%s">%s</a></p>',
			'https://wpforms.com/account/',
			esc_html__( 'Login to your WPForms account to update', 'wpforms' )
		);

		return $result;
	}

	/**
	 * Add WPForms section to Info tab.
	 *
	 * @since 1.6.3
	 *
	 * @param array $debug_info Array of all information.
	 *
	 * @return array Array with added WPForms info section.
	 */
	public function add_info_section( $debug_info ) {

		$debug_info = parent::add_info_section( $debug_info );

		$fields = [];

		$fields['total_entries'] = [
			'label' => esc_html__( 'Total entries', 'wpforms' ),
			'value' => wpforms()->get( 'entry' )->get_entries( [], true ),
		];

		// We should be aware if license instance exists before using it.
		if ( wpforms()->get( 'license' ) === null ) {
			return $debug_info;
		}

		$license        = wpforms()->get( 'license' )->get();
		$license_status = ucfirst( wpforms()->get( 'license' )->validate_key( $license, false, false, true ) );

		if ( $license_status !== 'Valid' && ! empty( $license ) ) {
			$license_status .= " ($license)";
		}

		$fields['license_status'] = [
			'label' => esc_html__( 'License status', 'wpforms' ),
			'value' => $license_status,
		];

		$fields['license'] = [
			'label' => esc_html__( 'License key type', 'wpforms' ),
			'value' => ucfirst( wpforms_get_license_type() ),
		];

		$fields['license_location'] = [
			'label' => esc_html__( 'License key location', 'wpforms' ),
			'value' => wpforms()->get( 'license' )->get_key_location(),
		];

		$debug_info['wpforms']['fields'] = wpforms_array_insert(
			$debug_info['wpforms']['fields'],
			$fields,
			'total_forms'
		);

		return $debug_info;
	}
}
