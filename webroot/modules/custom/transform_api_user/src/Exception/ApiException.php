<?php

namespace Drupal\transform_api_user\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ApiException extends HttpException {

  private ?string $resultCode;

  private array $additionalFields;

  public function __construct(int $statusCode, ?string $message = '', ?string $resultCode = '', \Throwable $previous = NULL, array $headers = [], ?int $code = 0, array $additionalFields = []) {
    parent::__construct($statusCode, $message, $previous, $headers, $code);
    $this->additionalFields = $additionalFields;
    $this->resultCode = $resultCode;
  }

  public function getResultCode(): ?string {
    return $this->resultCode;
  }

  public function getAdditionalFields(): array {
    return $this->additionalFields;
  }

}
