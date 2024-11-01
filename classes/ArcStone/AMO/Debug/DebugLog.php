<?php namespace ArcStone\AMO\Debug;


class DebugLog
{
  /**
   * @var array
   */
  protected $items = [];

  /**
   * @var string
   */
  protected $title;

  /**
   * @var bool
   */
  protected $default = false;

  public function __construct($title, $default = false) {
    $this->title = $title;
    $this->default = $default;
    DebugRenderer::add_log($this);
  }

  /**
   * @param string $item
   */
  public function add_item($item) {
    if (!is_string($item)) {
      $item = print_r($item, true);
    }
    $this->items[] = $item;
  }

  public function get_items() {
    return $this->items;
  }

  public function has_items() {
    return count($this->items) > 0;
  }

  /**
   * @return boolean
   */
  public function is_default() {
    return $this->default;
  }

  public function get_title() {
    return $this->title;
  }
}