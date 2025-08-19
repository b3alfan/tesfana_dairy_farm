<?php

namespace Drupal\Tests\tesfana_dairy_farm\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @group tesfana_dairy_farm
 * Protects the agreed tag format: LOC-BR-YY-NICK-0001.
 */
class TagFormatValidatorTest extends UnitTestCase {

  /**
   * @dataProvider validTags
   */
  public function testValidTags(string $tag): void {
    $this->assertMatchesRegularExpression(
      '/^[A-Z]{2,5}-[A-Z]{2}-\d{2}-[A-Z]{3,6}-\d{4}$/',
      $tag
    );
  }

  public function validTags(): array {
    return [
      ['ASM-FR-25-LUNA-0001'],
      ['ASM-HF-24-BELU-1023'],
      ['KSM-JR-27-MILK-9999'],
    ];
  }

  /**
   * @dataProvider invalidTags
   */
  public function testInvalidTags(string $tag): void {
    $this->assertDoesNotMatchRegularExpression(
      '/^[A-Z]{2,5}-[A-Z]{2}-\d{2}-[A-Z]{3,6}-\d{4}$/',
      $tag
    );
  }

  public function invalidTags(): array {
    return [
      ['asm-FR-25-LUNA-0001'],   // lowercase LOC
      ['ASM-FR-2025-LUNA-0001'], // 4-digit year
      ['ASM-FR-25-L-0001'],      // short nickname
      ['ASM-FR-25-LUNA-1'],      // short serial
      ['ASM-FR-25-LUNA-00001'],  // long serial
      ['ASMFR25LUNA0001'],       // missing dashes
    ];
  }
}
