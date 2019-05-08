<?php
	/**
	 * The form to be loaded on the plugin's admin page
	 */
	if( current_user_can( 'manage_options' ) ) {		
	// Generate a custom nonce value.
	$fb_app_add_meta_nonce = wp_create_nonce( 'facebook_app_add_form_nonce' ); 
	$app_id = get_option( "cfl_facebook_app_id" );
	$app_secret = get_option( "cfl_facebook_app_secret" );
	// Build the Form
?>				
	<h2><?php _e( 'Facebook app configuration', 'wp_custom_facebook_login' ); ?></h2>
	<?php if(isset($_GET['fbnotice'])):?>
		<?php $msg = ($_GET['fbnotice']=='saved')?  'Options saved!!':"error";?>
		<div class="updated below-h2" id="message"><p><?php echo $msg;?></p></div>
	<?php endif;?>

	<div class="facebook_app_add_form">

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="facebook_app_add_form" >			

		<input type="hidden" name="action" value="facebook_app_form_response">
		<input type="hidden" name="facebook_app_add_nonce" value="<?php echo $fb_app_add_meta_nonce; ?>" />			
		<div>
			<br>
			<input required id="app_id" type="text" name="app_id" value="<?php echo $app_id;?>" placeholder="<?php _e('APP ID','wp_custom_facebook_login');?>" /><br>
		</div>
		<div>
			<input required id="app_secret" type="password" name="app_secret" value="<?php echo $app_secret ?>" placeholder="<?php _e('APP Secret', 'wp_custom_facebook_login');?>"/><br>
		</div> 

		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Submit Form"></p>
	</form>
	<br/><br/>		
	</div>
<?php    
}
else {  
?>
	<p> <?php __("You are not authorized to perform this operation.", "wp_custom_facebook_login") ?> </p>
<?php   
}