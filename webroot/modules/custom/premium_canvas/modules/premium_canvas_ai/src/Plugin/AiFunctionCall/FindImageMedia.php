<?php

declare(strict_types=1);

namespace Drupal\premium_canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\canvas_ai\CanvasAiPermissions;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Finds existing image media without downloading or creating any assets.
 */
#[FunctionCall(
  id: 'premium_canvas_ai:find_image_media',
  function_name: 'find_image_media',
  name: 'Find image media',
  description: 'Find accessible published image media by name, filename, or media ID. Returns media IDs to use directly as image prop values, for example image: 24. Never substitute a file ID. If multiple results match, ask the user to choose. If no results match, ask the user to upload or select media.',
  group: 'information_tools',
  context_definitions: [
    'query' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Media name, filename, or ID'),
      required: TRUE,
    ),
  ],
  module_dependencies: ['premium_canvas_ai'],
)]
final class FindImageMedia extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $account = \Drupal::currentUser();
    if (!$account->hasPermission(CanvasAiPermissions::USE_CANVAS_AI)) {
      throw new \RuntimeException('You do not have permission to use Canvas AI.');
    }
    $query = trim((string) $this->getContextValue('query'));
    if ($query === '') {
      $this->setOutput('Provide a media name, filename, or numeric media ID.');
      return;
    }
    $manager = \Drupal::entityTypeManager();
    $storage = $manager->getStorage('media');
    $matches = [];
    foreach ($manager->getStorage('media_type')->loadMultiple() as $type) {
      if ($type->getSource()->getPluginId() !== 'image') {
        continue;
      }
      $field = $type->getSource()->getConfiguration()['source_field'];
      $search = $storage->getQuery()->accessCheck(TRUE)
        ->condition('bundle', $type->id())->condition('status', 1);
      if (ctype_digit($query)) {
        $search->condition('mid', (int) $query);
      }
      else {
        $or = $search->orConditionGroup()->condition('name', $query, 'CONTAINS');
        $or->condition($field . '.entity.filename', $query, 'CONTAINS');
        $search->condition($or);
      }
      $ids = $search->sort('mid')->range(0, 10)->execute();
      foreach ($storage->loadMultiple($ids) as $media) {
        if (!$media->access('view', $account) || !$media->get($field)->access('view', $account)) {
          continue;
        }
        $file = $media->get($field)->entity;
        if (!$file || !$file->access('view', $account)) {
          continue;
        }
        $matches[] = [
          'media_id' => (int) $media->id(),
          'name' => $media->label(),
          'filename' => $file->getFilename(),
          'alt' => $media->get($field)->alt,
        ];
        if (count($matches) === 10) {
          break 2;
        }
      }
    }
    $this->setOutput(json_encode(['matches' => $matches, 'limit' => 10], JSON_THROW_ON_ERROR));
  }

}
