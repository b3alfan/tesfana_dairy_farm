<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\tesfana_dairy_farm\Service\TesfanaData;
use Drupal\asset\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class CowProfileController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(private readonly TesfanaData $data) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('tesfana_dairy_farm.data'));
  }

  public function title(Asset $asset): string {
    return $this->t('Cow: @label', ['@label' => $asset->label()]);
  }

  private function action(string $title, string $route, array $params = [], array $query = [], array $classes = []): ?array {
    try {
      \Drupal::service('router.route_provider')->getRouteByName($route);
      $url = Url::fromRoute($route, $params, $query ? ['query' => $query] : []);
      $link = Link::fromTextAndUrl($title, $url)->toRenderable();
      $link['#attributes']['class'] = array_merge(['btn'], $classes);
      return $link;
    } catch (RouteNotFoundException $e) {
      return NULL;
    } catch (\Throwable $e) {
      return NULL;
    }
  }

  public function view(Asset $asset): array {
    $asset_id = (int) $asset->id();

    $summary    = $this->data->getCowSummary($asset_id);
    $series     = $this->data->getCowMilkWeeklySeries($asset_id, 30);
    $vaccs      = $this->data->getCowVaccinations($asset_id, 10);
    $bcs        = $this->data->getCowBcsLatest($asset_id);

    $prodDaily  = $this->data->getCowDailyProduction($asset_id, 14);
    $quality    = $this->data->getCowMilkQuality($asset_id);
    $repro      = $this->data->getCowRepro($asset_id);
    $health     = $this->data->getCowHealth($asset_id, 365);
    $feeding    = $this->data->getCowFeeding($asset_id, 30);
    $behavior   = $this->data->getCowBehavior($asset_id);
    $finance    = $this->data->getCowFinancial($asset_id);

    $cow = [
      'id'     => $asset_id,
      'label'  => $asset->label(),
      'tag'    => $asset->hasField('id_tag') ? (string) $asset->get('id_tag')->value : NULL,
      'status' => $asset->hasField('status') ? (string) $asset->get('status')->value : NULL,
    ];

    $qa = array_values(array_filter([
      $this->action('📝 ' . $this->t('Milk (Quick)')->render(), 'tesfana_dairy_farm.milk_quick_form', [], ['asset' => $asset_id], ['btn-primary']),
      $this->action('📊 ' . $this->t('Milk Quality')->render(), 'tesfana_dairy_farm.milk_quality_form', [], ['asset' => $asset_id], ['btn-outline']),
      $this->action('🧮 ' . $this->t('Record BCS')->render(), 'tesfana_dairy_farm.bcs_quick_form', [], ['asset' => $asset_id], ['btn-outline']),
      $this->action('✏️ ' . $this->t('Edit Cow')->render(), 'entity.asset.edit_form', ['asset' => $asset_id], [], ['btn-outline']),
    ]));

    return [
      '#theme' => 'cow_profile',
      '#cow' => $cow,
      '#kpis' => [
        'milk_7d' => $summary['milk_7d'],
        'milk_30d' => $summary['milk_30d'],
        'avg_daily_30d' => $summary['avg_daily_30d'],
      ],
      '#charts' => ['milkWeekly' => $series],
      '#vaccinations' => $vaccs,
      '#bcs' => $bcs,
      '#quick_actions' => $qa,

      '#production' => $prodDaily,
      '#quality'    => $quality,
      '#repro'      => $repro,
      '#health'     => $health,
      '#feeding'    => $feeding,
      '#behavior'   => $behavior,
      '#finance'    => $finance,

      '#attached' => [
        'drupalSettings' => [
          'tesfana' => [
            'cow' => [
              'id' => $asset_id,
              'milkWeekly' => $series,
            ],
          ],
        ],
      ],
    ];
  }

}
