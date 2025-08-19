<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MilkQuickForm extends FormBase {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'tesfana_milk_quick_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $asset_default = NULL;
    $asset_id = (int) (\Drupal::request()->query->get('asset') ?? 0);
    if ($asset_id > 0) {
      $asset_default = $this->etm->getStorage('asset')->load($asset_id);
    }

    $form['asset'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Cow'),
      '#target_type' => 'asset',
      '#selection_settings' => ['target_bundles' => ['animal']],
      '#required' => TRUE,
      '#default_value' => $asset_default,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['am'] = [
      '#type' => 'number',
      '#title' => $this->t('Morning (L)'),
      '#min' => 0,
      '#step' => '0.01',
      '#default_value' => '',
    ];

    $form['pm'] = [
      '#type' => 'number',
      '#title' => $this->t('Afternoon (L)'),
      '#min' => 0,
      '#step' => '0.01',
      '#default_value' => '',
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 2,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save milk'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $am = (float) $form_state->getValue('am');
    $pm = (float) $form_state->getValue('pm');
    if ($am < 0 || $pm < 0) {
      $form_state->setErrorByName('am', $this->t('Values must be zero or positive.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $asset_id = (int) $form_state->getValue('asset');
    $date = (string) $form_state->getValue('date');
    $ts = strtotime($date . ' 08:00:00') ?: time();
    $am = (float) $form_state->getValue('am');
    $pm = (float) $form_state->getValue('pm');
    $notes = (string) $form_state->getValue('notes');

    $log_storage = $this->etm->getStorage('log');
    $qty_storage = $this->etm->getStorage('quantity');

    // Save to farmOS 'harvest' bundle (we treat as Milk).
    $log = $log_storage->create([
      'type' => 'harvest',
      'name' => $this->t('Milk yield @date', ['@date' => $date]),
      'timestamp' => $ts,
      'status' => 1,
      'asset' => [['target_id' => $asset_id]],
      'notes' => [['value' => $notes]],
    ]);
    $log->save();

    if ($am > 0) {
      $qam = $qty_storage->create([
        'type' => 'standard',
        'label' => (string) $this->t('AM'),
        'value' => $am,
        'units' => 'l',
      ]);
      $qam->save();
      $log->get('quantity')->appendItem($qam);
    }

    if ($pm > 0) {
      $qpm = $qty_storage->create([
        'type' => 'standard',
        'label' => (string) $this->t('PM'),
        'value' => $pm,
        'units' => 'l',
      ]);
      $qpm->save();
      $log->get('quantity')->appendItem($qpm);
    }

    $log->save();

    $this->messenger()->addStatus($this->t('Milk recorded.'));
    $form_state->setRedirect('tesfana_dairy_farm.dashboard');
  }

}
