<?php

/**
 * @file
 * Integration regression checks; run with Drush php:script on the demo site.
 *
 * Uses an existing published image and deletes its temporary page and draft.
 * Does not call an AI provider or change existing content.
 */

use Drupal\ai_agents\Event\AgentToolPreExecuteEvent;
use Drupal\ai_agents\Event\AgentToolFinishedExecutionEvent;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  echo "PASS: $message\n";
};
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
$store = \Drupal::service(CanvasAiTempStore::class);
$before = $store->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
$auto_save = \Drupal::service(AutoSaveManager::class);
$normalizer = \Drupal::service('premium_canvas_ai.media_reference_normalizer');
$page = NULL;
$draft_media = NULL;
try {
  $media_ids = \Drupal::entityTypeManager()->getStorage('media')->getQuery()
    ->accessCheck(TRUE)->condition('bundle', 'image')->condition('status', 1)
    ->range(0, 1)->execute();
  $media_id = (int) reset($media_ids);
  if (!$media_id) {
    throw new RuntimeException('This integration test requires a published image media item.');
  }
  $component = Component::load('sdc.premium.canvas-image');
  $uuid = \Drupal::service('uuid')->generate();
  $page = Page::create([
    'title' => 'Temporary media context regression test',
    'status' => FALSE,
    'components' => [[
      'uuid' => $uuid,
      'component_id' => $component->id(),
      'component_version' => $component->getActiveVersion(),
      'inputs' => ['image' => ['target_id' => $media_id], 'caption' => 'Original', 'ratio' => 'natural'],
    ]],
  ]);
  $page->save();
  $build = $page->getComponentTree()->getClientSideRepresentation($page);
  $node = ['uuid' => $uuid, 'name' => $component->id(), 'props' => $build['model'][$uuid]['resolved']];
  $layout = ['regions' => ['content' => ['components' => [$node]]]];
  $normalized = $normalizer->normalize($layout, $page->id());
  $check($normalized['regions']['content']['components'][0]['props']['image'] === $media_id, 'Rendered image becomes its stored media ID');
  $check($normalized['regions']['content']['components'][0]['props']['caption'] === 'Original', 'Other props remain unchanged');
  $check($normalizer->normalize($normalized, $page->id()) === $normalized, 'Explicit numeric selections remain unchanged');
  $stale = $layout;
  $stale['regions']['content']['components'][0]['props']['image']['src'] = '/different-image.png';
  $check($normalizer->normalize($stale, $page->id()) === $stale, 'A stale image is never assigned the saved image ID');
  $wrong_component = $layout;
  $wrong_component['regions']['content']['components'][0]['name'] = 'sdc.other.image';
  $check($normalizer->normalize($wrong_component, $page->id()) === $wrong_component, 'Component identity must match');
  $nested = ['regions' => ['content' => ['components' => [['uuid' => 'wrapper', 'slots' => ['content' => ['components' => [$node]]]]]]]];
  $check($normalizer->normalize($nested, $page->id())['regions']['content']['components'][0]['slots']['content']['components'][0]['props']['image'] === $media_id, 'Images in nested slots are normalized');
  $switcher->switchTo(new AnonymousUserSession());
  try {
    $check($normalizer->normalize($layout, $page->id()) === $layout, 'No references are exposed without page edit access');
  }
  finally {
    $switcher->switchBack();
  }
  // A draft differs from the stored page: only its current image can be used.
  $draft_media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id)->createDuplicate();
  $draft_media->setName('Temporary media context draft fixture');
  $draft_media->save();
  $draft = clone $page;
  $input = $draft->getComponentTree()->get(0)->getInputs();
  $input['caption'] = 'Draft caption';
  $input['image'] = ['target_id' => (int) $draft_media->id()];
  $draft->getComponentTree()->get(0)->setInput($input);
  $auto_save->saveEntity($draft);
  $draft_layout = $layout;
  $draft_layout['regions']['content']['components'][0]['props']['caption'] = 'Draft caption';
  $check($normalizer->normalize($draft_layout, $page->id())['regions']['content']['components'][0]['props']['image'] === (int) $draft_media->id(), 'Autosaved media takes precedence over the published selection');

  // Exercise the real event and native edit tool with a caption-only payload.
  $store->setData(CanvasAiTempStore::CURRENT_LAYOUT_KEY, json_encode($draft_layout, JSON_THROW_ON_ERROR));
  $request = Request::create('/admin/api/canvas/ai-dev', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['entity_type' => 'canvas_page', 'entity_id' => $page->id()]));
  $request->attributes->set('_route', 'canvas_dev_ai.builder');
  $requests = \Drupal::service('request_stack');
  $requests->push($request);
  try {
    $tool = \Drupal::service('plugin.manager.ai.function_calls')->createInstance('canvas_ai:edit_components');
    $tool->setContextValue('component_edits', [['component_uuid' => $uuid, 'props' => 'caption: Updated caption']]);
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('drupal_canvas_page_agent');
    $event = new AgentToolPreExecuteEvent($agent, 'regression-test', $tool, 'regression-test');
    \Drupal::service('event_dispatcher')->dispatch($event, $event::EVENT_NAME);
    $tool->execute();
    $check($tool->getStructuredOutput() === ['component_updates' => [$uuid => ['caption' => 'Updated caption']]], 'Native caption-only edits validate without resupplying media');
    $finished = new AgentToolFinishedExecutionEvent($agent, 'regression-test', $tool, 'regression-test');
    \Drupal::service('event_dispatcher')->dispatch($finished, $finished::EVENT_NAME);
    $check($tool->getStructuredOutput() === ['component_updates' => [$uuid => ['caption' => 'Updated caption', 'image' => (int) $draft_media->id()]]], 'Editor receives the preserved media ID with a partial edit');
    $explicit = [$uuid => ['image' => NULL]];
    $check($normalizer->completeUpdates($explicit, $normalized) === $explicit, 'Explicit media changes are never overwritten');
    // Multipart attachment requests use the same normalization.
    $request->headers->set('Content-Type', 'multipart/form-data');
    $request->request->replace(['entity_type' => 'canvas_page', 'entity_id' => $page->id()]);
    $store->setData(CanvasAiTempStore::CURRENT_LAYOUT_KEY, json_encode($layout));
    \Drupal::service('event_dispatcher')->dispatch($event, $event::EVENT_NAME);
    $check(json_decode($store->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY), TRUE)['regions']['content']['components'][0]['props']['image'] === (int) $draft_media->id(), 'Multipart AI requests are supported');
  }
  finally {
    $requests->pop();
  }
}
finally {
  if ($page) {
    $auto_save->delete($page);
    $page->delete();
  }
  if ($draft_media) {
    $draft_media->delete();
  }
  if ($before === NULL) {
    $store->deleteData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
  }
  else {
    $store->setData(CanvasAiTempStore::CURRENT_LAYOUT_KEY, $before);
  }
  $switcher->switchBack();
}
