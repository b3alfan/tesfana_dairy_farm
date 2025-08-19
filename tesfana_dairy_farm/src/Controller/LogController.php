<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lists FarmOS log entries.
 */
class LogController extends ControllerBase {

  public function list(string $type, Request $request): array {
    $storage = $this->entityTypeManager()->getStorage('farm_log');
    $ids = $storage->getQuery()
      ->condition('type', $type)
      ->sort('timestamp', 'DESC')
      ->execute();
    $logs = $storage->loadMultiple($ids);

    return [
      '#theme'    => 'log_list',
      '#logs'     => $logs,
      '#log_type' => $type,
      '#attached' => ['library' => ['tesfana_dairy_farm/ui']],
    ];
  }

  public static function title(string $type): string {
    return match($type) {
      'milk_log'   => t('Milk Logs'),
      'feed_log'   => t('Feed Logs'),
      'health_log' => t('Health Logs'),
      default      => t('@type Logs', ['@type' => $type]),
    };
  }

}
