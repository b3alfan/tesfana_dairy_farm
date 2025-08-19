<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for MilkLog add/edit.
 */
final class MilkLogForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Light UX tweaks.
    if (isset($form['date'])) {
      $form['date']['widget'][0]['value']['#title'] = $this->t('Date');
    }
    if (isset($form['am_yield'])) {
      $form['am_yield']['widget'][0]['value']['#placeholder'] = '0.00';
    }
    if (isset($form['pm_yield'])) {
      $form['pm_yield']['widget'][0]['value']['#placeholder'] = '0.00';
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $am = (float) $form_state->getValue(['am_yield', 0, 'value']);
    $pm = (float) $form_state->getValue(['pm_yield', 0, 'value']);
    if ($am < 0 || $pm < 0) {
      $form_state->setErrorByName('am_yield', $this->t('Yields must be non-negative.'));
    }
  }

  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus(
      $status === SAVED_NEW ? $this->t('Milk log created.') : $this->t('Milk log updated.')
    );
    $form_state->setRedirect('entity.milk_log.collection');
    return $status;
  }

}
