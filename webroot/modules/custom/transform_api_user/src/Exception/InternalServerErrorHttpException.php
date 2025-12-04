<?php

namespace Drupal\transform_api_user\Exception;

class InternalServerErrorHttpException extends ApiException {

  public function __construct(?string $message = '', ?string $resultCode = '', array $additionalFields = [], \Throwable $previous = NULL, array $headers = [], ?int $code = 0) {
    parent::__construct(500, $message, $resultCode, $previous, $headers, $code, $additionalFields);
  }

}
