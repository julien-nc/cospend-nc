<?php

namespace OCA\Cospend\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings
{
    /**
     * @var IConfig
     */
    private $config;

    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse
    {
        $allow = $this->config->getAppValue('cospend', 'allowAnonymousCreation');

        $parameters = [
            'allowAnonymousCreation' => $allow
        ];
        return new TemplateResponse('cospend', 'admin', $parameters, '');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection(): string
    {
        return 'additional';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority(): int
    {
        return 5;
    }
}
