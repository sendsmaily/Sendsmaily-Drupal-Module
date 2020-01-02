<?php

namespace Drupal\smaily_for_drupal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use GuzzleHttp\Exception\ClientException;

/**
 * Smaily module admin configuraiton form.
 */
class AdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'smaily_for_drupal.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smaily_for_drupal_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('smaily_for_drupal.settings');

    // Wrapper for editing and returning form data with ajax.
    $form['smaily_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'smaily-wrapper',
      ],
    ];

    $form['smaily_container']['smaily_api_credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API credentials'),
    ];

    $form['smaily_container']['smaily_api_credentials']['message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="smaily_message"></div>',
    ];

    $form['smaily_container']['smaily_api_credentials']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('smaily_api_credentials.domain', ''),
      '#attributes' => [
        'placeholder' => $this->t('For example "demo" from https://demo.sendsmaily.net/'),
      ],
      '#required' => TRUE,
    ];

    $form['smaily_container']['smaily_api_credentials']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('smaily_api_credentials.username', ''),
      '#required' => TRUE,
    ];

    $form['smaily_container']['smaily_api_credentials']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('smaily_api_credentials.password', ''),
      '#required' => TRUE,
    ];

    $form['smaily_container']['smaily_api_credentials']['button_validate_credentials'] = [
      '#type' => 'button',
      '#value' => $this->t('Validate credentials'),
      '#ajax' => [
        'event' => 'click',
        'callback' => '::validateCredentials',
        'wrapper' => 'smaily-wrapper',
      ],
    ];

    $form['smaily_container']['smaily_custom_fields'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('List of key-value pairs on separated rows,
        that will be displayed on the registration form as checkboxes.
        ie. subscription1|Main news'),
      '#title' => $this->t('Custom fields'),
    ];

    $form['smaily_container']['smaily_custom_fields']['customfields'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('smaily_custom_fields', ''),
    ];

    $form['smaily_container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $lines = $form_state->getValue('customfields');
    if (!empty($lines)) {
      foreach (explode("\n", $lines) as $line) {
        // Match NOT "|" unlimited times until hits "|" then match NOT "|" unlimited times until end.
        if (!preg_match('/^[^|]*\|[^|]*$/', $line)) {
          $form_state->setErrorByName('customfields', $this->t('Incorrect formatting for at least one custom field line.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subdomain = trim($form_state->getValue('domain'));
    $this->config('smaily_for_drupal.settings')
      ->set('smaily_api_credentials.domain', $this->normalizeSubdomain($subdomain))
      ->set('smaily_api_credentials.username', trim($form_state->getValue('username')))
      ->set('smaily_api_credentials.password', trim($form_state->getValue('password')))
      ->set('smaily_custom_fields', trim($form_state->getValue('customfields')))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function validateCredentials(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    $username = trim($form_state->getValue('username'));
    $password = trim($form_state->getValue('password'));
    $subdomain = trim($form_state->getValue('domain'));
    // Drupal's validation is not done with Ajax.
    if (empty($username) || empty($password) || empty($subdomain)) {
      $ajax_response->addCommand(
        new HtmlCommand(
          '.smaily_message',
          '<div class="messages messages--error">' . 'Please fill out all fields.' . '</div>')
      );
      return $ajax_response;
    }

    $subdomain = $this->normalizeSubdomain($subdomain);
    $url = 'https://' . $subdomain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted';
    
    try {
      $client = \Drupal::httpClient();
      $client->request('GET', $url, [
        'auth' => [$username, $password],
      ]);

      $this->config('smaily_for_drupal.settings')
        ->set('smaily_api_credentials.domain', $subdomain)
        ->set('smaily_api_credentials.username', $username)
        ->set('smaily_api_credentials.password', $password)
        ->save();
      $ajax_response->addCommand(
        new HtmlCommand(
          '.smaily_message',
          '<div class="messages messages--status">' . 'Credentials valid' . '</div>')
      );
      return $ajax_response;
    }
    catch (ClientException $e) {
      switch ($e->getResponse()->getStatusCode()) {
        case 200:
          // OK code, continue validateCredentials.
          break;

        case 401:
          $ajax_response->addCommand(
            new HtmlCommand(
              '.smaily_message',
              '<div class="messages messages--error">' . 'Credentials invalid.' . '</div>')
          );
          return $ajax_response;

        case 404:
          $ajax_response->addCommand(
            new HtmlCommand(
              '.smaily_message',
              '<div class="messages messages--error">' . 'Subdomain error' . '</div>')
          );
          return $ajax_response;

        default:
          $ajax_response->addCommand(
            new HtmlCommand(
              '.smaily_message',
              '<div class="messages messages--error">' . 'Something went wrong.' . '</div>')
          );
          return $ajax_response;
      }

      $credentials = [
        '@username' => $username,
        '@password' => $password,
        '@subdomain' => $subdomain,
      ];
      \Drupal::logger('smaily_for_drupal')->error(
        'Failed trying to validate credentials to Smaily subdomain: @subdomain with credentials: @username & @password',
        $credentials
      );
      $ajax_response->addCommand(
        new HtmlCommand(
          '.smaily_message',
          '<div class="messages messages--error">' . 'Something went wrong.' . '</div>')
      );
      return $ajax_response;
    }
  }

  /**
   * Normalize subdomain into the bare necessity.
   *
   * @param string $subdomain
   *   Messy subdomain, http://demo.sendsmaily.net for example.
   *
   * @return string
   *   demo from demo.sendsmaily.net
   */
  public function normalizeSubdomain($subdomain) {
    // First, try to parse as full URL.
    // If that fails, try to parse as subdomain.sendsmaily.net.
    // Last resort clean up subdomain and pass as is.
    if (filter_var($subdomain, FILTER_VALIDATE_URL)) {
      $url = parse_url($subdomain);
      $parts = explode('.', $url['host']);
      $subdomain = count($parts) >= 3 ? $parts[0] : '';
    }
    elseif (preg_match('/^[^\.]+\.sendsmaily\.net$/', $subdomain)) {
      $parts = explode('.', $subdomain);
      $subdomain = $parts[0];
    }
    $subdomain = preg_replace('/[^a-zA-Z0-9]+/', '', $subdomain);
    return $subdomain;
  }

}
