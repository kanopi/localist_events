<?php

namespace Drupal\localist_events\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Localist Events settings for this site.
 */
final class LocalistEventsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localist_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['localist_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('Enter the domain for the hosted Localist widget. Do not include a trailing slash or the "/widget/view" part of the URL'),
      '#default_value' => $this->config('localist_events.settings')->get('domain'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $domain = $form_state->getValue('domain');

    if (!filter_var($domain, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName(
        'domain',
        $this->t('Domain must be a valid URL.'),
      );
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('localist_events.settings')
      ->set('domain', $form_state->getValue('domain'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
