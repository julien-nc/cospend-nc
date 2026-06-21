<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Activity;

use OCA\Cospend\AppInfo\Application;

class BillCreatedSetting extends Setting {

	public function getIdentifier(): string {
		return Application::ACTIVITY_BILL_CREATED_EVENT;
	}

	public function getName(): string {
		return $this->l->t('A Cospend <strong>bill</strong> has been created');
	}
}
