<?php

namespace Gigya\WordPress\Admin;

/*
 * Plugin editing permission levels
 */

use Exception;
use Gigya\CMSKit\GigyaApiHelper;
use Gigya\CMSKit\GigyaCMS;
use Gigya\PHP\GSException;

define( "GIGYA__PERMISSION_LEVEL", "manage_options" );
define( "GIGYA__SECRET_PERMISSION_LEVEL", "install_plugins" ); // Network super admin + single site admin

// custom Gigya capabilities are added separately on installation
define( "CUSTOM_GIGYA_EDIT", 'edit_gigya' );
define( "CUSTOM_GIGYA_EDIT_SECRET", 'edit_gigya_secret' );

class GigyaSettings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add Javascript and css to admin page
		wp_enqueue_style( 'gigya_admin_css', GIGYA__PLUGIN_URL . 'admin/gigya_admin.css' );
		wp_enqueue_script( 'gigya_admin_js', GIGYA__PLUGIN_URL . 'admin/gigya_admin.js' );
		wp_enqueue_script( 'gigya_jsonlint_js', GIGYA__PLUGIN_URL . 'admin/jsonlint.js' );

		//Adding variables to gigya_Admin.js
		$params = ['offLineSyncMinFreq'=>GIGYA__OFFLINE_SYNC_MIN_FREQ];
		wp_localize_script( 'gigya_admin_js', 'gigyaAdminParams', $params );


		// Actions.
		add_action( 'admin_init', array( $this, 'adminInit' ) );
		add_action( 'admin_menu', array( $this, 'adminMenu' ) );
	}

	/**
	 * Hook admin_init callback.
	 * Initialize Admin section.
	 */
	public function adminInit() {

		// Add settings sections.
		foreach ( $this->getSections() as $id => $section ) {
			$option_group = $section['slug'] . '-group';
			add_settings_section( $id, $section['title'], $section['func'], $section['slug'] );
			register_setting( $option_group, $section['slug'], array( $this, 'validate' ) );
			add_filter( "option_page_capability_{$option_group}", array( $this, 'addGigyaCapabilities' ) );
		}
	}

	/**
	 * Add gigya edit capability to allow custom roles to edit Gigya
	 */
	public function addGigyaCapabilities() {
		return CUSTOM_GIGYA_EDIT;
	}

	/**
	 * Hook admin_menu callback.
	 * Set Gigya's Setting area.
	 */
	public function adminMenu() {
		// Default admin capabilities
		if ( current_user_can( 'GIGYA__PERMISSION_LEVEL' ) ) {
			// Register the main Gigya setting route page.
			add_menu_page( 'Customer Data Cloud', 'Customer Data Cloud', GIGYA__PERMISSION_LEVEL, 'gigya_global_settings', array(
				$this,
				'adminPage'
			), GIGYA__PLUGIN_URL . 'admin/images/SAP_R_grad_scrn.jpg', '70.1' );

			// Register the sub-menus Gigya setting pages.
			foreach ( $this->getSections() as $section ) {

				require_once GIGYA__PLUGIN_DIR . 'admin/forms/' . $section['func'] . '.php';
				add_submenu_page( 'gigya_global_settings', __( $section['title'], $section['title'] ), __( $section['title'], $section['title'] ), GIGYA__PERMISSION_LEVEL, $section['slug'], array(
					$this,
					'adminPage'
				) );

			}
		} elseif ( current_user_can( CUSTOM_GIGYA_EDIT ) ) {
			// Register the main Gigya setting route page.
			add_menu_page( 'Customer Data Cloud', 'Customer Data Cloud', CUSTOM_GIGYA_EDIT, 'gigya_global_settings', array(
				$this,
				'adminPage'
			), GIGYA__PLUGIN_URL . 'admin/images/SAP_R_grad_scrn.png', '70.1' );

			// Register the sub-menus Gigya setting pages.
			foreach ( $this->getSections() as $section ) {

				require_once GIGYA__PLUGIN_DIR . 'admin/forms/' . $section['func'] . '.php';
				add_submenu_page( 'gigya_global_settings', __( $section['title'], $section['title'] ), __( $section['title'], $section['title'] ), CUSTOM_GIGYA_EDIT, $section['slug'], array(
					$this,
					'adminPage'
				) );

			}
		}
	}

	/**
	 * Returns the form sections definition
	 * @return array
	 */
	public static function getSections() {
		$login_options = get_option( GIGYA__SETTINGS_LOGIN );

		return array(
			'gigya_global_settings'        => array(
				'title' => 'Global Settings',
				'func'  => 'globalSettingsForm',
				'slug'  => 'gigya_global_settings'
			),
			'gigya_login_settings'         => array(
				'title' => 'User Management',
				'func'  => 'loginSettingsForm',
				'slug'  => 'gigya_login_settings'
			),
			'gigya_field_mapping_settings' => array(
				'title'   => 'Field Mapping',
				'func'    => 'fieldMappingForm',
				'slug'    => 'gigya_field_mapping_settings',
				'display' => ( isset( $login_options['mode'] ) and in_array( $login_options['mode'], [
						'raas',
						'wp_sl'
					] ) ) ? 'visible' : 'hidden',
			),
			'gigya_screenset_settings'     => array(
				'title' => 'Screen-Sets',
				'func'  => 'screenSetSettingsForm',
				'slug'  => 'gigya_screenset_settings'
			),
			'gigya_session_management'     => array(
				'title' => 'Session Management',
				'func'  => 'sessionManagementForm',
				'slug'  => 'gigya_session_management'
			),
		);
	}

	/**
	 * Render the Gigya admin pages wrapper (Tabs, Form, etc.).
	 */
	public static function adminPage() {
		$page   = $_GET['page'];
		$render = '';

		echo _gigya_render_tpl( 'admin/tpl/adminPage-wrapper.tpl.php', array(
			'sections' => self::getSections(),
			'page'     => $page,
		) );
		settings_errors();

		echo '<form class="gigya-settings" action="options.php" method="post">' . PHP_EOL;
		echo '<input type="hidden" name="action" value="gigya_settings_submit">' . PHP_EOL;

		wp_nonce_field( 'update-options', 'update_options_nonce' );
		wp_nonce_field( 'wp_rest', 'wp_rest_nonce' );
		settings_fields( $page . '-group' );
		do_settings_sections( $page );
		submit_button();

		echo '</form>';

		return $render;
	}

	/**
	 * On Setting page save event.
	 *
	 * @throws Exception
	 */
	public static function onSave() {
		/* When a Gigya's setting page is submitted */
		if ( isset( $_POST['gigya_global_settings'] ) ) {
			$cms = new gigyaCMS();

			$auth_field = 'api_secret';
			if ($_POST['gigya_global_settings']['auth_mode'] === 'user_rsa') {
				$auth_field = 'rsa_private_key';
				$_POST['gigya_gigya_settings']['rsa_private_key'] = '';
			} else {
				$_POST['gigya_gigya_settings']['api_secret'] = '';
			}

			if ( self::_setObfuscatedField( $auth_field ) ) {
				$res = $cms->apiValidate(
					( empty( $_POST['gigya_global_settings']['auth_mode'] === 'user_rsa' ) ) ? 'user_secret' : $_POST['gigya_global_settings']['auth_mode'],
					$_POST['gigya_global_settings']['api_key'],
					$_POST['gigya_global_settings']['user_key'],
					GigyaApiHelper::decrypt( $_POST['gigya_global_settings'][ $auth_field ], SECURE_AUTH_KEY ),
					_gigya_data_center( $_POST['gigya_global_settings'] )
				);

				if ( ! empty( $res ) ) {
					$gigyaErrCode = $res->getErrorCode();
					if ( $gigyaErrCode > 0 ) {
						$gigyaErrMsg = $res->getErrorMessage();

						self::setError( $gigyaErrCode, $gigyaErrMsg, ( ! empty( $res->getData() ) ) ? $res->getString( "callId", "N/A" ) : null );

						/* Prevent updating values */
						static::_keepOldApiValues();
					}
				} else {
					add_settings_error( 'gigya_global_settings', 'api_validate', __( 'Error sending request to SAP CDC' ), 'error' );
				}
			} else {
				add_settings_error( 'gigya_global_settings', 'api_validate', __( 'Error retrieving existing secret key or private key from the database. This is normal if you have a multisite setup. Please re-enter the key.' ), 'error' );
			}
		} elseif ( isset( $_POST['gigya_login_settings'] ) ) {
			/*
			 * When we turn on the Gigya's social login plugin, we also turn on the WP 'Membership: Anyone can register' option
			 */
			if ( $_POST['gigya_login_settings']['mode'] == 'wp_sl' ) {
				update_option( 'users_can_register', 1 );
			} elseif ( $_POST['gigya_login_settings']['mode'] == 'raas' ) {
				update_option( 'users_can_register', 0 );
			}
		} elseif ( isset( $_POST['gigya_field_mapping_settings'] ) ) {
			$has_error = false;

			/* Validate field mapping settings, including offline sync */
			$data = $_POST['gigya_field_mapping_settings'];
			if ( $data['map_offline_sync_enable'] ) {
				if ( $data['map_offline_sync_frequency'] < GIGYA__OFFLINE_SYNC_MIN_FREQ ) {
					add_settings_error( 'gigya_field_mapping_settings', 'gigya_validate',
						__( 'Error: Offline sync job frequency cannot be lower than ' . GIGYA__OFFLINE_SYNC_MIN_FREQ . ' minutes' ),
						'error' );
					static::_keepOldApiValues( 'gigya_field_mapping_settings' );
					$has_error = true;
				}

				$emails_are_valid = true;
				foreach ( array_merge( explode( ',', $data['map_offline_sync_email_on_success'] ), explode( ',', $data['map_offline_sync_email_on_failure'] ) ) as $email ) {
					if ( $email and ! is_email( $email ) ) {
						$emails_are_valid = false;
					}
				}
				if ( ! $emails_are_valid ) {
					add_settings_error( 'gigya_field_mapping_settings', 'gigya_validate', __( 'Error: Invalid emails entered' ), 'error' );
					static::_keepOldApiValues( 'gigya_field_mapping_settings' );
					$has_error = true;
				}
			}

			/*
			 * Deletes cron and re-enables it. This way it's possible to change the cron's interval, and prevents from scheduling duplicates
			 * (WP doesn't overwrite a cron even if it has the same name. Instead, it creates a new one).
			 */
			$cron_name = 'gigya_offline_sync_cron';
			wp_clear_scheduled_hook( $cron_name );
			if ( $data['map_offline_sync_enable'] ) {
				wp_schedule_event( time(), 'gigya_offline_sync_custom', $cron_name );
			}

			if ( $data['map_raas_full_map'] ) {
				$cms      = new gigyaCMS();
				$method   = 'accounts.getSchema';
				$params   = [ 'include' => 'profileSchema, dataSchema, subscriptionsSchema, preferencesSchema, systemSchema' ];
				$response = '';

				try {
					$response = $cms->call( $method, $params );

				} catch ( GSException $e ) {
					add_settings_error( 'gigya_field_mapping_settings', 'gigya_validate', __( 'Setting saved.<p>Warning: Can\'t reach SAP servers, please check the global configuration settings.<br> Can\'t validate SAP CDC fields.</p>' ), 'warning' );
					static::_keepOldApiValues( 'gigya_field_mapping_settings' );
					$has_error = true;
				}
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'gigya_field_mapping_settings', 'gigya_validate', __( 'Setting saved.<br>Warning: Can\'t reach SAP servers, please check the global configuration settings: ' . $response->get_error_message() . '.<br> Can\'t validate SAP CDC fields.' ), 'warning' );
					static::_keepOldApiValues( 'gigya_field_mapping_settings' );

				} else if ( $response['errorCode'] === 0 ) {
					$error_message = static::getDuplicateAndMissingFields( $data, $response );

					//Sending the error message if necessary.
					if ( ! empty( $error_message ) and ( ! $has_error ) ) {
						add_settings_error( 'gigya_field_mapping_settings', 'gigya_validate', $error_message, 'warning' );
					}
				}
			}

		} elseif ( isset( $_POST['gigya_screenset_settings'] ) ) {
			/* Screen-set page validation */
			foreach ( $_POST['gigya_screenset_settings']['custom_screen_sets'] as $key => $screen_set ) {
				if ( ! empty( $screen_set['desktop'] ) ) {
					if ( in_array( $screen_set['desktop'], array_column( $_POST['gigya_screenset_settings']['custom_screen_sets'], 'id' ) ) ) {
						$_POST['gigya_screenset_settings']['custom_screen_sets'][ $key ]['id'] = self::generateMachineName( $screen_set['desktop'], $key );
					} else {
						$_POST['gigya_screenset_settings']['custom_screen_sets'][ $key ]['id'] = $screen_set['desktop'];
					}
					$_POST['gigya_screenset_settings']['custom_screen_sets'][ $key ]['value'] = $screen_set['desktop'];

					if ( empty( $screen_set['mobile'] ) && ! empty( $screen_set['desktop'] ) ) {
						$_POST['gigya_screenset_settings']['custom_screen_sets'][ $key ]['mobile'] = 'desktop';
					}
				} else {
					unset( $_POST['gigya_screenset_settings']['custom_screen_sets'][ $key ] );
				}
			}
		}
	}


	/**
	 * The function reads the field mapping map and searches for duplication in cmsName fields,
	 * and missing fields in the gigyaCms property.
	 *
	 * @param $data array The data of field mapping setting page.
	 * @param $response array The response from 'accounts.getSchema' query.
	 *
	 * @return string The error message should be printing. Empty string will return in case of no errors.
	 */
	private static function getDuplicateAndMissingFields( $data, $response ) {

		$array_of_wp_fields        = array();
		$duplications_of_wp_fields = array();
		$unnecessary_fields        = array();
		$not_existing_fields       = array();
		$is_valid                  = true;
		$error_message             = __( 'Setting saved.<p>Warning:</p>' );
		$json_block                = json_decode( stripslashes( $data['map_raas_full_map'] ), true );
		$warnings_counter          = 0;
		//Searching each block.
		foreach ( $json_block as $meta_key ) {

			$gigya_name = $meta_key['gigyaName'];
			$cms_name   = $meta_key['cmsName'];


			//Searching for duplications of WP fields.
			if ( array_key_exists( $cms_name, $array_of_wp_fields ) === false ) {
				$array_of_wp_fields[ $cms_name ] = $gigya_name;
			} else if ( $array_of_wp_fields[ $cms_name ] !== $gigya_name ) {
				$duplications_of_wp_fields[] = $cms_name;
			}

			//Searching for not existing fields in gigya
			if ( ! static::doesFieldExist( $response, $gigya_name ) ) {
				$not_existing_fields[] = $gigya_name;

			}

			//Getting unnecessary fields.
			if ( count( $meta_key ) > 2 ) {
				$fields = array_keys( $meta_key );
				foreach ( $fields as $field ) {
					if ( ( $field !== 'gigyaName' ) and ( $field !== "cmsName" ) ) {
						$unnecessary_fields [] = $field;
					}
				}
			}
		}


		$duplications_of_wp_fields_str = implode( ', ', $duplications_of_wp_fields );
		$not_existing_fields_str       = implode( ', ', $not_existing_fields );
		$unnecessary_fields_str        = implode( ', ', $unnecessary_fields );

		//Checking if there is two different warnings cases
		if ( ( ! empty( $duplications_of_wp_fields_str ) and ! empty( $not_existing_fields_str ) )
			 or ( ! empty( $duplications_of_wp_fields_str ) and ! empty( $unnecessary_fields_str ) )
			 or ( ! empty( $not_existing_fields_str ) and ! empty( $unnecessary_fields_str ) ) ) {
			$error_message    = __( 'Setting saved.<p>Warnings: </p>' );
			$warnings_counter = 1;
		}


		//Builds the error message.
		if ( ! empty( $not_existing_fields_str ) ) {
			$case         = __( 'The following fields were not found in Customer Data Cloud\'s account schema:' );
			$solution           = __( 'Please make sure that the names are spelled correctly, or add the fields in the schema editor.' );
			$error_message .= static:: fieldsMappingWarningsBuilder( $case, $not_existing_fields_str, $solution, $warnings_counter );
			$is_valid      = false;
		}

		if ( ! empty( $duplications_of_wp_fields_str ) ) {

			$case         = __( 'The following duplicates have been found in the cmsName field:' );
			$solution           = __( 'Field mapping for these fields may not work as expected.' );
			$error_message .= static:: fieldsMappingWarningsBuilder( $case, $duplications_of_wp_fields_str, $solution, $warnings_counter );
			$is_valid      = false;
		}

		if ( ! empty( $unnecessary_fields_str ) ) {

			$case         = __( 'The following unnecessary fields have been found in the JSON:' );
			$solution           = __( 'These fields will be ignored.' );
			$error_message .= static:: fieldsMappingWarningsBuilder( $case, $unnecessary_fields_str, $solution, $warnings_counter );
			$is_valid      = false;
		}

		if ( $is_valid ) {
			return '';
		} else {
			return $error_message;
		}
	}

	/**
	 * @param $response array The response from 'accounts.getSchema' query.
	 * @param $key string Field to search for in Gigya schema, with dot notation.
	 *                        Example: subscriptions.mySubscription.isSubscribed
	 *
	 * @return bool True if the field has been found false otherwise.
	 */
	private static function doesFieldExist( $response, $key ) {

		if ( empty( $key ) ) {
			return false;
		}

		$field_name_in_array = explode( '.', $key );
		$schema_type         = $field_name_in_array[0];
		$field_name          = substr( strpbrk( $key, '.' ), 1 );


		switch ( $schema_type ) {
			case 'profile':
				$array_of_keys = $response['profileSchema'] ['fields'];
				break;
			case'data':
				$array_of_keys = $response['dataSchema']['fields'];
				break;
			case 'subscriptions':
				$array_of_keys = $response['subscriptionsSchema']['fields'];
				break;
			case 'preferences':
				$array_of_keys = $response['preferencesSchema']['fields'];
				break;

			//Should be the systemSchema (doesn't include specific name).
			default:
				$field_name    = $key;
				$array_of_keys = $response['systemSchema']['fields'];
				break;

		}

		return ( ( ! empty ( $field_name ) ) and array_key_exists( $field_name, $array_of_keys ) );
	}

	public static function generateMachineName( $desktop_screen_set_id, $serial ) {
		$machine_name = $desktop_screen_set_id;
		if ( $serial !== 0 ) {
			$machine_name .= '_' . $serial;
		}

		return $machine_name;
	}

	public static function fieldsMappingWarningsBuilder( $case_Str, $fields, $solution, &$warnings_counter ) {

		$error = '<p class="gigya-field-mapping-error-p">';
		if ( $warnings_counter !== 0 ) {
			$error .= $warnings_counter ++ . '. ';
		}
		$error .= $case_Str
				  . '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
				  . '<i>' . $fields . '</i>'
				  . '<br>'
				  . $solution
				  . '</p>';

		return $error;
	}

	/**
	 * Set the POSTed secret key.
	 * If it's not submitted, take it from DB.
	 *
	 * @param string $field	The obfuscated field
	 *
	 * @return bool
	 */
	private static function _setObfuscatedField( $field ) {
		if ( empty( $_POST['gigya_global_settings'][$field] ) ) {
			$options = static::_getSiteOptions();
			if ( $options === false ) {
				return false;
			}

			$_POST['gigya_global_settings'][$field] = $options[$field];
		} else {
			$_POST['gigya_global_settings'][$field] = GigyaApiHelper::encrypt( $_POST['gigya_global_settings'][$field], SECURE_AUTH_KEY );
		}

		return true;
	}

	private static function setError( $errorCode, $errorMessage, $callId = null ) {
		$errorLink  = "<a href='https://developers.gigya.com/display/GD/Response+Codes+and+Errors+REST' target='_blank' rel='noopener noreferrer'>Response_Codes_and_Errors</a>";
		$message     = "SAP CDC API error: {$errorCode} - {$errorMessage}.";
		add_settings_error( 'gigya_global_settings', 'api_validate', __( $message . " For more information please refer to {$errorLink}", 'error' ) );
		error_log( 'Error updating SAP CDC settings: ' . $message . ' Call ID: ' . $callId );
	}

	/**
	 * Set the posted api related values to the old (from DB) values
	 *
	 * @param string $option The option under which to keep the settings
	 * @param null|string|array $settings Tells the function which specific old values to get, if we don't want all of them.
	 */
	public static function _keepOldApiValues( $option = '', $settings = [] ) {
		if ( ! $option ) {
			$options                                           = self::_getSiteOptions();
			$_POST['gigya_global_settings']['api_key']         = $options['api_key'];
			$_POST['gigya_global_settings']['user_key']        = $options['user_key'];
			$_POST['gigya_global_settings']['auth_mode']       = $options['auth_mode'];
			$_POST['gigya_global_settings']['api_secret']      = $options['api_secret'];
			$_POST['gigya_global_settings']['rsa_private_key'] = $options['rsa_private_key'];
			$_POST['gigya_global_settings']['data_center']     = $options['data_center'];
			$_POST['gigya_global_settings']['other_ds']        = ( ! empty( $_POST['gigya_global_settings']['other_ds'] ) ) ? $options['other_ds'] : '';
			if ( isset( $options['sub_site_settings_saved'] ) ) {
				$_POST['gigya_global_settings']['sub_site_settings_saved'] = $options['sub_site_settings_saved'];
			}
		} elseif ( ! empty( $settings ) ) { /* $settings is an array--retrieve specific options */
			if ( $option ) {
				$options = self::_getSiteOptions( $option );
				foreach ( $settings as $setting ) {
					$_POST[ $option ][ $setting ] = $options[ $setting ];
				}
			}
		} else {
			$_POST[ $option ] = self::_getSiteOptions( $option );
		}
	}

	/**
	 * If multisite, get options from main site, else from current site
	 *
	 * @param string $option
	 *
	 * @return mixed
	 */
	public static function _getSiteOptions( $option = GIGYA__SETTINGS_GLOBAL ) {
		if ( is_multisite() ) {
			$options = get_blog_option( get_current_blog_id(), $option );
		} else {
			$options = get_option( $option );
		}

		return $options;
	}

}
