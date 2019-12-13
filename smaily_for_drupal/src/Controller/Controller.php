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
    if ($response_bool) {
      switch ((int) $_GET['code']) {
        case 201:
          $this->messenger()->addWarning($this->t('Data must be posted with POST method.'));
          break;

        case 204:
          $this->messenger()->addWarning($this->t('Input does not contain a valid email address.'));
          break;

        case 205:
          $this->messenger()->addWarning($this->t(
            'Could not add to subscriber list for an unknown reason. Probably something in Smaily.'
          ));
          break;

        default:
          $this->messenger()->addError($this->t('Something went wrong. Try again later'));
          break;
      }
    }
    return [
      '#markup' => '<p>' . $this->t('You have not been added to subscribers list.') . '</p>',
    ];
  }

}
