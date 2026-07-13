<?php

/**
 * @file plugins/generic/similarTitlesReport/pages/similarTitlesReport/SimilarTitlesReportHandler.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class SimilarTitlesReportHandler
 *
 * @brief Backend page handler for the similar titles report.
 */

namespace APP\plugins\generic\similarTitlesReport\pages\similarTitlesReport;

use APP\handler\Handler;
use APP\plugins\generic\similarTitlesReport\classes\ReportAccess;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlePairFinder;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlesDataProvider;
use APP\template\TemplateManager;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SimilarTitlesReportHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            ['index']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function index($args, $request): void
    {
        $plugin = PluginRegistry::getPlugin('generic', 'similartitlesreportplugin');
        $context = $request->getContext();

        if (!$plugin || !$context || !$plugin->getEnabled($context->getId())) {
            throw new NotFoundHttpException();
        }

        $access = new ReportAccess($request);
        $allowedSectionIds = $access->getAllowedSectionIds();
        if ($allowedSectionIds === []) {
            throw new NotFoundHttpException();
        }

        $dataProvider = new SimilarTitlesDataProvider($context, $allowedSectionIds);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'breadcrumbs' => [
                [
                    'id' => 'reports',
                    'name' => __('manager.statistics.reports'),
                    'url' => $request->getRouter()->url($request, null, 'stats', 'reports'),
                ],
                [
                    'id' => 'similarTitlesReport',
                    'name' => __('plugins.generic.similarTitlesReport.displayName'),
                ],
            ],
            'pageTitle' => __('plugins.generic.similarTitlesReport.displayName'),
            'similarTitlePairs' => $dataProvider->getPairs(),
            'threshold' => SimilarTitlePairFinder::DEFAULT_THRESHOLD,
        ]);

        $this->setupTemplate($request);
        $templateMgr->display($plugin->getTemplateResource('similarTitlesReport.tpl'));
    }
}
