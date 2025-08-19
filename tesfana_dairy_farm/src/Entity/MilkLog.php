<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Milk Log entity.
 *
 * @ContentEntityType(
 *   id = "milk_log",
 *   label = @Translation("Milk log"),
 *   label_collection = @Translation("Milk logs"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\tesfana_dairy_farm\MilkLogListBuilder",
 *     "form" = {
 *       "add" = "Drupal\tesfana_dairy_farm\Form\MilkLogForm",
 *       "edit" = "Drupal\tesfana_dairy_farm\Form\MilkLogForm",
 *       "delete" = "Drupal\tesfana_dairy_farm\Form\MilkLogDeleteForm"
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer tesfana dairy farm",
 *   base_table = "milk_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/tesfana-dairy/milk-log/{milk_log}",
 *     "edit-form" = "/admin/tesfana-dairy/milk-log/{milk_log}/edit",
 *     "delete-form" = "/admin/tesfana-dairy/milk-log/{milk_log}/delete",
 *     "collection" = "/admin/tesfana-dairy/milk-logs",
 *     "add-form" = "/admin/tesfana-dairy/milk-log/add"
 *   }
 * )
 */
final class MilkLog extends ContentEntityBase implements MilkLogInterface {
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    // Date (date-only).
    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setRequired(TRUE)
      ->setSettings(['datetime_type' => 'date'])
      ->setDefaultValueCallback(static::class . '::todayDefault')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Cow numeric ID (if you later wire to your own Cow entity/table).
    $fields['cow_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Cow ID'))
      ->setRequired(FALSE)
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -9,
        'settings' => ['placeholder' => t('e.g., 12')],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Cow tag (text identifier; used in exports and lists).
    $fields['cow_tag'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cow tag'))
      ->setSettings(['max_length' => 64])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -8,
        'settings' => ['placeholder' => t('e.g., ASM-FR-25-LUNA-0001')],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // AM yield.
    $fields['am_yield'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('AM yield (L)'))
      ->setRequired(TRUE)
      ->setSettings(['precision' => 10, 'scale' => 2])
      ->setDefaultValue('0.00')
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -7,
        'settings' => ['min' => '0', 'step' => '0.01'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // PM yield.
    $fields['pm_yield'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('PM yield (L)'))
      ->setRequired(TRUE)
      ->setSettings(['precision' => 10, 'scale' => 2])
      ->setDefaultValue('0.00')
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -6,
        'settings' => ['min' => '0', 'step' => '0.01'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Notes.
    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Created/changed.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

  public static function todayDefault(): array {
    return [date('Y-m-d')];
  }

  public function getDate(): ?string {
    $value = $this->get('date')->value;
    return $value ? substr($value, 0, 10) : NULL;
  }
  public function setDate(string $date): self {
    $this->set('date', $date);
    return $this;
  }

  public function getAmYield(): float {
    return (float) $this->get('am_yield')->value;
  }
  public function setAmYield(float $value): self {
    $this->set('am_yield', $value);
    return $this;
  }

  public function getPmYield(): float {
    return (float) $this->get('pm_yield')->value;
  }
  public function setPmYield(float $value): self {
    $this->set('pm_yield', $value);
    return $this;
  }

  public function getTotalYield(): float {
    return $this->getAmYield() + $this->getPmYield();
  }

  public function getCowId(): ?int {
    $v = $this->get('cow_id')->value;
    return $v === NULL ? NULL : (int) $v;
  }
  public function setCowId(?int $id): self {
    $this->set('cow_id', $id);
    return $this;
  }

  public function getCowTag(): ?string {
    $v = $this->get('cow_tag')->value;
    return $v === NULL ? NULL : (string) $v;
  }
  public function setCowTag(?string $tag): self {
    $this->set('cow_tag', $tag);
    return $this;
  }

  public function getNotes(): ?string {
    $v = $this->get('notes')->value;
    return $v === NULL ? NULL : (string) $v;
  }
  public function setNotes(?string $notes): self {
    $this->set('notes', $notes);
    return $this;
  }

}
