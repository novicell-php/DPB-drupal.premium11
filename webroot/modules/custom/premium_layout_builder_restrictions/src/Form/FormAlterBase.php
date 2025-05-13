<?php

namespace Drupal\premium_layout_builder_restrictions\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
abstract class FormAlterBase implements ContainerInjectionInterface {

  use PluginHelperTrait;
  use DependencySerializationTrait;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * FormAlterBase constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Block\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, BlockManagerInterface $block_manager, LayoutPluginManagerInterface $layout_manager, ContextHandlerInterface $context_handler) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->blockManager = $block_manager;
    $this->layoutManager = $layout_manager;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block'),
      $container->get('plugin.manager.core.layout'),
      $container->get('context.handler')
    );
  }

  /**
   * The plugin the form alter is for.
   */
  abstract function getPluginId(): string;

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayFormAllowedBlockCategories(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
    }
  }

  /**
   * Alter form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', $this->getPluginId(), []);
      $this->applyEntityViewDisplayFormAlterations($form, $form_state, $form_id, $third_party_settings);
    }
  }

  /**
   * The actual form alterations.
   */
  public function applyEntityViewDisplayFormAlterations(&$form, FormStateInterface $form_state, $form_id, $third_party_settings) {
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', $this->getPluginId());
    $this->setPluginSettings($third_party_settings, $form_state);
    // Save!
    $display->setThirdPartySetting('layout_builder_restrictions', $this->getPluginId(), $third_party_settings);
  }

  /**
   * Helper function to prepare saved plugin settings.
   *
   * @param array $third_party_settings
   *   The third party settings.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  abstract protected function setPluginSettings(&$third_party_settings, FormStateInterface $form_state): void;

}
