<?php

namespace ArcStone\AMO\Requests;

class BaseAPIRequest extends BaseRequest
{
  static $api_url = 'https://admin.associationsonline.com/rest/AMO/'; // hardcoded b/c it won't change (often).

  protected function _apiUrl()
  {
    return self::$api_url;
  }
}