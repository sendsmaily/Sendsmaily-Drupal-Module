<?php

namespace Drupal\smaily_for_drupal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
      '#type' => 'hidden',
      '#value' => $block_config['autoresponder'],
    ];

    $current_url = Url::fromRoute('<current>', [], ['absolute' => 'true'])->toString();
    $form['success_url'] = [
      '#type' => 'hidden',
      '#value' => $block_config['success_url'] ?: $current_url,
    ];

    $form['failure_url'] = [
      '#type' => 'hidden',
      '#value' => $block_config['failure_url'] ?: $current_url,
    ];

    if (!empty($config->get('smaily_custom_fields'))) {
      $customfields = $this->getCustomFields();
      foreach ($customfields as $field => $label) {
        $form['category_' . $field] = [
          '#type' => 'checkbox',
          '#title' => $label,
        ];
      }
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
    $this->handleResponseMessage();
    return $form;
  }

  /**
   * Get an array of custom fields from config.
   *
   * @return array
   *   Array of custom fields in format [subscription1 => Main news]
   */
  public function getCustomFields() {
    $config = $this->config('smaily_for_drupal.settings');
    $options = [];
    foreach (explode("\n", $config->get('smaily_custom_fields', '')) as $line) {
      $values = explode("|", $line);
      $options[trim($values[0])] = trim($values[1]);
    }
    return $options;
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

  /**
   * Handle and display response messages from Smaily.
   */
  public function handleResponseMessage() {
    $request = \Drupal::request();
    $message = isset($request->query->all()['message']);
    $code = isset($request->query->all()['code']);

    if ($message && $code) {
      switch ((int) $request->query->all()['code']) {
        case 101:
          $this->messenger()->addMessage($this->t('You have been successfully subscribed.'), 'status');
          break;

        case 201:
          $this->messenger()->addMessage($this->t('Data must be posted with POST method.'), 'error');
          break;

        case 204:
          $this->messenger()->addMessage($this->t('Data does not contain a recognizable email address.'), 'warning');
          break;

        case 205:
          $this->messenger()->addMessage($this->t(
            'Could not add to subscriber list for an unknown reason. Probably something in Smaily.'), 'error');
          break;

        default:
          $this->messenger()->addMessage($this->t('Something went wrong, try again later.'), 'error');
          break;
      }
    }
  }

}
