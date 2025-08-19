<?php

namespace Drupal\tesfana_dairy_farm\Drush\Commands;

use Drupal\tesfana_dairy_farm\Service\ChartSnapshotService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Tesfana chart snapshot operations.
 */
class ChartSnapshotCommands extends DrushCommands {

  public function __construct(
    protected ChartSnapshotService $snapshots,
  ) {}

  /**
   * Refresh chart snapshots for all cows.
   *
   * @command tesfana:charts:refresh
   * @option limit Limit number of cows to process.
   * @option dry-run Do not write anything, only simulate.
   *
   * @usage drush tesfana:charts:refresh --limit=50
   * @aliases tcr
   */
  public function refresh(array $options = ['limit' => 0, 'dry-run' => false]): int {
    $limit = (int) ($options['limit'] ?? 0);
    $dry = !empty($options['dry-run']);

    $summary = $this->snapshots->regenerateAll([
      'limit' => $limit,
      'dryRun' => $dry,
    ]);

    $this->logger()->notice(dt('Tesfana charts refresh complete. Processed: @n', ['@n' => $summary['processed'] ?? 0]));
    return 0;
  }

  /**
   * Refresh chart snapshot for a single cow.
   *
   * @command tesfana:charts:refresh-one
   * @param int $asset_id
   *   Asset ID of the cow.
   * @option dry-run Do not write anything, only simulate.
   * @aliases tcr1
   */
  public function refreshOne($asset_id, array $options = ['dry-run' => false]): int {
    $dry = !empty($options['dry-run']);

    $ok = $this->snapshots->regenerateForCow($asset_id, ['dryRun' => $dry]);
    if ($ok) {
      $this->logger()->success(dt('Refreshed snapshot for asset @id.', ['@id' => $asset_id]));
      return 0;
    }
    $this->logger()->error(dt('Failed to refresh snapshot for asset @id.', ['@id' => $asset_id]));
    return 1;
  }

}
