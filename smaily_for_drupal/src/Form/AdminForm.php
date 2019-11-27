<?php

namespace Drupal\smaily_for_drupal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;

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
      // Limit errors here, validateCredentials will highlight errors in autoresponder/title.
      '#limit_validation_errors' => [],
    ];

    $form['smaily_container']['autoresponder'] = [
      '#type' => 'select',
      '#title' => $this->t('Autoresponder'),
      '#options' => $this->fetchAutoresponders(),
      '#default_value' => $config->get('smaily_autoresponder', 1),
      '#empty_option' => $this->t('Select autoresponder'),
      // First time users validating and saving will get illegal autoresponder choice.
      // Validated needs to be set to TRUE if changing select options with Ajax.
      '#validated' => TRUE,
    ];

    $form['smaily_container']['smaily_button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscribe button title'),
      '#default_value' => $config->get('smaily_button_title', 'Subscribe to newsletter'),
      '#required' => TRUE,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subdomain = trim($form_state->getValue('domain'));
    $this->config('smaily_for_drupal.settings')
      ->set('smaily_api_credentials.domain', $this->normalizeSubdomain($subdomain))
      ->set('smaily_api_credentials.username', trim($form_state->getValue('username')))
      ->set('smaily_api_credentials.password', trim($form_state->getValue('password')))
      ->set('smaily_autoresponder', trim($form_state->getValue('autoresponder')))
      ->set('smaily_button_title', trim($form_state->getValue('smaily_button_title')))
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
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,
      'https://' . $subdomain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted'
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    switch ((int) $http_code) {
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

    $form['smaily_container']['autoresponder']['#options'] = $this->fetchAutoresponders();
    // Refreshes $form to display autoresponders.
    $ajax_response->addCommand(new ReplaceCommand(NULL, $form));
    return $ajax_response;
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

  /**
   * Ask for a list of autoresponders from Smaily.
   *
   * @return array
   *   Array of autoresponders with an id and title for Form select.
   */
  public function fetchAutoresponders() {
    $config = $this->config('smaily_for_drupal.settings');
    $username = $config->get('smaily_api_credentials.username');
    $password = $config->get('smaily_api_credentials.password');
    $domain = $config->get('smaily_api_credentials.domain');

    $autoresponder_list = [];
    if (!empty($domain)) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL,
        'https://' . $domain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted'
      );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
      $autoresponders = json_decode(curl_exec($ch), TRUE);
      curl_close($ch);

      if (!empty($autoresponders)) {
        foreach ($autoresponders as $autoresponder) {
          if (!empty($autoresponder['id']) && !empty($autoresponder['title'])) {
            $autoresponder_list[$autoresponder['id']] = trim($autoresponder['title']);
          }
        }
      }
    }
    return $autoresponder_list;
  }

}
