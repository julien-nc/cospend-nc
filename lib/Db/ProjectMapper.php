<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * @extends QBMapper<Project>
 */
class ProjectMapper extends QBMapper {
	public const TABLE_NAME = 'cospend_projects';

	public const ARCHIVED_TS_UNSET = -1;
	public const ARCHIVED_TS_NOW = 0;

	public function __construct(
		IDBConnection $db,
		private IL10N $l10n,
	) {
		parent::__construct($db, self::TABLE_NAME, Project::class);
	}

	/**
	 * @param string $projectId
	 * @return Project
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getById(string $projectId): Project {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	public function createProject(
		string $name, string $id, ?string $contact_email, array $defaultCategories, array $defaultPaymentModes,
		string $userid = '', bool $createDefaultCategories = true, bool $createDefaultPaymentModes = true
	): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from($this->getTableName(), 'p')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();

		$dbId = null;
		while ($row = $req->fetch()) {
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();
		if ($dbId === null) {
			// check if id is valid
			if (strpos($id, '/') !== false) {
				return ['message' => $this->l10n->t('Invalid project id')];
			}
			if ($contact_email === null) {
				$contact_email = '';
			}
			$ts = (new DateTime())->getTimestamp();
			$qb->insert($this->getTableName())
				->values([
					'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
					'id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
					'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
					'email' => $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR),
					'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
				]);
			$qb->executeStatement();
			$qb = $this->db->getQueryBuilder();

			// create default categories
			if ($createDefaultCategories) {
				foreach ($defaultCategories as $category) {
					$icon = urlencode($category['icon']);
					$color = $category['color'];
					$name = $category['name'];
					$qb->insert('cospend_categories')
						->values([
							'projectid' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
							'encoded_icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
							'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
							'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
						]);
					$qb->executeStatement();
					$qb = $this->db->getQueryBuilder();
				}
			}

			// create default payment modes
			if ($createDefaultPaymentModes) {
				foreach ($defaultPaymentModes as $pm) {
					$icon = urlencode($pm['icon']);
					$color = $pm['color'];
					$name = $pm['name'];
					$oldId = $pm['old_id'];
					$qb->insert('cospend_paymentmodes')
						->values([
							'projectid' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
							'encoded_icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
							'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
							'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
							'old_id' => $qb->createNamedParameter($oldId, IQueryBuilder::PARAM_STR),
						]);
					$qb->executeStatement();
					$qb = $this->db->getQueryBuilder();
				}
			}

			return ['id' => $id];
		} else {
			return ['message' => $this->l10n->t('A project with id "%1$s" already exists', [$id])];
		}
	}

	/**
	 * @param string $id
	 * @return Project|null
	 */
	public function find(string $id): ?Project {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException | MultipleObjectsReturnedException |\OCP\DB\Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $userId
	 * @return Project[]
	 * @throws \OCP\DB\Exception
	 */
	public function getProjects(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $projectId
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function deleteBillOwersOfProject(string $projectId): void {
		// old style
		/*
		$query = 'DELETE FROM `*PREFIX*cospend_bill_owers`
		WHERE `billid` IN (
			SELECT `id` FROM `*PREFIX*cospend_bills` WHERE `projectid` = ?
		)';
		$this->db->executeQuery($query, [$projectId]);
		*/

		// inspired from the tables app
		$qb = $this->db->getQueryBuilder();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('id')
			->from('cospend_bills')
			->where(
				$qb2->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);

		$qb->delete('cospend_bill_owers')
			->where(
				$qb2->expr()->in('billid', $qb->createFunction($qb2->getSQL()), IQueryBuilder::PARAM_STR_ARRAY)
			);
		$qb->executeStatement();
	}

	/**
	 * Touch a project
	 *
	 * @param string $projectId
	 * @param int $timestamp
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function updateProjectLastChanged(string $projectId, int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName());
		$qb->set('lastchanged', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT));
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		);
		$qb->executeStatement();
	}
}
