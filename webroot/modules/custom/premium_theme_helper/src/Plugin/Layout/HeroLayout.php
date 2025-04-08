<?php
namespace Drupal\premium_theme_helper\Plugin\Layout;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\premium_core\Plugin\Layout\BaseLayout;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

class HeroLayout extends BaseLayout implements ContainerFactoryPluginInterface {

  /**
   * The breadcrumb manager.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbManager;

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BreadcrumbBuilderInterface $breadcrumb_manager, RouteMatchInterface $routeMatch, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->breadcrumbManager = $breadcrumb_manager;
    $this->routeMatch = $routeMatch;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    /** @var \Drupal\Core\Routing\RouteMatchInterface $routeMatch */
    $routeMatch = $container->get('current_route_match');
    /** @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb */
    $breadcrumb = $container->get('breadcrumb');
    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger */
    $logger = $container->get('logger.factory');
    return new static($configuration, $plugin_id, $plugin_definition, $breadcrumb, $routeMatch, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);
    $build['#attributes']['class'] = [
      'layout',
      $this->getPluginDefinition()->getTemplate()
    ];
    if (!$this->configuration['hide_breadcrumbs']) {
      //$build['breadcrumbs'] = $this->buildBreadcrumbs();
    }
    return $build;
  }

  /**
   * @return array
   */
  public function buildBreadcrumbs(): array {
    $build = $this->breadcrumbManager->build($this->routeMatch)->toRenderable();

    $breadcrumb_json_data = [
      '@context' => 'http://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => [],
    ];
    $links = $this->breadcrumbManager->build($this->routeMatch)->getLinks();
    if (!empty($links)) {
      foreach ($links as $key => $item) {
        $breadcrumb_json_data['itemListElement'] = [
          '@type' => 'ListItem',
          'position' => $key,
          'item' => [
            '@id' => $item->getUrl()->toString(),
            'name' => $item->getText(),
          ],
        ];
      }
    }
    /*$build['breadcrumb_json_data'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => [
        'type' => 'application/ld+json',
      ],
      '#value' => json_encode($breadcrumb_json_data),
    ];*/

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['column_widths']['#access'] = FALSE;
    $form['column_width']['#access'] = FALSE;
    $form['column_spacing_top']['#access'] = FALSE;
    $form['column_spacing_bottom']['#access'] = FALSE;

    $form['hide_breadcrumbs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide breadcrumbs'),
      '#default_value' => $this->configuration['hide_breadcrumbs'] ?? FALSE,
      '#description' => $this->t('Whether to hide breadcrumbs on this page.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['hide_breadcrumbs'] = (bool) $form_state->getValue('hide_breadcrumbs');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['hide_breadcrumbs'] = FALSE;
    $configuration['column_width'] = 'section--width-full';
    $configuration['column_spacing_top'] = 'section--spacing-top-none';
    $configuration['column_spacing_bottom'] = 'section--spacing-bottom-none';
    return $configuration;
  }

  /**
   * Returns an entity parameter from a route match object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return mixed|null
   *   The entity, or null if it's not an entity route.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if (!$route) {
      return NULL;
    }

    $entity_type_id = $this->getEntityTypeFromRoute($route);
    if ($entity_type_id) {
      return $route_match->getParameter($entity_type_id);
    }

    return NULL;
  }

  /**
   * Return the entity type id from a route object.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return string|null
   *   The entity type id, null if it doesn't exist.
   */
  protected function getEntityTypeFromRoute(Route $route): ?string {
    if (!empty($route->getOptions()['parameters'])) {
      foreach ($route->getOptions()['parameters'] as $option) {
        if (isset($option['type']) && strpos($option['type'], 'entity:') === 0) {
          return substr($option['type'], strlen('entity:'));
        }
      }
    }

    return NULL;
  }

}
