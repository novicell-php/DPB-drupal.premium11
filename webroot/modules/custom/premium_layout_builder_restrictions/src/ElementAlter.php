<?php

namespace Drupal\premium_layout_builder_restrictions;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\OverridesSectionStorageInterface;

class ElementAlter implements TrustedCallbackInterface {

  /**
   * Applies SingularBlockRegion settings to the Layout Builder element.
   *
   * @param array $element
   *   The Layout Builder render element.
   *
   * @return array
   *   The modified Layout Builder render element.
   */
  public static function preRender(array $element) {
    /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
    $section_storage = $element['#section_storage'];
    $default = $section_storage instanceof OverridesSectionStorageInterface ? $section_storage->getDefaultSectionStorage() : $section_storage;
    if ($default instanceof ThirdPartySettingsInterface) {
      $unique_layouts = $default->getThirdPartySetting('layout_builder_restrictions', 'unique_section_restriction', [])['unique_layouts'] ?? [];
      $singular_regions = $default->getThirdPartySetting('layout_builder_restrictions', 'singular_block_region_restriction', [])['singular_regions'] ?? [];
      if (empty($unique_layouts) && empty($singular_regions)) {
        // This entity has no restrictions. Look no further.
        return $element;
      }

      foreach ($element['layout_builder'] as $index => $item) {
        $class = $item['#attributes']['class'] ?? [];
        if (in_array('layout-builder__add-section', $class)) {
          $link = $item['link'];
          /** @var Url $url */
          $url = $link['#url'];
          $section_delta = $url->getRouteParameters()['delta'] ?? NULL;
          if (!is_null($section_delta) && $section_delta < $section_storage->count()) {
            $section = $section_storage->getSection($section_delta);
            if (in_array($section->getLayoutId(), $unique_layouts)) {
              unset($element['layout_builder'][$index]);
            }
          }
        } elseif (in_array('layout-builder__section', $class)) {
          /** @var LayoutDefinition $layout */
          $layout = $item['layout-builder__section']['#layout'];
          $section_delta = $item['layout-builder__section']['#attributes']['data-layout-delta'] ?? NULL;
          if (is_null($section_delta)) {
            continue;
          }
          $section = $section_storage->getSection($section_delta);
          if (array_key_exists($layout->id(), $singular_regions)) {
            foreach ($singular_regions[$layout->id()] as $region) {
              if (count($section->getComponentsByRegion($region)) > 0) {
                unset($element['layout_builder'][$index]['layout-builder__section'][$region]['layout_builder_add_block']);
              }
            }
          }
          if (in_array($layout->id(), $unique_layouts)) {
            unset($element['layout_builder'][$index]['layout-builder__section']['#header']['remove']);
          }
        }
      }
    }
    return $element;
  }


  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }
}
