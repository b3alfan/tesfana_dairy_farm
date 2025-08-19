<?php

namespace Drupal\Tests\tesfana_dairy_farm\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @group tesfana_dairy_farm
 * Basic contract test: method returns an array, even with no data.
 */
class BreedingReminderServiceKernelTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'tesfana_dairy_farm',
  ];

  public function testGetAllRemindersReturnsArray(): void {
    $service = $this->container->get('tesfana_dairy_farm.breeding_reminder_service');
    $result = $service->getAllReminders('TEST-TAG-0000');
    $this->assertIsArray($result);
  }
}
