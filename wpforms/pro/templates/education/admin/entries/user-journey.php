<?php
/**
 * User Journey product education template.
 *
 * @var bool   $plugin_allow  Determine if user's license level has access to the addon.
 * @var string $clear_slug    Clear slug (without `wpforms-` prefix).
 * @var string $modal_name    Name of the addon used in modal window.
 * @var string $license_level License level.
 * @var string $name          Name of the addon.
 * @var string $icon          Addon icon.
 * @var string $action        Action.
 * @var string $path          Plugin path.
 * @var string $nonce         Nonce.
 * @var string $url           Download URL.
 */

use WPForms\Admin\Education\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!-- Entry User Journey metabox -->
<div id="wpforms-entry-user-journey" class="postbox wpforms-dismiss-container wpforms-addon-container">

	<div class="postbox-header">
		<h2 class="hndle">
			<span><?php esc_html_e( 'User Journey', 'wpforms' ); ?></span>
			<a class="wpforms-education-hide wpforms-dismiss-button"
			   data-section="admin-user-journey-metabox"
			   data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<span class="dashicons dashicons-no"></span>
			</a>
		</h2>
	</div>

	<div class="inside">
		<div class="wpforms-user-journey-preview">
			<div class="inside">

				<table class='wpforms-user-journey-education-table' role="presentation">
					<tbody>
					<tr>
						<td colspan="3" class="date">
							<?php
							$user_journey_time = time() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

							$user_journey_time -= 17 * MINUTE_IN_SECONDS;

							echo esc_html( wpforms_date_format( $user_journey_time ) );
							?>
						</td>
					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Search Results', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">https://www.google.com/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">

						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Homepage', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/ (Homepage)</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							1 min
						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Frequently Asked Questions', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/faq/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							2 mins
						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS * 3, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'About Us', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/about-us/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							3 mins
						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS * 6, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Meet The Team', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/about-us/meet-the-team/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							5 mins
						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS * 11, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Testimonials', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/testimonials/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							2 mins
						</td>

					</tr>

					<tr class="visit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS * 13, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<span class="title"><?php esc_html_e( 'Contact Us', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path">/contact-us/</span>

							<a href="#" class="go" target="blank" rel="noopener noreferrer" title="Go to URL">
								<i class="fa fa-external-link" aria-hidden="true"></i>
							</a>

						</td>

						<td class="duration">
							1 min
						</td>

					</tr>

					<tr class="submit">

						<td class="time"><?php echo esc_html( wpforms_date_format( $user_journey_time + MINUTE_IN_SECONDS * 14, get_option( 'time_format' ) ) ); ?></td>

						<td class="title-area">
							<i class="fa fa-check" aria-hidden="true"></i>
							<span class="title"><?php esc_html_e( 'Contact form submitted', 'wpforms' ); ?></span>

							<i class="fa fa-circle" aria-hidden="true"></i>

							<span class="path"><?php esc_html_e( 'User took 7 steps over 14 mins', 'wpforms' ); ?></span>
						</td>

						<td class="duration"></td>
					</tr>
					</tbody>
				</table>
			</div>
			<div class="overlay"></div>
			<div class="wpforms-addon-form wpforms-user-journey-form">
				<h2>
					<?php
					esc_html_e( 'User Journey', 'wpforms' );
					if ( ! $plugin_allow ) {
						Helpers::print_badge( 'Pro', 'sm', 'inline', 'platinum' );
					}
					?>
				</h2>
				<p><?php esc_html_e( 'Easily trace each visitor’s path through your site, right up to the moment they hit ‘Submit’!', 'wpforms' ); ?></p>
				<?php if ( $plugin_allow ) { ?>
					<?php if ( $action !== 'activate' ) { ?>
						<p><?php esc_html_e( 'You can install the User Journey addon with just a few clicks!', 'wpforms' ); ?></p>
					<?php } ?>
					<a
							class="<?php echo esc_attr( $action === 'activate' ? 'status-installed' : 'status-missing' ); ?> wpforms-btn wpforms-btn-lg wpforms-btn-blue wpforms-education-toggle-plugin-btn"
							data-plugin="<?php echo $action === 'activate' ? esc_attr( $path ) : esc_url( $url ); ?>"
							data-action="<?php echo esc_attr( $action ); ?>"
							data-type="addon"
							href="#">
						<?php
						$action === 'activate' ?
							esc_html_e( 'Activate', 'wpforms' ) :
							esc_html_e( 'Install & Activate', 'wpforms' );
						?>
					</a>
				<?php } else { ?>
					<p><?php esc_html_e( 'Please upgrade to the PRO plan to unlock User Journey and more awesome features.', 'wpforms' ); ?></p>
					<a
							href="<?php echo esc_url( wpforms_admin_upgrade_link( 'Entries Single', 'User Journey' ) ); ?>"
							class="wpforms-btn wpforms-btn-lg wpforms-btn-orange"><?php esc_html_e( 'Upgrade to WPForms Pro', 'wpforms' ); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>

</div>
