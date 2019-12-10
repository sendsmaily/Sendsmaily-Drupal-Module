<?php

namespace Drupal\smaily_for_drupal\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

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
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Pass autoresponder and title as arguments to subscription form.
    $config = [
      'autoresponder' => $this->configuration['smaily_autoresponder'],
      'button_title' => $this->configuration['smaily_button_title'],
    ];
    $form = \Drupal::formBuilder()->getForm('Drupal\smaily_for_drupal\Form\SubscribeForm', $config);

    return $form;
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
