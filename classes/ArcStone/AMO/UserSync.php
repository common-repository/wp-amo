<?php namespace ArcStone\AMO;

use ArcStone\AMO\Requests\IndividualsRequest;

class UserSync
{
  /**
   * @var array
   */
  private $debug = array();

  /**
   * @param int $page_per
   * @return array
   * @throws \Exception
   */
  public function sync_users($page_per = 5000) {
    $sync_start = microtime(true);
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('max_execution_time', 300); //300 seconds = 5 minutes

    $users = array();
    $page_number = 1;
    $returned_count = $page_per;

    // So long as we're receiving a "full" page, we want to keep sending requests.
    // This can be changed if the API is updated to return a "total" count
    // in metadata along with AMOIndividuals queries
    $requests_start = microtime(true);
    while( !($returned_count < $page_per)) {
      $params = array('page_per' => $page_per, 'page_number' => $page_number);
      $requester = new IndividualsRequest($params);
      $this->debug[] = 'Requesting data from: ' . $requester->getRequestUrl();
      $response = $requester->getRequest();

      if (false === $response) {
        throw new \Exception($requester->getErrorMessage());
        break;
      }

      if (!is_array($response)) {
        throw new \Exception('Received unexpected API response.');
        break;
      }
      $users = array_merge($users, $response);
      $returned_count = count($response);
      $this->debug[] = 'Received ' . count($response) . ' records';
      $page_number++;
    }
    $requests_execution_time = (microtime(true) - $requests_start);
    $this->debug[] = 'API Requests took ' . $requests_execution_time;

    $user_processing_start = microtime(true);
    $result = self::_process_users($users);
    $user_processing_execution_time = (microtime(true) - $user_processing_start);
    $this->debug[] = 'User processing (updates, inserts, deletes) took ' . $user_processing_execution_time . ' seconds';

    $sync_execution_time = (microtime(true) - $sync_start);
    $this->debug[] = 'Sync total execution took: ' . $sync_execution_time . ' seconds.';

    return $result;
  }

  public function show_debug() {
    echo '<h4>Debug Log</h4>';
    echo '<ul style="list-style: disc; margin-left: 25px;">';
    foreach($this->debug as $log_entry) {
      echo '<li style="list-style: disc">' . $log_entry . '</li>';
    }
    echo '</ul>';
    echo '<hr/>';
  }

  /**
   * @param array $users
   * @return array
   */
  private function _process_users(array $users = array()) {
    global $wpdb;
    $results = array( 'added' => 0, 'updated' => 0, 'error' => array());
    $results['deleted'] = self::_purge_deleted_users();

    $amo_pks = array_column($users, 'pk_association_individual');
    $db_users = self::_retrieve_wp_users($amo_pks);

    foreach ( $users as $user ) {
      $role = sanitize_title( $user['member_type_name'] );

      // if the role is empty, assign this user as a subscriber
      if ( empty( $role ) ) {
        $role = 'subscriber';
      }

      $userdata = array(
        'user_login'	=>	$user['username'],
        'first_name'	=>	$user['first_name'],
        'last_name'		=>	$user['last_name'],
        'user_email'	=>	$user['email'],
        'role'			=>	$role
      );

      /**
       * Find matching user
       */
      $user_result = $db_users->find($user['pk_association_individual']);

      /**
       * Update user
       */
      if ( !empty($user_result) ) {
        // if fields have changed.
        if (
          $user_result->user_login 	!= $user['username'] ||
          $user_result->user_firstname != $user['first_name'] ||
          $user_result->user_lastname 	!= $user['last_name'] ||
          $user_result->user_email 	!= $user['email'] ||
          ( (!empty($user_result->roles) && !in_array($role, $user_result->roles)) ||
            empty($user_result->roles) && !empty($userdata['role'])
          )
        )
        {
          $userdata = array_merge( $userdata, array( 'ID' => $user_result->ID ) );

          // do not send the email changed email.
          add_filter( 'send_email_change_email', '__return_false' );

          $user_id = wp_update_user( $userdata );

          // user id is found
          if ( !is_wp_error( $user_id ) ) {
            $wpdb->update($wpdb->users, array('user_login' => $user['username']), array('ID' => $user_result->ID));
            update_user_meta( $user_id, 'amo_pk_association_individual', $user['pk_association_individual'] );
            $results['updated']++;
          }
        }

        /**
         * Insert User
         */
      } else {
        // generate a big huge random password
        $user_pass = wp_generate_password( 20, true, true );

        $userdata = array_merge( $userdata, array(
          'user_pass'		=>	$user_pass,
        ));

        $user_id = wp_insert_user( $userdata );
        if ( !is_wp_error( $user_id ) ) {
          add_user_meta( $user_id, 'amo_pk_association_individual', $user['pk_association_individual'] );
          $results['added']++;
//          $u = get_user_by('ID', $user_id);
//          $this->debug[] = 'Added user ' . $u->user_email . ' with role ' . print_r($u->roles, true) . '. Role in AMO was ' . ($user['member_type_name'] ?: ' Empty');
        }

      }

      /**
       * Error updating or adding user
       */
      if ( isset($user_id) && is_wp_error( $user_id ) ) {

        $results['error'][] = array(
          'userdata'	=>	$userdata,
          'error'		=>	$user_id->get_error_message()
        );
        unset($user_id);
      }
    }
    ini_set('max_execution_time', 30); //300 seconds = 5 minutes
    return $results;
  }


