<?php

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CowListController extends ControllerBase {
  public function list(): RedirectResponse {
    return new RedirectResponse(Url::fromUserInput('/assets/animal')->toString(), 302);
  }
}
