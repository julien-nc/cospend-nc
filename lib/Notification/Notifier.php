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

namespace OCA\Cospend\Notification;

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IUserManager $userManager,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'cospend';
	}
	/**
	 * Human-readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('cospend')->t('Cospend');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'cospend') {
			// Not my app => throw
			throw new InvalidArgumentException();
		}

		$l = $this->factory->get('cospend', $languageCode);

		switch ($notification->getSubject()) {
			case 'add_user_share':
				$p = $notification->getSubjectParameters();
				$fromUserId = $p[0];
				$projectName = $p[1];
				$projectId = $notification->getObjectId();
				$user = $this->userManager->get($fromUserId);
				if ($user instanceof IUser) {
					$richSubjectUser = [
						'type' => 'user',
						'id' => $fromUserId,
						'name' => $user->getDisplayName(),
					];
					$richSubjectProject = [
						'type' => 'highlight',
						'id' => $projectId,
						'name' => $projectName,
						'link' => $this->url->linkToRouteAbsolute('cospend.page.indexProject', [
							'projectId' => $projectId,
						]),
					];

					$subject = $l->t('Cospend project shared');
					// $content = $l->t('User "%s" shared Cospend project "%s" with you.', [$fromUserId, $projectName]);
					$iconUrl = $this->url->getAbsoluteURL(
						$this->url->imagePath('core', 'actions/share.svg')
					);

					$notification
						->setParsedSubject($subject)
						// ->setParsedMessage($content)
						->setLink($this->url->linkToRouteAbsolute('cospend.page.indexProject', ['projectId' => $projectId]))
						->setRichMessage(
							$l->t('{user} shared project {project} with you'),
							[
								'user' => $richSubjectUser,
								'project' => $richSubjectProject,
							]
						)
						->setIcon($iconUrl);
				}
				return $notification;

			case 'delete_user_share':
				$p = $notification->getSubjectParameters();
				$fromUserId = $p[0];
				$projectName = $p[1];
				$projectId = $notification->getObjectId();
				$user = $this->userManager->get($p[0]);
				if ($user instanceof IUser) {
					$richSubjectUser = [
						'type' => 'user',
						'id' => $fromUserId,
						'name' => $user->getDisplayName(),
					];
					$richSubjectProject = [
						'type' => 'highlight',
						'id' => $projectId,
						'name' => $projectName,
					];
					$subject = $l->t('Cospend project share removed');
					// $content = $l->t('User "%s" stopped sharing Cospend project "%s" with you.', [$fromUserId, $projectName]);
					$iconUrl = $this->url->getAbsoluteURL(
						$this->url->imagePath('core', 'actions/unshare.svg')
					);

					$notification
						->setParsedSubject($subject)
						// ->setParsedMessage($content)
						->setLink($this->url->linkToRouteAbsolute('cospend.page.index'))
						->setRichMessage(
							$l->t('{user} stopped sharing project {project} with you'),
							[
								'user' => $richSubjectUser,
								'project' => $richSubjectProject,
							]
						)
						->setIcon($iconUrl);
				}
				return $notification;

			case 'remote_cospend_share':
				// $inviteId = (int)$notification->getObjectId();
				$p = $notification->getSubjectParameters();

				$subject = $l->t('Cospend federated project shared');
				$content = $l->t('%s (%s) shared Cospend project "%s" (%s) with you.', [
					$p['sharedByDisplayName'],
					$p['sharedByFederatedId'],
					$p['remoteProjectName'],
					$p['remoteProjectId'],
				]);
				$iconUrl = $this->url->getAbsoluteURL(
					$this->url->imagePath('core', 'actions/share.svg')
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($this->url->linkToRouteAbsolute('cospend.page.index'))
					->setIcon($iconUrl);
				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}
}
