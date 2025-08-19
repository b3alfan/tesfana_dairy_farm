<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class TesfanaSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['tesfana_dairy_farm.settings'];
  }

  public function getFormId(): string {
    return 'tesfana_dairy_farm_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cfg = $this->config('tesfana_dairy_farm.settings');

    $form['pricing'] = [
      '#type' => 'details',
      '#title' => $this->t('Milk pricing'),
      '#open' => TRUE,
    ];
    $form['pricing']['milk_price_per_liter'] = [
      '#type' => 'number',
      '#title' => $this->t('Milk price per liter'),
      '#description' => $this->t('Used for revenue calculations in cow profile & reports.'),
      '#step' => 0.01,
      '#default_value' => $cfg->get('milk_price_per_liter') ?? 1.0,
      '#min' => 0,
    ];
    $form['pricing']['currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
      '#description' => $this->t('Three-letter code (e.g. ETB, USD).'),
      '#default_value' => $cfg->get('currency') ?? 'ETB',
      '#size' => 8,
      '#maxlength' => 12,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('tesfana_dairy_farm.settings')
      ->set('milk_price_per_liter', (float) $form_state->getValue('milk_price_per_liter'))
      ->set('currency', (string) $form_state->getValue('currency'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Tesfana settings saved.'));
  }

}
