<?php
/**
 * Plugin Name: Gigya - Make Your Site Social
 * Plugin URI: http://gigya.com
 * Description: Allows sites to utilize the Gigya API for authentication and social network updates.
 * Version: 5.0
 * Author: Gigya
 * Author URI: http://gigya.com
 * License: GPL2+
 */

// --------------------------------------------------------------------

/**
 * Global constants.
 */
define( 'GIGYA__MINIMUM_WP_VERSION', '3.5' );
define( 'GIGYA__MINIMUM_PHP_VERSION', '5.2' );
define( 'GIGYA__VERSION', '5.0' );
define( 'GIGYA__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIGYA__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Gigya settings sections.
 */
define( 'GIGYA__SETTINGS_GLOBAL', 'gigya_global_settings' );
define( 'GIGYA__SETTINGS_LOGIN', 'gigya_login_settings' );
define( 'GIGYA__SETTINGS_SHARE', 'gigya_share_settings' );
define( 'GIGYA__SETTINGS_COMMENTS', 'gigya_comments_settings' );
define( 'GIGYA__SETTINGS_REACTIONS', 'gigya_reactions_settings' );
define( 'GIGYA__SETTINGS_GM', 'gigya_gm_settings' );

// --------------------------------------------------------------------

/**
 * Hook init.
 */
add_action( 'init', '_gigya_init_action' );
function _gigya_init_action() {
	require_once( GIGYA__PLUGIN_DIR . 'sdk/GSSDK.php' );
	require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaUser.php' );
	require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaApi.php' );

	// Load jQuery.
	wp_enqueue_script( 'jquery' );

	// Gigya configuration values.
	$login_options  = get_option( GIGYA__SETTINGS_LOGIN );
	$global_options = get_option( GIGYA__SETTINGS_GLOBAL );

	if ( ($login_options['login_mode'] == 'wp_sl' || $login_options['login_mode'] == 'sl_only') && ! empty( $global_options['global_api_key'] ) ) {
		// Load Gigya's socialize.js.
		wp_enqueue_script( 'gigya', 'http://cdn.gigya.com/JS/socialize.js?apiKey=' . $global_options['global_api_key'], 'jquery' );
	}

	if ( is_admin() ) {
		// Load the settings.
		require_once( GIGYA__PLUGIN_DIR . 'admin/admin.GigyaSettings.php' );
		new GigyaSettings;
	}

}

// --------------------------------------------------------------------

/**
 * admin_action_ hook.
 * Fires when an 'action' request variable is sent.
 */
add_action( 'admin_action_update', '_gigya_settings_submit_action' );
function _gigya_settings_submit_action() {

	if ( isset( $_POST['gigya_login_settings'] ) ) {

		// When we turn on the Gigya's social login plugin,
		// We also turn on the WP 'Membership: Anyone can register' option.
		if ( $_POST['gigya_login_settings']['login_mode'] != 'wp_only' ) {
			update_option( 'users_can_register', 1 );
		}
	}
}

// --------------------------------------------------------------------

/**
 * Hook login form.
 * Hook register form.
 */
add_action( 'login_form', '_gigya_login_form_action' );
add_action( 'register_form', '_gigya_login_form_action' );
function _gigya_login_form_action() {

//	static $once = false;
//	if (empty($once)) {
//		$once = true;

	// Adds jQueryUI.
	wp_enqueue_script( 'jquery-ui-core' );
	wp_enqueue_script( 'jquery-ui-dialog' );

	require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaLoginForm.php' );
	$gigyaLoginForm = new GigyaLoginForm;
	$gigyaLoginForm->init();
//	}

}

// --------------------------------------------------------------------

/**
 * Hook AJAX login.
 */
add_action( 'wp_ajax_gigya_login', '_gigya_ajax_login_action' );
add_action( 'wp_ajax_nopriv_gigya_login', '_gigya_ajax_login_action' );
function _gigya_ajax_login_action() {

	require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaLoginAction.php' );
	$gigyaLoginAction = new GigyaLoginAction;
	$gigyaLoginAction->init();

}

// --------------------------------------------------------------------

/**
 * Hook AJAX RAAS login.
 */
add_action( 'wp_ajax_gigya_raas_login', '_gigya_ajax_raas_login_action' );
add_action( 'wp_ajax_nopriv_gigya_raas_login', '_gigya_ajax_raas_login_action' );
function _gigya_ajax_raas_login_action() {

	require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaRassLoginAction.php' );
	$gigyaLoginAction = new GigyaRaasLoginAction;
	$gigyaLoginAction->init();

}

// --------------------------------------------------------------------

/**
 * Hook script load.
 */
add_action( 'wp_enqueue_scripts', '_gigya_wp_enqueue_scripts_action' );
function _gigya_wp_enqueue_scripts_action() {
}

// --------------------------------------------------------------------

/**
 * Hook user login.
 */
add_action( 'wp_login', '_gigya_wp_login_action', 10, 2 );
function _gigya_wp_login_action( $user_login, $account ) {

	if ( empty ( $_SESSION['gigya_uid'] ) && empty( $_POST['gigyaUID'] ) ) {

		// Notify Gigya socialize.notifyLogin
		// for a return user logged in from SITE.
		$gigyaUser = new GigyaUser( $account->ID );
		$gigyaUser->notifyLogin( $account->ID );

	}
}

// --------------------------------------------------------------------

/**
 * Hook user register.
 */
add_action( 'user_register', '_gigya_user_register_action', 10, 1 );
function _gigya_user_register_action( $uid ) {

	if ( ! empty( $_POST['gigyaUID'] ) ) {

		// Come from register extra form.
		// Make a login.
		// @todo: add  possibility to verify email.
		$wp_user = get_userdata( $uid );
		require_once( GIGYA__PLUGIN_DIR . 'class/class.GigyaLoginAction.php' );
		GigyaLoginAction::login( $wp_user );

	}

	if ( ! empty ( $_SESSION['gigya_uid'] ) || ! empty( $_POST['gigyaUID'] ) ) {

		// New user on site came from Gigya.
		// Make a notifyRegistration (link accounts) to Gigya.
		$gid       = ! empty( $_SESSION['gigya_uid'] ) ? $_SESSION['gigya_uid'] : $_POST['gigyaUID'];
		$gigyaUser = new GigyaUser( $gid );
		$gigyaUser->notifyRegistration( $uid );

	} else {

		// New user on site came from SITE.
		// Notify Gigya socialize.notifyLogin with a new user flag.
		$gigyaUser = new GigyaUser( $_SESSION['gigya_uid'] );
		$gigyaUser->notifyLogin( $uid, TRUE );

	}
}

// --------------------------------------------------------------------

/**
 * Hook user logout
 */
add_action( 'wp_logout', '_gigya_user_logout_action' );
function _gigya_user_logout_action() {

	// Get the current user.
	$account = wp_get_current_user();

	if ( ! empty ( $account->ID ) ) {

		// We using Gigya's account-linking (best practice).
		// So the siteUID is the same as Gigya's UID.
		$gigyaUser = new GigyaUser( $account->ID );
		$gigyaUser->logout();

	}
}

// --------------------------------------------------------------------

add_action( 'deleted_user', '_gigya_deleted_user_action' );
function _gigya_deleted_user_action( $user_id ) {

	// Check it logged in by Gigya.
	if ( empty ( $_SESSION['gigya_uid'] ) ) {
		$gigyaUser = new GigyaUser( $_SESSION['gigya_uid'] );
		$gigyaUser->deleteAccount( $user_id );
	}

}

// --------------------------------------------------------------------

add_shortcode( 'gigya_user_info', '_gigya_user_info_shortcode' );
function _gigya_user_info_shortcode( $attrs, $info = NULL ) {

	if ( NULL == $info ) {
		$user_info = GigyaSO_Util::get_user_info();
	}
	return $user_info->getString( key( $attrs ), current( $attrs ) );

}

// --------------------------------------------------------------------

/**
 * Renders a default template.
 *
 * @param $template_file
 *   The filename of the template to render.
 * @param $variables
 *   A keyed array of variables that will appear in the output.
 *
 * @return void The output generated by the template.
 */
function _gigya_render_tpl( $template_file, $variables ) {

	// Extract the variables to a local namespace
	extract( $variables, EXTR_SKIP );

	// Start output buffering
	ob_start();

	// Include the template file
	include GIGYA__PLUGIN_DIR . '/' . $template_file;

	// End buffering and return its contents
	return ob_get_clean();

}

// --------------------------------------------------------------------

/**
 * Render a form.
 *
 * @param        $form
 *
 * @param string $name_prefix
 *
 * @return string
 */
function _gigya_form_render( $form, $name_prefix = '' ) {

	$render = '';

	foreach ( $form as $id => $el ) {

		if ( empty( $el['type'] ) || $el['type'] == 'markup' ) {

			$render .= $el['markup'];

		} else {

			if ( ! empty( $name_prefix ) ) {

				// In cases like on admin multipage the element
				// name is build from the section and the ID.
				// This tells WP under which option to save this field value.
				$el['name'] = $name_prefix . '[' . $id . ']';

			} else {

				// Usually the element name is just the ID.
				$el['name'] = $id;

			}

			// Add the ID value to the array.
			$el['id'] = $id;

			// Render each element.
			$render .= _gigya_render_tpl( 'admin/tpl/formEl-' . $el['type'] . '.tpl.php', $el );

		}
	}

	return $render;

}

// --------------------------------------------------------------------

/**
 * Loads JSON string from file.
 *
 * @param $file | relative path from Gigya plugin root.
 *              DO NOT include filename extension.
 *
 * @return bool|string
 */
function _gigya_get_json( $file ) {

	$path = GIGYA__PLUGIN_DIR . $file . '.json';
	$json = file_get_contents( $path );
	return ! empty( $json ) ? $json : FALSE;

}

// --------------------------------------------------------------------