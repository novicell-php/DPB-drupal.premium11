<?php

declare(strict_types=1);

namespace Drupal\premium_canvas_ai\EventSubscriber;

use Drupal\ai_agents\Event\AgentToolPreExecuteEvent;
use Drupal\ai_agents\Event\AgentToolFinishedExecutionEvent;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai\Plugin\AiFunctionCall\EditComponents;
use Drupal\premium_canvas_ai\MediaReferenceNormalizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Supplies stored media references to Canvas's native layout and edit tools.
 */
final class MediaContextSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly CanvasAiTempStore $tempStore,
    private readonly MediaReferenceNormalizer $normalizer,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AgentToolPreExecuteEvent::EVENT_NAME => 'normalizeLayout',
      AgentToolFinishedExecutionEvent::EVENT_NAME => 'preserveMedia',
    ];
  }

  /**
   * Normalizes context before the agent reads it or validates a partial edit.
   */
  public function normalizeLayout(AgentToolPreExecuteEvent $event): void {
    if (!in_array($event->getTool()->getPluginId(), ['canvas_ai:get_current_layout', 'canvas_ai:edit_components'], TRUE)) {
      return;
    }
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || $request->attributes->get('_route') !== 'canvas_dev_ai.builder') {
      return;
    }
    $payload = $request->getContentTypeFormat() === 'json'
      ? json_decode($request->getContent(), TRUE)
      : $request->request->all();
    if (($payload['entity_type'] ?? NULL) !== 'canvas_page' || !ctype_digit((string) ($payload['entity_id'] ?? ''))) {
      return;
    }
    $stored = $this->tempStore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
    $layout = json_decode($stored ?? '', TRUE);
    if (!is_array($layout)) {
      return;
    }
    $normalized = $this->normalizer->normalize($layout, (string) $payload['entity_id']);
    $request->attributes->set('premium_canvas_ai.media_context', TRUE);
    if ($normalized !== $layout) {
      $this->tempStore->setData(CanvasAiTempStore::CURRENT_LAYOUT_KEY, json_encode($normalized, JSON_THROW_ON_ERROR));
    }
  }

  /**
   * Sends unchanged media IDs alongside edits so the editor retains them.
   */
  public function preserveMedia(AgentToolFinishedExecutionEvent $event): void {
    $tool = $event->getTool();
    if (!$tool instanceof EditComponents || !$this->requestStack->getCurrentRequest()?->attributes->get('premium_canvas_ai.media_context')) {
      return;
    }
    $output = $tool->getStructuredOutput();
    $layout = json_decode($this->tempStore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY) ?? '', TRUE);
    if (!isset($output['component_updates']) || !is_array($layout)) {
      return;
    }
    $output['component_updates'] = $this->normalizer->completeUpdates($output['component_updates'], $layout);
    $tool->setStructuredOutput($output);
  }

}
