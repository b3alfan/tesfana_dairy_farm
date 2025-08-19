<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

final class MilkLogListBuilder extends EntityListBuilder {

  public function buildHeader(): array {
    $header['date'] = $this->t('Date');
    $header['cow_tag'] = $this->t('Cow tag');
    $header['am_yield'] = $this->t('AM (L)');
    $header['pm_yield'] = $this->t('PM (L)');
    $header['total'] = $this->t('Total (L)');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\tesfana_dairy_farm\Entity\MilkLog $entity */
    $row['date'] = $entity->getDate();
    $row['cow_tag'] = $entity->getCowTag();
    $row['am_yield'] = number_format($entity->getAmYield(), 2, '.', '');
    $row['pm_yield'] = number_format($entity->getPmYield(), 2, '.', '');
    $row['total'] = number_format($entity->getTotalYield(), 2, '.', '');
    return $row + parent::buildRow($entity);
  }

  public function render(): array {
    $build = parent::render();

    // Add total row.
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $total_am = 0.0; $total_pm = 0.0;
    if ($ids) {
      $entities = $storage->loadMultiple($ids);
      foreach ($entities as $e) {
        /** @var \Drupal\tesfana_dairy_farm\Entity\MilkLog $e */
        $total_am += $e->getAmYield();
        $total_pm += $e->getPmYield();
      }
    }
    $build['table']['#rows'][] = [
      'data' => [
        ['data' => $this->t('Totals'), 'colspan' => 2],
        number_format($total_am, 2, '.', ''),
        number_format($total_pm, 2, '.', ''),
        number_format($total_am + $total_pm, 2, '.', ''),
      ],
      'class' => ['milk-log-total-row'],
    ];

    return $build;
  }

}
