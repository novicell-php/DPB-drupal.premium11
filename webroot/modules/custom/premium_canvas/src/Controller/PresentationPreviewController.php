<?php

declare(strict_types=1);

namespace Drupal\premium_canvas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\premium_canvas\PagePresentation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PresentationPreviewController extends ControllerBase {

  public function layout(NodeInterface $node): array {
    return $this->preview(PagePresentation::editable($node), 'full');
  }

  public function canvas(NodeInterface $node): array {
    $node = PagePresentation::editable($node);
    $page = PagePresentation::alternative($node);
    if (!$page) {
      throw new NotFoundHttpException('This page has no Canvas alternative in the current language.');
    }
    if (!$page->access('view')) {
      throw new AccessDeniedHttpException();
    }
    return $this->preview($node, 'canvas');
  }

  private function preview(NodeInterface $node, string $mode): array {
    return [
      '#cache' => ['max-age' => 0],
      'notice' => [
        '#type' => 'container',
        'text' => ['#markup' => $this->t('Preview only. The page’s published presentation has not changed.')],
      ],
      'page' => $this->entityTypeManager()->getViewBuilder('node')->view($node, $mode),
    ];
  }

}
