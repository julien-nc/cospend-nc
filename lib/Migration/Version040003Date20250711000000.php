<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-categorisation feature (gh-402):
 * - Creates cospend_auto_category_mappings for bill title → category mappings
 * - Adds auto_categorization column to cospend_projects (per-project toggle)
 */
class Version040003Date20250711000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$schemaChanged = false;

		if (!$schema->hasTable('cospend_auto_category_mappings')) {
			$table = $schema->createTable('cospend_auto_category_mappings');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('project_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('bill_title', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('category_id', Types::INTEGER, [
				'notnull' => false,
				'length' => 4,
				'default' => null,
			]);
			$table->addColumn('last_changed', Types::BIGINT, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['project_id', 'bill_title'], 'acm_project_title_idx');
			$table->addIndex(['project_id', 'category_id'], 'acm_project_cat_idx');
			$schemaChanged = true;
		}

		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			if (!$table->hasColumn('auto_categorization')) {
				$table->addColumn('auto_categorization', Types::STRING, [
					'notnull' => true,
					'length' => 1,
					'default' => '1',
				]);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
