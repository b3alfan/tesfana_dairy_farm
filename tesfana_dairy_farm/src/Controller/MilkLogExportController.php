<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MilkLogExportController extends ControllerBase {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function csv(): Response {
    $request = $this->getRequest();
    $date_from = $request->query->get('from') ?: '';
    $date_to   = $request->query->get('to') ?: '';

    $storage = $this->etm->getStorage('log');
    $query = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'harvest');

    $from_ts = $this->parseDateToTs($date_from, '00:00');
    $to_ts   = $this->parseDateToTs($date_to, '23:59');

    if ($from_ts) { $query->condition('timestamp', $from_ts, '>='); }
    if ($to_ts)   { $query->condition('timestamp', $to_ts, '<='); }

    $ids = $query->sort('timestamp', 'ASC')->range(0, 5000)->execute();

    $rows = [];
    $header = ['Date', 'Cow', 'AM (L)', 'PM (L)', 'Total (L)'];

    foreach ($storage->loadMultiple($ids) as $log) {
      $ts_val = $log->get('timestamp')->value ?? NULL;
      if ($ts_val === NULL) { continue; }
      $ts = (int) $ts_val;

      $date = (new \DateTimeImmutable("@$ts"))
        ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d');

      $cow_label = '';
      if ($log->hasField('asset')) {
        $assets = $log->get('asset')->referencedEntities();
        if (!empty($assets)) { $cow_label = $assets[0]->label(); }
      }

      [$am, $pm] = $this->extractAmPmLiters($log);
      $total = $am + $pm;

      $rows[] = [$date, $cow_label, $this->fmt($am), $this->fmt($pm), $this->fmt($total)];
    }

    $csv = $this->toCsv(array_merge([$header], $rows));
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="milk_logs.csv"');
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

  private function extractAmPmLiters($log): array {
    $am = 0.0; $pm = 0.0;

    if (!$log->hasField('quantity')) {
      return [$am, $pm];
    }

    $qs = $log->get('quantity')->referencedEntities();
    foreach ($qs as $q) {
      $value = (float) ($q->get('value')->value ?? 0);
      $units = (string) ($q->get('units')->value ?? '');
      $label = (string) ($q->label() ?? '');
      $name  = (string) ($q->get('name')->value ?? '');

      $liters = $this->toLiters($value, $units);
      $hint = strtolower($label . ' ' . $name);

      if (preg_match('/\bam\b/', $hint)) { $am += $liters; continue; }
      if (preg_match('/\bpm\b/', $hint)) { $pm += $liters; continue; }
    }

    if (($am + $pm) == 0 && count($qs) === 2) {
      $am = $this->toLiters((float) ($qs[0]->get('value')->value ?? 0), (string) ($qs[0]->get('units')->value ?? ''));
      $pm = $this->toLiters((float) ($qs[1]->get('value')->value ?? 0), (string) ($qs[1]->get('units')->value ?? ''));
    }

    if (($am + $pm) == 0 && count($qs) === 1) {
      $pm = $this->toLiters((float) ($qs[0]->get('value')->value ?? 0), (string) ($qs[0]->get('units')->value ?? ''));
    }

    return [$am, $pm];
  }

  private function toLiters(float $value, string $units): float {
    $u = strtolower($units);
    if ($u === '' || $u === 'l' || $u === 'liter' || $u === 'liters') return $value;
    if ($u === 'ml' || $u === 'milliliter' || $u === 'milliliters') return $value / 1000.0;
    return $value;
  }

  private function fmt(float $v): string {
    return number_format($v, 2, '.', '');
  }

  private function toCsv(array $rows): string {
    $fh = fopen('php://temp', 'w+');
    foreach ($rows as $r) { fputcsv($fh, $r); }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv;
  }

}
