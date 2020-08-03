<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

use OCA\Cospend\Service\ProjectService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class CospendSearchProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * CospendSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param ProjectService $projectService
	 */
	public function __construct(IAppManager $appManager,
                                IL10N $l10n,
                                IConfig $config,
								IURLGenerator $urlGenerator,
                                ProjectService $projectService) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->projectService = $projectService;
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
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('cospend', $user)) {
			return SearchResult::complete($this->getName(), []);
        }

        $limit = $query->getLimit();
        $term = $query->getTerm();
        $offset = $query->getCursor();

        $theme = $this->config->getUserValue($user->getUID(), 'accessibility', 'theme', '');
        $thumbnailUrl = ($theme === 'dark') ?
            $this->urlGenerator->imagePath('cospend', 'app.svg') :
            $this->urlGenerator->imagePath('cospend', 'app_black.svg');

        $resultBills = [];

        // get user's projects
        $projects = $this->projectService->getProjects($user->getUID());
        $projectsById = [];
        foreach ($projects as $project) {
            $projectsById[$project['id']] = $project;
        }

        // search bills for each project
        foreach ($projects as $project) {
            $searchResults = $this->projectService->searchBills($project['id'], $term);
            $resultBills = array_merge($resultBills, $searchResults);
        }

        // sort by timestamp
        $a = usort($resultBills, function($a, $b) {
            $ta = $a['timestamp'];
            $tb = $b['timestamp'];
            return ($ta > $tb) ? -1 : 1;
        });

        // build formatted
        $formattedResults = \array_map(function (array $bill) use ($projectsById, $thumbnailUrl):CospendSearchResultEntry {
            $projectId = $bill['projectId'];
            $projectName = $projectsById[$projectId]['name'];
            return new CospendSearchResultEntry(
                $thumbnailUrl, $this->getMainText($bill), $this->getSubline($projectsById[$projectId]),
                $this->getDeepLinkToCospendApp($projectId), '', true
            );
        }, $resultBills);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
        );
	}

	/**
	 * @return string
	 */
	protected function getMainText(array $bill): string {
        return $bill['what']. ' ('. $bill['amount'] . ')';
	}

	/**
	 * @return string
	 */
	protected function getSubline(array $project): string {
        return $this->l10n->t('In project %1$s', [$project['name']]);
	}

	/**
	 * @return string
	 */
	protected function getDeepLinkToCospendApp(string $projectId): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('cospend.page.index', [
				'project' => $projectId
			])
		);
	}

}