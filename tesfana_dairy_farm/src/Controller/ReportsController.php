<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\tesfana_dairy_farm\Service\TesfanaData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reports page: milk over time + CSV export (farmOS-native).
 */
final class ReportsController extends ControllerBase {

  public function __construct(private readonly TesfanaData $data) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('tesfana_dairy_farm.data'));
  }

  /**
   * Render the reports page.
   */
  public function view(Request $request): array {
    // Filter: last N days (defaults to 30).
    $days = (int) $request->query->get('days', 30);
    if ($days < 1 || $days > 365) {
      $days = 30;
    }

    $series = $this->data->getMilkDailySeries($days);

    // CSV export link with same filter.
    $export_url = Url::fromRoute(
      'tesfana_dairy_farm.reports_export_csv',
      [],
      ['query' => ['days' => $days]]
    )->toString();

    return [
      '#theme' => 'report_view',
      '#data' => [
        'series' => $series,
        'filters' => [
          'days' => $days,
          'export_url' => $export_url,
        ],
      ],
      '#attached' => [
        'drupalSettings' => [
          'tesfana' => [
            'reports' => [
              'series' => $series,
            ],
          ],
        ],
      ],
    ];
  }

}
