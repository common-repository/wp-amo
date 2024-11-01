<?php

namespace ArcStone\AMO\Requests;

class IndividualsRequest extends BaseLegacyAPIRequest
{
  public static $endpoint = 'AMOIndividuals';

  public function __construct(array $params = [])
  {
    parent::__construct(self::$endpoint, $params);
  }
}