<?php

namespace Drupal\tesfana_dairy_farm\Service;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Generates recurring tasks and merges with user-created tasks from storage.
 */
class TaskPlannerService {

  public function __construct(
    private readonly TimeInterface $time,
  ) {}

  public function mergedTasks(\DateTimeZone $tz, int $startTs, int $endTs): array {
    $recurring = $this->generateRecurring($tz, $startTs, $endTs);
    $stored = $this->loadStored($startTs, $endTs);

    foreach ($stored as &$t) {
      $t['id'] = 'db:' . $t['id'];
    }
    unset($t);

    return array_values(array_merge($recurring, $stored));
  }

  public function countTasksToday(\DateTimeZone $tz): int {
    [$s, $e] = $this->dayBounds(0, $tz);
    return count($this->mergedTasks($tz, $s, $e));
  }

  public function countTasksNextDays(\DateTimeZone $tz, int $days): int {
    [$s, $eToday] = $this->dayBounds(0, $tz);
    $e = (new \DateTimeImmutable('@' . $eToday))->setTimezone($tz)->modify('+' . ($days) . ' day')->setTime(23, 59, 59)->getTimestamp();
    return count($this->mergedTasks($tz, $s, $e));
  }

  public function countTasksOverdue(\DateTimeZone $tz): int {
    [$sToday, ] = $this->dayBounds(0, $tz);
    $start = (new \DateTimeImmutable('@' . $sToday))->setTimezone($tz)->modify('-14 day')->setTime(0, 0, 0)->getTimestamp();
    return count($this->mergedTasks($tz, $start, $sToday - 1));
  }

  /* ---------------- Recurring generator ---------------- */

  public function generateRecurring(\DateTimeZone $tz, int $startTs, int $endTs): array {
    $out = [];

    $start = (new \DateTimeImmutable('@' . $startTs))->setTimezone($tz)->setTime(0, 0, 0);
    $end   = (new \DateTimeImmutable('@' . $endTs))->setTimezone($tz)->setTime(23, 59, 59);

    // Daily routines.
    $out = array_merge($out, $this->repeatDaily('Parlor cleaning', 'cleaning', 'normal', $start, $end, 18, 0));
    $out = array_merge($out, $this->repeatDaily('Milk tank sanitation', 'cleaning', 'high', $start, $end, 20, 0));
    $out = array_merge($out, $this->repeatDaily('Morning herd inspection', 'inspection', 'normal', $start, $end, 6, 0));

    // Weekly routines.
    $out = array_merge($out, $this->repeatWeekly('Barn deep clean', 'cleaning', 'high', $start, $end, 1, 9, 0));  // Monday
    $out = array_merge($out, $this->repeatWeekly('Filter replacement check', 'maintenance', 'normal', $start, $end, 5, 11, 0)); // Friday

    // Monthly routines.
    $out = array_merge($out, $this->repeatMonthlyOnDay('Vaccination clinic', 'vaccination', 'high', $start, $end, 15, 10, 0));
    $out = array_merge($out, $this->repeatMonthlyOnDay('Hoof care review', 'health', 'normal', $start, $end, 1, 14, 0));

    foreach ($out as &$t) {
      $t['id'] = $t['category'] . ':' . md5($t['title'] . '|' . $t['due_ts']);
    }
    unset($t);

    return array_values(array_filter($out, fn($t) => $t['due_ts'] >= $startTs && $t['due_ts'] <= $endTs));
  }

  /* ---------------- Stored tasks loader (hardened) ---------------- */

  private function loadStored(int $startTs, int $endTs): array {
    // If service isn’t defined or class can’t be autoloaded, safely skip.
    if (!\Drupal::hasService('tesfana_dairy_farm.task_store')) {
      return [];
    }
    // Extra guard against class autoload failures.
    if (!class_exists(\Drupal\tesfana_dairy_farm\Service\TaskStoreService::class)) {
      return [];
    }
    try {
      /** @var \Drupal\tesfana_dairy_farm\Service\TaskStoreService $store */
      $store = \Drupal::service('tesfana_dairy_farm.task_store');
      return $store->loadBetween($startTs, $endTs);
    } catch (\Throwable $e) {
      // Never fatal the dashboard if the service can’t be created.
      \Drupal::logger('tesfana_dairy_farm')->error('TaskStoreService unavailable: @m', ['@m' => $e->getMessage()]);
      return [];
    }
  }

  /* ---------------- Helpers ---------------- */

  private function dayBounds(int $daysAgo, \DateTimeZone $tz): array {
    $d = new \DateTimeImmutable('now', $tz);
    $d = $d->setTime(0, 0, 0);
    if ($daysAgo > 0) $d = $d->modify("-{$daysAgo} day");
    $start = $d->getTimestamp();
    $end   = $d->setTime(23, 59, 59)->getTimestamp();
    return [$start, $end];
  }

  private function repeatDaily(string $title, string $category, string $priority, \DateTimeImmutable $start, \DateTimeImmutable $end, int $hour, int $minute): array {
    $out = [];
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
      $out[] = ['title' => $title, 'category' => $category, 'priority' => $priority, 'due_ts' => $d->setTime($hour, $minute, 0)->getTimestamp()];
    }
    return $out;
  }

  private function repeatWeekly(string $title, string $category, string $priority, \DateTimeImmutable $start, \DateTimeImmutable $end, int $weekday, int $hour, int $minute): array {
    $out = [];
    $cursor = $start;
    if ((int) $cursor->format('N') !== $weekday) {
      $cursor = $cursor->modify('next ' . ['', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'][$weekday]);
    }
    for (; $cursor <= $end; $cursor = $cursor->modify('+1 week')) {
      $out[] = ['title' => $title, 'category' => $category, 'priority' => $priority, 'due_ts' => $cursor->setTime($hour, $minute, 0)->getTimestamp()];
    }
    return $out;
  }

  private function repeatMonthlyOnDay(string $title, string $category, string $priority, \DateTimeImmutable $start, \DateTimeImmutable $end, int $dom, int $hour, int $minute): array {
    $out = [];
    $cursor = $start->setDate((int) $start->format('Y'), (int) $start->format('m'), 1)->setTime($hour, $minute, 0);
    for (; $cursor <= $end; $cursor = $cursor->modify('first day of next month')) {
      $dt = $cursor->setDate((int) $cursor->format('Y'), (int) $cursor->format('m'), min($dom, (int) $cursor->format('t')));
      $ts = $dt->getTimestamp();
      if ($ts >= $start->getTimestamp() && $ts <= $end->getTimestamp()) {
        $out[] = ['title' => $title, 'category' => $category, 'priority' => $priority, 'due_ts' => $ts];
      }
    }
    return $out;
  }

}
