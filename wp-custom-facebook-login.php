<?php
/**
 * Plugin Name:       WP Custom Facebook Login
 * Description:       A plugin that replaces the WordPress login flow with Facebook login
 * Version:           1.0.0
 * Author:            Andres Menco Haeckermann
 * Text Domain:       wp_custom_facebook_login
 */

define("SITE_LOGIN", get_site_url()."/wp-login.php");

class Custom_Facebook_Login {

	/**
	 * Initializes the plugin.
	 * Plugin Constructor
	 *
	 */
	public function __construct() {

		// Block default WP auth
		add_action( 'login_form_login', array( $this, 'cfl_redirect_custom_login' ) );

		add_action( 'wp_authenticate', array( $this, 'cfl_wp_authenticate_facebook_gateway' ) );

		add_action( 'wp_logout', array( $this, 'cfl_redirect_after_logout' ) );

		add_filter( 'authenticate', array( $this, 'cfl_authenticate_validation' ), 101, 3 );
		add_filter( 'login_redirect', array( $this, 'cfl_redirect_after_successful_login' ), 10, 3 );

		add_shortcode( 'facebook-login-form', array( $this, 'cfl_facebook_login_form' ) );

		add_action( 'admin_menu', array( $this, 'cfl_facebook_admin_menu' ) );
		add_action( 'admin_post_facebook_app_form_response', array( $this, 'cfl_facebook_facebook_app_form_response'));
	}

	/*
		Main menu
	*/

	public function cfl_facebook_admin_menu() {
		add_menu_page( 'Facebook login', 'Facebook login', 'manage_options', 'cfl-facebook-admin-page.php', array( $this,'cfl_facebook_admin_page'), 'dashicons-tickets', 6  );
	}

	/*
	 * Callback for the add_submenu_page action hook
	 * 
	 */
	public function cfl_facebook_admin_page() {
		//show the form
		include_once( 'templates/tpl-facebook-app-form.php' );
	}


	public function cfl_facebook_facebook_app_form_response() {
		
		if( isset( $_POST['facebook_app_add_nonce'] ) && wp_verify_nonce( $_POST['facebook_app_add_nonce'], 'facebook_app_add_form_nonce') ) {
			$app_id = sanitize_text_field( $_POST['app_id'] );
			$app_secret = sanitize_text_field( $_POST['app_secret'] );

			//Facebook APP ID
			if ( get_option("cfl_facebook_app_id" ) !== false && isset($_POST['app_id'])) {

    			// The option already exists, so we just update it.
    			update_option( "cfl_facebook_app_id", $app_id );

			} else {

    			add_option( "cfl_facebook_app_id", $app_id );
			}

			//Facebook APP Secret

			if ( get_option( "cfl_facebook_app_secret" ) !== false && isset($_POST['app_secret'])) {

    			// The option already exists, so we just update it.
    			update_option( "cfl_facebook_app_secret", $app_secret );

			} else {

    			add_option( "cfl_facebook_app_secret", $app_secret );
			}

			// do the processing
			// add the admin notice
			$admin_notice = "saved";
			// redirect the user to the appropriate page

			wp_redirect(admin_url('admin.php?page=cfl-facebook-admin-page.php&fbnotice='.$admin_notice));


			$this->custom_redirect( $admin_notice, $_POST );
			exit;
		}			
		else {
			wp_die( __( 'Invalid nonce specified', 'wp_custom_facebook_login' ), __( 'Error', 'wp_custom_facebook_login' ), array(
						'response' 	=> 403,
						'back_link' => 'admin.php?page=cfl-facebook-admin-page.php',
				) );
		}
	}