  /**
   * Delete all the users in wordpress that have been deleted from AMO
   * @return integer - number of purged aka deleted users
   */
  private function _purge_deleted_users() {
    $last_deleted_user_purge = get_option('amo_last_deleted_user_purge');
    if (!$last_deleted_user_purge)  {
      // If the "last_deleted_user_purge" option hasn't been set yet, then
      // we set it to the moment the oldest user was registered, ensuring
      // that the purge check can not possibly miss any users in the DB
      global $wpdb;
      $user_table = $wpdb->prefix . 'users';
      $query = "SELECT * FROM $user_table ORDER BY user_registered ASC LIMIT 1";
      $oldest_user = $wpdb->get_row($query);
      $last_deleted_user_purge = $oldest_user->user_registered;
    }

    update_option('amo_last_deleted_user_purge', current_time('mysql'));

    // Query the AMO API users deleted since the last purge
    $api = new APIREST( AMO_API_KEY );
    $formatted_last_purge_date = date_create($last_deleted_user_purge)->format('m/d/Y');
    $deleted_users = $api->processRequest('AMODeletedIndividuals', array(
      'deleted_since' => $formatted_last_purge_date
    ));

    if (empty($deleted_users) || !is_array($deleted_users) || empty($deleted_users['DATA']) || !is_array($deleted_users['DATA'])) {
      return 0;
    }

    $result = 0;

    foreach ($deleted_users['DATA'] as $user) {
      $wp_user = self::_find_wp_user($user['PK_ASSOCIATION_INDIVIDUAL']);
      if (!$wp_user) {
        continue;
      }
      wp_delete_user($wp_user[0]->ID);
      $result++; //[] = (array) $wp_user[0];
    }

    return $result;
  }

  /**
   * @param $amo_pk
   * @return array
   */
  private function _find_wp_user($amo_pk) {
    $query = new \WP_User_Query( array(
      'meta_key'	=>	'amo_pk_association_individual',
      'meta_value'	=>	$amo_pk
    ));

    return $query->get_results();
  }

  /**
   * Returns a collection where you can search by `amo_pk_association_individual`
   * @param array $amo_pks
   * @return KeyedSearchCollection
   */
  private function _retrieve_wp_users(array $amo_pks = array())
  {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $list_of_percent_ds = implode(', ', array_fill(0, count($amo_pks), '%d'));
    $sql = "SELECT * from {$prefix}users AS u
      INNER JOIN {$prefix}usermeta AS m
      ON u.ID = m.user_id
      WHERE meta_key = 'amo_pk_association_individual'
        AND meta_value IN ({$list_of_percent_ds})";

    $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $amo_pks));
    $results = $wpdb->get_results($query, ARRAY_A);
    return new KeyedSearchCollection('meta_value', $results);
  }
}

