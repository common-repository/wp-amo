<?php

namespace ArcStone\AMO\Debug;

class DebugRenderer
{
  /**
   * @var DebugRenderer
   */
  protected static $instance;

  /**
   * @var array [DebugLog ...]
   */
  public $logs;

  /**
   * @return DebugRenderer
   */
  public static function get_instance() {
    if (!self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public static function add_log(DebugLog $log) {
    self::get_instance()->logs[] = $log;
  }

  public static function render() {
    ?>
    <div>
<!--      <h3>Debug Info</h3>-->
      <div id="wpamo-debug-default">
        <?php foreach(self::get_instance()->logs as $log) {
          if ($log->has_items() && $log->is_default()) {
            self::_render_log($log);
          }
        } ?>
      </div>
      <div>
        <span id="wpamo-debug-toggle-show" style="cursor:pointer; text-decoration:underline">More debug info ...</span>
      </div>

    <div id="wpamo-debug-info" class="hidden">
        <?php foreach(self::get_instance()->logs as $log) {
          if ($log->has_items() && !$log->is_default()) {
            self::_render_log($log);
          }
        } ?>
      </div>
      <span id="wpamo-debug-toggle-hide" class="hidden" style="cursor:pointer; text-decoration:underline">Less debug info...</span>
      <hr/>
    </div>
    <script>
      jQuery(document).ready(function() {
        var $ = jQuery
        var hideSpan = $('#wpamo-debug-toggle-hide')
        var showSpan = $('#wpamo-debug-toggle-show')
        var infoDiv =  $('#wpamo-debug-info')

        showSpan.click(function() {
          showSpan.hide();
          hideSpan.show();
          infoDiv.show()
        })

        hideSpan.click(function() {
          hideSpan.hide();
          showSpan.show();
          infoDiv.hide()
        })
      })
    </script>
    <?php
  }

  protected static function _render_log(DebugLog $log) {
    $list = '';
    foreach ($log->get_items() as $entry) {
      $list .= '<li style="list-style: disc">' . $entry . '</li>';
    }
    ?>
      <h4><?= $log->get_title() ?></h4>
      <ul style="list-style: disc; margin-left: 25px;">
        <?= $list ?>
      </ul>
    <?php
  }
}