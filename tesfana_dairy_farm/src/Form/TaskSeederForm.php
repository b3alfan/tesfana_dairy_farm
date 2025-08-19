<?php

declare(strict_types=1);

namespace Drupal\tesfana_dairy_farm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tesfana_dairy_farm\Service\TaskScheduler;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TaskSeederForm extends FormBase {

  public function __construct(private readonly TaskScheduler $scheduler) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('tesfana_dairy_farm.scheduler'));
  }

  public function getFormId(): string {
    return 'tesfana_dairy_farm_task_seeder';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['weeks'] = [
      '#type' => 'number',
      '#title' => $this->t('Weeks ahead'),
      '#default_value' => 8,
      '#min' => 1,
      '#max' => 52,
      '#required' => TRUE,
    ];

    $form['start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start date/time (defaults to next Monday 08:00)'),
      '#default_value' => NULL,
      '#required' => FALSE,
    ];

    $form['tasks'] = [
      '#type' => 'details',
      '#title' => $this->t('Tasks to create'),
      '#open' => TRUE,
    ];
    $form['tasks']['bcs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Weekly BCS task'),
      '#default_value' => 1,
    ];
    $form['tasks']['bcs_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('BCS time (HH:MM)'),
      '#default_value' => '08:00',
      '#size' => 8,
    ];
    $form['tasks']['quality'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Weekly Milk Quality task'),
      '#default_value' => 1,
    ];
    $form['tasks']['quality_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Milk quality time (HH:MM)'),
      '#default_value' => '10:00',
      '#size' => 8,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Seed tasks'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $weeks = (int) $form_state->getValue('weeks');
    $start = $form_state->getValue('start');
    $startTs = $start ? $start->getTimestamp() : NULL;

    $created = $this->scheduler->seedWeekly(
      weeksAhead: $weeks,
      startTs: $startTs,
      seedBcs: (bool) $form_state->getValue('bcs'),
      bcsTime: (string) $form_state->getValue('bcs_time'),
      seedQuality: (bool) $form_state->getValue('quality'),
      qualityTime: (string) $form_state->getValue('quality_time'),
    );

    $this->messenger()->addStatus($this->t('Created @n tasks.', ['@n' => $created]));
    $form_state->setRedirect('tesfana_dairy_farm.dashboard');
  }

}
