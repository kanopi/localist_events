<?php

namespace Drupal\localist_events\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
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
final class LocalistEventsListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactory $configFactory,
    private readonly CurrentPathStack $currentPath,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('path.current'),
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
      'schools' => 'ucsf',
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
    $id = Html::getUniqueId($this->getPluginId());
    $domain = $this->configFactory->get('localist_events.settings')->get('domain');

    if (filter_var($domain, FILTER_VALIDATE_URL)) {
      $domain = rtrim($domain, '/');
      $build['content'] = [
        '#theme' => 'localist_events',
        '#id' => $id,
        '#domain' => $domain,
        '#schools' => $this->configuration['schools'],
        '#groups' => $this->configuration['groups'],
        '#days' => $this->configuration['days'],
        '#total' => $this->configuration['total'],
        '#all_instances' => $this->configuration['all_instances'],
        '#show_times' => $this->configuration['show_times'],
        '#target_blank' => $this->configuration['target_blank'],
      ];
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

}
