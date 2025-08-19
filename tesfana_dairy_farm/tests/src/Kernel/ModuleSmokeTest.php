<?php

namespace Drupal\Tests\tesfana_dairy_farm\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @group tesfana_dairy_farm
 * Ensures the module is enabled, services are registered, and critical routes exist.
 */
class ModuleSmokeTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'tesfana_dairy_farm',
  ];

  public function testModuleEnabled(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('tesfana_dairy_farm'));
  }

  public function testRequiredServicesAreWired(): void {
    $c = \Drupal::getContainer();
    $this->assertTrue($c->has('tesfana_dairy_farm.breeding_reminder_service'), 'BreedingReminderService registered.');
    $this->assertTrue($c->has('tesfana_dairy_farm.anomaly_detection_service'), 'AnomalyDetectionService registered.');
  }

  public function testOptionalServicesPresentIfConfigured(): void {
    $c = \Drupal::getContainer();
    if (!$c->has('tesfana_dairy_farm.chart_snapshot_service')) {
      $this->markTestSkipped('chart_snapshot_service not defined (optional).');
    }
    else {
      $this->assertTrue($c->has('tesfana_dairy_farm.chart_snapshot_service'));
    }
  }

  public function testRequiredRoutesExist(): void {
    $provider = \Drupal::service('router.route_provider');
    $required = [
      'tesfana_dairy_farm.dashboard',
      'tesfana_dairy_farm.cow_profile',
    ];
    foreach ($required as $name) {
      try {
        $this->assertNotNull($provider->getRouteByName($name), sprintf('Route "%s" exists.', $name));
      }
      catch (\Throwable $e) {
        $this->fail(sprintf('Missing REQUIRED route "%s": %s', $name, $e->getMessage()));
      }
    }
  }

  public function testOptionalRoutesExistIfConfigured(): void {
    $provider = \Drupal::service('router.route_provider');
    $optional = [
      'tesfana_dairy_farm.milk_export_csv',
      'tesfana_dairy_farm.milk_export_pdf',
      'tesfana_dairy_farm.bulk_export_pdf',
      'tesfana_dairy_farm.anomaly_logs',
      'tesfana_dairy_farm.calendar',
      'tesfana_dairy_farm.form_queue_inspector',
    ];
    foreach ($optional as $name) {
      try {
        $provider->getRouteByName($name);
        $this->assertTrue(true, sprintf('Optional route "%s" present.', $name));
      }
      catch (\Throwable $e) {
        $this->markTestSkipped(sprintf('Optional route "%s" not defined.', $name));
      }
    }
  }
}
