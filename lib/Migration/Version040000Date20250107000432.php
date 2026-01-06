<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCA\Cospend\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040000Date20250107000432 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
		private IUserConfig $userConfig,
		private IAppConfig $appConfig,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// app config
		foreach (['balance_past_bills_only', 'federation_enabled'] as $key) {
			$value = $this->appConfig->getValueString(Application::APP_ID, $key);
			if ($value !== '') {
				$this->appConfig->updateLazy(Application::APP_ID, $key, true);
			}
		}

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('userid', 'configvalue', 'configkey')
			->from('preferences')
			->where(
				$qbSelect->expr()->eq('appid', $qbSelect->createNamedParameter(Application::APP_ID, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qbSelect->expr()->eq('lazy', $qbSelect->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$req = $qbSelect->executeQuery();
		while ($row = $req->fetch()) {
			$userId = $row['userid'];
			$key = $row['configkey'];
			$value = $row['configvalue'];

			if ($value === null) {
				$this->userConfig->deleteUserConfig($userId, Application::APP_ID, $key);
			} else {
				$this->userConfig->setValueString($userId, Application::APP_ID, $key, $value, lazy: true);
			}
		}
	}
}
