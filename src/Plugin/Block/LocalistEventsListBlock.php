<?php

namespace Drupal\localist_events\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\localist_events\FetchEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a localist events list block.
 *
 * @Block(
 *   id = "localist_events_list",
 *   admin_label = @Translation("Localist Events List"),
 *   category = @Translation("Custom"),
 * )
 */
class LocalistEventsListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactory $configFactory,
    private readonly CurrentPathStack $currentPath,
    private readonly FetchEvents $fetchEvents,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('path.current'),
      $container->get('localist_events.fetch_events'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'all_instances' => TRUE,
      'days' => 31,
      'groups' => NULL,
      'schools' => NULL,
      'show_times' => FALSE,
      'target_blank' => TRUE,
      'total' => 3,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['schools'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schools'),
      '#default_value' => $this->configuration['schools'],
    ];
    $form['groups'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Groups'),
      '#default_value' => $this->configuration['groups'],
    ];
    $form['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days'),
      '#default_value' => $this->configuration['days'],
      '#min' => 1,
    ];
    $form['total'] = [
      '#type' => 'number',
      '#title' => $this->t('Total items to show'),
      '#default_value' => $this->configuration['total'],
      '#min' => 1,
    ];
    $form['all_instances'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('All instances'),
      '#default_value' => $this->configuration['all_instances'],
    ];
    $form['show_times'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show times'),
      '#default_value' => $this->configuration['show_times'],
    ];
    $form['target_blank'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in a new window'),
      '#default_value' => $this->configuration['target_blank'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['all_instances'] = $form_state->getValue('all_instances');
    $this->configuration['days'] = $form_state->getValue('days');
    $this->configuration['groups'] = $form_state->getValue('groups');
    $this->configuration['schools'] = $form_state->getValue('schools');
    $this->configuration['show_times'] = $form_state->getValue('show_times');
    $this->configuration['target_blank'] = $form_state->getValue('target_blank');
    $this->configuration['total'] = $form_state->getValue('total');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $domain = $this->configFactory->get('localist_events.settings')->get('domain');
    $items = $this->fetchEvents->fetch($this->configuration);

    if (filter_var($domain, FILTER_VALIDATE_URL)) {
      $domain = rtrim($domain, '/');

      if (isset($items['error'])) {
        $build['content'] = $items['error'];
      }
      else {
        $items = array_map(function ($item) {
          return [
            '#theme' => 'localist_events_item',
            '#date' => $item['date'] ?? NULL,
            '#description' => $item['description'] ?? NULL,
            '#image' => $item['image'] ?? NULL,
            '#link' => $item['link'] ?? NULL,
            '#location' => $item['location'] ?? NULL,
            '#tags' => $item['tags'] ?? NULL,
          ];
        }, $items);
        $build['content'] = [
          '#theme' => 'localist_events_items',
          '#items' => $items,
        ];
      }
    }
    else {
      $url = Url::fromRoute('localist_events.localist_events_settings');
      $destination = $this->currentPath->getPath();
      $build['content'] = [
        '#markup' => $this->t('A domain hasn\'t been configured yet. Add one <a href="@url?destination=@destination">here</a>.', [
          '@url' => $url->toString(),
          '@destination' => $destination,
        ]),
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache for one day in seconds.
    return (60 * 60 * 24);
  }

}
