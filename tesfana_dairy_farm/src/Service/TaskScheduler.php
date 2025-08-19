<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

final class TaskScheduler {

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  /**
   * Seed future weekly tasks for each cow.
   *
   * @param int    $weeksAhead   How many weeks in the future to create.
   * @param int    $startTs      Start timestamp (defaults to next Monday 08:00).
   * @param bool   $seedBcs      Create BCS tasks.
   * @param string $bcsTime      HH:MM for BCS (e.g. '08:00').
   * @param bool   $seedQuality  Create Milk Quality tasks.
   * @param string $qualityTime  HH:MM for Milk Quality (e.g. '10:00').
   */
  public function seedWeekly(
    int $weeksAhead = 8,
    ?int $startTs = NULL,
    bool $seedBcs = true,
    string $bcsTime = '08:00',
    bool $seedQuality = true,
    string $qualityTime = '10:00'
  ): int {
    $created = 0;

    // Determine start timestamp (next Monday 08:00) if not provided.
    if ($startTs === NULL) {
      $start = new \DateTimeImmutable('next monday');
      [$bh, $bm] = explode(':', $bcsTime);
      $start = $start->setTime((int) $bh, (int) $bm);
      $startTs = $start->getTimestamp();
    }

    $assets = $this->loadCows();
    if (!$assets) return 0;

    $log_storage = $this->etm->getStorage('log');

    foreach ($assets as $asset) {
      $aid = (int) $asset->id();

      for ($w = 0; $w < $weeksAhead; $w++) {
        $weekBase = $startTs + ($w * 7 * 86400);

        if ($seedBcs) {
          $ts = $weekBase; // same day/time as start
          $log = $log_storage->create([
            'type' => 'activity',
            'name' => (string) t('Task: Record BCS'),
            'timestamp' => $ts,
            'status' => 1,
            'asset' => [['target_id' => $aid]],
            'notes' => [['value' => (string) t('Weekly BCS assessment')]],
          ]);
          $log->save();
          $created++;
        }

        if ($seedQuality) {
          // Same week, different time (qualityTime)
          [$qh, $qm] = explode(':', $qualityTime);
          $qDate = (new \DateTimeImmutable("@$weekBase"))->setTime((int) $qh, (int) $qm);
          $log = $log_storage->create([
            'type' => 'activity',
            'name' => (string) t('Task: Milk quality sample'),
            'timestamp' => $qDate->getTimestamp(),
            'status' => 1,
            'asset' => [['target_id' => $aid]],
            'notes' => [['value' => (string) t('Weekly fat/protein/SCC sample')]],
          ]);
          $log->save();
          $created++;
        }
      }
    }

    return $created;
  }

  /** @return \Drupal\asset\Entity\Asset[] */
  private function loadCows(): array {
    $s = $this->etm->getStorage('asset');
    $q = $s->getQuery()->accessCheck(TRUE)->condition('type', 'animal')->range(0, 5000);
    $ids = $q->execute();
    return $ids ? $s->loadMultiple($ids) : [];
  }

}
