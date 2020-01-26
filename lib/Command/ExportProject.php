<?php

/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Command;

use OCP\Encryption\IManager;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCP\IConfig;

use OCA\Cospend\Service\ProjectService;
use \OCA\Cospend\AppInfo\Application;

class ExportProject extends Command {

    protected $userManager;

    protected $output;

    protected $encryptionManager;

    public function __construct(IUserManager $userManager,
                                IManager $encryptionManager,
                                ProjectService $projectService,
                                IConfig $config) {
        parent::__construct();
        $this->userManager = $userManager;
        $this->encryptionManager = $encryptionManager;
        $this->config = $config;
        $this->projectService = $projectService;
    }

    protected function configure() {
        $this->setName('cospend:export-project')
            ->setDescription('Export a project to CSV')
            ->addArgument(
                'project_id',
                InputArgument::REQUIRED,
                'The id of the project you want to export'
            )
            ->addArgument(
                'filename',
                InputArgument::OPTIONAL,
                'The name of the exported file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $projectId = $input->getArgument('project_id');
        $name = $input->getArgument('filename');
        $project = $this->projectService->getProjectById($projectId);
        if ($project !== null) {
            $result = $this->projectService->exportCsvProject($projectId, $name, $project['userid']);
            if (array_key_exists('path', $result)) {
                $output->writeln(
                    'Project "'.$projectId.'" exported in "'.$result['path'].
                    '" of user "'.$project['userid'].'" storage'
                );
            }
            else {
                $output->writeln('Error: '.$result['message']);
            }
        }
        else {
            $output->writeln('Project '.$projectId.' not found');
        }
        return 0;
    }
}
