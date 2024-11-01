<?php

namespace ArcStone\AMO\Users;

use ArcStone\AMO\API;
use ArcStone\AMO\APIREST;
use ArcStone\AMO\Debug\DebugLog;
use ArcStone\AMO\Requests\IndividualsRequest;
use ArcStone\AMO\WP_AMO;

class Users {
  /**
   * @var self
   */
	private static $instance = null;
	private $installed_path = '';
	private function __construct() {}

	public static function get_instance() {

		if( is_null( self::$instance ) ) {
			self::$instance = new Users;
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		add_action( 'parse_request', array( $this, 'process_nojs' ));

		add_shortcode( 'amo_user_login_form', array( $this, 'login_form_shortcode' ) );
		add_shortcode( 'amo_sso_link', array( $this, 'sso_link_shortcode' ) );
		add_action( 'wp_ajax_amo_login', array( $this, 'login_process' ) );
		add_action( 'wp_ajax_nopriv_amo_login', array( $this, 'login_process' ) );

		add_action( 'wp_ajax_amo_sso', array( $this, 'sso_process' ) );
		add_action( 'wp_ajax_nopriv_amo_sso', array( $this, 'sso_process' ) );
		add_action( 'wp_logout', array( $this, 'sso_logout_redirect'));

    UserSyncCron::init();

		/**
		 * Limit what users can do
		 */
		add_action( 'after_setup_theme', array( $this, 'remove_admin_toolbar' ) ); // Remove admin toolbar
		add_action( 'init', array( $this, 'block_wp_admin' ) );
	}

	public static function activation_hook() {
		UserSyncCron::schedule_cron();
	}

	public static function uninstall_hook() {
		delete_option( 'wpamo_website_url' );
		UserSyncCron::unschedule_cron();
	}


	public function process_nojs() {

		if ( $_SERVER['REQUEST_URI'] == '/wpamo-login' ) {
			$this->login_process();
		}
		else if ( (isset( $_GET['amo_signin_token'] ) && isset( $_GET['wp_uri'] )) || (isset( $_GET['error_code'])) ) {
			$this->sso_process();
		}
		return;

	}

	public function api_login_request( $username, $password ) {

		$api = new API( \Arcstone\AMO\AMO_API_KEY );

		$params = array(
						'username'	=>	$username,
						'password'	=> hash('sha256', $password)
						);

		$results = $api->processRequest( 'AMOLogin', $params, false );

		if ( count( $results ) == 1 ) {
			return $results[0]['pk_association_individual'];
		} else {
			return false;
		}

	}

	public function api_sso_request( $amo_signin_token, $wp_uri ) {

		$api = new APIREST( \ArcStone\AMO\AMO_API_KEY );

		$params = array(
						'amo_signin_token'	=>	$amo_signin_token,
						'wp_uri'	=> $wp_uri
						);

		$results = $api->processRequest( 'AMOSigninToken', $params, false );

		if ($results) {
			return array($results['DATA'][0]['PK_ASSOCIATION_INDIVIDUAL'],$results['DATA'][0]['WP_RETURN']);
		} else {
			return false;
		}

	}

	public function login_process() {

		$error = false;
		$response = '';


   		if ( isset( $_POST['destination'] ) && isset( $_POST['username'] ) && isset( $_POST['password'] ) ) {
   			$success_url = esc_url( $_POST['destination'] );

   			$amo_user_id = $this->api_login_request( $_POST['username'], $_POST['password']  );
	   		if ( !$amo_user_id ) {
	   			$error = true;
	   			$response = 'Invalid username/password';
	   		} else {
		   		if ( $this->do_login( $amo_user_id ) ) {

		   			$response = array( 'destination' => $success_url );

	   			} else {
	   				// user not found error
	   				$error = true;
	   				$response = 'AMO username not found in the database.';
	   			}
	   		}


   		} else {
   			// invalid login error
   			$error = true;
   			$response = 'Invalid request';
   		}

   		if ( defined( 'DOING_AJAX' ) ) {
   			header('content-type: application/json');
   			echo json_encode( array( 'error' => $error, 'response' => $response ) );
   			wp_die();
   		} else {
   			if ( !$error ) {
   				wp_redirect( $success_url );
   				exit;
   			} else {
   				// this is kind of dirty, but will only fire for login attempts with javascript disabled.
   				// (AKA. probably only bots?)
   				die( $response );
   			}
   		}

	}


	public function sso_process() {

		$error = false;
		$response = '';


   		if ( isset( $_GET['amo_signin_token'] ) && isset( $_GET['wp_uri'] ) ) {

   			$amo_user_id = $this->api_sso_request( $_GET['amo_signin_token'], $_GET['wp_uri']  );
	   		if ( !$amo_user_id ) {
	   			$error = true;
	   			$response = 'Invalid username/password SSO';
	   		} else {
		   		if ( $this->do_login( $amo_user_id[0] ) ) {

		   			$response = array( 'destination' => $amo_user_id[1] );

	   			} else {
	   				// user not found error
	   				$error = true;
	   				$response = 'AMO username not found in the database.';	   				
	   			}
	   		}


   		} else {
   			// invalid login error
   			$error = true;
   			$response = 'Invalid request - please review your AMO WordPress settings.';
   		}

   		if ( defined( 'DOING_AJAX' ) ) {
   			header('content-type: application/json');
   			echo json_encode( array( 'error' => $error, 'response' => $response ) );
   			wp_die();
   		} else {
   			if ( !$error ) {
   				wp_redirect( $amo_user_id[1] );
   				exit;
   			} else {
   				// this is kind of dirty, but will only fire for login attempts with javascript disabled.
   				// (AKA. probably only bots?)
   				die( $response );
   			}
   		}

	}		
		
	function sso_logout_redirect(){
		
		$api = new API( \Arcstone\AMO\AMO_API_KEY );
		$results = $api->processRequest('AMOAssociation');
		$amo_website_url = $results[0]['website_url'];
	
		wp_redirect( 'https://' .$amo_website_url. '/site_logout_wp.cfm' );
		exit();	
	  }

	public function login_form_shortcode( $atts ) {

		wp_register_script( 'amo-login-form', WP_AMO::$installed_url . '/js/login-form.js', array( 'jquery' ), '3.0', true );
		wp_enqueue_script( 'amo-login-form' );
		wp_localize_script( 'amo-login-form', 'ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

		ob_start();
		include WP_AMO::$installed_path . 'templates/amo_shortcode_login_form.php';
		return ob_get_clean();
	}

	public function sso_link_shortcode( $atts ) {

		ob_start();
		include WP_AMO::$installed_path . 'templates/amo_shortcode_sso_link.php';
		return ob_get_clean();
	}

	private static function find_wp_user( $amo_id ) {
		global $wpdb;
		$usermeta_query = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'amo_pk_association_individual' AND meta_value = %d ORDER BY user_id desc LIMIT 1";
		$usermeta_query_result = $wpdb->get_var( $wpdb->prepare( $usermeta_query, $amo_id ));

		if ( $usermeta_query_result ) {
			return get_user_by( 'id', $usermeta_query_result );
		} else {
			return false;
		}
	}

	private function do_login( $amo_id ) {

		$found_user = self::find_wp_user( $amo_id );

		if ( $found_user ) {
			$user_id = $found_user->ID;
			$user_login = $found_user->user_login;
			wp_set_current_user( $user_id, $user_login );
			wp_set_auth_cookie( $user_id );
			do_action( 'wp_login', $user_login, $found_user );
			return $found_user;
		} else {
			return false;
		}
	}


	public function remove_admin_toolbar() {
		if (!current_user_can('administrator') && !is_admin()) {
			show_admin_bar(false);
		}
	}

	public function block_wp_admin() {
		// limit wp-admin to users who can edit post (ie. Author or greater)
		if ( is_user_logged_in() && is_admin() && !current_user_can( 'edit_published_posts' ) && !( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			wp_redirect( home_url() );
			exit;
		}
	}

  /**
   * @return array
   */
	public static function sync_users() {
		$user_sync = new UserSync;
		try {
      $result = $user_sync->sync_users();
      return $result;
    } catch (\Exception $e) {
		  return array('error' => ['userdata' => [], 'error' => array($e->getMessage())]);
    }
	}

	public static function sync_roles() {
		$results = array( 'success' => 0, 'error' => array());
		$api = new API( \ArcStone\AMO\AMO_API_KEY );

		$roles = $api->processRequest( 'AMOMemberTypes', null, false );
		if ( $roles ) {
			foreach ( $roles as $role ) {
			  $role_slug = self::member_type_to_role($role['member_type_name']);

        // technically, $role_slug could be empty in some error cases
        if (!empty($role_slug)) {
          $role_results = add_role(
            $role_slug,
            $role['member_type_name'],
            array('read' => true)
          );
					if ($role_results) {
            $results['success']++;
          }
				}
      }
		}

		return $results;
	}

	public static function sync_users_and_roles() {
    $sync_log = new DebugLog('Sync Results', true);
    $role_results = self::sync_roles();
		$user_result = self::sync_users();

		$sync_log->add_item('<strong>Roles synced: </strong>' . $role_results['success']);
		$sync_log->add_item('<strong>Users added: </strong>' . $user_result['added']);
    $sync_log->add_item('<strong>Users updated: </strong>' . $user_result['updated']);
    $sync_log->add_item('<strong>Users deleted: </strong>' . $user_result['deleted']);

    UserSyncCron::update_cron_interval();
	}

	private static function member_type_to_role ( $member_type_name ) {
		return sanitize_title( $member_type_name );
	}
}
