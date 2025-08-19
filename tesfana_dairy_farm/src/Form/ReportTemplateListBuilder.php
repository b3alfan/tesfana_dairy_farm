<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for report templates.
 */
class ReportTemplateListBuilder extends ConfigEntityListBuilder {

  public function buildHeader() {
    $header['label']   = $this->t('Report name');
    $header['metrics'] = $this->t('Metrics');
    $header['charts']  = $this->t('Chart types');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\tesfana_dairy_farm\Entity\ReportTemplate $e */
    $e = $entity;
    $row['label']   = $e->label();
    $row['metrics'] = implode(', ', $e->getMetrics());
    $row['charts']  = implode(', ', $e->getChartTypes());
    return $row + parent::buildRow($entity);
  }

}
