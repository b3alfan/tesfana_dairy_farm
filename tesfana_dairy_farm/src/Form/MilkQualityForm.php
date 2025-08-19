<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MilkQualityForm extends FormBase {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'tesfana_milk_quality_form';
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
      '#title' => $this->t('Sampling date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['fat'] = [
      '#type' => 'number',
      '#title' => $this->t('Fat %'),
      '#min' => 0,
      '#step' => '0.01',
      '#required' => TRUE,
    ];
    $form['protein'] = [
      '#type' => 'number',
      '#title' => $this->t('Protein %'),
      '#min' => 0,
      '#step' => '0.01',
      '#required' => TRUE,
    ];
    $form['scc'] = [
      '#type' => 'number',
      '#title' => $this->t('Somatic Cell Count (cells/mL)'),
      '#min' => 0,
      '#step' => '1',
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 2,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save quality'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $asset_id = (int) $form_state->getValue('asset');
    $date = (string) $form_state->getValue('date');
    $ts = strtotime($date . ' 10:00:00') ?: time();
    $fat = (float) $form_state->getValue('fat');
    $protein = (float) $form_state->getValue('protein');
    $scc = (float) $form_state->getValue('scc');
    $notes = (string) $form_state->getValue('notes');

    $log_storage = $this->etm->getStorage('log');
    $qty_storage = $this->etm->getStorage('quantity');

    $log = $log_storage->create([
      'type' => 'lab_test',
      'name' => $this->t('Milk quality @date', ['@date' => $date]),
      'timestamp' => $ts,
      'status' => 1,
      'asset' => [['target_id' => $asset_id]],
      'notes' => [['value' => $notes]],
    ]);
    $log->save();

    $q1 = $qty_storage->create(['type' => 'test', 'label' => (string) $this->t('Fat %'),     'value' => $fat,     'units' => '%']);
    $q2 = $qty_storage->create(['type' => 'test', 'label' => (string) $this->t('Protein %'), 'value' => $protein, 'units' => '%']);
    $q3 = $qty_storage->create(['type' => 'test', 'label' => (string) $this->t('SCC'),       'value' => $scc,     'units' => 'cells_ml']);
    foreach ([$q1, $q2, $q3] as $q) { $q->save(); $log->get('quantity')->appendItem($q); }
    $log->save();

    $this->messenger()->addStatus($this->t('Milk quality saved.'));
    $form_state->setRedirect('tesfana_dairy_farm.dashboard');
  }

}
