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
use OCA\Cospend\Exception\CospendBasicException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Http;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * @extends QBMapper<Project>
 */
class ProjectMapper extends QBMapper {
	public const ARCHIVED_TS_UNSET = -1;
	public const ARCHIVED_TS_NOW = 0;

	public function __construct(
		IDBConnection $db,
		private IL10N $l10n,
		private CategoryMapper $categoryMapper,
		private PaymentModeMapper $paymentModeMapper,
	) {
		parent::__construct($db, 'cospend_projects', Project::class);
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
	 * @param string $name
	 * @param string $id
	 * @param string|null $contact_email
	 * @param array $defaultCategories
	 * @param array $defaultPaymentModes
	 * @param string $userid
	 * @param bool $createDefaultCategories
	 * @param bool $createDefaultPaymentModes
	 * @return Project
	 * @throws CospendBasicException
	 * @throws Exception
	 */
	public function createProject(
		string $name, string $id, ?string $contact_email, array $defaultCategories, array $defaultPaymentModes,
		string $userid = '', bool $createDefaultCategories = true, bool $createDefaultPaymentModes = true
	): Project {
		// check if id is valid
		if (str_contains($id, '/')) {
			throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('Invalid project id')]);
		}

		$ts = (new DateTime())->getTimestamp();
		$project = new Project();
		$project->setUserid($userid);
		$project->setId($id);
		$project->setName($name);
		$project->setEmail($contact_email === null ? '' : $contact_email);
		$project->setLastChanged($ts);
		$insertedProject = $this->insert($project);

		if ($createDefaultCategories) {
			foreach ($defaultCategories as $defaultCategory) {
				$category = new Category();
				$category->setProjectid($insertedProject->getId());
				$category->setName($defaultCategory['name']);
				$category->setColor($defaultCategory['color']);
				$category->setEncodedIcon(urlencode($defaultCategory['icon']));
				$this->categoryMapper->insert($category);
			}
		}

		if ($createDefaultPaymentModes) {
			foreach ($defaultPaymentModes as $defaultPm) {
				$paymentMode = new PaymentMode();
				$paymentMode->setProjectid($insertedProject->getId());
				$paymentMode->setName($defaultPm['name']);
				$paymentMode->setColor($defaultPm['color']);
				$paymentMode->setEncodedIcon(urlencode($defaultPm['icon']));
				$paymentMode->setOldId($defaultPm['old_id']);
				$this->paymentModeMapper->insert($paymentMode);
			}
		}

		return $insertedProject;
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
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
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
