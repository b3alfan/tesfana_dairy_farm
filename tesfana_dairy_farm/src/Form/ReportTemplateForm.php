<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Config\Entity\ConfigEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for ReportTemplate entities.
 */
class ReportTemplateForm extends ConfigEntityForm {

  protected $entity;

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $e    = $this->entity;

    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Report name'),
      '#default_value' => $e->label(),
      '#required'      => TRUE,
    ];
    $form['id'] = [
      '#type'          => 'machine_name',
      '#title'         => $this->t('Machine name'),
      '#default_value' => $e->id(),
      '#machine_name'  => ['exists' => '\Drupal\tesfana_dairy_farm\Entity\ReportTemplate::load'],
      '#disabled'      => !$e->isNew(),
    ];

    $options = [
      'milk_today'        => $this->t('Milk Today'),
      'milk_yesterday'    => $this->t('Milk Yesterday'),
      'milk_total'        => $this->t('Total Milk'),
      'feed_conversion'   => $this->t('Feed Conversion'),
      'breeding_reminders'=> $this->t('Breeding Reminders'),
      'anomalies'         => $this->t('Anomalies'),
    ];
    $form['metrics'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Metrics to include'),
      '#options'       => $options,
      '#default_value' => $e->getMetrics(),
      '#required'      => TRUE,
    ];

    $chart_options = [
      'line' => $this->t('Line chart'),
      'bar'  => $this->t('Bar chart'),
      'pie'  => $this->t('Pie chart'),
    ];
    $form['chart_types'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Chart types'),
      '#options'       => $chart_options,
      '#default_value' => $e->getChartTypes(),
    ];

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    $e = $this->entity;
    $e->setMetrics(array_filter($form_state->getValue('metrics')));
    $e->setChartTypes(array_filter($form_state->getValue('chart_types')));
    parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved report template %name.', ['%name' => $e->label()]));
    $form_state->setRedirectUrl($e->toUrl('collection'));
  }

}
