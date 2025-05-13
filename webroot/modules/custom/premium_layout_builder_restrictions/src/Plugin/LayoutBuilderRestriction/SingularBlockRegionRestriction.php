<?php

namespace Drupal\premium_layout_builder_restrictions\Plugin\LayoutBuilderRestriction;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionBase;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls behavior of the per-domain access plugin.
 *
 * @LayoutBuilderRestriction(
 *   id = "singular_block_region_restriction",
 *   title = @Translation("Singular block region"),
 *   description = @Translation("Restrict regions to only contain one block"),
 * )
 */
class SingularBlockRegionRestriction extends LayoutBuilderRestrictionBase {

  use PluginHelperTrait;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Database connection service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, Connection $connection) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->moduleHandler = $module_handler;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('database'),
    );
  }
  /**
   * {@inheritdoc}
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    // If this method is being called by any action other than 'Add block',
    // then do nothing.
    if (!isset($context['delta'])) {
      return $definitions;
    }
    // Respect restrictions on allowed blocks specified by the section storage.
    if (isset($context['section_storage'])) {
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', $this->getPluginId(), []);
        if (empty($third_party_settings)) {
          // This entity has no restrictions. Look no further.
          return $definitions;
        }

        $layout_id = $context['section_storage']->getSection($context['delta'])->getLayoutId();
        $region = $context['region'];
        $singular_regions = (isset($third_party_settings['singular_regions'])) ? $third_party_settings['singular_regions'] : [];
        if (array_key_exists($layout_id, $singular_regions) && in_array($region, $singular_regions[$layout_id])) {
          if (count($context['section_storage']->getSection($context['delta'])->getComponentsByRegion($region)) > 0) {
            return [];
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');
    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', $this->getPluginId(), []);
    $singular_regions = (isset($third_party_settings['singular_regions'])) ? $third_party_settings['singular_regions'] : [];
    $layout_id = $section_storage->getSection($delta)->getLayoutId();
    if (array_key_exists($layout_id, $singular_regions) && in_array($region, $singular_regions[$layout_id])) {
      if (count($section_storage->getSection($delta)->getComponentsByRegion($region)) > 0) {
        return [];
      }
    }
    return $this->getInlineBlockPlugins();
  }
}
