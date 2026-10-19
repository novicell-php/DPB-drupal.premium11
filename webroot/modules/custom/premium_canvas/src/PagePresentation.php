<?php

declare(strict_types=1);

namespace Drupal\premium_canvas;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves a page's independently editable Canvas alternative.
 */
final class PagePresentation {

  /**
   * Gets the latest editable translation without publishing pending changes.
   */
  public static function editable(NodeInterface $node): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $langcode = $node->language()->getId();
    $revision = $storage->getLatestTranslationAffectedRevisionId($node->id(), $langcode);
    $latest = $revision ? $storage->loadRevision($revision) : $node;
    return $latest->hasTranslation($langcode) ? $latest->getTranslation($langcode) : $node;
  }

  public static function alternative(NodeInterface $node): ?Page {
    if (!$node->hasField('field_canvas_page')) {
      return NULL;
    }
    $page = $node->get('field_canvas_page')->entity;
    if (!$page instanceof Page) {
      return NULL;
    }
    $langcode = $node->language()->getId();
    return $page->hasTranslation($langcode) ? $page->getTranslation($langcode) : NULL;
  }

  public static function usesCanvas(NodeInterface $node): bool {
    if (!$node->hasField('field_page_presentation') || $node->get('field_page_presentation')->value !== 'canvas') {
      return FALSE;
    }
    $page = self::alternative($node);
    return $page && $page->isPublished() && !$page->getComponentTree()->isEmpty();
  }

  public static function manageAccess(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($node->bundle() === 'page')
      ->andIf(AccessResult::allowedIfHasPermission($account, Page::EDIT_PERMISSION))
      ->addCacheableDependency($node);
  }

}
