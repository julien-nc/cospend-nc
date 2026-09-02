<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$appId = OCA\Cospend\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-main');
