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

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Service\LocalProjectService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Search\IProvider;

use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class CospendSearchProjectProvider implements IProvider {

	public function __construct(
		private IAppManager $appManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private LocalProjectService $projectService,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'cospend-search-projects';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Cospend projects');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (str_starts_with($route, Application::APP_ID . '.')) {
			// Active app, prefer Cospend results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? (int)$offset : 0;

		$resultBills = [];

		// get user's projects
		$projects = $this->projectService->getLocalProjects($user->getUID());
		$resultProjects = array_filter($projects, static function(array $project) use ($term) {
			$projectName = $project['name'];
			return str_contains(strtolower($projectName), strtolower($term));
		});

		$resultProjects = array_slice($resultProjects, $offset, $limit);

		// build formatted
		$formattedResults = array_map(function (array $project): SearchResultEntry {
			$thumbnailUrl = $this->getThumbnailUrl($project);
			return new SearchResultEntry(
				$thumbnailUrl,
				$this->getMainText($project),
				$this->getSubline($project),
				$this->getDeepLinkToCospendApp($project['id']),
				$thumbnailUrl === '' ? 'icon-cospend-search-fallback' : '',
				true
			);
		}, $resultProjects);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $project
	 * @return string
	 */
	protected function getMainText(array $project): string {
		return $project['name'];
	}

	/**
	 * @param array $project
	 * @return string
	 */
	protected function getSubline(array $project): string {
		$ownerId = $project['userid'];
		$owner = $this->userManager->get($ownerId);
		if ($owner === null) {
			return '';
		}
		return $this->l10n->t('Owned by %1$s', [$owner->getDisplayName()]);
	}

	/**
	 * @param string $projectId
	 * @return string
	 */
	protected function getDeepLinkToCospendApp(string $projectId): string {
		return $this->urlGenerator->linkToRouteAbsolute('cospend.page.indexProject', [
			'projectId' => $projectId,
		]);
	}

	/**
	 * @param array $project
	 * @return string
	 */
	protected function getThumbnailUrl(array $project): string {
		return '';
		// return $this->urlGenerator->imagePath(Application::APP_ID, '');
	}
}
