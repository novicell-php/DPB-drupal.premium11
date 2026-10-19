<?php

declare(strict_types=1);

namespace Drupal\premium_canvas\Form;

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\premium_canvas\PagePresentation;

/**
 * Creates and compares a Canvas draft without replacing the existing layout.
 */
final class CanvasAlternativeForm extends FormBase {

  public function getFormId(): string {
    return 'premium_canvas_alternative';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $node = PagePresentation::editable($node);
    $form_state->set('node', $node);
    $form['#cache']['max-age'] = 0;
    $form['intro'] = ['#markup' => $this->t('Keep the existing Layout Builder page and prepare a separate Canvas version. Choose the published presentation on the page edit form, using the normal editorial workflow.')];
    $form['layout_preview'] = [
      '#type' => 'link', '#title' => $this->t('Preview Layout Builder'),
      '#url' => Url::fromRoute('premium_canvas.preview_layout', ['node' => $node->id()]),
      '#prefix' => '<p>', '#suffix' => '</p>',
    ];
    if ($page = PagePresentation::alternative($node)) {
      CacheableMetadata::createFromRenderArray($form)->addCacheableDependency($page)->applyTo($form);
      $form['status'] = ['#markup' => $page->isPublished() ? $this->t('The Canvas alternative is published and can be selected on the page edit form.') : $this->t('The Canvas alternative is a draft. Publish it in Canvas before selecting it as the page presentation.')];
      $editor_access = $page->access('update', NULL, TRUE);
      $form['editor'] = [
        '#type' => 'link', '#title' => $this->t('Edit Canvas alternative'),
        '#url' => $page->toUrl('edit-form'), '#access' => $editor_access,
        '#prefix' => '<p>', '#suffix' => '</p>',
      ];
      $form['canvas_preview'] = [
        '#type' => 'link', '#title' => $this->t('Preview Canvas'),
        '#url' => Url::fromRoute('premium_canvas.preview_canvas', ['node' => $node->id()]),
        '#prefix' => '<p>', '#suffix' => '</p>',
      ];
    }
    else {
      $form['description'] = ['#markup' => $this->t('Create a starter draft from the page title and its description or body. Layout Builder blocks and styling are not automatically converted.')];
      $form['actions']['#type'] = 'actions';
      $form['actions']['create'] = [
        '#type' => 'submit', '#value' => $this->t('Create Canvas alternative'),
        '#access' => $this->currentUser()->hasPermission(Page::CREATE_PERMISSION),
      ];
    }
    $form['edit_page'] = [
      '#type' => 'link', '#title' => $this->t('Edit page and choose presentation'),
      '#url' => $node->toUrl('edit-form'), '#prefix' => '<p>', '#suffix' => '</p>',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasPermission(Page::CREATE_PERMISSION)) {
      $this->messenger()->addError($this->t('You do not have permission to create Canvas pages.'));
      return;
    }
    $node = $form_state->get('node');
    $lock = \Drupal::lock();
    $lock_name = 'premium_canvas.create.' . $node->id() . '.' . $node->language()->getId();
    if (!$lock->acquire($lock_name)) {
      $this->messenger()->addWarning($this->t('An alternative is being created. Please reload this page.'));
      return;
    }
    $transaction = \Drupal::database()->startTransaction();
    try {
      \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
      $node = PagePresentation::editable($node);
      if (!$node->access('update')) {
        throw new \RuntimeException('The page can no longer be edited.');
      }
      $page = PagePresentation::alternative($node);
      if (!$page) {
        $page = self::createDraft($node, (int) $this->currentUser()->id());
        $node->set('field_canvas_page', $page->id());
        $node->setNewRevision(TRUE);
        $node->setRevisionUserId($this->currentUser()->id());
        $node->setRevisionLogMessage('Linked a Canvas draft; existing presentation retained.');
        $node->save();
      }
      $this->messenger()->addStatus($this->t('Canvas alternative ready. The page’s presentation is unchanged.'));
      $form_state->setRedirect('canvas.boot.entity', ['entity_type' => 'canvas_page', 'entity' => $page->id()]);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    finally {
      // Commit before allowing a second request to check the new reference.
      unset($transaction);
      $lock->release($lock_name);
    }
  }

  /**
   * Seeds editable content; deliberately does not claim layout conversion.
   */
  public static function createDraft(NodeInterface $node, int $owner): Page {
    $uuid = \Drupal::service('uuid');
    $section_uuid = $uuid->generate();
    $item = static function (string $name, array $inputs, ?string $parent = NULL) use ($uuid): array {
      $component = Component::load('sdc.premium.' . $name);
      if (!$component || !$component->status()) {
        throw new \RuntimeException('Required Canvas demo component is unavailable: ' . $name);
      }
      return [
        'uuid' => $uuid->generate(), 'component_id' => $component->id(),
        'component_version' => $component->getActiveVersion(), 'inputs' => $inputs,
      ] + ($parent ? ['parent_uuid' => $parent, 'slot' => 'content'] : []);
    };
    $section = $item('canvas-section', ['background' => 'light']);
    $section['uuid'] = $section_uuid;
    $tree = [$section, $item('canvas-heading', ['text' => $node->label(), 'level' => 'h1'], $section_uuid)];
    $body = $node->hasField('body') ? $node->get('body')->value : '';
    $description = $node->hasField('field_description') ? $node->get('field_description')->value : '';
    $text = $body ?: ($description ? '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>' : '');
    if ($text !== '') {
      $tree[] = $item('canvas-text', ['text' => [
        'value' => Xss::filter($text, ['strong', 'em', 'u', 'a', 'p', 'br', 'ul', 'ol', 'li']),
        'format' => 'canvas_html_block',
      ]], $section_uuid);
    }
    $page = Page::create([
      'title' => $node->label(), 'owner' => $owner, 'status' => FALSE,
      'langcode' => $node->language()->getId(), 'components' => $tree,
    ]);
    $violations = $page->validate();
    if (count($violations)) {
      throw new \RuntimeException((string) $violations);
    }
    $page->save();
    return $page;
  }

}
