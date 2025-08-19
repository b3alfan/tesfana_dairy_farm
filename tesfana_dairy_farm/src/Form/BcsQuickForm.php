<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BcsQuickForm extends FormBase {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'tesfana_bcs_quick_form';
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

    $form['bcs'] = [
      '#type' => 'number',
      '#title' => $this->t('BCS score (1–5)'),
      '#min' => 1,
      '#max' => 5,
      '#step' => '0.1',
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
      '#value' => $this->t('Save BCS'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $asset_id = (int) $form_state->getValue('asset');
    $date = (string) $form_state->getValue('date');
    $ts = strtotime($date . ' 09:00:00') ?: time();
    $bcs = (float) $form_state->getValue('bcs');
    $notes = (string) $form_state->getValue('notes');

    $log_storage = $this->etm->getStorage('log');
    $qty_storage = $this->etm->getStorage('quantity');

    $log = $log_storage->create([
      'type' => 'observation',
      'name' => $this->t('BCS @date', ['@date' => $date]),
      'timestamp' => $ts,
      'status' => 1,
      'asset' => [['target_id' => $asset_id]],
      'notes' => [['value' => $notes]],
    ]);
    $log->save();

    $q = $qty_storage->create([
      'type' => 'test',
      'label' => (string) $this->t('BCS'),
      'value' => $bcs,
      'units' => 'score',
    ]);
    $q->save();
    $log->get('quantity')->appendItem($q);
    $log->save();

    $this->messenger()->addStatus($this->t('BCS saved.'));
    $form_state->setRedirect('tesfana_dairy_farm.dashboard');
  }

}
