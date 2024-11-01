<?php

namespace ArcStone\AMO\Requests;

abstract class BaseRequest
{
  private $error = false;
  private $method = '';
  private $params = [];
  private $requestUrl = '';

  public function __construct($method, array $params = [])
  {
    $this->method = $method;
    $this->params = $params;
  }

  /**
   * @return string
   */
  abstract protected function _apiUrl();

  public function getErrorMessage()
  {
    return $this->error;
  }

  /**
   * @param string $method
   * @param array $params
   * @return array|false
   * @throws \Exception
   */
  public static function get($method, array $params = []) {
    $instance = new static($method, $params);
    return $instance->getRequest();
  }

  /**
   * @return string
   * @throws \Exception
   */
  private function _buildRequest() {
    if (!empty($this->requestUrl)) {
      return $this->requestUrl;
    }
    $api_params = array ( 'apikey' => \ArcStone\AMO\AMO_API_KEY );

    if ( empty( $this->method ) ) {
      throw new \Exception( 'Invalid API method.' );
    }

    if ( !empty( $this->params ) && !is_array( $this->params ) ) {
      throw new \Exception( 'Invalid API parameters. Array expected.', 1);
    }

    if ( !empty( $this->params ) ) {
      $api_params = array_merge( $this->params, $api_params );
    }

    $query_string = http_build_query( $api_params );
    $this->requestUrl = $this->_apiUrl() . $this->method . '?' . $query_string;
    return $this->requestUrl;
  }

  /**
   * @return string
   * @throws \Exception
   */
  public function getRequestUrl() {
    return $this->_buildRequest();
  }

  /**
   * Curl the API request.
   *
   * Request data from API.
   * Returns json data on HTTP status = 200. Otherwise sets an error and returns false
   *
   * @throws \Exception
   * @return array | false
   */
  public function getRequest() {
    $curl_opts = array(
      CURLOPT_URL 			=>	$this->_buildRequest(),
      CURLOPT_SSL_VERIFYPEER	=>	true,
      CURLOPT_RETURNTRANSFER	=>	true
    );

    $ch = curl_init();
    curl_setopt_array( $ch, $curl_opts );
    $returned_data = curl_exec( $ch );
    $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE);

    switch ( $http_status ) {
      case 0:
        $this->error = 'Unable to reach API.';
        break;
      case 200:
        return json_decode($returned_data, true);
        break;
      case 401:
        $this->error = 'Invalid API Key.';
        break;
      case 500:
        $this->error = 'API Server: Internal error.';
        break;
      default:
        $this->error = 'API server returned HTTP code: ' . $http_status;
    }

    return false;
  }
}