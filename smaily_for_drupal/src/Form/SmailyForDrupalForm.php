<?php

namespace Drupal\smaily_for_drupal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Smaily newsletter signup form.
 */
class SmailyForDrupalForm extends FormBase {

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
    $config = $this->config('smaily_for_drupal.adminsettings');

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
      '#value' => $config->get('smaily_button_title', ''),
      '#default_value' => $this->t('Subscribe to newsletter'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // #type => 'email' does validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('smaily_for_drupal.adminsettings');

    $username = $config->get('smaily_username');
    $password = $config->get('smaily_password');
    $domain = $config->get('smaily_domain');

    $query_data = [
      'remote' => 1,
      'email' => $form_state->getValue('email'),
    ];

    $name = $form_state->getValue('name');
    if (!empty($name)) {
      $query_data['name'] = $name;
    }

    $autoresponder = $config->get('smaily_autoresponder');
    if (!empty($autoresponder)) {
      $query_data['autoresponder'] = $autoresponder;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $domain . '.sendsmaily.net/api/opt-in/');
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
            $this->messenger()->addWarning($this->t('Could not add to subscriber list for an unknown reason. Probably something in Smaily.'));
            return;

          default:
            $this->messenger()->addError($this->t('Something went wrong.'));
            $this->logger('smaily')->error(nl2br(print_r((array_merge(['query_parameters' => $query_data], ['response' => (array) $response])), TRUE)));
            return;
        }
      }
    }
    $this->messenger()->addError($this->t('Something went wrong with connecting to Smaily.'));
    $this->logger('smaily')->error(nl2br(print_r((array_merge(['query_parameters' => $query_data], ['response' => (array) $response])), TRUE)));
  }

}
