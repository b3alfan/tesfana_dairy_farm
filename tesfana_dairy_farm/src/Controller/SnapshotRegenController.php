<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Stub endpoint for chart snapshot regeneration.
 * Replace with real service calls later.
 */
final class SnapshotRegenController extends ControllerBase {

  public function run(): RedirectResponse {
    // TODO: inject and call your snapshot service here.
    $this->messenger()->addStatus($this->t('Chart regeneration started (stub).'));
    $url = Url::fromRoute('tesfana_dairy_farm.dashboard')->toString();
    return new RedirectResponse($url);
  }

}
