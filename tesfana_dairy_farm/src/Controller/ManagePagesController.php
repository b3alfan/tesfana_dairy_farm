<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Simple stubs for admin pages referenced by routes.
 * Replace with full implementations later.
 */
class ManagePagesController extends ControllerBase {

  /**
   * /admin/tesfana_dairy/milk-logs
   */
  public function milkLogs(): array {
    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h2>Milk Logs</h2>',
      ],
      'body' => [
        '#markup' => '<p>This is a placeholder page for Milk Logs. Use <a href="/milk/quick-add">Quick Milk Log</a> to add entries. Export via the buttons on the dashboard.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * /admin/tesfana_dairy/bcs
   */
  public function bcs(): array {
    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h2>Body Condition Scores</h2>',
      ],
      'body' => [
        '#markup' => '<p>Placeholder for BCS management.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * /admin/tesfana_dairy/milk-tests
   */
  public function milkTests(): array {
    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h2>Milk Quality Tests</h2>',
      ],
      'body' => [
        '#markup' => '<p>Placeholder for Milk Quality Tests.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
