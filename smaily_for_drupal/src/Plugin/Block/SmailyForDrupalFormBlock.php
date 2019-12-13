<?php

namespace Drupal\smaily_for_drupal\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a 'Smaily Newsletter form' block.
 *
 * @Block(
 *   id = "smaily_for_drupal_block",
 *   admin_label = @Translation("Smaily Newsletter"),
 *   category = @Translation("Smaily Newsletter")
 * )
 */
class SmailyForDrupalFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'smaily_autoresponder' => '',
      'smaily_button_title' => 'Subscribe',
      'smaily_success_url' => '',
      'smaily_failure_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * This method defines form elements for custom block configuration.
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['autoresponder'] = [
      '#type' => 'select',
      '#title' => $this->t('Autoresponder'),
      '#options' => $this->fetchAutoresponders(),
      '#default_value' => $this->configuration['smaily_autoresponder'],
      '#empty_option' => $this->t('Select autoresponder'),
    ];

    $form['button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscribe button title'),
      '#default_value' => $this->configuration['smaily_button_title'],
      '#required' => TRUE,
    ];

    $form['success_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Success Direct Page'),
      '#default_value' => $this->configuration['smaily_success_url'],
      '#attributes' => [
        'placeholder' => $this->t('Default Success Page'),
      ],
    ];

    $form['failure_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Failure Direct Page'),
      '#default_value' => $this->configuration['smaily_failure_url'],
      '#attributes' => [
        'placeholder' => $this->t('Default Failure Page'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['smaily_autoresponder']
      = $form_state->getValue('autoresponder');

    $this->configuration['smaily_button_title']
      = $form_state->getValue('button_title');

    $this->configuration['smaily_success_url']
      = $form_state->getValue('success_url');

    $this->configuration['smaily_failure_url']
      = $form_state->getValue('failure_url');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Pass autoresponder and title as arguments to subscription form.
    $config = [
      'autoresponder' => $this->configuration['smaily_autoresponder'],
      'button_title' => $this->configuration['smaily_button_title'],
      'success_url' => $this->configuration['smaily_success_url'] ?: $this->getSuccessPath(),
      'failure_url' => $this->configuration['smaily_failure_url'] ?: $this->getFailurePath(),
    ];
    $form = \Drupal::formBuilder()->getForm('Drupal\smaily_for_drupal\Form\SubscribeForm', $config);
    return $form;
  }

  /**
   * Get full URL to default success response page.
   *
   * @return string
   *   URL i.e. https://localhost/drupal/smaily_for_drupal/success
   */
  public function getSuccessPath() {
    return Url::fromUri('base:/smaily_for_drupal/success', ['absolute' => TRUE, 'https' => TRUE])->toString();
  }

  /**
   * Get full URL to default failure response page.
   *
   * @return string
   *   URL i.e. https://localhost/drupal/smaily_for_drupal/failure
   */
  public function getFailurePath() {
    return Url::fromUri('base:/smaily_for_drupal/failure', ['absolute' => TRUE, 'https' => TRUE])->toString();
  }

  /**
   * Ask for a list of autoresponders from Smaily.
   *
   * @return array
   *   Array of autoresponders with an id and title for Form select.
   */
  public function fetchAutoresponders() {
    $config = \Drupal::config('smaily_for_drupal.settings');
    $username = $config->get('smaily_api_credentials.username');
    $password = $config->get('smaily_api_credentials.password');
    $domain = $config->get('smaily_api_credentials.domain');

    $autoresponder_list = [];
    if (!empty($domain) && !empty($username) && !empty($password)) {
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
