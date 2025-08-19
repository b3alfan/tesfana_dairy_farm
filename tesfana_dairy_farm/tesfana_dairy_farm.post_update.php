<?php

/**
 * @file
 * Post-update functions for Tesfana Dairy Farm.
 */

use Drupal\Core\Database\Database;

/**
 * Ensure tesfana_task table exists (idempotent).
 */
function tesfana_dairy_farm_post_update_create_task_table(&$sandbox = NULL) {
  $schema = Database::getConnection()->schema();

  if (!$schema->tableExists('tesfana_task')) {
    $schema->createTable('tesfana_task', [
      'description' => 'Stores user-created operational tasks for the dashboard calendar.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'title' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
        'due_ts' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'category' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'other'],
        'priority' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE, 'default' => 'normal'],
        'created' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'changed' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'due_ts' => ['due_ts'],
        'category' => ['category'],
      ],
    ]);
  }

  // Nothing to report in $sandbox; this is a one-shot ensure.
}
