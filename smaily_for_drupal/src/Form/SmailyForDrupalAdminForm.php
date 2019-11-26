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
class SmailyForDrupalAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'smaily_for_drupal.adminsettings',
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
    $config = $this->config('smaily_for_drupal.adminsettings');

    // Wrapper for editing and returning form data with ajax.
    $form['smaily_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'smaily-wrapper',
      ],
    ];

    $form['smaily_container']['credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API credentials'),
    ];

    $form['smaily_container']['credentials']['message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="smaily_message"></div>',
    ];

    $form['smaily_container']['credentials']['smaily_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('smaily_domain', ''),
      '#attributes' => [
        'placeholder' => $this->t('For example "demo" from https://demo.sendsmaily.net/'),
      ],
      '#required' => TRUE,
    ];

    $form['smaily_container']['credentials']['smaily_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('smaily_username', ''),
      '#required' => TRUE,
    ];

    $form['smaily_container']['credentials']['smaily_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('smaily_password', ''),
      '#required' => TRUE,

    ];

    $form['smaily_container']['credentials']['validate_credentials'] = [
      '#type' => 'button',
      '#value' => $this->t('Validate credentials'),
      '#ajax' => [
        'event' => 'click',
        'callback' => '::validateCredentials',
        'wrapper' => 'smaily-wrapper',
      ],
      // Limit errors here, validateCredentials also highlights errors in autoresponder/title.
      '#limit_validation_errors' => [],
    ];

    $username = $config->get('smaily_username');
    $password = $config->get('smaily_password');
    $domain = $config->get('smaily_domain');

    // Fetch and update autoresponders every time form is built.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $domain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    $autoresponders = json_decode(curl_exec($ch), TRUE);
    curl_close($ch);

    $autoresponder_list = [];
    if (!empty($autoresponders)) {
      foreach ($autoresponders as $autoresponder) {
        if (!empty($autoresponder['id']) && !empty($autoresponder['title'])) {
          $autoresponder_list[$autoresponder['id']] = trim($autoresponder['title']);
        }
      }
    }

    $form['smaily_container']['smaily_autoresponder'] = [
      '#type' => 'select',
      '#title' => $this->t('Autoresponder'),
      '#options' => $autoresponder_list,
      '#default_value' => $config->get('smaily_autoresponder', 1),
      '#empty_option' => $this->t('Select autoresponder'),
    ];

    $form['smaily_container']['smaily_button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscribe button title'),
      '#default_value' => $config->get('smaily_button_title', 'Subscribe to newsletter'),
      '#required' => TRUE,
    ];

    $form['smaily_container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation handled by #required and in ajax function.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Normalize subdomain.
    // First, try to parse as full URL.
    // If that fails, try to parse as subdomain.sendsmaily.net, and, then clean up subdomain and pass as is.
    $subdomain = trim($form_state->getValue('smaily_domain'));
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
    // Save form to config.
    $this->config('smaily_for_drupal.adminsettings')
      ->set('smaily_domain', $subdomain)
      ->set('smaily_username', trim($form_state->getValue('smaily_username')))
      ->set('smaily_password', trim($form_state->getValue('smaily_password')))
      ->set('smaily_autoresponder', trim($form_state->getValue('smaily_autoresponder')))
      ->set('smaily_button_title', trim($form_state->getValue('smaily_button_title')))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function validateCredentials(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    $username = trim($form_state->getValue('smaily_username'));
    $password = trim($form_state->getValue('smaily_password'));
    $subdomain = trim($form_state->getValue('smaily_domain'));
    // Drupal's validation is only done with submit.
    if (empty($username) || empty($password) || empty($subdomain)) {
      $ajax_response->addCommand(
        new HtmlCommand(
          '.smaily_message',
          '<div class="messages messages--error">' . 'Please fill out all fields.' . '</div>')
      );
      return $ajax_response;
    }

    // Normalize subdomain.
    // First, try to parse as full URL.
    // If that fails, try to parse as subdomain.sendsmaily.net, and, then clean up subdomain and pass as is.
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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $subdomain . '.sendsmaily.net/api/workflows.php?trigger_type=form_submitted');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    $output = curl_exec($ch);
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

    $autoresponders = json_decode($output, TRUE);
    $autoresponder_list = [];
    if (!empty($autoresponders)) {
      foreach ($autoresponders as $autoresponder) {
        if (!empty($autoresponder['id']) && !empty($autoresponder['title'])) {
          $autoresponder_list[$autoresponder['id']] = trim($autoresponder['title']);
        }
      }
    }

    $form['smaily_container']['smaily_autoresponder']['#options'] = $autoresponder_list;
    // Refreshes $form to display autoresponders.
    $ajax_response->addCommand(new ReplaceCommand(NULL, $form));

    $this->config('smaily_for_drupal.adminsettings')
      ->set('smaily_domain', $subdomain)
      ->set('smaily_username', $username)
      ->set('smaily_password', $password)
      ->save();
    $ajax_response->addCommand(
      new HtmlCommand(
        '.smaily_message',
        '<div class="messages messages--status">' . 'Credentials valid' . '</div>')
    );
    return $ajax_response;

  }

}
