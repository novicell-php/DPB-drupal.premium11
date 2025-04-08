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
class FormAlter implements ContainerInjectionInterface {

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
   * FormAlter constructor.
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
   * The actual form elements.
   */
  public function alterEntityViewDisplayFormAllowedBlockCategories(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      $allowed_block_categories = $display->getThirdPartySetting('premium_layout_builder_restrictions', 'allowed_block_categories', []);
      $form['layout']['premium_layout_builder_restrictions']['allowed_block_categories'] = [
        '#title' => $this->t('Default restriction for new categories of blocks not listed below.'),
        '#description_display' => 'before',
        '#type' => 'radios',
        '#options' => [
          "allowed" => $this->t('Allow all blocks from newly available categories.'),
          "restricted" => $this->t('Restrict all blocks from newly available categories.'),
        ],
        '#parents' => [
          'premium_layout_builder_restrictions',
          'allowed_block_categories',
        ],
        '#default_value' => !empty($allowed_block_categories) ? "restricted" : "allowed",
      ];
    }
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      $third_party_settings = $display->getThirdPartySetting('premium_layout_builder_restrictions', 'unique_section_restriction', []);
      // Unique layout settings.
      $unique_layouts = (isset($third_party_settings['unique_layouts'])) ? $third_party_settings['unique_layouts'] : [];
      $layout_form = [
        '#type' => 'details',
        '#title' => $this->t('Unique layouts for sections'),
        '#parents' => ['premium_layout_builder_restrictions', 'unique_layouts'],
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $definitions = $this->getLayoutDefinitions();
      foreach ($definitions as $plugin_id => $definition) {
        $enabled = FALSE;
        if (!empty($unique_layouts) && in_array($plugin_id, $unique_layouts)) {
          $enabled = TRUE;
        }
        $layout_form['layouts'][$plugin_id] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#description' => [
            $definition->getIcon(60, 80, 1, 3),
            [
              '#type' => 'container',
              '#children' => $definition->getLabel() . ' (' . $plugin_id . ')',
            ],
          ],
        ];
      }
      $form['layout']['premium_layout_builder_restrictions']['unique_layouts'] = $layout_form;
      // Singilar layout settings.
      $singular_layouts = (isset($third_party_settings['singular_layouts'])) ? $third_party_settings['singular_layouts'] : [];
      $layout_form = [
        '#type' => 'details',
        '#title' => $this->t('Singular layouts for sections'),
        '#parents' => ['premium_layout_builder_restrictions', 'singular_layouts'],
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $definitions = $this->getLayoutDefinitions();
      foreach ($definitions as $plugin_id => $definition) {
        $enabled = FALSE;
        if (!empty($singular_layouts) && in_array($plugin_id, $singular_layouts)) {
          $enabled = TRUE;
        }
        $layout_form['layouts'][$plugin_id] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#description' => [
            $definition->getIcon(60, 80, 1, 3),
            [
              '#type' => 'container',
              '#children' => $definition->getLabel() . ' (' . $plugin_id . ')',
            ],
          ],
        ];
      }
      $form['layout']['premium_layout_builder_restrictions']['singular_layouts'] = $layout_form;
    }
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    $third_party_settings = $display->getThirdPartySetting('premium_layout_builder_restrictions', 'unique_section_restriction');
    $third_party_settings['unique_layouts'] = $this->setUniqueLayouts($form_state);
    $third_party_settings['singular_layouts'] = $this->setSingularLayouts($form_state);
    // Save!
    $display->setThirdPartySetting('premium_layout_builder_restrictions', 'unique_section_restriction', $third_party_settings);
  }

  /**
   * Helper function to prepare saved allowed blocks.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of layout names or empty.
   */
  protected function setAllowedBlocks(FormStateInterface $form_state) {
    $categories = $form_state->getValue([
      'premium_layout_builder_restrictions',
      'allowed_blocks',
    ]);
    $block_restrictions = [];
    $block_restrictions['restricted_categories'] = [];
    if (!empty($categories)) {
      foreach ($categories as $category => $settings) {
        $restriction_type = $settings['restriction'];
        if (in_array($restriction_type, ['allowlisted', 'denylisted'])) {
          $block_restrictions[$restriction_type][$category] = [];
          foreach ($settings['available_blocks'] as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $block_restrictions[$restriction_type][$category][] = $block_id;
            }
          }
        }
        elseif ($restriction_type === "restrict_all") {
          $block_restrictions['restricted_categories'][] = $category;
        }
      }
    }
    return $block_restrictions;
  }

  /**
   * Helper function to prepare saved unique layouts.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of layout names or empty.
   */
  protected function setUniqueLayouts(FormStateInterface $form_state): array {
    // Set unique layouts.
    return array_keys(array_filter($form_state->getValue([
      'premium_layout_builder_restrictions',
      'unique_layouts',
      'layouts',
    ])));
  }

  /**
   * Helper function to prepare saved singular layouts.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of layout names or empty.
   */
  protected function setSingularLayouts(FormStateInterface $form_state): array {
    // Set singular layouts.
    return array_keys(array_filter($form_state->getValue([
      'premium_layout_builder_restrictions',
      'singular_layouts',
      'layouts',
    ])));
  }

  /**
   * Helper function to prepare saved block definition categories.
   *
   * @return array
   *   An array of block category names or empty.
   */
  protected function setAllowedBlockCategories(FormStateInterface $form_state, LayoutEntityDisplayInterface $display) {
    // Set default for allowed block categories.
    $block_category_default = $form_state->getValue([
      'premium_layout_builder_restrictions',
      'allowed_block_categories',
    ]);
    if ($block_category_default == 'restricted') {
      // Create a allowlist of categories whose blocks should be allowed.
      // Newly available categories' blocks not in this list will be
      // disallowed.
      $allowed_block_categories = array_keys($this->getBlockDefinitions($display));
    }
    else {
      // The UI choice indicates that all newly available categories'
      // blocks should be allowed by default. Represent this in the schema
      // as an empty array.
      $allowed_block_categories = [];
    }
    return $allowed_block_categories;
  }

}
