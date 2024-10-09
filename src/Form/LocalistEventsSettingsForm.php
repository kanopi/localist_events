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
      '#description' => $this->t('Enter the domain for the hosted Localist widget. Do not include a trailing slash or the "/widget/view" part of the URL.'),
      '#default_value' => $this->config('localist_events.settings')->get('domain'),
    ];
    $form['image_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Selector'),
      '#description' => $this->t('The CSS selector to use for the image for each item, Usually an image found on the detail page for the given item. Be sure to include the <code>`img`</code> tag, or the class/ID directly on the tag. E.g. <code>`.some-selector img`</code> or <code>`#image-id`</code>.'),
      '#default_value' => $this->config('localist_events.settings')->get('image_selector'),
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
      ->set('domain', rtrim($form_state->getValue('domain')))
      ->set('image_selector', $form_state->getValue('image_selector'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
