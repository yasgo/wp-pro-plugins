<?php
/**
 * Head template for the Entry Print page.
 *
 * @var object $entry     Entry.
 * @var array  $form_data Form data and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$min        = wpforms_get_min_suffix();
$form_title = isset( $form_data['settings']['form_title'] ) ? ucfirst( $form_data['settings']['form_title'] ) : '';

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>WPForms Print Preview - <?php echo esc_html( $form_title ); ?></title>
	<meta name="description" content="">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet, WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
	<link rel="stylesheet"
		  href="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/lib/font-awesome/font-awesome.min.css' ); ?>"
		  type="text/css">
	<link rel="stylesheet"
		  href="<?php echo esc_url( WPFORMS_PLUGIN_URL . "assets/pro/css/entry-print{$min}.css" ); ?>"
		  type="text/css">
	<script type="text/javascript" src="<?php echo esc_url( includes_url( 'js/utils.js' ) ); ?>"></script>
	<script type="text/javascript" src="<?php echo esc_url( includes_url( 'js/jquery/jquery.js' ) ); ?>"></script>
	<script type="text/javascript"
			src="<?php echo esc_url( WPFORMS_PLUGIN_URL . "assets/pro/js/admin/print-entry{$min}.js" ); ?>"></script>
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet, WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
	<?php
	/**
	 * Fires on entry print page at the <head> section.
	 *
	 * @since 1.5.1
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	do_action( 'wpforms_pro_admin_entries_printpreview_print_html_head', $entry, $form_data );
	?>
</head>
<body class="wp-core-ui">
<a href="#" class="close-window no-print" title="<?php esc_attr_e( 'Close', 'wpforms' ); ?>">
	<svg xmlns="http://www.w3.org/2000/svg" width="31" height="31" viewBox="0 0 31 31">
		<path d="M15.5 0C11.3891 0 7.44666 1.63303 4.53984 4.53984C1.63303 7.44666 0 11.3891 0 15.5C0 19.6109 1.63303 23.5533 4.53984 26.4602C7.44666 29.367 11.3891 31 15.5 31C19.6109 31 23.5533 29.367 26.4602 26.4602C29.367 23.5533 31 19.6109 31 15.5C31 11.3891 29.367 7.44666 26.4602 4.53984C23.5533 1.63303 19.6109 0 15.5 0V0ZM15.5 29C11.9196 29 8.4858 27.5777 5.95406 25.0459C3.42232 22.5142 2 19.0804 2 15.5C2 11.9196 3.42232 8.4858 5.95406 5.95406C8.4858 3.42232 11.9196 2 15.5 2C19.0804 2 22.5142 3.42232 25.0459 5.95406C27.5777 8.4858 29 11.9196 29 15.5C29 19.0804 27.5777 22.5142 25.0459 25.0459C22.5142 27.5777 19.0804 29 15.5 29ZM21.425 11.169C21.5632 11.0268 21.6405 10.8363 21.6405 10.638C21.6405 10.4397 21.5632 10.2492 21.425 10.107L20.894 9.576C20.7532 9.43518 20.5622 9.35607 20.363 9.35607C20.1638 9.35607 19.9728 9.43518 19.832 9.576L15.5 13.906L11.169 9.575C11.0282 9.43418 10.8372 9.35507 10.638 9.35507C10.4388 9.35507 10.2478 9.43418 10.107 9.575L9.576 10.106C9.43518 10.2468 9.35607 10.4378 9.35607 10.637C9.35607 10.8362 9.43518 11.0272 9.576 11.168L13.906 15.5L9.575 19.831C9.43418 19.9718 9.35507 20.1628 9.35507 20.362C9.35507 20.5612 9.43418 20.7522 9.575 20.893L10.106 21.424C10.2468 21.5648 10.4378 21.6439 10.637 21.6439C10.8362 21.6439 11.0272 21.5648 11.168 21.424L15.5 17.094L19.831 21.425C19.9718 21.5658 20.1628 21.6449 20.362 21.6449C20.5612 21.6449 20.7522 21.5658 20.893 21.425L21.424 20.894C21.5648 20.7532 21.6439 20.5622 21.6439 20.363C21.6439 20.1638 21.5648 19.9728 21.424 19.832L17.094 15.5L21.425 11.169Z"/>
	</svg>
</a>
