<?php

namespace Drupal\tesfana_dairy_farm\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Minimal service to (eventually) regenerate chart snapshots.
 * This is a safe placeholder that won't break your site.
 */
class ChartSnapshotService {

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Regenerate snapshots for all cows.
   *
   * @param array $options
   *   Options: limit (int), since (string Y-m-d), dryRun (bool).
   *
   * @return array
   *   Summary: ['processed' => int].
   */
  public function regenerateAll(array $options = []): array {
    $limit = isset($options['limit']) ? (int) $options['limit'] : 0;
    $dry   = !empty($options['dryRun']);

    // In real impl: load asset list (type cow), loop and call regenerateForCow().
    // For now, just log a message so Drush and UI can complete successfully.
    $msg = 'ChartSnapshotService::regenerateAll invoked';
    if ($limit) { $msg .= " (limit={$limit})"; }
    if ($dry)   { $msg .= " [DRY RUN]"; }
    $this->logger->notice($msg);

    return ['processed' => 0];
  }

  /**
   * Regenerate for a single cow by asset ID.
   */
  public function regenerateForCow(int|string $asset_id, array $options = []): bool {
    $dry = !empty($options['dryRun']);
    $this->logger->notice('Regenerating chart snapshot for asset @id@dry', [
      '@id'  => $asset_id,
      '@dry' => $dry ? ' [DRY RUN]' : '',
    ]);
    // No-op placeholder; return true to indicate success.
    return true;
  }

}
