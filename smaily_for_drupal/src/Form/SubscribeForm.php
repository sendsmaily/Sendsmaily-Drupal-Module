<?php

namespace Drupal\smaily_for_drupal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Smaily newsletter signup form.
 */
class SubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smaily_for_drupal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $block_config = $form_state->getBuildInfo()['args'][0];
    $config = $this->config('smaily_for_drupal.settings');

    $form['#action'] = 'https://' . $config->get('smaily_api_credentials.domain') . '.sendsmaily.net/api/opt-in/';
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name:'),
      '#attributes' => ['placeholder' => $this->t('Name')],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email:'),
      '#attributes' => ['placeholder' => $this->t('Email')],
      '#required' => TRUE,
    ];

    $form['autoresponder'] = [
      '#type' => 'autoresponder',
      '#value' => $block_config['autoresponder'],
    ];

    $form['success_url'] = [
      '#type' => 'hidden',
      '#value' => $block_config['success_url'],
    ];

    $form['failure_url'] = [
      '#type' => 'hidden',
      '#value' => $block_config['failure_url'],
    ];

    if ($config->get('smaily_custom_fields')) {
      $options = [];
      foreach (explode("\n", $config->get('smaily_custom_fields', '')) as $line) {
        $values = explode("|", $line);
        $options[trim($values[0])] = trim($values[1]);
      }

      $form['custom_fields'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => array_keys($options),
        '#title' => $this->t('Pick which news and updates you would like to receive:'),
      ];
    }

    // Disable being able to submit to ".sendsmaily.net".
    if (!empty($config->get('smaily_api_credentials.domain'))) {
      $form['subscribe'] = [
        // #name 'op' is recognized as a custom field by Smaily, leaving it blank here.
        '#name' => '',
        '#type' => 'submit',
        '#value' => $block_config['button_title'],
        '#default_value' => $this->t('Subscribe to newsletter'),
      ];
    }
    // Remove token so as to not send it to Smaily as a field.
    $form['#token'] = FALSE;
    $form['#after_build'][] = [$this, 'removeHiddenDrupalInputs'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Fullfill FormBase.
  }

  /**
   * Remove form_id and form_build_id as to not send them to Smaily as fields.
   *
   * @param array $form
   *   Form after it is built for display.
   *
   * @return array
   *   Form without form_id and form_build_id hidden elements.
   */
  public function removeHiddenDrupalInputs(array $form) {
    $form['form_id']['#access'] = FALSE;
    $form['form_build_id']['#access'] = FALSE;
    return $form;
  }

}
