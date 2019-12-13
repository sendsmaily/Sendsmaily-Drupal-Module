<?php

namespace Drupal\smaily_for_drupal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Html;

/**
 * Controller routines for redirect routes.
 */
class Controller extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'smaily_for_drupal';
  }

  /**
   * Constructs a simple success page.
   */
  public function success() {
    return [
      '#markup' => '<p>' . $this->t('You have been added to subscribers list.') . '</p>',
    ];
  }

  /**
   * Constructs a simple failure page.
   */
  public function failure() {
    $response_bool = isset($_GET['code']) && isset($_GET['message']);
    if ($response_bool && $_GET['code'] !== 101) {
      $this->messenger()->addStatus(Html::escape($_GET['message']));
    }
    return [
      '#markup' => '<p>' . $this->t('You have not been added to subscribers list.') . '</p>',
    ];
  }

}
