<?php
/**
 * Admin View: Notice - who must use two-factor.
 *
 * @package Force_2FA
 *
 * @var string|null $scope    Scope saved in the admin, or null before the first choice.
 * @var string      $selected Choice to pre-select.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="notice notice-warning">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'Require Email 2FA: who must use two-factor login?', 'force-email-two-factor' ); ?></strong>
			<?php if ( null === $scope ) : ?>
				<?php esc_html_e( 'Until you save a choice, it is required for all users.', 'force-email-two-factor' ); ?>
			<?php endif; ?>
		</p>
		<p>
			<?php foreach ( Force_2FA_Policy::scopes() as $value => $label ) : ?>
				<label>
					<input type="radio" name="<?php echo esc_attr( Force_2FA_Admin::SCOPE_FIELD ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $selected ); ?>>
					<?php echo esc_html( $label ); ?>
				</label><br>
			<?php endforeach; ?>
		</p>
		<input type="hidden" name="action" value="<?php echo esc_attr( Force_2FA_Admin::SCOPE_ACTION ); ?>">
		<?php wp_nonce_field( Force_2FA_Admin::SCOPE_ACTION ); ?>
		<?php submit_button( __( 'Save', 'force-email-two-factor' ), 'primary', 'submit', false ); ?>
	</form>
</div>
