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
      '#type' => 'select',
      '#title' => $this->t('Success Direct Page'),
      '#default_value' => $this->configuration['smaily_success_url'],
      '#empty_option' => $this->t('Redirect back'),
      '#options' => $this->getSuccessPages(),
    ];

    $form['failure_url'] = [
      '#type' => 'select',
      '#title' => $this->t('Failure Direct Page'),
      '#default_value' => $this->configuration['smaily_failure_url'],
      '#empty_option' => $this->t('Redirect back'),
      '#options' => $this->getFailurePages(),
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
      'success_url' => $this->configuration['smaily_success_url'],
      'failure_url' => $this->configuration['smaily_failure_url'],
    ];
    $form = \Drupal::formBuilder()->getForm('Drupal\smaily_for_drupal\Form\SubscribeForm', $config);
    return $form;
  }

  /**
   * Fetch all success pages of type smaily_response_page (Smaily Response Page)
   *
   * @return array
   *   Array of response pages in format [url => page title]
   */
  public function getSuccessPages() {
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $nids = $query->condition('type', 'smaily_response_page')
      ->condition('status', '1')
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    $pages = [];
    foreach ($nodes as $node) {
      if ($node->field_success_page->value == 1) {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE]);
        $pages[$url->toString()] = $node->getTitle();
      }
    }
    return $pages;
  }

  /**
   * Fetch all failure pages of type smaily_response_page (Smaily Response Page)
   *
   * @return array
   *   Array of response pages in format [url => page title]
   */
  public function getFailurePages() {
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $nids = $query->condition('type', 'smaily_response_page')
      ->condition('status', '1')
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    $pages = [];
    foreach ($nodes as $node) {
      if ($node->field_success_page->value == 0) {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE]);
        $pages[$url->toString()] = $node->getTitle();
      }
    }
    return $pages;
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
