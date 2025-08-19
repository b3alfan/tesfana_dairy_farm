<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the Form Queue Inspector page (client-side reads localStorage).
 */
class FormQueueInspectorController extends ControllerBase {

  public function view(): array {
    // Load cow tag list for filtering (best-effort, raw SQL to match your style).
    $db = \Drupal::database();
    $tags = [];
    try {
      $result = $db->select('cow', 'c')
        ->fields('c', ['tag_number'])
        ->orderBy('tag_number', 'ASC')
        ->range(0, 5000)
        ->execute();
      foreach ($result as $row) {
        if (!empty($row->tag_number)) {
          $tags[] = $row->tag_number;
        }
      }
    }
    catch (\Throwable $e) {
      // Ignore quietly; filter UI will just not show options.
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tesfana-form-queue-inspector']],
      'header' => [
        '#markup' => '<h1>Offline Form Queue Inspector</h1><p>This reads your browser\'s localStorage and lists queued forms. You can filter by cow tag, resubmit, export CSV/JSON, or clear items.</p>',
      ],
      'filters' => [
        '#markup' => '
          <div class="fqi-filters">
            <label>Filter by Cow Tag:</label>
            <select id="fqi-cow-filter"><option value="">-- All cows --</option></select>
            <input id="fqi-text-filter" type="text" placeholder="Text filter (key/payload)"/>
            <button class="fqi-apply-filters" type="button">Apply</button>
          </div>',
      ],
      'actions' => [
        '#markup' => '
          <div class="fqi-actions">
            <button class="fqi-refresh" type="button">Refresh</button>
            <button class="fqi-resubmit-selected" type="button">Resubmit Selected</button>
            <button class="fqi-resubmit-all" type="button">Resubmit All</button>
            <button class="fqi-clear-all" type="button">Clear All</button>
            <button class="fqi-download" type="button">Download JSON</button>
            <button class="fqi-download-csv" type="button">Download CSV</button>
          </div>',
      ],
      'content' => [
        '#markup' => '<div id="form-queue-inspector"></div>',
      ],
      '#attached' => [
        'library' => [
          'tesfana_dairy_farm/form_queue_inspector',
        ],
        'drupalSettings' => [
          'tesfana' => [
            'cowTags' => $tags,
            // Optional default endpoints map the JS can use if items lack an explicit "action".
            'queueTypeEndpoints' => [
              // Adapt these to your real handlers if needed.
              'cow' => '/admin/tesfana_dairy/cow/add',
              'milk_log' => '/admin/tesfana_dairy/milk-log/add',
              'feed_log' => '/admin/tesfana_dairy/feed-log/add',
              'health_log' => '/admin/tesfana_dairy/health-log/add',
              'breeding_entry' => '/admin/tesfana_dairy/breeding/add',
            ],
          ],
        ],
      ],
    ];
    return $build;
  }

}
