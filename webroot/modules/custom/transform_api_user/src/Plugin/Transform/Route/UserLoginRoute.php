<?php

namespace Drupal\transform_api_user\Plugin\Transform\Route;

use Drupal\transform_api\Exception\ResponseTransformationException;
use Drupal\transform_api\RouteTransformBase;
use Drupal\transform_api\Transform\TransformInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @RouteTransform(
 *  id = "user.login",
 *  title = "User login"
 * )
 */
class UserLoginRoute extends RouteTransformBase {

  public function transform(TransformInterface $transform): array {
    throw new ResponseTransformationException(new Response(Response::HTTP_FORBIDDEN, 403), Response::$statusTexts[Response::HTTP_FORBIDDEN]);
  }
}
