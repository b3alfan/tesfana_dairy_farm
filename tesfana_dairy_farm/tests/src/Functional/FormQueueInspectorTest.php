<?php

namespace Drupal\Tests\tesfana_dairy_farm\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group tesfana_dairy_farm
 */
class FormQueueInspectorTest extends BrowserTestBase {

  protected static $modules = ['system', 'user', 'tesfana_dairy_farm'];
  protected $defaultTheme = 'stark';

  public function testInspectorLoads(): void {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/tesfana_dairy/form-queue-inspector');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Offline Form Queue Inspector');
  }
}
