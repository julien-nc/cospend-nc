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

class Version040103Date20260902000000 extends SimpleMigrationStep {

	public function __construct() {
	}

	/**
	 * Add a default payment mode to categories, so a bill can inherit its payment mode
	 * from its category. 0 means "no default", matching cospend_bills.payment_mode_id.
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$schemaChanged = false;

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			if (!$table->hasColumn('default_payment_mode_id')) {
				$table->addColumn('default_payment_mode_id', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
