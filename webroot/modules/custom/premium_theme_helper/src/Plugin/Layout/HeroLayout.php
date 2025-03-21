<?php
namespace Drupal\premium_theme_helper\Plugin\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\premium_core\Plugin\Layout\BaseLayout;

class HeroLayout extends BaseLayout {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['column_widths']['#access'] = FALSE;
    $form['column_width']['#access'] = FALSE;
    $form['column_spacing_top']['#access'] = FALSE;
    $form['column_spacing_bottom']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['column_width'] = 'section--width-full';
    $configuration['column_spacing_top'] = 'section--spacing-top-none';
    $configuration['column_spacing_bottom'] = 'section--spacing-bottom-none';
    return $configuration;
  }
}
