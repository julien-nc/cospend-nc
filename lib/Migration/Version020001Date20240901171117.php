<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020001Date20240901171117 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_shares')) {
			$table = $schema->getTable('cospend_shares');
			if (!$table->hasColumn('remote_user_id')) {
				$table->addColumn('remote_user_id', Types::STRING, [
					'notnull' => false,
					'default' => null,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('remote_server_url')) {
				$table->addColumn('remote_server_url', Types::STRING, [
					'notnull' => false,
					'default' => null,
					'length' => 512,
				]);
				$schemaChanged = true;
			}
		}

		if (!$schema->hasTable('cospend_invitations')) {
			$table = $schema->createTable('cospend_invitations');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('state', Types::SMALLINT, [
				'notnull' => true,
				'length' => 5,
				'unsigned' => true,
				'default' => 0,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('remote_project_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('remote_server_url', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('inviter_cloud_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
			$table->addColumn('inviter_display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
			$table->setPrimaryKey(['id']);
			$schemaChanged = true;
		}

		return $schemaChanged ? $schema : null;
	}
}
