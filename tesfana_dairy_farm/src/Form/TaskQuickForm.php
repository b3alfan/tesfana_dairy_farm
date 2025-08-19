<?php

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;

class TaskQuickForm extends FormBase {

  public function getFormId(): string {
    return 'tesfana_task_quick_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'tesfana-quick-form';
    $form['#attached']['library'][] = 'tesfana_dairy_farm/quick_form_ui';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Task title'),
      '#required' => TRUE,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Due date'),
      '#required' => TRUE,
      '#default_value' => (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('Y-m-d'),
    ];

    $form['time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Due time (HH:MM, 24h)'),
      '#required' => TRUE,
      '#default_value' => '09:00',
      '#size' => 8,
      '#maxlength' => 8,
      '#description' => $this->t('Example: 14:30'),
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => [
        'cleaning' => $this->t('Cleaning'),
        'vaccination' => $this->t('Vaccination'),
        'maintenance' => $this->t('Maintenance'),
        'inspection' => $this->t('Inspection'),
        'health' => $this->t('Health'),
        'other' => $this->t('Other'),
      ],
      '#default_value' => 'other',
    ];

    $form['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#options' => [
        'low' => $this->t('Low'),
        'normal' => $this->t('Normal'),
        'high' => $this->t('High'),
      ],
      '#default_value' => 'normal',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];
    $form['actions']['save_add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save & add another'),
      '#button_type' => 'secondary',
      '#submit' => ['::submitAndAddAnother'],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $time = (string) $form_state->getValue('time');
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
      $form_state->setErrorByName('time', $this->t('Time must be in HH:MM format.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->saveTaskFromForm($form_state);
    $this->messenger()->addStatus($this->t('Task saved.'));
    $form_state->setRedirectUrl(Url::fromRoute('tesfana_dairy_farm.dashboard'));
  }

  public function submitAndAddAnother(array &$form, FormStateInterface $form_state): void {
    $this->saveTaskFromForm($form_state);
    $this->messenger()->addStatus($this->t('Task saved. Add another.'));
    $form_state->setRedirect('tesfana_dairy_farm.task_quick_form');
  }

  private function saveTaskFromForm(FormStateInterface $form_state): void {
    $title = (string) $form_state->getValue('title');
    $date  = (string) $form_state->getValue('date');   // Y-m-d
    $time  = (string) $form_state->getValue('time');   // HH:MM
    $category = (string) $form_state->getValue('category');
    $priority = (string) $form_state->getValue('priority');

    // Site timezone.
    $site_tz = \Drupal::config('system.date')->get('timezone.default') ?: 'UTC';
    $tz = new \DateTimeZone($site_tz);

    $dt = new DrupalDateTime($date . 'T' . $time . ':00', $tz);
    $due_ts = $dt->getTimestamp();

    /** @var \Drupal\tesfana_dairy_farm\Service\TaskStoreService $store */
    $store = \Drupal::service('tesfana_dairy_farm.task_store');
    $store->add($title, $due_ts, $category, $priority);
  }

}
