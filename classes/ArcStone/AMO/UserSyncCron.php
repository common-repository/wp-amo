<?php

namespace ArcStone\AMO;

class UserSyncCron
{
  public static function init() {
    add_action( 'wpamo_sync_cron', array( '\ArcStone\AMO\UserSyncCron', 'sync_cron_hook' ));
    self::schedule_cron();
  }

  public static function sync_cron_hook() {
    Users::sync_users_and_roles();
  }

  public static function schedule_cron() {
    if ( !wp_next_scheduled( 'wpamo_sync_cron' ) ){
      wp_schedule_event( time(), 'hourly', 'wpamo_sync_cron' );
    }
  }

  public static function unschedule_cron() {
    wp_clear_scheduled_hook( 'wpamo_sync_cron' );
  }
}