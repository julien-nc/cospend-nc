<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Cospend\Search;

use DateTime;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\LocalProjectService;
use OCP\App\IAppManager;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;

use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class CospendSearchProvider implements IProvider {

	public function __construct(
		private IAppManager         $appManager,
		private IL10N               $l10n,
		private IURLGenerator       $urlGenerator,
		private IDateTimeFormatter  $dateFormatter,
		private LocalProjectService $projectService,
		private BillMapper          $billMapper,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'cospend-search';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Cospend');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Cospend results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('cospend', $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? (int) $offset : 0;

		$resultBills = [];

		// get user's projects
		$projects = $this->projectService->getLocalProjects($user->getUID());
		$projectsById = [];
		foreach ($projects as $project) {
			$projectsById[$project['id']] = $project;
		}

		// search bills for each project
		foreach ($projects as $project) {
			$searchResults = $this->billMapper->searchBills($project['id'], $term);
			$resultBills = array_merge($resultBills, $searchResults);
		}

		// sort by timestamp
		usort($resultBills, function ($a, $b) {
			$ta = $a['timestamp'];
			$tb = $b['timestamp'];
			return ($ta > $tb) ? -1 : 1;
		});

		$resultBills = array_slice($resultBills, $offset, $limit);

		// build formatted
		$formattedResults = array_map(function (array $bill) use ($projectsById):SearchResultEntry {
			$projectId = $bill['projectId'];
			$thumbnailUrl = $this->getThumbnailUrl($bill);
			return new SearchResultEntry(
				$thumbnailUrl,
				$this->getMainText($bill, $projectsById[$projectId]),
				$this->getSubline($bill, $projectsById[$projectId]),
				$this->getDeepLinkToCospendApp($projectId, $bill['id']),
				$thumbnailUrl === '' ? 'icon-cospend-search-fallback' : '',
				true
			);
		}, $resultBills);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $bill
	 * @param array $project
	 * @return string
	 */
	protected function getMainText(array $bill, array $project): string {
		$currency = $bill['currencyname'] ?? '';
		$currency = $currency ? ' ' . $currency : '';
		$what = $this->truncate($bill['what'], 24);
		$catPmChars = '';
		if (isset($bill['categoryid'])
			&& !is_null($bill['categoryid'])
			&& $bill['categoryid'] !== 0
		) {
			if (isset($project['categories'][$bill['categoryid']])) {
				$catPmChars .= $project['categories'][$bill['categoryid']]['icon'] . ' ';
			} elseif (isset(Application::HARDCODED_CATEGORIES[$bill['categoryid']])) {
				$catPmChars .= Application::HARDCODED_CATEGORIES[$bill['categoryid']]['icon'];
			}
		}
		if (isset($bill['paymentmodeid'])
			&& !is_null($bill['paymentmodeid'])
			&& $bill['paymentmodeid'] !== 0
		) {
			if (isset($project['paymentmodes'][$bill['paymentmodeid']])) {
				$catPmChars .= $project['paymentmodes'][$bill['paymentmodeid']]['icon'] . ' ';
			}
		}
		$amount = number_format($bill['amount'], 2);
		return $what. ' ('. $amount . $currency . ') ' . $catPmChars;
	}

	/**
	 * @param array $bill
	 * @param array $project
	 * @return string
	 */
	protected function getSubline(array $bill, array $project): string {
		$d = new DateTime();
		$d->setTimestamp($bill['timestamp']);
		$fd = $this->dateFormatter->formatDate($d, 'short');
		return '[' . $fd . '] ' . $this->l10n->t('in %1$s', [$project['name']]);
	}

	/**
	 * @param string $projectId
	 * @return string
	 */
	protected function getDeepLinkToCospendApp(string $projectId, int $billId): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('cospend.page.indexBill', [
				'projectId' => $projectId,
				'billId' => $billId,
			])
		);
	}

	protected function getThumbnailUrl(array $bill): string {
		if ($bill['payer_user_id']) {
			return $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', ['userId' => $bill['payer_user_id'], 'size' => 44]);
		} elseif ($bill['payer_name']) {
			return $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $bill['payer_name'], 'size' => 44]);
		}
		return '';
	}

	/**
	 * @param string $s
	 * @param int $len
	 * @return string
	 */
	private function truncate(string $s, int $len): string {
		return strlen($s) > $len
				? substr($s, 0, $len) . '…'
				: $s;
	}
}
