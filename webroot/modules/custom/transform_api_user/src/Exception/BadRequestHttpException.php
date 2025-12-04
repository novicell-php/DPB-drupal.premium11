<?php

namespace Drupal\transform_api_user\Exception;

class BadRequestHttpException extends ApiException {

  public function __construct(?string $message = '', ?string $resultCode = '', array $additionalFields = [], \Throwable $previous = NULL, array $headers = [], ?int $code = 0) {
    parent::__construct(400, $message, $resultCode, $previous, $headers, $code, $additionalFields);
  }

}
