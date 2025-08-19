<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'tesfana_dairy_farm_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['tesfana_dairy_farm.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cfg = $this->config('tesfana_dairy_farm.settings');

    $form['milk_price_per_liter'] = [
      '#type' => 'number',
      '#title' => $this->t('Milk price per liter (Nakfa)'),
      '#default_value' => $cfg->get('milk_price_per_liter') ?? 18,
      '#min' => 0,
      '#step' => 0.01,
      '#required' => TRUE,
      '#description' => $this->t('Used to compute revenue KPIs on the dashboard.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('tesfana_dairy_farm.settings')
      ->set('milk_price_per_liter', (float) $form_state->getValue('milk_price_per_liter'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Settings saved.'));
  }
}