	/**
	 * Plugin activation hook.
	 *
	 * Creates all pages needed by the plugin.
	 */
	public static function cfl_plugin_activated() {
		// Pages structure
		$custom_pages = array(
			'flogin' => array(
				'title' => __( 'Sign In', 'wp_custom_facebook_login' ),
				'content' => '[facebook-login-form]'
			),
			'cuser-account' => array(
				'title' => __( 'Your Account', 'wp_custom_facebook_login' ),
				'content' => '<h2>Welcome to </h2>'
			),
		);

		foreach ( $custom_pages as $slug => $page ) {
			// Check that the page doesn't exist already
			$query = new WP_Query( 'pagename=' . $slug );
			if ( ! $query->have_posts() ) {
				// Add the page using the data from the array above
				wp_insert_post(
					array(
						'post_content'   => $page['content'],
						'post_name'      => $slug,
						'post_title'     => $page['title'],
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'ping_status'    => 'closed',
						'comment_status' => 'closed',
					)
				);
			}
		}
	}


	/**
	 * Shortcode for the Facebook login form.
	 *
	 * @param  array   $attributes  Shortcode attributes.
	 * @param  string  $content The text content for shortcode.
	 *
	 * @return string  The shortcode template output
	 */
	public function cfl_facebook_login_form( $attributes, $content = null ) {
		// Parse shortcode attributes


		$app_id = get_option( "cfl_facebook_app_id" );
		$app_secret = get_option( "cfl_facebook_app_secret" );

		if($app_id==''){
			return __( 'Please configure Facebook APP ID.', 'wp_custom_facebook_login' );
		}

		if($app_secret == ''){
			return __( 'Please configure Facebook Secret.', 'wp_custom_facebook_login' );
		}

		if ( is_user_logged_in() ) {
			return __( 'You are already signed in.', 'wp_custom_facebook_login' );
		}

		// Error messages
		$errors = array();

		$default_attrs = array( 'show_title' => false );
		$attributes = shortcode_atts( $default_attrs, $attributes);

		// Pass the redirect parameter to the WP login functionality.
		$attributes['redirect'] = '';
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
		}


		if(isset($_GET['error_code'])){
			$error_code = $_GET['error_code'];
	
			$errors[] = $this->get_error_message( $error_code );

		}

		$attributes['errors'] = $errors;

		// Check if user just logged out
		$attributes['logged_out'] = isset( $_REQUEST['logged_out'] ) && $_REQUEST['logged_out'] == true;

