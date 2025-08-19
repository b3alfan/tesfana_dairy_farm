<?php

namespace Drupal\tesfana_dairy_farm\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Lightweight CRUD for user-created tasks.
 *
 * Storage table: tesfana_task (declared in tesfana_dairy_farm.install).
 */
class TaskStoreService {

  public function __construct(
    private readonly Connection $db,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Add a new task.
   *
   * @return int
   *   Inserted task ID.
   */
  public function add(string $title, int $due_ts, string $category = 'other', string $priority = 'normal'): int {
    $now = \Drupal::time()->getRequestTime();

    // On PostgreSQL, Drupal's Insert::execute() returns the new serial ID using RETURNING.
    $id = (int) $this->db->insert('tesfana_task')
      ->fields([
        'title'    => $title,
        'due_ts'   => $due_ts,
        'category' => $category,
        'priority' => $priority,
        'created'  => $now,
        'changed'  => $now,
      ])
      ->execute();

    $this->logger->notice('Added task @id (@t) due @d.', [
      '@id' => $id,
      '@t'  => $title,
      '@d'  => $due_ts,
    ]);

    return $id;
  }

  /**
   * Load tasks in [start_ts, end_ts].
   *
   * @return array<int, array{ id:int, title:string, due_ts:int, category:string, priority:string }>
   */
  public function loadBetween(int $start_ts, int $end_ts): array {
    $q = $this->db->select('tesfana_task', 't')
      ->fields('t', ['id', 'title', 'due_ts', 'category', 'priority'])
      ->condition('due_ts', [$start_ts, $end_ts], 'BETWEEN')
      ->orderBy('due_ts', 'ASC');

    $rows = $q->execute()->fetchAllAssoc('id');

    $out = [];
    foreach ($rows as $row) {
      $out[] = [
        'id'       => (int) $row->id,
        'title'    => (string) $row->title,
        'due_ts'   => (int) $row->due_ts,
        'category' => (string) $row->category,
        'priority' => (string) $row->priority,
      ];
    }
    return $out;
  }

}
