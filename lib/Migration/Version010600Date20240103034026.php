<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010600Date20240103034026 extends SimpleMigrationStep {

	public function __construct() {
	}

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

		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			// delete password and guestaccesslevel columns
			if ($table->hasColumn('password')) {
				$table->dropColumn('password');
				$schemaChanged = true;
			}
			if ($table->hasColumn('guestaccesslevel')) {
				$table->dropColumn('guestaccesslevel');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
