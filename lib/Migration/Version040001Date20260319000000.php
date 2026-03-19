<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040001Date20260319000000 extends SimpleMigrationStep {

	public function __construct() {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('cospend_retry_ocm')) {
			return null;
		}

		$table = $schema->createTable('cospend_retry_ocm');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('remote_server', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('num_attempts', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 0,
		]);
		$table->addColumn('next_retry', Types::DATETIME, [
			'notnull' => false,
		]);
		$table->addColumn('notification_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('resource_type', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('provider_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('notification', Types::TEXT, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['next_retry'], 'cospend_retry_ocm_next');

		return $schema;
	}
}
