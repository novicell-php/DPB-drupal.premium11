<?php

declare(strict_types=1);

namespace Drupal\premium_canvas_ai;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Restores media IDs omitted from Canvas AI's rendered layout context.
 */
final class MediaReferenceNormalizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AutoSaveManager $autoSaveManager,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Uses the accessible page draft as the authority for media references.
   *
   * A stale client image is left unchanged rather than assigned another ID.
   * Explicit numeric selections and unrelated props are never overwritten.
   */
  public function normalize(array $layout, string $page_id): array {
    $page = $this->entityTypeManager->getStorage('canvas_page')->load($page_id);
    if (!$page instanceof Page || !$page->access('update', $this->account)) {
      return $layout;
    }
    $draft = $this->autoSaveManager->getAutoSaveEntity($page);
    if (!$draft->isEmpty()) {
      $page = $draft->entity;
    }
    $tree = $page->getComponentTree();
    if (!$tree->access('view', $this->account)) {
      return $layout;
    }
    $references = [];
    foreach ($tree as $item) {
      $component = $item->getComponent();
      $source = $component?->getComponentSource();
      if (!$source || !$source->requiresExplicitInput()) {
        continue;
      }
      $model = $source->inputToClientModel($source->getExplicitInput($item->getUuid(), $item, $page));
      foreach ($model['source'] ?? [] as $prop => $definition) {
        if (($definition['sourceType'] ?? NULL) !== 'static:field_item:entity_reference' || ($definition['sourceTypeSettings']['storage']['target_type'] ?? NULL) !== 'media') {
          continue;
        }
        $value = $definition['value'] ?? NULL;
        $id = is_array($value) ? ($value['target_id'] ?? NULL) : $value;
        if (!is_numeric($id) || (int) $id < 1) {
          continue;
        }
        $media = $this->entityTypeManager->getStorage('media')->load($id);
        if (!$media || !$media->access('view', $this->account)) {
          continue;
        }
        $references[$item->getUuid()][$prop] = [
          'component' => $component->id(),
          'value' => $model['resolved'][$prop] ?? NULL,
          'id' => (int) $id,
        ];
      }
    }
    if (isset($layout['regions'])) {
      foreach ($layout['regions'] as &$region) {
        if (isset($region['components'])) {
          $this->normalizeComponents($region['components'], $references);
        }
      }
    }
    return $layout;
  }

  /**
   * Walks nested slots without changing any layout structure.
   */
  private function normalizeComponents(array &$components, array $references): void {
    foreach ($components as &$component) {
      foreach ($references[$component['uuid'] ?? ''] ?? [] as $prop => $reference) {
        $value = $component['props'][$prop] ?? NULL;
        if (($component['name'] ?? NULL) === $reference['component'] && is_array($value) && $value === $reference['value']) {
          $component['props'][$prop] = $reference['id'];
        }
      }
      if (isset($component['slots'])) {
        foreach ($component['slots'] as &$slot) {
          if (isset($slot['components'])) {
            $this->normalizeComponents($slot['components'], $references);
          }
        }
      }
    }
  }

  /**
   * Retains media IDs when the editor applies partial prop updates.
   *
   * Canvas otherwise merges rendered image objects into its widget values.
   * Explicit changes (including clearing a value) always take precedence.
   */
  public function completeUpdates(array $updates, array $layout): array {
    $visit = function (array $components) use (&$visit, &$updates): void {
      foreach ($components as $node) {
        $uuid = $node['uuid'] ?? '';
        if (isset($updates[$uuid]) && isset($node['name'])) {
          $component = Component::load($node['name']);
          $sources = $component?->normalizeForClientSide()->values['propSources'] ?? [];
          foreach ($sources as $prop => $source) {
            $id = $node['props'][$prop] ?? NULL;
            if (($source['sourceTypeSettings']['storage']['target_type'] ?? NULL) !== 'media' || array_key_exists($prop, $updates[$uuid]) || !is_int($id) || $id < 1) {
              continue;
            }
            $media = $this->entityTypeManager->getStorage('media')->load($id);
            if ($media && $media->access('view', $this->account)) {
              $updates[$uuid][$prop] = $id;
            }
          }
        }
        foreach ($node['slots'] ?? [] as $slot) {
          $visit($slot['components'] ?? []);
        }
      }
    };
    foreach ($layout['regions'] ?? [] as $region) {
      $visit($region['components'] ?? []);
    }
    return $updates;
  }

}
