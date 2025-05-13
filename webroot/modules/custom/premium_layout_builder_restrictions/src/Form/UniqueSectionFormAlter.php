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
class UniqueSectionFormAlter extends FormAlterBase {

  /**
   * {@inheritDoc}
   */
  function getPluginId(): string
  {
    return 'unique_section_restriction';
  }

  /**
   * {@inheritDoc}
   */
  public function applyEntityViewDisplayFormAlterations(&$form, FormStateInterface $form_state, $form_id, $third_party_settings) {
    // Unique layout settings.
    $unique_layouts = (isset($third_party_settings['unique_layouts'])) ? $third_party_settings['unique_layouts'] : [];
    $layout_form = [
      '#type' => 'details',
      '#title' => $this->t('Unique layouts for sections'),
      '#parents' => ['layout_builder_restrictions', 'unique_layouts'],
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
    $form['layout']['layout_builder_restrictions']['unique_layouts'] = $layout_form;
  }

  /**
   * {@inheritDoc}
   */
  protected function setPluginSettings(&$third_party_settings, FormStateInterface $form_state): void {
    // Set unique layouts.
    $third_party_settings['unique_layouts'] = array_keys(array_filter($form_state->getValue([
      'layout_builder_restrictions',
      'unique_layouts',
      'layouts',
    ])));
  }
}
