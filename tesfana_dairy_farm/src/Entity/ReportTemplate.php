<?php

namespace Drupal\tesfana_dairy_farm\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * @ConfigEntityType(
 *   id = "tesfana_report_template",
 *   label = @Translation("Report Template"),
 *   handlers = {
 *     "list_builder" = "Drupal\tesfana_dairy_farm\Form\ReportTemplateListBuilder",
 *     "form" = {
 *       "add" = "Drupal\tesfana_dairy_farm\Form\ReportTemplateForm",
 *       "edit" = "Drupal\tesfana_dairy_farm\Form\ReportTemplateForm",
 *       "delete" = "Drupal\tesfana_dairy_farm\Form\ReportTemplateDeleteForm"
 *     }
 *   },
 *   config_prefix = "template",
 *   admin_permission = "administer tesfana dairy farm",
 *   entity_keys = {
 *     "id"    = "id",
 *     "label" = "label",
 *     "uuid"  = "uuid"
 *   },
 *   config_export = {"id","label","metrics","chart_types"},
 *   links = {
 *     "collection" = "/admin/tesfana-dairy/report-templates",
 *     "add-form"   = "/admin/tesfana-dairy/report-templates/add",
 *     "edit-form"  = "/admin/tesfana-dairy/report-templates/{tesfana_report_template}/edit",
 *     "delete-form"= "/admin/tesfana-dairy/report-templates/{tesfana_report_template}/delete"
 *   }
 * )
 */
class ReportTemplate extends ConfigEntityBase implements ReportTemplateInterface {
  protected string $id;
  protected string $label;
  protected array $metrics     = [];
  protected array $chart_types = [];

  public function getMetrics(): array { return $this->metrics; }
  public function setMetrics(array $metrics) { $this->metrics = $metrics; return $this; }
  public function getChartTypes(): array  { return $this->chart_types; }
  public function setChartTypes(array $types) { $this->chart_types = $types; return $this; }
}
