<?php

namespace ArcStone\AMO\Requests;

class AssociationMetaDataRequest extends BaseLegacyAPIRequest
{
  public static $endpoint = 'AMOAssociation';

  public function __construct(array $params = [])
  {
    parent::__construct(self::$endpoint, $params);
  }
}