<?php

namespace ArcStone\AMO\Users;

use ArcStone\AMO\Requests\AssociationMetaDataRequest;

class UserSyncCron
{
  const CRON_HOOK = 'wpamo_sync_cron';
  const CRON_RECURRENCE_OPTION_KEY = 'wpamo_sync_cron_recurrence_interval';
  const PULL_INTERVALS = array(5, 10, 15, 30, 60);

  public static function init() {
    add_action( self::CRON_HOOK, array( '\ArcStone\AMO\Users\UserSyncCron', 'execute_cron' ));
    self::schedule_cron();
  }

  public static function add_custom_cron_intervals($schedules) {
    foreach (self::PULL_INTERVALS as $m) {
      $schedules["wp_amo_$m"] = array(
        'interval' => $m * 60,
        'display' => 'Every ' . $m . ' minutes'
      );
    }

    return $schedules;
  }

  public static function execute_cron() {
    Users::sync_users_and_roles();
    self::update_cron_interval();
  }

  public static function schedule_cron() {
//    self::unschedule_cron();
    add_filter( 'cron_schedules', array( '\ArcStone\AMO\Users\UserSyncCron', 'add_custom_cron_intervals'));
    if ( !wp_next_scheduled( self::CRON_HOOK ) ){
      $recurrence = get_option(self::CRON_RECURRENCE_OPTION_KEY, 'wp_amo_15');
      wp_schedule_event( time(), $recurrence, self::CRON_HOOK );
    }
  }

  public static function unschedule_cron() {
    wp_clear_scheduled_hook( self::CRON_HOOK );
  }

  /**
   * TODO: Notify users of cron update failures.
   * Seems like this would require a whole system to manage it though
   * i.e., storing error as a wp option and checking for it on every
   * admin page load
   */
  public static function update_cron_interval() {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
//    try {
      $request = new AssociationMetaDataRequest;
      $result = $request->getRequest();
//    } catch (\Exception $e) {
//      return;
//    }
//    var_dump($result); die();
    if (!is_array($result)
        || !isset($result[0])
        || !is_array($result[0])
        || !isset($result[0]['wp_pull_interval'])
        || !is_int($result[0]['wp_pull_interval'])
        || !in_array($result[0]['wp_pull_interval'], self::PULL_INTERVALS)) {

      return;
    }

    $interval = 'wp_amo_' . $result[0]['wp_pull_interval'];
    $current = get_option(self::CRON_RECURRENCE_OPTION_KEY);
    if ($interval === $current) {
      return;
    }

    update_option(self::CRON_RECURRENCE_OPTION_KEY, $interval);
    self::unschedule_cron();
    self::schedule_cron();
  }

}