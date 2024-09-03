<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use SensitiveParameter;

/**
 * Class InvitationMapper
 *
 * @package OCA\Cospend\Db
 *
 * @method Invitation mapRowToEntity(array $row)
 * @method Invitation findEntity(IQueryBuilder $query)
 * @method Invitation[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Invitation>
 */
class InvitationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_invitations', Invitation::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getInvitationById(int $id): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $remoteServerUrl
	 * @param string $remoteProjectId
	 * @return Invitation
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByRemoteServerAndId(
		string $remoteServerUrl,
		string $remoteProjectId,
	): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('remote_server_url', $qb->createNamedParameter($remoteServerUrl)))
			->andWhere($qb->expr()->eq('remote_project_id', $qb->createNamedParameter($remoteProjectId)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param int|null $state
	 * @param string|null $remoteServerUrl
	 * @param string|null $remoteProjectId
	 * @return Invitation[]
	 * @throws Exception
	 */
	public function getInvitationsForUser(
		string $userId, ?int $state = null, ?string $remoteServerUrl = null, ?string $remoteProjectId = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		if ($state !== null) {
			$qb->andWhere($qb->expr()->eq('state', $qb->createNamedParameter($state, IQueryBuilder::PARAM_INT)));
		}

		if ($remoteServerUrl !== null) {
			$qb->andWhere($qb->expr()->eq('remote_server_url', $qb->createNamedParameter($remoteServerUrl, IQueryBuilder::PARAM_STR)));
		}

		if ($remoteProjectId !== null) {
			$qb->andWhere($qb->expr()->eq('remote_project_id', $qb->createNamedParameter($remoteProjectId, IQueryBuilder::PARAM_STR)));
		}

		return $this->findEntities($qb);
	}

	/**
	 * @psalm-param Invitation::STATE_*|null $state
	 */
	public function countInvitationsForUser(string $userId, ?int $state = null): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		if ($state !== null) {
			$qb->andWhere($qb->expr()->eq('state', $qb->createNamedParameter($state, IQueryBuilder::PARAM_INT)));
		}

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}
}
