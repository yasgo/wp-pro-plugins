<?php

namespace WPForms\Pro\Integrations\LiteConnect;

/**
 * Class API.
 *
 * @since 1.7.4
 */
class API extends \WPForms\Integrations\LiteConnect\API {

	/**
	 * Batch size.
	 *
	 * @since 1.7.4
	 *
	 * @var int
	 */
	const LITE_CONNECT_BATCH_SIZE = 500;

	/**
	 * Retrieve site entries from the Lite Connect API.
	 *
	 * @since 1.7.4
	 *
	 * @param string $access_token   The access token.
	 * @param string $last_import_id The ID of the last imported entry.
	 *
	 * @return false|string
	 */
	public function retrieve_site_entries( $access_token, $last_import_id = null ) {

		$body = [
			'domain'  => $this->domain,
			'site_id' => $this->site_id,
			'size'    => self::LITE_CONNECT_BATCH_SIZE,
		];

		if ( $last_import_id ) {
			$body['last_record'] = $last_import_id;
		}

		return $this->request(
			'/retrieval/entries',
			$body,
			[
				'X-WPForms-Lite-Connect-Access-Token' => $access_token,
			]
		);
	}

	/**
	 * Add restored flag to the Lite Connect API.
	 *
	 * @since 1.7.4
	 *
	 * @param string $site_key The site key.
	 *
	 * @return false|string
	 */
	public function add_restored_flag( $site_key ) {

		return $this->request(
			'/utils/add_restored_tag',
			[
				'domain'  => $this->domain,
				'site_id' => $this->site_id,
			],
			[
				'X-WPForms-Lite-Connect-Site-Key' => $site_key,
			]
		);
	}
}
