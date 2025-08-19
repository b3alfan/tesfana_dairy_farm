<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MilkPriceSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'tesfana_dairy_farm_milk_price_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['tesfana_dairy_farm.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cfg = $this->config('tesfana_dairy_farm.settings');

    $form['milk_price_per_liter'] = [
      '#type' => 'number',
      '#title' => $this->t('Milk price per liter (Nakfa)'),
      '#description' => $this->t('Used to compute revenue KPIs (yesterday, total).'),
      '#default_value' => $cfg->get('milk_price_per_liter') ?? 18,
      '#min' => 0,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('tesfana_dairy_farm.settings')
      ->set('milk_price_per_liter', (float) $form_state->getValue('milk_price_per_liter'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Milk price saved.'));
  }
}
