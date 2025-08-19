<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\Response;

class MilkExportController extends ControllerBase {

  /** Utility: convert a fraction/number value field to float. */
  private function valueToFloat($field): float {
    if ($field->isEmpty()) return 0.0;
    try {
      $raw = $field->first()->getValue();
      if (is_array($raw) && isset($raw['numerator'])) {
        $num = (float) ($raw['numerator'] ?? 0);
        $den = (float) ($raw['denominator'] ?? 1);
        return $den != 0 ? $num / $den : 0.0;
      }
    } catch (\Throwable) {}
    return (float) ($field->value ?? 0);
  }

  /** Utility: liters from a quantity entity (supports fraction + entity-ref unit). */
  private function litersFromQuantity($q): float {
    $val = 0.0;
    if ($q->hasField('value')) $val = $this->valueToFloat($q->get('value'));
    // Unit handling (optional; default to liters if unknown).
    $unitId = '';
    if ($q->hasField('units') && !$q->get('units')->isEmpty()) {
      try { if ($e = $q->get('units')->entity) $unitId = strtolower($e->id()); } catch (\Throwable) {}
    }
    if ($unitId === 'ml' || $unitId === 'milliliter' || $unitId === 'millilitre') return $val / 1000.0;
    return $val; // treat as liters by default
  }

  /** Utility: sum liters for one log. */
  private function litersFromLog($log, string $logType): float {
    $sum = 0.0;
    $refs = $this->getQuantityRefFields($logType, $log);
    foreach ($refs as $f) {
      if ($log->hasField($f) && !$log->get($f)->isEmpty()) {
        foreach ($log->get($f)->referencedEntities() as $q) {
          $sum += $this->litersFromQuantity($q);
        }
      }
    }
    return round($sum, 3);
  }

  private function getQuantityRefFields(string $logType, $log): array {
    $bundle = $log->bundle();
    $efm = \Drupal::service('entity_field.manager');
    $defs = $efm->getFieldDefinitions($logType, $bundle);
    $quantityType = \Drupal::entityTypeManager()->hasDefinition('quantity') ? 'quantity' : 'farm_quantity';
    $refs = [];
    foreach ($defs as $name => $def) {
      if (in_array($def->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
        $settings = $def->getSettings();
        if (!empty($settings['target_type']) && $settings['target_type'] === $quantityType) {
          $refs[] = $name;
        }
      }
    }
    return $refs ?: ['quantity'];
  }

  /** Simple CSV for general use. */
  public function exportMilkCsv(): Response {
    $logType = \Drupal::entityTypeManager()->hasDefinition('log') ? 'log' : 'farm_log';
    $storage = \Drupal::entityTypeManager()->getStorage($logType);

    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'harvest')->sort('timestamp', 'DESC')->execute();
    $logs = $storage->loadMultiple($ids);

    $config   = $this->config('tesfana_dairy_farm.settings');
    $price    = (float) ($config->get('milk_price_per_liter') ?? 1.0);

    $rows = [];
    $rows[] = ['Date','Cow(s)','Liters','Unit','PricePerLiter','Revenue','Log ID','Label'];

    foreach ($logs as $log) {
      $date = '';
      if ($log->hasField('timestamp') && !$log->get('timestamp')->isEmpty()) {
        $ts = (int) $log->get('timestamp')->value;
        $date = date('Y-m-d', $ts);
      }

      // Cow labels (asset refs)
      $cows = [];
      if ($log->hasField('asset') && !$log->get('asset')->isEmpty()) {
        foreach ($log->get('asset')->referencedEntities() as $a) {
          $cows[] = $a->label();
        }
      }
      $cowsStr = implode(' | ', $cows);

      $liters = $this->litersFromLog($log, $logType);
      $revenue = round($liters * $price, 2);

      $rows[] = [$date, $cowsStr, $liters, 'L', $price, $revenue, $log->id(), $log->label()];
    }

    $csv = $this->arrayToCsv($rows);
    return $this->csvResponse($csv, 'milk_logs.csv');
  }

  /**
   * QuickBooks-ish CSV.
   * Columns: Date, Customer, Product/Service, Qty, Rate, Amount, Description
   * Adjust "Customer" and "Product/Service" as needed in QuickBooks import.
   */
  public function exportQuickBooksCsv(): Response {
    $logType = \Drupal::entityTypeManager()->hasDefinition('log') ? 'log' : 'farm_log';
    $storage = \Drupal::entityTypeManager()->getStorage($logType);

    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'harvest')->sort('timestamp', 'DESC')->execute();
    $logs = $storage->loadMultiple($ids);

    $config   = $this->config('tesfana_dairy_farm.settings');
    $price    = (float) ($config->get('milk_price_per_liter') ?? 1.0);

    $rows = [];
    $rows[] = ['Date','Customer','Product/Service','Qty','Rate','Amount','Description'];

    foreach ($logs as $log) {
      $date = '';
      if ($log->hasField('timestamp') && !$log->get('timestamp')->isEmpty()) {
        $ts = (int) $log->get('timestamp')->value;
        $date = date('Y-m-d', $ts);
      }

      $liters = $this->litersFromLog($log, $logType);
      $amount = round($liters * $price, 2);

      // Choose a simple mapping; tweak names to your QuickBooks list
      $customer = 'Milk Sales';          // or a real customer/project
      $item     = 'Milk (L)';            // Product/Service name in QB
      $desc     = $log->label() ?: 'Milk harvest';

      $rows[] = [$date, $customer, $item, $liters, $price, $amount, $desc];
    }

    $csv = $this->arrayToCsv($rows);
    return $this->csvResponse($csv, 'milk_logs_quickbooks.csv');
  }

  private function arrayToCsv(array $rows): string {
    $f = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
      fputcsv($f, $row);
    }
    rewind($f);
    return stream_get_contents($f);
  }

  private function csvResponse(string $csv, string $filename): Response {
    return new Response(
      $csv,
      200,
      [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
      ]
    );
  }
}
