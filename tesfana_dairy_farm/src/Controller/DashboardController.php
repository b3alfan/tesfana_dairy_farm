<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\tesfana_dairy_farm\Service\TaskPlannerService;

/**
 * Tesfana dashboard controller (no constructor DI; always callable).
 */
class DashboardController extends ControllerBase {

  /**
   * Render dashboard.
   */
  public function view(): array {
    $logger  = \Drupal::logger('tesfana_dairy_farm');
    $notices = [];

    // Timezone.
    $site_tz = $this->config('system.date')->get('timezone.default') ?: 'UTC';
    $tz = new \DateTimeZone($site_tz);

    // Resolve entity types and timestamp field.
    $logType      = $this->resolveEntityType(['log', 'farm_log']);
    $quantityType = $this->resolveEntityType(['quantity', 'farm_quantity']);
    [$tsField, $tsFieldType] = $this->resolveTimestampField($logType);

    // Detect milk bundles that actually exist.
    $milkBundles = $this->detectMilkBundles($logType, ['harvest', 'harvest_milk', 'milking', 'milk_log']);
    if (!$milkBundles) {
      $notices[] = $this->t('No milk bundles found. Expected one of: @c', [
        '@c' => implode(', ', ['harvest', 'harvest_milk', 'milking', 'milk_log']),
      ]);
    }

    // Settings.
    $config   = $this->config('tesfana_dairy_farm.settings');
    $price    = (float) ($config->get('milk_price_per_liter') ?? 1.0);
    $currency = $config->get('currency') ?: 'NKF';

    // Core KPIs.
    $milk_today     = $this->sumMilkForDayPhp($logType, $milkBundles, $quantityType, $tsField, $tsFieldType, 0, $tz);
    $milk_yesterday = $this->sumMilkForDayPhp($logType, $milkBundles, $quantityType, $tsField, $tsFieldType, 1, $tz);

    $total_milk_logs = $this->countLogsAcross($logType, $milkBundles);
    $feed_logs       = $this->countLogsAcross($logType, ['feeding', 'feed', 'ration']);
    $health_logs     = $this->countLogsAcross($logType, ['treatment', 'health_treatment', 'health']);

    // Extra (milk) KPIs.
    $milk_7d_total = $this->sumMilkRange($logType, $milkBundles, $tsField, $tsFieldType, $this->rangeDaysAgo(6, 0, $tz), $tz);
    $milk_7d_avg   = round($milk_7d_total / 7, 2);
    $cows_7d       = $this->uniqueCowsRange($logType, $milkBundles, $tsField, $tsFieldType, $this->rangeDaysAgo(6, 0, $tz), $tz);
    $revenue_mtd   = number_format(
      $this->sumMilkRange($logType, $milkBundles, $tsField, $tsFieldType, $this->rangeMonthToDate($tz), $tz) * $price,
      2, '.', ''
    );

    // ---- Tasks via TaskPlannerService (with graceful fallback) ----
    /** @var \Drupal\tesfana_dairy_farm\Service\TaskPlannerService $taskPlanner */
    $taskPlanner = \Drupal::hasService('tesfana_dairy_farm.task_planner')
      ? \Drupal::service('tesfana_dairy_farm.task_planner')
      : new TaskPlannerService(\Drupal::service('datetime.time'));

    // Window: today .. +30 days.
    [$todayStart, $todayEnd] = $this->dayBoundsTs(0, $tz);
    $windowEnd = (new \DateTimeImmutable('@' . $todayEnd))->setTimezone($tz)->modify('+30 day')->setTime(23,59,59)->getTimestamp();
    // Use merged recurring + stored tasks:
    $tasks_window = $taskPlanner->mergedTasks($tz, $todayStart, $windowEnd);

    $tasks_today   = $taskPlanner->countTasksToday($tz);
    $tasks_7d      = $taskPlanner->countTasksNextDays($tz, 7);
    $tasks_overdue = $taskPlanner->countTasksOverdue($tz);

    // KPI payload.
    $kpis = [
      'milk_today'        => $milk_today,
      'milk_yesterday'    => $milk_yesterday,
      'total_milk_logs'   => $total_milk_logs,
      'feed_logs'         => $feed_logs,
      'health_logs'       => $health_logs,
      'revenue_today'     => number_format($milk_today * $price, 2, '.', ''),
      'revenue_yesterday' => number_format($milk_yesterday * $price, 2, '.', ''),
      'revenue_30d'       => number_format(
        $this->sumMilkLastDaysPhp($logType, $milkBundles, $quantityType, $tsField, $tsFieldType, 30, $tz) * $price,
        2, '.', ''
      ),
      'currency'          => $currency,

      // Extra milk KPIs.
      'milk_7d_total'     => $milk_7d_total,
      'milk_7d_avg'       => $milk_7d_avg,
      'cows_7d'           => $cows_7d,
      'revenue_mtd'       => $revenue_mtd,

      // Task KPIs.
      'tasks_today'       => $tasks_today,
      'tasks_7d'          => $tasks_7d,
      'tasks_overdue'     => $tasks_overdue,
    ];

    // Chart data.
    $chartPairs  = $this->milkTotalsLastDaysPhp($logType, $milkBundles, $quantityType, $tsField, $tsFieldType, 30, $tz);
    $chartSeries = array_map(static fn($p) => (float) $p['y'], $chartPairs);

    // Notices.
    if ($kpis['milk_today'] == 0.0 && $kpis['milk_yesterday'] == 0.0 && $kpis['total_milk_logs'] > 0) {
      $notices[] = $this->t('Milk logs found but units were missing/unsupported. Defaulting to liters where unknown.');
    }
    if ($kpis['total_milk_logs'] === 0) {
      $notices[] = $this->t('No milk logs found. Tried bundles: @b.', [
        '@b' => implode(', ', $milkBundles ?: ['(none)']),
      ]);
    }

    // Calendar events from generated + stored tasks.
    $calendarEvents = array_map(function (array $t) use ($tz) {
      $start = (new \DateTimeImmutable('@' . $t['due_ts']))->setTimezone($tz)->format('Y-m-d\TH:i:s');
      $color = match ($t['category']) {
        'vaccination' => '#10b981',
        'cleaning'    => '#f59e0b',
        'maintenance' => '#8b5cf6',
        'inspection'  => '#06b6d4',
        'health'      => '#ef4444',
        default       => '#3b82f6',
      };
      return [
        'id'              => $t['id'],
        'title'           => $t['title'],
        'start'           => $start,
        'allDay'          => false,
        'backgroundColor' => $color,
        'borderColor'     => $color,
      ];
    }, $tasks_window);

    $logger->notice('Dashboard bundles: @b | ts: @f (@t)', [
      '@b' => implode(', ', $milkBundles), '@f' => $tsField, '@t' => $tsFieldType,
    ]);

    return [
      '#theme' => 'tesfana_dashboard',
      '#kpis'  => $kpis,

      // Header actions:
      '#add_cow_url'        => Url::fromUserInput('/asset/add/animal')->toString(),
      '#log_milk_url'       => $this->safeRouteUrl('tesfana_dairy_farm.milk_quick_add'),
      '#bcs_url'            => $this->safeRouteUrl('tesfana_dairy_farm.bcs_quick_add'),
      '#milk_tests_url'     => $this->safeRouteUrl('tesfana_dairy_farm.milk_test_quick_add'),
      '#settings_url'       => $this->safeRouteUrl('tesfana_dairy_farm.settings'),
      '#milk_csv_url'       => $this->safeRouteUrl('tesfana_dairy_farm.milk_log_export_csv'),
      '#quickbooks_csv_url' => $this->safeRouteUrl('tesfana_dairy_farm.quickbooks_export_csv'),
      '#task_add_url'       => $this->safeRouteUrl('tesfana_dairy_farm.task_quick_form'),

      '#notices' => $notices,
      '#tasks'   => $this->formatTaskList($tasks_window, 15, $tz),

      '#attached' => [
        'library' => [
          'tesfana_dairy_farm/dashboard_ui',
          'tesfana_dairy_farm/charts',
          'tesfana_dairy_farm/ui',
          'tesfana_dairy_farm/dashboard_calendar',
        ],
        'drupalSettings' => [
          'tesfana' => [
            'dashboard' => [
              'milkChart'      => $chartSeries,
              'milkChartXY'    => $chartPairs,
              'calendarEvents' => $calendarEvents,
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /* ---------- Helpers ---------- */

  private function formatTaskList(array $tasks, int $limit, \DateTimeZone $tz): array {
    usort($tasks, fn($a, $b) => $a['due_ts'] <=> $b['due_ts']);
    $tasks = array_slice($tasks, 0, $limit);
    $out = [];
    foreach ($tasks as $t) {
      $out[] = [
        'title'    => $t['title'],
        'category' => $t['category'],
        'priority' => $t['priority'],
        'date'     => (new \DateTimeImmutable('@' . $t['due_ts']))->setTimezone($tz)->format('Y-m-d H:i'),
      ];
    }
    return $out;
  }

  private function safeRouteUrl(string $route): ?string {
    try { return Url::fromRoute($route)->toString(); }
    catch (\Throwable) { return null; }
  }

  private function resolveEntityType(array $candidates): string {
    $etm = \Drupal::entityTypeManager();
    foreach ($candidates as $id) { if ($etm->hasDefinition($id)) return $id; }
    return $candidates[0];
  }

  private function resolveTimestampField(string $logType): array {
    $efm = \Drupal::service('entity_field.manager');
    $defs = $efm->getBaseFieldDefinitions($logType);
    foreach (['timestamp', 'created', 'changed'] as $f) { if (isset($defs[$f])) return [$f, 'int']; }
    foreach ($defs as $name => $def) {
      $type = $def->getType();
      if (in_array($type, ['datetime','daterange','timestamp','created','changed'], TRUE)) {
        return ($type === 'datetime' || $type === 'daterange') ? [$name,'datetime'] : [$name,'int'];
      }
    }
    return ['timestamp', 'int'];
  }

  private function detectMilkBundles(string $logType, array $candidates): array {
    $found = [];
    try {
      $info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($logType) ?? [];
      $bundle_ids = array_keys($info);
      foreach ($candidates as $cand) {
        if (in_array($cand, $bundle_ids, TRUE)) { $found[] = $cand; }
      }
    } catch (\Throwable) {}
    if ($found) return $found;

    // Probe storage if bundle info didn’t list them.
    $storage = \Drupal::entityTypeManager()->getStorage($logType);
    foreach ($candidates as $cand) {
      try {
        $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', $cand)->range(0, 1)->execute();
        if (!empty($ids)) $found[] = $cand;
      } catch (\Throwable) {}
    }
    return $found ?: ['harvest'];
  }

  private function dayBoundsTs(int $daysAgo, \DateTimeZone $tz): array {
    $d = new DrupalDateTime('now', $tz);
    $d->setTime(0,0,0);
    if ($daysAgo > 0) $d->modify("-{$daysAgo} day");
    $start_ts = $d->getTimestamp();
    $end = clone $d; $end->setTime(23,59,59);
    return [$start_ts, $end->getTimestamp()];
  }

  private function rangeDaysAgo(int $startDaysAgo, int $endDaysAgo, \DateTimeZone $tz): array {
    $start = new DrupalDateTime('now', $tz); $start->setTime(0,0,0)->modify("-{$startDaysAgo} day");
    $end   = new DrupalDateTime('now', $tz); $end->setTime(23,59,59)->modify("-{$endDaysAgo} day");
    return [$start->getTimestamp(), $end->getTimestamp()];
  }

  private function rangeMonthToDate(\DateTimeZone $tz): array {
    $start = new DrupalDateTime('first day of this month', $tz); $start->setTime(0,0,0);
    $end   = new DrupalDateTime('now', $tz);                     $end->setTime(23,59,59);
    return [$start->getTimestamp(), $end->getTimestamp()];
  }

  private function getLogTimestamp($log, string $tsField, string $tsFieldType, \DateTimeZone $tz): ?int {
    if (!$log->hasField($tsField) || $log->get($tsField)->isEmpty()) return null;
    $item = $log->get($tsField)->first();
    if ($tsFieldType === 'int') {
      $raw = $item->getValue();
      foreach (['value', 'timestamp'] as $k) { if (isset($raw[$k]) && is_numeric($raw[$k])) return (int) $raw[$k]; }
      $v = $item->value ?? null; return is_numeric($v) ? (int) $v : null;
    }
    $val = $item->value ?? null; if (!$val) return null;
    try { return (new DrupalDateTime($val, $tz))->getTimestamp(); }
    catch (\Throwable) { $t = strtotime((string) $val); return $t ?: null; }
  }

  private function getQuantityRefFields(string $logType, $log): array {
    $efm = \Drupal::service('entity_field.manager');
    $bundle = $log->bundle();
    $defs = $efm->getFieldDefinitions($logType, $bundle);
    $quantityType = $this->resolveEntityType(['quantity', 'farm_quantity']);
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

  private function getAssetRefFields(string $logType, $sampleLog = null): array {
    $efm = \Drupal::service('entity_field.manager');
    $bundle = $sampleLog ? $sampleLog->bundle() : 'harvest';
    $defs = $efm->getFieldDefinitions($logType, $bundle);
    $assetType = $this->resolveEntityType(['asset', 'farm_asset']);
    $refs = [];
    foreach ($defs as $name => $def) {
      if (in_array($def->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
        $settings = $def->getSettings();
        if (!empty($settings['target_type']) && $settings['target_type'] === $assetType) {
          $refs[] = $name;
        }
      }
    }
    return $refs ?: ['asset'];
  }

  private function toLiters(float $value, string $unitId): float {
    $u = strtolower($unitId);
    if ($u === 'ml' || $u === 'milliliter' || $u === 'millilitre') return $value / 1000.0;
    return $value; // default to liters
  }

  private function litersFromQuantity($q): float {
    $val = 0.0;
    if ($q->hasField('value') && !$q->get('value')->isEmpty()) {
      try {
        $raw = $q->get('value')->first()->getValue();
        if (is_array($raw) && isset($raw['numerator'])) {
          $num = (float) $raw['numerator'];
          $den = (float) ($raw['denominator'] ?? 1);
          $val = $den != 0 ? $num / $den : 0.0;
        } else {
          $val = (float) ($q->get('value')->value ?? 0);
        }
      } catch (\Throwable) {
        $val = (float) ($q->get('value')->value ?? 0);
      }
    }
    $unitId = '';
    if ($q->hasField('units') && !$q->get('units')->isEmpty()) {
      try { if ($e = $q->get('units')->entity) $unitId = $e->id(); } catch (\Throwable) {}
    }
    return $this->toLiters($val, $unitId);
  }

  private function litersFromLogAny(string $logType, $log): float {
    $sum = 0.0;
    foreach ($this->getQuantityRefFields($logType, $log) as $f) {
      if ($log->hasField($f) && !$log->get($f)->isEmpty()) {
        foreach ($log->get($f)->referencedEntities() as $q) {
          $sum += $this->litersFromQuantity($q);
        }
      }
    }
    if ($sum > 0) return round($sum, 2);

    // Fallbacks to log-level numeric fields (if any).
    foreach ([['milk_total'], ['milk_liters'], ['total_milk'], ['morning_milk','evening_milk'], ['am_yield','pm_yield'], ['am_milk','pm_milk']] as $set) {
      $s = 0.0; $found = FALSE;
      foreach ($set as $fname) {
        if ($log->hasField($fname) && !$log->get($fname)->isEmpty()) { $s += (float) ($log->get($fname)->value ?? 0); $found = TRUE; }
      }
      if ($found && $s > 0) return round($s, 2);
    }
    return 0.0;
  }

  private function loadRecentMilkLogs(string $logType, array $milkBundles, ?string $tsField, int $limit = 50000): array {
    $storage = \Drupal::entityTypeManager()->getStorage($logType);
    $idsAll = [];
    foreach ($milkBundles as $bundle) {
      try {
        $q = $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle);
        if ($tsField) { try { $q->sort($tsField, 'DESC'); } catch (\Throwable) {} }
        $ids = $q->range(0, $limit)->execute();
        if ($ids) $idsAll = array_merge($idsAll, $ids);
      } catch (\Throwable) {}
    }
    if (!$idsAll) return [];
    return $storage->loadMultiple(array_unique($idsAll));
  }

  private function sumMilkForDayPhp(string $logType, array $milkBundles, string $quantityType, string $tsField, string $tsFieldType, int $daysAgo, \DateTimeZone $tz): float {
    [$start_ts, $end_ts] = $this->dayBoundsTs($daysAgo, $tz);
    $logs = $this->loadRecentMilkLogs($logType, $milkBundles, $tsField, 20000);
    $total = 0.0;
    foreach ($logs as $log) {
      $ts = $this->getLogTimestamp($log, $tsField, $tsFieldType, $tz);
      if ($ts === null || $ts < $start_ts || $ts > $end_ts) continue;
      $total += $this->litersFromLogAny($logType, $log);
    }
    return round($total, 2);
  }

  private function sumMilkLastDaysPhp(string $logType, array $milkBundles, string $quantityType, string $tsField, string $tsFieldType, int $days, \DateTimeZone $tz): float {
    [$range_start, ] = $this->dayBoundsTs($days - 1, $tz);
    [, $range_end]   = $this->dayBoundsTs(0, $tz);
    return $this->sumMilkRange($logType, $milkBundles, $tsField, $tsFieldType, [$range_start, $range_end], $tz);
  }

  private function sumMilkRange(string $logType, array $milkBundles, string $tsField, string $tsFieldType, array $range, \DateTimeZone $tz): float {
    [$start_ts, $end_ts] = $range;
    $logs = $this->loadRecentMilkLogs($logType, $milkBundles, $tsField, 50000);
    $sum = 0.0;
    foreach ($logs as $log) {
      $ts = $this->getLogTimestamp($log, $tsField, $tsFieldType, $tz);
      if ($ts === null || $ts < $start_ts || $ts > $end_ts) continue;
      $sum += $this->litersFromLogAny($logType, $log);
    }
    return round($sum, 2);
  }

  private function uniqueCowsRange(string $logType, array $milkBundles, string $tsField, string $tsFieldType, array $range, \DateTimeZone $tz): int {
    [$start_ts, $end_ts] = $range;
    $logs = $this->loadRecentMilkLogs($logType, $milkBundles, $tsField, 50000);
    $assetFields = $this->getAssetRefFields($logType, $logs ? reset($logs) : null);
    $ids = [];
    foreach ($logs as $log) {
      $ts = $this->getLogTimestamp($log, $tsField, $tsFieldType, $tz);
      if ($ts === null || $ts < $start_ts || $ts > $end_ts) continue;
      foreach ($assetFields as $f) {
        if ($log->hasField($f) && !$log->get($f)->isEmpty()) {
          foreach ($log->get($f)->referencedEntities() as $a) {
            $ids[$a->id()] = TRUE;
          }
        }
      }
    }
    return count($ids);
  }

  private function milkTotalsLastDaysPhp(string $logType, array $milkBundles, string $quantityType, string $tsField, string $tsFieldType, int $days, \DateTimeZone $tz): array {
    $buckets = [];
    for ($i = $days - 1; $i >= 0; $i--) {
      [$start_ts, $end_ts] = $this->dayBoundsTs($i, $tz);
      $label = (new \DateTimeImmutable("@$start_ts"))->setTimezone($tz)->format('Y-m-d');
      $buckets[$label] = ['start' => $start_ts, 'end' => $end_ts, 'sum' => 0.0];
    }
    $first = reset($buckets);
    $last  = end($buckets);

    $logs = $this->loadRecentMilkLogs($logType, $milkBundles, $tsField, 20000);

    foreach ($logs as $log) {
      $ts = $this->getLogTimestamp($log, $tsField, $tsFieldType, $tz);
      if ($ts === null || $ts < $first['start'] || $ts > $last['end']) continue;

      foreach ($buckets as $date => &$b) {
        if ($ts >= $b['start'] && $ts <= $b['end']) {
          $b['sum'] += $this->litersFromLogAny($logType, $log);
          break;
        }
      }
      unset($b);
    }

    $pairs = [];
    foreach ($buckets as $date => $b) {
      $pairs[] = ['x' => $date, 'y' => round($b['sum'], 2)];
    }
    return $pairs;
  }

  private function countLogsAcross(string $logType, array $bundles): int {
    $storage = \Drupal::entityTypeManager()->getStorage($logType);
    $total = 0;
    foreach ($bundles as $bundle) {
      try {
        $q = $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle)->count();
        $total += (int) $q->execute();
      } catch (\Throwable) {}
    }
    return $total;
  }

}
