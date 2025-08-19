<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * farmOS-native data access for dashboard, reports, and cow profile.
 *
 * NO custom tables. We read:
 * - Cows      => asset (bundle 'animal')
 * - Milk logs => log (bundle 'harvest') + quantity entities (units in liters)
 * - Tasks     => log (bundle 'activity') -- calendar/events
 * - Vaccines  => log (bundle 'medical'|'maintenance'), title contains 'vaccin'
 * - BCS       => quantity where label/unit mentions 'BCS'
 * - Milk quality => lab_test quantities (fat %, protein %, SCC)
 * - Repro metrics => birth/activity/observation/medical logs
 * - Health      => medical/maintenance (mastitis/lameness), cost quantities
 * - Feeding     => input (feed), kg DM quantities & cost
 * - Behavior    => observation/lab_test quantities (rumination/activity/lying/feeding)
 * - Finance     => revenue from milk @ price configured in tesfana settings
 */
final class TesfanaData {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  private function log(string $level, string $message, array $context = []): void {
    $this->loggerFactory->get('tesfana')->$level($message, $context);
  }

  private function cfg(string $key, mixed $default = NULL): mixed {
    $config = \Drupal::config('tesfana_dairy_farm.settings');
    return $config->get($key) ?? $default;
  }

  /* ---------------------------
   * GLOBAL DASHBOARD / REPORTS
   * -------------------------*/

  public function getDashboardKpis(): array {
    $today = new \DateTimeImmutable('today');
    $yest  = $today->modify('-1 day');

    $milk_today = $this->sumMilkBetween($today->setTime(0,0)->getTimestamp(), $today->setTime(23,59,59)->getTimestamp());
    $milk_yest  = $this->sumMilkBetween($yest->setTime(0,0)->getTimestamp(),  $yest->setTime(23,59,59)->getTimestamp());
    $total_cows = $this->countCows();
    $open_tasks = $this->countUpcomingActivities(7);

    return [
      'milk_today'     => round($milk_today, 2),
      'milk_yesterday' => round($milk_yest, 2),
      'total_cows'     => $total_cows,
      'active_alerts'  => $open_tasks,
    ];
  }

  /** Daily milk series (herd) for last N days. */
  public function getMilkDailySeries(int $days = 30): array {
    $end = new \DateTimeImmutable('today 23:59:59');
    $start = $end->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);

    $byDay = [];
    $cursor = $start;
    while ($cursor <= $end) {
      $byDay[$cursor->format('Y-m-d')] = 0.0;
      $cursor = $cursor->modify('+1 day');
    }

    try {
      $logs = $this->loadLogs('harvest', (int) $start->getTimestamp(), (int) $end->getTimestamp());
      foreach ($logs as $log) {
        $ts = (int) ($log->get('timestamp')->value ?? 0);
        if (!$ts) { continue; }
        $key = date('Y-m-d', $ts);
        $byDay[$key] = ($byDay[$key] ?? 0) + $this->sumMilkQuantitiesOnLog($log);
      }
    }
    catch (\Throwable $e) { $this->log('warning', 'Milk daily series failed: @err', ['@err' => $e->getMessage()]); }

