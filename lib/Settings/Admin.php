<?php
namespace OCA\Cospend\Settings;

use bantu\IniGetWrapper\IniGetWrapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;

class Admin implements ISettings {

    /** @var IniGetWrapper */
    private $iniWrapper;

    /** @var IRequest */
    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        IniGetWrapper $iniWrapper,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator) {
        $this->urlGenerator = $urlGenerator;
        $this->iniWrapper = $iniWrapper;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $allow = $this->config->getAppValue('cospend', 'allowAnonymousCreation');

        $parameters = [
            'allowAnonymousCreation' => $allow
        ];
        return new TemplateResponse('cospend', 'admin', $parameters, '');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection() {
        return 'additional';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 5;
    }

    /**
     * @return TemplateResponse
     * for ownCloud 10+
     */
    public function getPanel() {
        $allow = $this->config->getAppValue('cospend', 'allowAnonymousCreation');

        $parameters = [
            'allowAnonymousCreation' => $allow
        ];
        return new TemplateResponse('cospend', 'admin', $parameters, '');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     * for ownCloud 10+
     */
    public function getSectionID() {
        return 'additional';
    }

}
