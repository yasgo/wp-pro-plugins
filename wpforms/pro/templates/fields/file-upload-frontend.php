<?php
/**
 * Modern file upload template.
 *
 * @var int    $field_id        Field ID.
 * @var int    $form_id         Form ID.
 * @var string $value           Field value.
 * @var string $input_name      Field name.
 * @var string $extensions      Allowed extensions.
 * @var int    $max_size        Max file size.
 * @var int    $max_file_number Max file number.
 * @var int    $post_max_size   Max size for POST request.
 * @var int    $chunk_size      Chunk size.
 * @var string $preview_hint    Preview hint.
 * @var string $required        Is the field required?
 * @var bool   $is_full         Is the field has maximum uploaded files?
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div
		class="wpforms-uploader"
		data-field-id="<?php echo absint( $field_id ); ?>"
		data-form-id="<?php echo absint( $form_id ); ?>"
		data-input-name="<?php echo esc_attr( $input_name ); ?>"
		data-extensions="<?php echo esc_attr( $extensions ); ?>"
		data-max-size="<?php echo absint( $max_size ); ?>"
		data-max-file-number="<?php echo absint( $max_file_number ); ?>"
		data-post-max-size="<?php echo absint( $post_max_size ); ?>"
		data-max-parallel-uploads="4"
		data-parallel-uploads="true"
		data-file-chunk-size="<?php echo absint( $chunk_size ); ?>">
	<div class="dz-message<?php echo $is_full ? ' hide' : ''; ?>">
		<svg viewBox="0 0 1024 1024" focusable="false" data-icon="inbox" width="50px" height="50px" fill="currentColor" aria-hidden="true">
			<path d="M885.2 446.3l-.2-.8-112.2-285.1c-5-16.1-19.9-27.2-36.8-27.2H281.2c-17 0-32.1 11.3-36.9 27.6L139.4 443l-.3.7-.2.8c-1.3 4.9-1.7 9.9-1 14.8-.1 1.6-.2 3.2-.2 4.8V830a60.9 60.9 0 0 0 60.8 60.8h627.2c33.5 0 60.8-27.3 60.9-60.8V464.1c0-1.3 0-2.6-.1-3.7.4-4.9 0-9.6-1.3-14.1zm-295.8-43l-.3 15.7c-.8 44.9-31.8 75.1-77.1 75.1-22.1 0-41.1-7.1-54.8-20.6S436 441.2 435.6 419l-.3-15.7H229.5L309 210h399.2l81.7 193.3H589.4zm-375 76.8h157.3c24.3 57.1 76 90.8 140.4 90.8 33.7 0 65-9.4 90.3-27.2 22.2-15.6 39.5-37.4 50.7-63.6h156.5V814H214.4V480.1z"></path>
		</svg>
		<span class="modern-title"><?php echo esc_html( _n( 'Click or drag a file to this area to upload.', 'Click or drag files to this area to upload.', (int) $max_file_number, 'wpforms' ) ); ?></span>

		<?php if ( (int) $max_file_number > 1 ) : ?>
			<span class="modern-hint"><?php echo esc_html( $preview_hint ); ?></span>
		<?php endif; ?>
	</div>
</div>
<input
		type="text"
		autocomplete="off"
		class="dropzone-input"
		style="position:absolute!important;clip:rect(0,0,0,0)!important;height:1px!important;width:1px!important;border:0!important;overflow:hidden!important;padding:0!important;margin:0!important;"
		id="wpforms-<?php echo absint( $form_id ); ?>-field_<?php echo absint( $field_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>" <?php echo esc_attr( $required ); ?>
		value="<?php echo esc_attr( $value ); ?>">
