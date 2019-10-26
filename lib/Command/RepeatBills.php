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

use OCA\Cospend\Controller\PageController;
use \OCA\Cospend\AppInfo\Application;

class RepeatBills extends Command {

    protected $userManager;

    protected $output;

    protected $encryptionManager;

    private $pageController;

    public function __construct(IUserManager $userManager,
                                IManager $encryptionManager,
                                IConfig $config) {
        parent::__construct();
        $this->userManager = $userManager;
        $this->encryptionManager = $encryptionManager;
        $this->config = $config;
    }

    protected function configure() {
        $this->setName('cospend:repeat-bills')
            ->setDescription('Repeat bills if necessary');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $repeated = (new Application())->getContainer()->query('PageController')->cronRepeatBills();
        foreach ($repeated as $r) {
            $output->writeln(
                '[Project "'.$r['project_name'].'"] Bill "'.$r['what'].
                '" ('.$r['date_orig'].') repeated on ('.$r['date_repeat'].')'
            );
        }
        return 0;
    }
}
