<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\Cospend\Service;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

abstract class AbstractService
{
	/**
	 * @var IL10N
	 */
	protected $translation;

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var IUserManager
	 */
	protected $userManager;

	/**
	 * @var IRootFolder
	 */
	protected $rootFolder;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @param IL10N $translation
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IRootFolder $rootFolder
	 * @param LoggerInterface $logger
	 */
	public function __construct(IL10N           $translation,
								IConfig         $config,
								IUserManager    $userManager,
								IRootFolder     $rootFolder,
								LoggerInterface $logger)
	{
		$this->translation = $translation;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
	}
}
