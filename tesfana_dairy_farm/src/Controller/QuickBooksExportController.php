<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Exports milk logs aggregated for QuickBooks import (CSV).
 */
class QuickBooksExportController extends ControllerBase {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function csv(): Response {
    $request = $this->getRequest();
    $from = $request->query->get('from') ?: '';
    $to   = $request->query->get('to') ?: '';

    $storage = $this->etm->getStorage('log');
    $query = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'harvest');

    $from_ts = $this->parseDateToTs($from, '00:00');
    $to_ts   = $this->parseDateToTs($to, '23:59');
    if ($from_ts) { $query->condition('timestamp', $from_ts, '>='); }
    if ($to_ts)   { $query->condition('timestamp', $to_ts, '<='); }

    $query->sort('timestamp', 'ASC')->range(0, 10000);
    $ids = $query->execute();

    $rows = [];
    $header = ['TxnDate','Customer','Product/Service','Qty','Rate','Amount','Memo'];

    // Load price per liter from settings (default 1).
    $config = $this->config('tesfana_dairy_farm.settings');
    $rate = (float) ($config->get('milk_price_per_liter') ?? 1.0);

    foreach ($storage->loadMultiple($ids) as $log) {
      $ts = (int) $log->get('timestamp')->value;
      $date = (new \DateTimeImmutable("@$ts"))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d');

      $cow = '';
      if ($log->hasField('asset')) {
        $assets = $log->get('asset')->referencedEntities();
        if (!empty($assets)) {
          $cow = $assets[0]->label();
        }
      }

      // Total liters for this log (AM+PM combined).
      $liters = 0.0;
      if ($log->hasField('quantity')) {
        foreach ($log->get('quantity')->referencedEntities() as $q) {
          $liters += $this->toLiters((float) ($q->get('value')->value ?? 0), (string) ($q->get('units')->value ?? ''));
        }
      }

      if ($liters <= 0) continue;

      $amount = $rate * $liters;

      $rows[] = [
        $date,
        'Dairy Customers',         // QuickBooks Customer name (you can change or make per-cow).
        'Milk (Liters)',           // Product/Service in QB.
        number_format($liters, 2, '.', ''),
        number_format($rate, 2, '.', ''),
        number_format($amount, 2, '.', ''),
        $cow ? "Milk sale from $cow" : 'Milk sale',
      ];
    }

    $csv = $this->toCsv(array_merge([$header], $rows));
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="quickbooks_milk_sales.csv"');
    return $response;
  }

  private function parseDateToTs(?string $d, string $hm): ?int {
    if (!$d) return NULL;
    try {
      $dt = new DrupalDateTime("$d $hm");
      return $dt->getTimestamp();
    } catch (\Throwable) {
      return NULL;
    }
  }

  private function toLiters(float $value, string $units): float {
    $u = strtolower($units);
    if ($u === 'l' || $u === 'liter' || $u === 'liters' || $u === '') return $value;
    if ($u === 'ml' || $u === 'milliliter' || $u === 'milliliters') return $value / 1000.0;
    return $value;
  }

  private function toCsv(array $rows): string {
    $fh = fopen('php://temp', 'w+');
    foreach ($rows as $r) fputcsv($fh, $r);
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv;
  }

}
