<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\tesfana_dairy_farm\Service\TesfanaData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export that mirrors the report series (farmOS logs).
 */
final class ReportsExportController extends ControllerBase {

  public function __construct(private readonly TesfanaData $data) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('tesfana_dairy_farm.data'));
  }

  /**
   * Stream CSV for the current report range.
   */
  public function csv(Request $request): StreamedResponse {
    $days = (int) $request->query->get('days', 30);
    if ($days < 1 || $days > 365) {
      $days = 30;
    }

    $series = $this->data->getMilkDailySeries($days);

    $response = new StreamedResponse(function () use ($series) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Date', 'Total Milk (L)']);
      foreach ($series as $row) {
        fputcsv($out, [$row['date'], number_format((float) $row['total'], 2, '.', '')]);
      }
      fclose($out);
    });

    $filename = 'tesfana_milk_' . date('Ymd_His') . '.csv';
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}
