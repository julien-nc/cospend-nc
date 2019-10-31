<?php
/**
 * @copyright Copyright (c) 2019 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Cospend\Activity;

use InvalidArgumentException;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\Bill;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\Project;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IUserManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use OCP\IUser;

class ActivityManager {

	private $manager;
	private $userId;
	private $projectService;
	private $projectMapper;
	private $billMapper;
	private $l10n;

	const COSPEND_OBJECT_BILL = 'cospend_bill';
	const COSPEND_OBJECT_PROJECT = 'cospend_project';

	const SUBJECT_BILL_CREATE = 'bill_create';
	const SUBJECT_BILL_UPDATE = 'bill_update';
	const SUBJECT_BILL_DELETE = 'bill_delete';

	const SUBJECT_PROJECT_SHARE = 'project_share';
	const SUBJECT_PROJECT_UNSHARE = 'project_unshare';

	public function __construct(
		IManager $manager,
		ProjectService $projectService,
		ProjectMapper $projectMapper,
		BillMapper $billMapper,
		IL10N $l10n,
		IUserManager $userManager,
		$userId
	) {
		$this->manager = $manager;
		$this->projectService = $projectService;
		$this->projectMapper = $projectMapper;
		$this->billMapper = $billMapper;
		$this->l10n = $l10n;
		$this->userId = $userId;
		$this->userManager = $userManager;
	}

	/**
	 * @param $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		switch ($subjectIdentifier) {
			case self::SUBJECT_BILL_CREATE:
				$subject = $ownActivity ? $this->l10n->t('You have created a new bill {bill} in project {project}'): $this->l10n->t('{user} has created a new bill {bill} in project {project}');
				break;
			case self::SUBJECT_BILL_DELETE:
				$subject = $ownActivity ? $this->l10n->t('You have deleted the bill {bill} of project {project}') : $this->l10n->t('{user} has deleted the bill {bill} of project {project}');
				break;
			case self::SUBJECT_PROJECT_SHARE:
				$subject = $ownActivity ? $this->l10n->t('You have shared the project {project} with {who}') : $this->l10n->t('{user} has shared the project {project} with {who}');
				break;
			case self::SUBJECT_PROJECT_UNSHARE:
				$subject = $ownActivity ? $this->l10n->t('You have removed {who} from the project {project}') : $this->l10n->t('{user} has removed {who} from the project {project}');
				break;
			case self::SUBJECT_BILL_UPDATE:
				$subject = $ownActivity ? $this->l10n->t('You have updated the bill {bill} of project {project}') : $this->l10n->t('{user} has updated the bill {bill} of project {project}');
				break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams, $author);
			if ($event !== null) {
				$this->sendToUsers($event);
			}
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @param $subject
	 * @param array $additionalParams
	 * @return IEvent|null
	 * @throws \Exception
	 */
	private function createEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$object = $this->findObjectForEntity($objectType, $entity);
		} catch (DoesNotExistException $e) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		} catch (MultipleObjectsReturnedException $e) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$eventType = 'cospend';
		$subjectParams = [];
		$message = null;
		$objectName = null;
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_BILL_CREATE:
			case self::SUBJECT_BILL_UPDATE:
			case self::SUBJECT_BILL_DELETE:
				$subjectParams = $this->findDetailsForBill($entity->getId());
				$objectName = $object->getWhat();
				$eventType = 'cospend_bill_event';
				break;
			case self::SUBJECT_PROJECT_SHARE:
			case self::SUBJECT_PROJECT_UNSHARE:
				$subjectParams = $this->findDetailsForProject($entity->getId());
				$objectName = $object->getId();
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}
		$subjectParams['author'] = $this->l10n->t('A Cospend client');

		$event = $this->manager->generateEvent();
		$event->setApp('cospend')
			->setType($eventType)
			->setAuthor($author === null ? $this->userId ?? '' : $author)
			->setObject($objectType, (int)$object->getId(), $objectName)
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		if ($message !== null) {
			$event->setMessage($message);
		}
		return $event;
	}

	/**
	 * Publish activity to all users that are part of the project of a given object
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event) {
		switch ($event->getObjectType()) {
			case self::COSPEND_OBJECT_BILL:
				$mapper = $this->billMapper;
				$projectId = $mapper->findProjectId($event->getObjectId());
				break;
			case self::COSPEND_OBJECT_PROJECT:
				$mapper = $this->projectMapper;
				$projectId = $event->getObjectName();
				break;
		}
		/** @var IUser $user */
		foreach ($this->projectService->findUsers($projectId) as $user) {
			$event->setAffectedUser($user);
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @return null|\OCA\Cospend\Db\RelationalEntity|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	private function findObjectForEntity($objectType, $entity) {
		$className = \get_class($entity);
		$objectId = null;
		if ($objectType === self::COSPEND_OBJECT_BILL) {
			switch ($className) {
				case Bill::class:
					$objectId = $entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
			}
			return $this->billMapper->find($objectId);
		}
		if ($objectType === self::COSPEND_OBJECT_PROJECT) {
			switch ($className) {
				case Project::class:
					$objectId = $entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
			}
			return $this->projectMapper->find($objectId);
		}
		throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
	}

	private function findDetailsForBill($billId) {
		$bill = $this->billMapper->find($billId);
		$project = $this->projectMapper->find($bill->getProjectid());
		$bill = [
			'id' => $bill->getId(),
			'name' => $bill->getWhat(),
			'amount' => $bill->getAmount()
		];
		$project = [
			'id' => $project->getId(),
			'name' => $project->getName()
		];
		return [
			'bill' => $bill,
			'project' => $project
		];
	}

	private function findDetailsForProject($projectId, $subject = null) {
		$project = $this->projectMapper->find($projectId);
		$project = [
			'id' => $project->getId(),
			'name' => $project->getName()
		];
		return [
			'project' => $project
		];
	}

}
