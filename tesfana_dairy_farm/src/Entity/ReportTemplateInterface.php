<?php

namespace Drupal\tesfana_dairy_farm\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface ReportTemplateInterface extends ConfigEntityInterface {
  public function getMetrics(): array;
  public function setMetrics(array $metrics);
  public function getChartTypes(): array;
  public function setChartTypes(array $types);
}


