<?php

/**
 * @file plugins/generic/similarTitlesReport/SimilarTitlesReportPlugin.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class SimilarTitlesReportPlugin
 *
 * @brief HTML report with active submissions whose titles are similar.
 */

namespace APP\plugins\generic\similarTitlesReport;

use APP\core\Application;
use APP\plugins\generic\similarTitlesReport\classes\ReportAccess;
use APP\plugins\generic\similarTitlesReport\pages\similarTitlesReport\SimilarTitlesReportHandler;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class SimilarTitlesReportPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && Config::getVar('general', 'installed') && $this->getEnabled($mainContextId)) {
            $this->addLocaleData();
            Hook::add('TemplateManager::setupBackendPage', [$this, 'addReportsMenuItem']);
            Hook::add('LoadHandler', [$this, 'setupSimilarTitlesReportHandler']);
        }

        return $success;
    }

    public function getName(): string
    {
        return 'similartitlesreportplugin';
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.similarTitlesReport.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.similarTitlesReport.description');
    }

    public function setupSimilarTitlesReportHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];

        if ($page !== 'similarTitlesReport') {
            return false;
        }

        $handler = new SimilarTitlesReportHandler();
        return true;
    }

    public function addReportsMenuItem(string $hookName): bool
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();

        if (!$context || !$user) {
            return false;
        }

        $access = new ReportAccess($request);
        if ($access->getAllowedSectionIds() === []) {
            return false;
        }

        $templateMgr = TemplateManager::getManager($request);
        $menu = (array) $templateMgr->getState('menu');
        if (!isset($menu['statistics']['submenu'])) {
            return false;
        }

        $router = $request->getRouter();
        $menu['statistics']['submenu']['similarTitlesReport'] = [
            'name' => __('plugins.generic.similarTitlesReport.menu'),
            'url' => $router->url($request, null, 'similarTitlesReport'),
            'isCurrent' => $router->getRequestedPage($request) === 'similarTitlesReport',
        ];
        $templateMgr->setState(['menu' => $menu]);

        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias(
        '\APP\plugins\generic\similarTitlesReport\SimilarTitlesReportPlugin',
        '\SimilarTitlesReportPlugin'
    );
}
