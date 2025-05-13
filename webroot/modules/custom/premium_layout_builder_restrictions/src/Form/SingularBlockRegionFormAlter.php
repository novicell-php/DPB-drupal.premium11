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
class SingularBlockRegionFormAlter extends FormAlterBase {

  /**
   * {@inheritDoc}
   */
  function getPluginId(): string
  {
    return 'singular_block_region_restriction';
  }

  /**
   * {@inheritDoc}
   */
  public function applyEntityViewDisplayFormAlterations(&$form, FormStateInterface $form_state, $form_id, $third_party_settings) {
    // Singular layout settings.
    $singular_regions = (isset($third_party_settings['singular_regions'])) ? $third_party_settings['singular_regions'] : [];
    $layout_form = [
      '#type' => 'details',
      '#title' => $this->t('Layout regions for single blocks'),
      '#parents' => ['layout_builder_restrictions', 'singular_regions'],
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
      $regions = $definition->getRegions();
      $options = [];
      foreach ($regions as $region_id => $region) {
        $options[$region_id] = $region['label'];
      }
      $enabled = FALSE;
      if (!empty($singular_regions) && in_array($plugin_id, $singular_regions)) {
        $enabled = TRUE;
      }
      $layout_form['layouts'][$plugin_id] = [
        '#type' => 'checkboxes',
        '#title' => $definition->getLabel(),
        '#default_value' => $singular_regions[$plugin_id],
        '#options' => $options,
      ];
    }
    $form['layout']['layout_builder_restrictions']['singular_regions'] = $layout_form;
  }

  /**
   * {@inheritDoc}
   */
  protected function setPluginSettings(&$third_party_settings, FormStateInterface $form_state): void {
    $third_party_settings['singular_regions'] = [];
    $regions = array_filter($form_state->getValue([
      'layout_builder_restrictions',
      'singular_regions',
      'layouts',
    ]));
    foreach ($regions as $region_id => $region) {
      $values = array_values(array_filter($region));
      if (!empty($values)) {
        $third_party_settings['singular_regions'][$region_id] = $values;
      }
    }
  }
}
