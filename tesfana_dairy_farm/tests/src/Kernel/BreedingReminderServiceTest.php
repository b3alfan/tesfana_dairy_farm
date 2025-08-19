<?php
// web/modules/custom/tesfana_dairy_farm/tests/src/Unit/BreedingReminderServiceTest.php

declare(strict_types=1);

namespace Drupal\Tests\tesfana_dairy_farm\Unit;

use Drupal\tesfana_dairy_farm\Service\BreedingReminderService;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\tesfana_dairy_farm\Service\BreedingReminderService
 */
final class BreedingReminderServiceTest extends TestCase {

  /**
   * @covers ::getAllReminders
   */
  public function testGetAllRemindersReturnsArray(): void {
    $db = $this->createMock(Connection::class);
    $cache = $this->createMock(CacheBackendInterface::class);

    $service = new BreedingReminderService($db, $cache);
    $all = $service->getAllReminders();
    $filtered = $service->getAllReminders('COW-001');

    $this->assertIsArray($all);
    $this->assertIsArray($filtered);
  }

}