<?php
/**
 * Template of the iframe document displaying Rich Text field value on the Entry View page.
 *
 * @since 1.7.0
 *
 * @var string $content Content HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$suffix  = SCRIPT_DEBUG ? '' : '.min';
$version = 'ver=' . get_bloginfo( 'version' );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>" />
	<title><?php echo esc_html__( 'View Entry &gt; Rich Text field', 'wpforms' ); ?></title>

	<?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( "js/tinymce/skins/lightgray/content.min.css?{$version}" ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( "css/dashicons{$suffix}.css?{$version}" ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( "js/tinymce/skins/wordpress/wp-content.css?{$version}" ) ); ?>">
	<?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>

	<style>
		body {
			margin: 8px 12px !important;
		}

		pre {
			white-space: pre !important;
			overflow-x: auto !important;
		}

		li {
			list-style-position: inside;
		}

		p img {
			display: block;
		}

		.aligncenter,
		.alignnone {
			clear: both;
			/* Make the same margins as alingleft/alignright. */
			margin-top: 0.5em;
			margin-bottom: 0.5em;
		}
	</style>
</head>
<body class="mce-content-body">
	<?php echo wpforms_esc_richtext_field( $content ); ?>
</body>
</html>
