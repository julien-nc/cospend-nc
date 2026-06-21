<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Activity;

use OCA\Cospend\AppInfo\Application;
use OCP\Activity\ActivitySettings;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IUserSession;

class Setting extends ActivitySettings {

	public function __construct(
		protected IL10N $l,
		protected IAppManager $appManager,
		protected IUserSession $userSession,
	) {
	}

	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('A Cospend <strong>project</strong> has been shared or unshared');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupIdentifier(): string {
		return Application::APP_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupName(): string {
		return $this->l->t('Cospend');
	}

	public function getPriority(): int {
		return 99;
	}

	public function canChangeStream(): bool {
		return true;
	}

	public function isDefaultEnabledStream(): bool {
		return true;
	}

	public function canChangeNotification(): bool {
		$user = $this->userSession->getUser();
		if ($user !== null && !$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return false;
		}
		return true;
	}

	public function canChangeMail(): bool {
		$user = $this->userSession->getUser();
		if ($user !== null && !$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return false;
		}
		return true;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
