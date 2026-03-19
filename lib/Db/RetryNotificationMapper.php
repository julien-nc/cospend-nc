<?php

declare(strict_types=1);

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method RetryNotification mapRowToEntity(array $row)
 * @method RetryNotification findEntity(IQueryBuilder $query)
 * @method list<RetryNotification> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<RetryNotification>
 */
class RetryNotificationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'cospend_retry_ocm', RetryNotification::class);
	}

	/**
	 * @return list<RetryNotification>
	 */
	public function getAllDue(\DateTimeInterface $dueDateTime, ?int $limit = 500): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->lte('next_retry', $query->createNamedParameter($dueDateTime, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));

		if ($limit !== null) {
			$query->setMaxResults($limit)
				->orderBy('next_retry', 'ASC')
				->addOrderBy('id', 'ASC');
		}

		return $this->findEntities($query);
	}
}
