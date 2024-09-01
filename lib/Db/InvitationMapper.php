<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
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
	 * @throws DoesNotExistException
	 */
	public function getByRemoteAndToken(
		string $remoteServerUrl,
		#[SensitiveParameter]
		string $token,
	): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('remote_server_url', $qb->createNamedParameter($remoteServerUrl)))
			->andWhere($qb->expr()->eq('access_token', $qb->createNamedParameter($token)));

		return $this->findEntity($qb);
	}

	/**
	 * @param IUser $user
	 * @return Invitation[]
	 */
	public function getInvitationsForUser(IUser $user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($user->getUID())));

		return $this->findEntities($qb);
	}

	/**
	 * @psalm-param Invitation::STATE_*|null $state
	 */
	public function countInvitationsForUser(IUser $user, ?int $state = null): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($user->getUID())));

		if ($state !== null) {
			$qb->andWhere($qb->expr()->eq('state', $qb->createNamedParameter($state)));
		}

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}
}