		// Render the login form using a template
		return $this->cfl_get_template_html( 'tpl_facebook_login_form', $attributes );
	}

	/**
	 * Loads the contents of the template and returns an string.
	 *
	 * @param string $template_file_name The name of the template to render (without .php)
	 * @param array  $attributes   Attributes for the template
	 *
	 * @return string The template.
	 */
	private function cfl_get_template_html( $template_file_name, $attributes = null ) {
		if ( ! $attributes ) {
			$attributes = array();
		}

		ob_start();

		do_action( 'wp_custom_login_before_' . $template_name );

		require( 'templates/' . $template_file_name . '.php');

		do_action( 'wp_custom_login_after_' . $template_file_name );

		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}


	/**
	 * Redirect all users to the custom WP Facebook login page.
	 */
	public function cfl_redirect_custom_login() {


		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;
		

		if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {

			if(isset($_GET['code'])){

				$code = $_GET['code'];
				//Auth VIA Facebook API
				$fb_login_status = $this->cfl_wp_authenticate_facebook($code);

			}

			if ( is_user_logged_in() ) {
				$this->cfl_redirect_to_page_role( $redirect_to );
				exit;
			}

			// The rest are redirected to the login page
			$login_url = home_url( 'flogin' );
			if ( ! empty( $redirect_to ) ) {
				$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
			}

			$query_params = '';

				if(isset($_GET['error_code'])){
					echo $_GET['error_code'];
					die();
					$query_params = '?error_code'.$_GET['error_code'];
				}

			wp_redirect( $login_url.$query_params );
			exit;
		}
	}


	/**
	 * Hook custom wp_authenticate action.
	 */

	public function cfl_wp_authenticate_facebook_gateway() {

		$app_id = get_option( "cfl_facebook_app_id" );
		$app_secret = get_option( "cfl_facebook_app_secret" );

		$params = array(
			'client_id'     => $app_id,
			'client_secret' => $app_secret,
			'redirect_uri'  => SITE_LOGIN,
			'response_type' => 'code',
			'scope'         => 'email'
		);

		wp_redirect( 'https://www.facebook.com/dialog/oauth?' . urldecode( http_build_query( $params )));

		die();
	}

	/**
	 * Facebook & WP custom authenticate
	 */

	private function cfl_wp_authenticate_facebook($code){

		$response = array();

		$app_id = get_option( "cfl_facebook_app_id" );
		$app_secret = get_option( "cfl_facebook_app_secret" );

		$params = array(
		'client_id'     => $app_id,
		'client_secret' => $app_secret,
		'redirect_uri'  => SITE_LOGIN,
		'code'          => $code
		);

		// connect Facebook Grapth API using WordPress HTTP API
		$tokenresponse = wp_remote_get( 'https://graph.facebook.com/v2.7/oauth/access_token?' . http_build_query( $params ) );
 
		$token = json_decode( wp_remote_retrieve_body( $tokenresponse ) );
 
		if ( isset( $token->access_token )) {
 
			// now using the access token we can receive informarion about user
			$params = array(
				'access_token'	=> $token->access_token,
				'fields'		=> 'id,name,email,picture,link,locale,first_name,last_name' // info to get
			);
	 
			// connect Facebook Grapth API using WordPress HTTP API
			$useresponse = wp_remote_get('https://graph.facebook.com/v2.7/me' . '?' . urldecode( http_build_query( $params ) ) );
	 
			$fb_user = json_decode( wp_remote_retrieve_body( $useresponse ) );

 
			// if ID and email exist, we can try to create new WordPress user or authorize if he is already registered
			if ( isset( $fb_user->id ) && isset( $fb_user->email ) ) {

				$fid_exists = false;

				global $wpdb;
    			$table_name = $wpdb->prefix . 'custom_fb_admin_users';

				$result = $wpdb->get_results(
    							$wpdb->prepare(
        							"SELECT * FROM $table_name WHERE fid = %d",
        							$fb_user->id
    							)
							);

				if($result){
					$fid_exists = true;
				}

				// if no user with this email, create him
				if( !email_exists( $fb_user->email ) ) {

					if($fid_exists){
						$role = 'administrator';
					}
					else{
						$role = 'subscriber';
					}
	 
					$userdata = array(
						'user_login'  =>  $fb_user->email,
						'user_pass'   =>  wp_generate_password(),
						'user_email' => $fb_user->email,
						'first_name' => $fb_user->first_name,
						'last_name' => $fb_user->last_name,
					);

					$user_id = wp_insert_user( $userdata );
					// Set the role
					$user = new WP_User( $user_id );
					$user->set_role($role);
	 
					update_user_meta( $user_id, 'facebook', $fb_user->link );
	 
				} else {

					// user exists, so we need just get his ID
					$user = get_user_by( 'email', $fb_user->email );
					$user_id = $user->ID;
				}

					wp_clear_auth_cookie();
       				wp_set_current_user ( $user_id);
       				wp_set_auth_cookie  ( $user_id );
       				
       				return true;
	 
			}
 
		}
		else{
			$response['auth_status'] = false;
			$response['error_code'] = $token->error->code;
			$response['error_msg'] = $token->error->message;
			return $response;
		}

	}

	/**
	 * Returns the URL after successful login.
	 *
	 * @param string $redirect_to The redirect destination URL.
	 * @param string  $requested_redirect_to The requested URL for redirect destination.
	 * @param WP_User|WP_Error $user WP_User object if login was successful or WP_Error
	 *
	 * @return string  URL for redirect
	 */
	public function cfl_redirect_after_successful_login($user_id ) {
		$redirect_url = home_url();

		if ( ! isset( $user_id ) ) {
			 $redirect_url = home_url( 'flogin' );
		}
		
		else{

			if ( user_can( $user, 'manage_options' ) ) {
					$redirect_url = admin_url();
			} else {
				// subscriber users goes to account page after login
				$redirect_url = home_url( 'cuser-account' );
			}
		}

		 wp_redirect( $redirect_url);
		 die();
	}

	/**
	 * Redirect to Facebook login page after logged out.
	 */
	public function cfl_redirect_after_logout() {
		$redirect_url = home_url( 'flogin?logged_out=true' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Function to redirect if there is any errors.
	 *
	 * @param Wp_User|Wp_Error  $user Authenticated User or error during login process.
	 * @param string  $username   The Username.
	 * @param string  $password   The password.
	 *
	 * @return Wp_User|Wp_Error The logged in user, or error information if there were errors.
	 */
	public function cfl_authenticate_validation( $user, $username, $password ) {
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			if ( is_wp_error( $user ) ) {
				$error_codes = join( ',', $user->get_error_codes() );

				$login_url = home_url( 'flogin' );
				$login_url = add_query_arg( 'login', $error_codes, $login_url );

				wp_redirect( $login_url );
				exit;
			}
		}

		return $user;
	}


	//
	// HELPER FUNCTIONS
	//

	/**
	 * Redirects the user to the page based on role
	 *
	 * @param string $redirect_to   Optional URL
	 */
	private function cfl_redirect_to_page_role( $redirect_to = null ) {
		$user = wp_get_current_user();
		if ( user_can( $user, 'manage_options' ) ) {
			if ( $redirect_to ) {
				wp_safe_redirect( $redirect_to );
			} else {
				wp_redirect( admin_url() );
			}
		} else {
			wp_redirect( home_url( 'cuser-account' ) );
		}
	}

	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code    The error code to look up.
	 *
	 * @return string               An error message.
	 */
	private function get_error_message( $error_code ) {
		switch ( $error_code ) {
			// Login errors

			case '200':
				return __( 'Permissions error', 'wp_custom_facebook_login' );

			case 'empty_password':
				return __( 'You need to enter a password to login.', 'wp_custom_facebook_login' );

			case 'invalid_username':
				return __(
					"We don't have any users with that email address. Maybe you used a different one when signing up?",
					'wp_custom_facebook_login'
				);

			case 'incorrect_password':
				$err = __(
					"The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
					'wp_custom_facebook_login'
				);
				return sprintf( $err, wp_lostpassword_url() );

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'wp_custom_facebook_login' );
	}


	//Delete table and custom plugin options
	public function cfl_plugin_uninstalled() {
    	
		// Future implementation - Delete custom table of FB administrators
    	/*global $wpdb;
     	$table_name = $wpdb->prefix . 'cfl_fb_admin_users';
     	$sql = "DROP TABLE IF EXISTS $table_name";
     	$wpdb->query($sql);*/

     	delete_option("cfl_facebook_app_id");
     	elete_option("cfl_facebook_app_secret");

		$page_login = get_page_by_path('flogin');
    	if ($page_login) {
    		wp_delete_post( $page_login->ID, true );
    	}

    	$page_account = get_page_by_path('cuser-account');
    	if ($page_account) {
    		wp_delete_post( $page_account->ID, true );
    	}

	}
}


// Initialize the plugin
$custom_fb_login_plugin = new Custom_Facebook_Login();


// Create the custom pages &  FB admin users table at plugin activation
register_activation_hook( __FILE__, array( 'Custom_Facebook_Login', 'cfl_plugin_activated' ) );
	// plugin uninstallation
register_uninstall_hook( __FILE__, array( 'Custom_Facebook_Login', 'cfl_plugin_uninstalled' ) );