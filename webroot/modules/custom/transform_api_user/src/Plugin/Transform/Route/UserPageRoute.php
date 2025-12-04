<?php

namespace Drupal\transform_api_user\Plugin\Transform\Route;

use Drupal\transform_api\Exception\RedirectTransformationException;
use Drupal\transform_api\Exception\ResponseTransformationException;
use Drupal\transform_api\RouteTransformBase;
use Drupal\transform_api\Transform\TransformInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @RouteTransform(
 *  id = "user.page",
 *  title = "User page"
 * )
 */
class UserPageRoute extends RouteTransformBase {

  public function transform(TransformInterface $transform): array {
    if (\Drupal::currentUser()->isAnonymous()) {
      throw new ResponseTransformationException(new Response(Response::HTTP_FORBIDDEN, 403), Response::HTTP_FORBIDDEN);
    } else {
      throw new RedirectTransformationException('entity.user.canonical', [
        'user' => \Drupal::currentUser()
          ->id()
      ]);
    }
  }

}
