<?php

namespace Drupal\smaily_for_drupal\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
  public function build() {
    // Include the already created newsletter signup form in a block.
    $form = \Drupal::formBuilder()->getForm('Drupal\smaily_for_drupal\Form\SubscribeForm');

    return $form;
  }

}
