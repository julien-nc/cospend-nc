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
 * Adds `cospend_bill_payers`, a sparse side table: rows exist only for bills with
 * more than one payer. `cospend_bills.payer_id` stays NOT NULL and holds the
 * primary payer, so a single-payer bill is unchanged on disk and every existing
 * query, filter and join keeps working. Schema only, no backfill.
 */
class Version040200Date20260905093000 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('cospend_bill_payers')) {
			$table = $schema->createTable('cospend_bill_payers');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('bill_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('member_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			// Explicit absolute amount, not a share: a second division on the credit
			// side would leak float residue into the settlement plan, which compares
			// balances against exactly 0.0.
			$table->addColumn('amount', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			// A member can be a payer of a given bill at most once. The write pattern
			// is delete-all-then-reinsert, so this can never fire on a legitimate
			// edit, and a duplicate row would double-credit that member's balance.
			// Its leftmost column also serves the per-bill lookup, so no separate
			// bill_id index is needed.
			$table->addUniqueIndex(['bill_id', 'member_id'], 'cospend_bp_bill_mem_uidx');
			$schemaChanged = true;
		}

		return $schemaChanged ? $schema : null;
	}
}