    $out = [];
    foreach ($byDay as $d => $v) { $out[] = ['date' => $d, 'total' => round($v, 2)]; }
    return $out;
  }

  /** Calendar events from planned activity logs. */
  public function getPlannedEvents(int $daysAhead = 30): array {
    $now = new \DateTimeImmutable('now');
    $end = $now->modify('+' . $daysAhead . ' days')->setTime(23,59,59);

    $events = [];
    try {
      $logs = $this->loadLogs('activity', (int) $now->getTimestamp(), (int) $end->getTimestamp());
      foreach ($logs as $log) {
        $ts = (int) ($log->get('timestamp')->value ?? 0);
        if (!$ts) { continue; }
        $events[] = [
          'title' => (string) ($log->label() ?? $this->dateFormatter->format($ts, 'short')),
          'start' => date('c', $ts),
        ];
      }
    }
    catch (\Throwable $e) { $this->log('warning', 'Planned events failed: @err', ['@err' => $e->getMessage()]); }
    return $events;
  }

  /* ---------------------------
   * PER-COW METRICS & KPIs
   * -------------------------*/

  /** Weekly milk totals for past N weeks for a cow. */
  public function getCowMilkWeeklySeries(int $asset_id, int $weeks = 30): array {
    $end = new \DateTimeImmutable('today 23:59:59');
    $start = (new \DateTimeImmutable('monday this week'))->modify('-' . ($weeks - 1) . ' weeks');

    $labels = []; $buckets = [];
    $cursor = $start;
    for ($i = 0; $i < $weeks; $i++) {
      $label = $cursor->format('o-\WW'); $labels[] = $label; $buckets[$label] = 0.0;
      $cursor = $cursor->modify('+1 week');
    }

    try {
      $logs = $this->loadLogs('harvest', (int) $start->getTimestamp(), (int) $end->getTimestamp(), $asset_id);
      foreach ($logs as $log) {
        $ts = (int) ($log->get('timestamp')->value ?? 0); if (!$ts) { continue; }
        $w  = date('o-\WW', $ts);
        $buckets[$w] = ($buckets[$w] ?? 0) + $this->sumMilkQuantitiesOnLog($log);
      }
    }
    catch (\Throwable $e) { $this->log('warning', 'Cow weekly milk failed: @err', ['@err' => $e->getMessage()]); }

    return ['weeks' => array_values(array_keys($buckets)), 'values' => array_values($buckets)];
  }

  /** Cow summary KPIs. */
  public function getCowSummary(int $asset_id): array {
    $sum7  = $this->sumMilkForAssetDays($asset_id, 7);
    $sum30 = $this->sumMilkForAssetDays($asset_id, 30);
    return [
      'milk_7d'        => round($sum7, 2),
      'milk_30d'       => round($sum30, 2),
      'avg_daily_30d'  => round($sum30 / 30.0, 2),
    ];
  }

  /** Latest BCS entry for a cow. */
  public function getCowBcsLatest(int $asset_id): array {
    $score = NULL; $timestamp = NULL;
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', ['observation','lab_test','medical'], 'IN')
        ->condition('asset', $asset_id)
        ->sort('timestamp', 'DESC')->range(0, 25);
      $ids = $q->execute();
      if ($ids) {
        $logs = $storage->loadMultiple($ids);
        foreach ($logs as $log) {
          $ts = (int) ($log->get('timestamp')->value ?? 0);
          if ($log->hasField('quantity')) {
            foreach ($log->get('quantity')->referencedEntities() as $qty) {
              $label = strtolower((string) ($qty->label() ?? ''));
              $unit  = strtolower((string) ($qty->get('units')->value ?? $qty->get('unit')->value ?? ''));
              if (str_contains($label, 'bcs') || $unit === 'bcs') {
                $val = (float) ($qty->get('value')->value ?? 0);
                $score = $val; $timestamp = $ts; break 2;
              }
            }
          }
          $title = strtolower((string) ($log->label() ?? ''));
          if (str_contains($title, 'bcs')) { $timestamp = $ts; break; }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'BCS lookup failed: @err', ['@err' => $e->getMessage()]); }
    return ['score' => $score, 'timestamp' => $timestamp];
  }

  /** Recent vaccination rows. */
  public function getCowVaccinations(int $asset_id, int $limit = 10): array {
    $rows = [];
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', ['medical','maintenance'], 'IN')
        ->condition('asset', $asset_id)
        ->sort('timestamp', 'DESC')->range(0, 100);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $title = strtolower((string) ($log->label() ?? ''));
          if (str_contains($title, 'vaccin')) {
            $ts = (int) ($log->get('timestamp')->value ?? 0);
            $rows[] = ['date' => $ts ? date('Y-m-d', $ts) : '', 'title' => (string) $log->label(), 'log_id' => (int) $log->id()];
            if (count($rows) >= $limit) { break; }
          }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Vaccination lookup failed: @err', ['@err' => $e->getMessage()]); }
    return $rows;
  }

  /* ---------------------------
   * PRODUCTION & QUALITY
   * -------------------------*/

  /**
   * Daily yields (last N days) and per-milking breakdown if logs are separate.
   * @return array{days: array<int,array{date:string,total:float,per_milking:array<int,float>}>}
   */
  public function getCowDailyProduction(int $asset_id, int $days = 14): array {
    $end = new \DateTimeImmutable('today 23:59:59');
    $start = $end->modify('-' . ($days - 1) . ' days')->setTime(0, 0);

    $byDay = [];
    $cursor = $start;
    while ($cursor <= $end) {
      $byDay[$cursor->format('Y-m-d')] = ['date' => $cursor->format('Y-m-d'), 'total' => 0.0, 'per_milking' => []];
      $cursor = $cursor->modify('+1 day');
    }

    try {
      $logs = $this->loadLogs('harvest', (int) $start->getTimestamp(), (int) $end->getTimestamp(), $asset_id);
      foreach ($logs as $log) {
        $ts = (int) ($log->get('timestamp')->value ?? 0); if (!$ts) { continue; }
        $date = date('Y-m-d', $ts);
        $qty = $this->sumMilkQuantitiesOnLog($log);
        $byDay[$date]['total'] += $qty;
        // If separate logs per milking exist, each becomes an entry in per_milking.
        $byDay[$date]['per_milking'][] = $qty;
      }
    }
    catch (\Throwable $e) { $this->log('warning', 'Daily production failed: @err', ['@err' => $e->getMessage()]); }

    return ['days' => array_values($byDay)];
  }

  /**
   * Milk quality: latest fat%, protein%, SCC for cow.
   * @return array{fat: array{value: float|null, date: string|null}, protein: array{value: float|null, date: string|null}, scc: array{value: float|null, date: string|null}}
   */
  public function getCowMilkQuality(int $asset_id): array {
    $out = [
      'fat'     => ['value' => NULL, 'date' => NULL],
      'protein' => ['value' => NULL, 'date' => NULL],
      'scc'     => ['value' => NULL, 'date' => NULL],
    ];
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', 'lab_test')
        ->condition('asset', $asset_id)
        ->sort('timestamp', 'DESC')->range(0, 50);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $ts = (int) ($log->get('timestamp')->value ?? 0);
          foreach (($log->hasField('quantity') ? $log->get('quantity')->referencedEntities() : []) as $qty) {
            $label = strtolower((string) ($qty->label() ?? ''));
            $unit  = strtolower((string) ($qty->get('units')->value ?? $qty->get('unit')->value ?? ''));
            $val   = (float) ($qty->get('value')->value ?? 0);
            if ((str_contains($label, 'fat') || $unit === '%' || str_contains($label, 'fat%')) && $out['fat']['value'] === NULL) {
              $out['fat'] = ['value' => $val, 'date' => $ts ? date('Y-m-d', $ts) : NULL];
            }
            if ((str_contains($label, 'protein') || $unit === '%' || str_contains($label, 'prot')) && $out['protein']['value'] === NULL) {
              $out['protein'] = ['value' => $val, 'date' => $ts ? date('Y-m-d', $ts) : NULL];
            }
            if (str_contains($label, 'scc') || str_contains($unit, 'cell')) {
              if ($out['scc']['value'] === NULL) {
                $out['scc'] = ['value' => $val, 'date' => $ts ? date('Y-m-d', $ts) : NULL];
              }
            }
          }
          if ($out['fat']['value'] !== NULL && $out['protein']['value'] !== NULL && $out['scc']['value'] !== NULL) {
            break;
          }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Milk quality failed: @err', ['@err' => $e->getMessage()]); }
    return $out;
  }

  /* ---------------------------
   * REPRODUCTION
   * -------------------------*/

  /**
   * Repro KPIs: first-service conception, pregnancy rate proxy, heat detection count,
   * days open, services per conception, calving interval (if ≥2 calvings).
   */
  public function getCowRepro(int $asset_id): array {
    $lastCalvings = $this->getCalvingDates($asset_id);
    $lastCalving = $lastCalvings[0] ?? NULL;
    $prevCalving = $lastCalvings[1] ?? NULL;

    $services = $this->getServiceTimestamps($asset_id, $lastCalving ? $lastCalving : (time() - 2*365*86400), time());
    $heats    = $this->getHeatTimestamps($asset_id, $lastCalving ? $lastCalving : (time() - 365*86400), time());
    $pregs    = $this->getPregCheckTimestamps($asset_id, $lastCalving ? $lastCalving : (time() - 365*86400), time());

    // Services per conception & first-service conception.
    $firstServiceConceived = NULL;
    $servicesPerConception = NULL;
    if ($services) {
      $conceivedAt = NULL;
      foreach ($services as $i => $svc) {
        $preg = $this->firstPregAfter($pregs, $svc);
        if ($preg !== NULL) { $conceivedAt = $i + 1; break; }
      }
      if ($conceivedAt !== NULL) {
        $servicesPerConception = $conceivedAt;
        $firstServiceConceived = ($conceivedAt === 1);
      }
    }

    // Days open: from last calving to conception (preg confirm), else to today.
    $daysOpen = NULL;
    if ($lastCalving) {
      $conceptionTs = NULL;
      if ($services) {
        foreach ($services as $svc) {
          $preg = $this->firstPregAfter($pregs, $svc);
          if ($preg) { $conceptionTs = $svc; break; }
        }
      }
      $end = $conceptionTs ?? time();
      $daysOpen = (int) floor(($end - $lastCalving) / 86400);
    }

    // Calving interval
    $calvingInterval = NULL;
    if ($lastCalving && $prevCalving) {
      $calvingInterval = (int) floor(($lastCalving - $prevCalving) / 86400);
    }

    // Simple proxies (counts).
    $heatDetections90 = 0;
    foreach ($heats as $h) { if ($h >= time() - 90*86400) { $heatDetections90++; } }
    $pregRateProxy = NULL;
    if ($services) {
      $pregRateProxy = $servicesPerConception ? 1.0 / $servicesPerConception : 0.0;
    }

    return [
      'first_service_conception' => $firstServiceConceived, // TRUE/FALSE/NULL
      'pregnancy_rate_proxy'     => $pregRateProxy,         // ~0..1 or NULL
      'heat_detection_count_90d' => $heatDetections90,
      'days_open'                => $daysOpen,
      'services_per_conception'  => $servicesPerConception,
      'calving_interval_days'    => $calvingInterval,
    ];
  }

  private function getCalvingDates(int $asset_id): array {
    $out = [];
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', 'birth')->condition('asset', $asset_id)
        ->sort('timestamp', 'DESC')->range(0, 5);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $ts = (int) ($log->get('timestamp')->value ?? 0); if ($ts) { $out[] = $ts; }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Calving dates failed: @err', ['@err' => $e->getMessage()]); }
    return $out;
  }

  private function getServiceTimestamps(int $asset_id, int $from, int $to): array {
    return $this->timestampsByLabel(['activity','medical'], $asset_id, $from, $to, ['service','insemin','ai','mating','breed']);
  }

  private function getPregCheckTimestamps(int $asset_id, int $from, int $to): array {
    return $this->timestampsByLabel(['medical','observation'], $asset_id, $from, $to, ['preg','gest']);
  }

  private function getHeatTimestamps(int $asset_id, int $from, int $to): array {
    return $this->timestampsByLabel(['observation','activity'], $asset_id, $from, $to, ['heat','estrus','oestrus']);
  }

  private function timestampsByLabel(array $types, int $asset_id, int $from, int $to, array $needles): array {
    $out = [];
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', $types, 'IN')
        ->condition('asset', $asset_id)
        ->condition('timestamp', [$from, $to], 'BETWEEN')
        ->sort('timestamp', 'ASC')->range(0, 200);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $title = strtolower((string) ($log->label() ?? ''));
          foreach ($needles as $n) {
            if (str_contains($title, $n)) {
              $ts = (int) ($log->get('timestamp')->value ?? 0);
              if ($ts) { $out[] = $ts; }
              break;
            }
          }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'timestampsByLabel failed: @err', ['@err' => $e->getMessage()]); }
    return $out;
  }

  private function firstPregAfter(array $pregs, int $svcTs): ?int {
    foreach ($pregs as $p) { if ($p >= $svcTs) { return $p; } }
    return NULL;
  }

  /* ---------------------------
   * HEALTH
   * -------------------------*/

  public function getCowHealth(int $asset_id, int $days = 365): array {
    $from = strtotime('-' . $days . ' days 00:00:00'); $to = time();
    $mastitis = 0; $lameness = 0; $treatments = 0; $vetCost = 0.0;
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', ['medical','maintenance'], 'IN')
        ->condition('asset', $asset_id)
        ->condition('timestamp', [$from, $to], 'BETWEEN')
        ->sort('timestamp','DESC')->range(0, 500);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $title = strtolower((string) ($log->label() ?? ''));
          if (str_contains($title, 'mastitis')) { $mastitis++; }
          if (str_contains($title, 'lameness') || str_contains($title, 'hoof')) { $lameness++; }
          $treatments++;
          $vetCost += $this->sumCurrencyOnLog($log);
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Health metrics failed: @err', ['@err' => $e->getMessage()]); }
    // Cull status hint from asset status fields if available (best-effort).
    $culled = FALSE;
    try {
      $asset = $this->etm->getStorage('asset')->load($asset_id);
      if ($asset && $asset->hasField('status')) {
        $status = strtolower((string) $asset->get('status')->value);
        $culled = in_array($status, ['archived','retired','culled'], TRUE);
      }
    } catch (\Throwable $e) {}
    return [
      'mastitis_count' => $mastitis,
      'lameness_count' => $lameness,
      'treatments_count' => $treatments,
      'vet_cost' => round($vetCost, 2),
      'culled' => $culled,
    ];
  }

  /* ---------------------------
   * FEEDING & EFFICIENCY
   * -------------------------*/

  public function getCowFeeding(int $asset_id, int $days = 30): array {
    $from = strtotime('-' . $days . ' days 00:00:00'); $to = time();
    $dmiKg = 0.0; $feedCost = 0.0;

    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', 'input')
        ->condition('asset', $asset_id)
        ->condition('timestamp', [$from, $to], 'BETWEEN')
        ->sort('timestamp','DESC')->range(0, 1000);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          $dmiKg += $this->sumDmiKgOnLog($log);
          $feedCost += $this->sumCurrencyOnLog($log);
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Feeding metrics failed: @err', ['@err' => $e->getMessage()]); }

    $avgDmiPerDay = $dmiKg / max(1, $days);
    $milk30 = $this->sumMilkForAssetDays($asset_id, 30);
    $avgMilkPerDay = $milk30 / 30.0;
    $feedEfficiency = $avgDmiPerDay > 0 ? ($avgMilkPerDay / $avgDmiPerDay) : NULL;

    // Age at first calving (if available).
    $ageFirstCalvingDays = NULL;
    try {
      $asset = $this->etm->getStorage('asset')->load($asset_id);
      if ($asset) {
        $birthTs = NULL;
        if ($asset->hasField('birthdate') && $asset->get('birthdate')->value) {
          $birthTs = strtotime($asset->get('birthdate')->value);
        }
        $calvings = $this->getCalvingDates($asset_id);
        $firstCalving = end($calvings); // oldest
        if ($birthTs && $firstCalving) {
          $ageFirstCalvingDays = (int) floor(($firstCalving - $birthTs) / 86400);
        }
      }
    } catch (\Throwable $e) {}

    return [
      'dmi_kg_per_day' => round($avgDmiPerDay, 2),
      'feed_cost_total' => round($feedCost, 2),
      'feed_efficiency_l_per_kgdm' => $feedEfficiency !== NULL ? round($feedEfficiency, 3) : NULL,
      'age_first_calving_days' => $ageFirstCalvingDays,
    ];
  }

  /* ---------------------------
   * BEHAVIOR / SENSORS
   * -------------------------*/

  /**
   * Behavior metrics (last 7 days averages) using observation/lab_test logs.
   * We look for quantities labeled rumination/feeding/activity/lying.
   */
  public function getCowBehavior(int $asset_id): array {
    $from = strtotime('-7 days 00:00:00'); $to = time();
    $found = ['rumination' => [], 'feeding' => [], 'activity' => [], 'lying' => []];
    try {
      $storage = $this->etm->getStorage('log');
      $q = $storage->getQuery()->accessCheck(TRUE)
        ->condition('type', ['observation','lab_test'], 'IN')
        ->condition('asset', $asset_id)
        ->condition('timestamp', [$from, $to], 'BETWEEN')
        ->sort('timestamp','ASC')->range(0, 500);
      $ids = $q->execute();
      if ($ids) {
        foreach ($storage->loadMultiple($ids) as $log) {
          foreach (($log->hasField('quantity') ? $log->get('quantity')->referencedEntities() : []) as $qty) {
            $label = strtolower((string) ($qty->label() ?? ''));
            $val = (float) ($qty->get('value')->value ?? 0);
            foreach (array_keys($found) as $k) {
              if (str_contains($label, $k)) { $found[$k][] = $val; break; }
            }
          }
        }
      }
    } catch (\Throwable $e) { $this->log('warning', 'Behavior metrics failed: @err', ['@err' => $e->getMessage()]); }

    $avg = fn(array $a): ?float => $a ? round(array_sum($a) / max(1, count($a)), 2) : NULL;
    return [
      'rumination' => $avg($found['rumination']),
      'feeding'    => $avg($found['feeding']),
      'activity'   => $avg($found['activity']),
      'lying'      => $avg($found['lying']),
    ];
  }

  /* ---------------------------
   * FINANCIAL & BENCHMARKS
   * -------------------------*/

  public function getCowFinancial(int $asset_id): array {
    $price = (float) $this->cfg('milk_price_per_liter', 1.0);
    $currency = (string) $this->cfg('currency', 'ETB');

    $milk7  = $this->sumMilkForAssetDays($asset_id, 7);
    $revDay = ($milk7 / 7.0) * $price;

    $feed = $this->getCowFeeding($asset_id, 7);
    $feedDay = $feed['feed_cost_total'] / 7.0;

    // Herd benchmark: avg milk per cow per day (last 30d).
    $totalMilk30 = 0.0;
    foreach ($this->getMilkDailySeries(30) as $row) { $totalMilk30 += (float) $row['total']; }
    $cowCount = max(1, $this->countCows());
    $herdAvgPerDay = ($totalMilk30 / 30.0) / $cowCount;

    $cowAvgPerDay  = $milk7 / 7.0;

    return [
      'currency' => $currency,
      'revenue_per_day' => round($revDay, 2),
      'revenue_minus_feed_per_day' => round($revDay - $feedDay, 2),
      'cow_avg_l_per_day' => round($cowAvgPerDay, 2),
      'herd_avg_l_per_day' => round($herdAvgPerDay, 2),
      'benchmark_delta_l_per_day' => round($cowAvgPerDay - $herdAvgPerDay, 2),
    ];
  }

  /* ---------------------------
   * INTERNAL HELPERS
   * -------------------------*/

  private function countCows(): int {
    try {
      $q = $this->etm->getStorage('asset')->getQuery()->accessCheck(TRUE)->condition('type', 'animal');
      return (int) $q->count()->execute();
    } catch (\Throwable $e) { $this->log('warning', 'Count cows failed: @err', ['@err' => $e->getMessage()]); return 0; }
  }

  private function countUpcomingActivities(int $daysAhead): int {
    try {
      $now = time(); $end = strtotime('+' . $daysAhead . ' days 23:59:59');
      $q = $this->etm->getStorage('log')->getQuery()
        ->accessCheck(TRUE)->condition('type', 'activity')
        ->condition('timestamp', [$now, $end], 'BETWEEN');
      return (int) $q->count()->execute();
    } catch (\Throwable $e) { $this->log('warning', 'Upcoming activities count failed: @err', ['@err' => $e->getMessage()]); return 0; }
  }

  /** @return \Drupal\Core\Entity\EntityInterface[] */
  private function loadLogs(string $type, int $fromTs, int $toTs, ?int $asset_id = NULL): array {
    $s = $this->etm->getStorage('log');
    $q = $s->getQuery()->accessCheck(TRUE)
      ->condition('type', $type)->condition('timestamp', [$fromTs, $toTs], 'BETWEEN')
      ->range(0, 5000)->sort('timestamp', 'ASC');
    if ($asset_id !== NULL) { $q->condition('asset', $asset_id); }
    $ids = $q->execute();
    return $ids ? $s->loadMultiple($ids) : [];
  }

  private function sumMilkBetween(int $fromTs, int $toTs): float {
    $sum = 0.0;
    try { foreach ($this->loadLogs('harvest', $fromTs, $toTs) as $log) { $sum += $this->sumMilkQuantitiesOnLog($log); } }
    catch (\Throwable $e) { $this->log('warning', 'sumMilkBetween failed: @err', ['@err' => $e->getMessage()]); }
    return $sum;
  }

  private function sumMilkForAssetDays(int $asset_id, int $days): float {
    $start = (new \DateTimeImmutable('-' . $days . ' days'))->setTime(0, 0);
    return $this->sumMilkBetweenForAsset($asset_id, (int) $start->getTimestamp(), time());
  }

  private function sumMilkBetweenForAsset(int $asset_id, int $fromTs, int $toTs): float {
    $sum = 0.0;
    try { foreach ($this->loadLogs('harvest', $fromTs, $toTs, $asset_id) as $log) { $sum += $this->sumMilkQuantitiesOnLog($log); } }
    catch (\Throwable $e) { $this->log('warning', 'sumMilkBetweenForAsset failed: @err', ['@err' => $e->getMessage()]); }
    return $sum;
  }

  /** Sum liters on a log from its quantities. */
  private function sumMilkQuantitiesOnLog(EntityInterface $log): float {
    $total = 0.0;
    if ($log->hasField('quantity')) {
      foreach ($log->get('quantity')->referencedEntities() as $qty) {
        $value = (float) ($qty->get('value')->value ?? 0);
        $unit  = strtolower((string) ($qty->get('units')->value ?? $qty->get('unit')->value ?? ''));
        $label = strtolower((string) ($qty->label() ?? ''));
        if (in_array($unit, ['l','liter','liters','litre','litres','lt'], TRUE) || str_contains($label, 'milk')) {
          $total += $value;
        }
      }
    }
    return $total;
  }

  /** Sum currency quantities (vet/feeds). */
  private function sumCurrencyOnLog(EntityInterface $log): float {
    $sum = 0.0;
    if ($log->hasField('quantity')) {
      foreach ($log->get('quantity')->referencedEntities() as $qty) {
        $value = (float) ($qty->get('value')->value ?? 0);
        $unit  = strtolower((string) ($qty->get('units')->value ?? $qty->get('unit')->value ?? ''));
        if (in_array($unit, ['etb','br','birr','usd','eur','gbp','kes'], TRUE) || str_contains($unit, 'currency')) {
          $sum += $value;
        }
      }
    }
    return $sum;
  }

  /** Sum DMI kilograms on a log (heuristics). */
  private function sumDmiKgOnLog(EntityInterface $log): float {
    $kg = 0.0;
    if ($log->hasField('quantity')) {
      foreach ($log->get('quantity')->referencedEntities() as $qty) {
        $value = (float) ($qty->get('value')->value ?? 0);
        $unit  = strtolower((string) ($qty->get('units')->value ?? $qty->get('unit')->value ?? ''));
        $label = strtolower((string) ($qty->label() ?? ''));
        if (in_array($unit, ['kgdm','kg_dm','kg'], TRUE) || str_contains($label, 'dm')) {
          $kg += $value;
        }
      }
    }
    return $kg;
  }

}
