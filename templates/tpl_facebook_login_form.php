<?php

$facebook_app_id = get_option("cfl_facebook_app_id" );

?>

<div class="login-form-container">
	<?php if ( $attributes['show_title'] ) : ?>
		<h2><?php _e( 'Sign In', 'wp_custom_facebook_login' ); ?></h2>
	<?php endif; ?>

	<!-- Show errors if there are any -->
	<?php if ( count( $attributes['errors'] ) > 0 ) : ?>
		<?php foreach ( $attributes['errors'] as $error ) : ?>
			<p class="login-error">
				<h2>An error ocurred</h2>
				<?php echo $error; ?>
			</p>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Show logged out message if user just logged out -->
	<?php if ( $attributes['logged_out'] ) : ?>
		<p class="login-info">
			<?php _e( 'You have signed out. Would you like to sign in again?', 'wp_custom_facebook_login' ); ?>
		</p>
	<?php endif; ?>

		<form name="loginform" id="loginform" action="<?php echo SITE_LOGIN;?>" method="post">
			
				<input type="hidden" name="client_id" id="client_id" class="input" value="<?php echo $facebook_app_id;?>">
				<input type="hidden" name="redirect_uri" id="redirect_uri" class="input" value="<?php echo SITE_LOGIN;?>" size="20">
			
			<p class="login-submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Sign In via Facebook">
			</p>
			
		</form>

</div>
