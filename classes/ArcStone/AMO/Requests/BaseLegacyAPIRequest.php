<?php

namespace ArcStone\AMO\Requests;

class BaseLegacyAPIRequest extends BaseRequest
{
  static $api_url = 'https://admin.associationsonline.com/secure/api/index.cfm/'; // hardcoded b/c it won't change (often).

  protected function _apiUrl()
  {
    return self::$api_url;
  }
}