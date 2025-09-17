<?php

/**
 * @copyright Copyright (c) 2019 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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

use Exception;
use InvalidArgumentException;
use OCA\Cospend\Db\Bill;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\Project;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\UserService;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use function get_class;

class ActivityManager {

	public const COSPEND_OBJECT_BILL = 'cospend_bill';
	public const COSPEND_OBJECT_PROJECT = 'cospend_project';

	public const SUBJECT_BILL_CREATE = 'bill_create';
	public const SUBJECT_BILL_UPDATE = 'bill_update';
	public const SUBJECT_BILL_DELETE = 'bill_delete';

	public const SUBJECT_PROJECT_SHARE = 'project_share';
	public const SUBJECT_PROJECT_UNSHARE = 'project_unshare';

	public function __construct(
		private IManager $manager,
		private UserService $userService,
		private ProjectMapper $projectMapper,
		private BillMapper $billMapper,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
	}

	/**
	 * @param string $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat(string $subjectIdentifier, array $subjectParams = [], bool $ownActivity = false): string {
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

	/**
	 * @param string $objectType
	 * @param Entity $entity
	 * @param string $subject
	 * @param array $additionalParams
	 * @param string|null $author
	 */
	public function triggerEvent(string $objectType, Entity $entity, string $subject, array $additionalParams = [], ?string $author = null) {
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams, $author);
			if ($event !== null) {
				$this->sendToUsers($event);
			}
		} catch (Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param string $objectType
	 * @param Entity $entity
	 * @param string $subject
	 * @param array $additionalParams
	 * @param string|null $author
	 * @return IEvent|null
	 * @throws Exception
	 */
	private function createEvent(string $objectType, Entity $entity, string $subject, array $additionalParams = [], ?string $author = null): ?IEvent {
		if ($subject === self::SUBJECT_BILL_DELETE) {
			$object = $entity;
		} else {
			try {
				$object = $this->findObjectForEntity($objectType, $entity);
			} catch (DoesNotExistException $e) {
				$this->logger->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
				return null;
			} catch (MultipleObjectsReturnedException $e) {
				$this->logger->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
				return null;
			}
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
				$subjectParams = $this->findDetailsForBill($object);
				/** @var Bill $object */
				$objectName = $object->getWhat();
				$eventType = 'cospend_bill_event';
				break;
			case self::SUBJECT_PROJECT_SHARE:
			case self::SUBJECT_PROJECT_UNSHARE:
				$subjectParams = $this->findDetailsForProject((string)$entity->getId());
				$objectName = $object->getId();
				break;
			default:
				throw new Exception('Unknown subject for activity.');
		}
		$subjectParams['author'] = $this->l10n->t('A guest user');

		$event = $this->manager->generateEvent();
		$event->setApp('cospend')
			->setType($eventType)
			->setAuthor($author === null ? $this->userId ?? '' : $author)
			->setObject($objectType, (int)$object->getId(), (string)$objectName)
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		/*
		if ($message !== null) {
			$event->setMessage($message);
		}
		*/
		return $event;
	}

	/**
	 * Publish activity to all users that are part of the project of a given object
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event) {
		$projectId = '';
		switch ($event->getObjectType()) {
			case self::COSPEND_OBJECT_BILL:
				$projectId = $event->getSubjectParameters()['project']['id'];
				break;
			case self::COSPEND_OBJECT_PROJECT:
				$projectId = $event->getObjectName();
				break;
		}
		foreach ($this->userService->findUsers($projectId) as $user) {
			$event->setAffectedUser($user);
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @return Entity
	 */
	private function findObjectForEntity($objectType, $entity): Entity {
		$className = get_class($entity);
		if ($objectType === self::COSPEND_OBJECT_BILL) {
			switch ($className) {
				case Bill::class:
					$objectId = $entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
			}
			return $this->billMapper->find($objectId);
		}
		if ($objectType === self::COSPEND_OBJECT_PROJECT) {
			switch ($className) {
				case Project::class:
					$objectId = (string)$entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
			}
			$dbProject = $this->projectMapper->find($objectId);
			if ($dbProject === null) {
				throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
			}
			return $dbProject;
		}
		throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
	}

	/**
	 * @param object $bill
	 * @return array[]
	 */
	private function findDetailsForBill(object $bill): array {
		$project = $this->projectMapper->find($bill->getProjectId());
		$bill = [
			'id' => $bill->getId(),
			'name' => $bill->getWhat(),
			'amount' => $bill->getAmount()
		];
		$project = [
			'id' => $project->getId(),
			'name' => $project->getName(),
			'currency_name' => $project->getCurrencyName(),
		];
		return [
			'bill' => $bill,
			'project' => $project
		];
	}

	/**
	 * @param string $projectId
	 * @return array[]
	 */
	private function findDetailsForProject(string $projectId): array {
		$project = $this->projectMapper->find($projectId);
		$project = [
			'id' => $project->getId(),
			'name' => $project->getName(),
			'currency_name' => $project->getCurrencyName(),
		];
		return [
			'project' => $project
		];
	}

}
