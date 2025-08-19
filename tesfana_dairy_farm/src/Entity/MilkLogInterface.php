<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface MilkLogInterface extends ContentEntityInterface {

  public function getDate(): ?string;

  public function setDate(string $date): self;

  public function getAmYield(): float;

  public function setAmYield(float $value): self;

  public function getPmYield(): float;

  public function setPmYield(float $value): self;

  public function getTotalYield(): float;

  public function getCowId(): ?int;

  public function setCowId(?int $id): self;

  public function getCowTag(): ?string;

  public function setCowTag(?string $tag): self;

  public function getNotes(): ?string;

  public function setNotes(?string $notes): self;

}
