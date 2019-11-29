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

    $form['subscribe'] = [
      '#type' => 'submit',
      '#value' => $block_config['button_title'],
      '#default_value' => $this->t('Subscribe to newsletter'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('smaily_for_drupal.settings');
    $block_config = $form_state->getBuildInfo()['args'][0];

    $username = $config->get('smaily_api_credentials.username');
    $password = $config->get('smaily_api_credentials.password');
    $domain = $config->get('smaily_api_credentials.domain');

    $query_data = [
      'autoresponder' => $block_config['autoresponder'],
      'addresses' => [
        [
          'email' => $form_state->getValue('email'),
          'name' => $form_state->getValue('name') ?: '',
        ],
      ],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $domain . '.sendsmaily.net/api/autoresponder.php');
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    $response = json_decode(curl_exec($ch), TRUE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && !empty($response)) {
      if (array_key_exists('code', $response)) {
        switch ((int) $response['code']) {
          case 101:
            $this->messenger()->addMessage($this->t('Successfully subscribed to mailing list.'));
            return;

          case 201:
            $this->messenger()->addWarning($this->t('Data must be posted with POST method.'));
            return;

          case 204:
            $this->messenger()->addWarning($this->t('Input does not contain a valid email address.'));
            return;

          case 205:
            $this->messenger()->addWarning($this->t(
              'Could not add to subscriber list for an unknown reason. Probably something in Smaily.'
            ));
            return;

          default:
            $this->messenger()->addError($this->t('Something went wrong.'));
            $this->logger('smaily')->error(nl2br(print_r(
              array_merge(['query_parameters' => $query_data], ['response' => (array) $response]), TRUE
            )));
            return;
        }
      }
    }
    $this->messenger()->addError($this->t('Something went wrong with connecting to Smaily.'));
    $this->logger('smaily')->error(nl2br(print_r(
      array_merge(['query_parameters' => $query_data], ['response' => (array) $response]), TRUE
    )));
  }

}
