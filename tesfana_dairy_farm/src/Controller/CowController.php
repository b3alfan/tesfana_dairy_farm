<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_asset\AssetRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists cows and shows individual profiles.
 */
class CowController extends ControllerBase {

  public function __construct(protected AssetRepositoryInterface $repo) {}

  public static function create(ContainerInterface $c): static {
    return new static($c->get('farm_asset.asset_repository'));
  }

  public function list(): array {
    $cows  = $this->repo->loadByType('cow');
    $header= [t('Name'), t('Tag'), t('Status'), t('Operations')];
    $rows  = [];
    foreach ($cows as $cow) {
      $rows[] = [
        'data' => [
          $cow->label(),
          $cow->get('field_tag_number')->value,
          $cow->get('field_status')->value,
          $this->l(t('View'), \Drupal\Core\Url::fromRoute('tesfana_dairy_farm.cow_view', ['id' => $cow->id()])),
        ],
      ];
    }
    return [
      '#type'     => 'table',
      '#header'   => $header,
      '#rows'     => $rows,
      '#empty'    => t('No cows found.'),
      '#attached' => ['library' => ['tesfana_dairy_farm/ui']],
    ];
  }

  public function view(int $id): array {
    $cows = $this->repo->loadByType('cow');
    if (empty($cows[$id])) {
      throw new NotFoundHttpException();
    }
    return [
      '#theme'    => 'cow_profile',
      '#cow'      => $cows[$id],
      '#attached' => [
        'library'        => ['tesfana_dairy_farm/ui', 'tesfana_dairy_farm/charts'],
        'drupalSettings' => ['tesfana' => ['cowId' => $id]],
      ],
    ];
  }

}
