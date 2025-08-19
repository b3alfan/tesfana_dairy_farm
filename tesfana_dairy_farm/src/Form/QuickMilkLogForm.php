<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuickMilkLogForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly EntityFieldManagerInterface $efm,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
    );
  }

  public function getFormId(): string {
    return 'tesfana_quick_milk_log_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'tesfana-quick-form';
    $form['#attached']['library'][] = 'tesfana_dairy_farm/quick_form_ui';

    $form['asset_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Cow'),
      '#options' => $this->cowOptions(),
      '#required' => TRUE,
    ];

    $form['am'] = [
      '#type' => 'number',
      '#title' => $this->t('Morning milk (L)'),
      '#step' => '0.01',
      '#min' => '0',
      '#default_value' => 0,
      '#attributes' => ['data-am' => '1'],
    ];

    $form['pm'] = [
      '#type' => 'number',
      '#title' => $this->t('Evening milk (L)'),
      '#step' => '0.01',
      '#min' => '0',
      '#default_value' => 0,
      '#attributes' => ['data-pm' => '1'],
    ];

    $total = (float) $form_state->getValue('am', 0) + (float) $form_state->getValue('pm', 0);
    $form['total_display'] = [
      '#type' => 'item',
      '#title' => $this->t('Total (L)'),
      '#markup' => '<strong data-total="1">' . number_format($total, 2, '.', '') . '</strong>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    // Primary: Save
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];
    // NEW: Save & add another
    $form['actions']['submit_add_another'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & add another'),
      '#button_type' => 'secondary',
      '#submit' => ['::submitAndAddAnother'],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['am', 'pm'] as $k) {
      $v = (float) $form_state->getValue($k);
      if ($v < 0) {
        $form_state->setErrorByName($k, $this->t('@k cannot be negative.', ['@k' => strtoupper($k)]));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $total = $this->saveMilkLogFromForm($form_state);
    $this->messenger()->addStatus($this->t('Saved milk log. Total: @t L', [
      '@t' => number_format($total, 2, '.', ''),
    ]));
    $form_state->setRedirectUrl(Url::fromRoute('tesfana_dairy_farm.milk_logs'));
  }

  /**
   * Save & add another: returns to the same form to log the next cow.
   */
  public function submitAndAddAnother(array &$form, FormStateInterface $form_state): void {
    $total = $this->saveMilkLogFromForm($form_state);
    $this->messenger()->addStatus($this->t('Saved (@t L). You can add the next cow now.', [
      '@t' => number_format($total, 2, '.', ''),
    ]));
    // Clear values for next entry.
    $form_state->setValues([
      'asset_id' => NULL,
      'am' => 0,
      'pm' => 0,
    ]);
    $form_state->setRedirect('tesfana_dairy_farm.milk_quick_add');
  }

  /* ---------- shared save logic ---------- */

  private function saveMilkLogFromForm(FormStateInterface $form_state): float {
    $asset_id = (int) $form_state->getValue('asset_id');
    $am = (float) $form_state->getValue('am');
    $pm = (float) $form_state->getValue('pm');
    $total = $am + $pm;

    // Resolve entity types and fields dynamically.
    $logType      = $this->resolveEntityType(['log', 'farm_log']);
    $quantityType = $this->resolveEntityType(['quantity', 'farm_quantity']);
    $quantityBundle = $this->chooseQuantityBundle($quantityType);

    $log_storage = $this->etm->getStorage($logType);
    $qty_storage = $this->etm->getStorage($quantityType);

    // Create harvest log.
    $log = $log_storage->create([
      'type' => 'harvest',
      'timestamp' => \Drupal::time()->getRequestTime(),
      'name' => $this->t('Milk harvest'),
      'asset' => [$asset_id],
    ]);
    $log->save();

    // Find the correct quantity reference field(s) on this log bundle.
    $qtyRefFields = $this->getQuantityRefFields($logType, $log, $quantityType);
    $refField = $qtyRefFields[0] ?? 'quantity';

    $add_q = function (string $name, float $value) use ($qty_storage, $quantityBundle, $log, $refField) {
      $q = $qty_storage->create([
        'type'  => $quantityBundle,
        'name'  => $name,   // AM/PM
        'value' => $value,
        'units' => 'l',
      ]);
      $q->save();
      if ($log->hasField($refField)) {
        $log->get($refField)->appendItem(['target_id' => $q->id()]);
      }
    };

    if ($am > 0) { $add_q('AM', $am); }
    if ($pm > 0) { $add_q('PM', $pm); }
    $log->save();

    return $total;
  }

  /* ---------- entity helpers ---------- */

  private function resolveEntityType(array $candidates): string {
    foreach ($candidates as $id) {
      if ($this->etm->hasDefinition($id)) return $id;
    }
    return $candidates[0];
  }

  private function chooseQuantityBundle(string $quantityType): string {
    $bundleInfo = \Drupal::service('entity_type.bundle.info');
    $bundles = $bundleInfo->getBundleInfo($quantityType) ?? [];
    if (isset($bundles['volume'])) return 'volume';
    $first = array_key_first($bundles);
    return $first ?: 'volume';
  }

  private function getQuantityRefFields(string $logType, $log, string $quantityType): array {
    $bundle = $log->bundle();
    $field_defs = $this->efm->getFieldDefinitions($logType, $bundle);

    $refs = [];
    foreach ($field_defs as $name => $def) {
      if ($def->getType() === 'entity_reference' || $def->getType() === 'entity_reference_revisions') {
        $settings = $def->getSettings();
        if (!empty($settings['target_type']) && $settings['target_type'] === $quantityType) {
          $refs[] = $name;
        }
      }
    }
    return $refs;
  }

  private function cowOptions(): array {
    $asset_storage = $this->etm->getStorage('asset');
    $ids = $asset_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'animal')
      ->range(0, 2000)
      ->execute();

    $opts = [];
    foreach ($asset_storage->loadMultiple($ids) as $asset) {
      $opts[$asset->id()] = $asset->label();
    }
    asort($opts, SORT_NATURAL | SORT_FLAG_CASE);
    return $opts;
  }

}
