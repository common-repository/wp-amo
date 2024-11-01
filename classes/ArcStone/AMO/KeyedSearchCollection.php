<?php


namespace ArcStone\AMO;
use WP_User;

class KeyedSearchCollection
{
  /**
   * @var array
   */

  private $items = array();
  /**
   * @var string
   */
  private $key;

  /**
   * @var array
   */
  private $key_column;

  /**
   * KeyedSearchCollection constructor.
   * @param string $key
   * @param array $items
   */
  public function __construct($key, array $items)
  {
    $this->key = $key;
    $this->items = $items;
    $this->key_column = array_flip(array_column($this->items, $this->key));
    /* $this->key_column looks like this now:
      [
        'an_amo_pk_12345' => 0,
        'an_amo_pk_97542' => 1,
        ...
        'an_amo_pk_68765' => 100000000,
      ]
    */
  }

  /**
   * @param $value
   * @return mixed|null
   */
  public function find($value) {
    if (isset($this->key_column[$value])) {
      $item_index = $this->key_column[$value];
      // In theory, this null check should not be necessary.
      // Really just being cautious so as not to get bit
      if (isset($this->items[$item_index])) {
        $item = (object) $this->items[$item_index];
        return new WP_User($item);
      }
    }
    return null;
  }
}