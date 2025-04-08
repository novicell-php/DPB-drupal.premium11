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
 *   id = "unique_section_restriction",
 *   title = @Translation("Unique section"),
 *   description = @Translation("Restrict layouts to be only one per entity"),
 * )
 */
class UniqueSectionRestriction extends LayoutBuilderRestrictionBase {

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

  public function getUniqueLayouts(): array {
    return ['layout_hero_section'];
  }

  public function getSingularLayouts(): array {
    return ['layout_hero_section'];
  }

  /**
   * {@inheritdoc}
   */
  public function alterSectionDefinitions(array $definitions, array $context) {
    // Respect restrictions on allowed layouts specified by section storage.
    if (isset($context['section_storage'])) {
      $section_storage = $context['section_storage'];
      // Respect restrictions on unique layouts specified by section storage.
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('premium_layout_builder_restrictions', 'unique_section_restriction', []);
        $unique_layouts = (isset($third_party_settings['unique_layouts'])) ? $third_party_settings['unique_layouts'] : [];
        // Filter blocks from entity-specific SectionStorage (i.e., UI).
        if (!empty($unique_layouts)) {
          $layout_counts = [];
          foreach ($unique_layouts as $unique_layout) {
            $layout_counts[$unique_layout] = 0;
          }
          /** @var Section $section */
          foreach ($section_storage->getSections() as $section) {
            if (in_array($section->getLayoutId(), $unique_layouts)) {
              unset($definitions[$section->getLayoutId()]);
            }
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
    $layout_id = $section_storage->getSection($delta)->getLayoutId();
    if (in_array($layout_id, $this->getSingularLayouts())) {
      if (count($section_storage->getSection($delta)->getComponents()) > 0) {
        return [];
      }
    }
    return $this->getInlineBlockPlugins();
  }
}
