<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/ReportAccess.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class ReportAccess
 *
 * @brief Resolves which report sections the current user can access.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

use APP\core\Request;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\security\Role;

class ReportAccess
{
    public function __construct(private Request $request)
    {
    }

    /**
     * Null means unrestricted inside the current context.
     *
     * @return ?int[]
     */
    public function getAllowedSectionIds(): ?array
    {
        $context = $this->request->getContext();
        $user = $this->request->getUser();

        if (!$context || !$user) {
            return [];
        }

        $contextId = (int) $context->getId();
        if (
            $user->hasRole(Role::ROLE_ID_MANAGER, $contextId)
            || $user->hasRole(Role::ROLE_ID_SITE_ADMIN, PKPApplication::SITE_CONTEXT_ID)
        ) {
            return null;
        }

        if (!$user->hasRole(Role::ROLE_ID_SUB_EDITOR, $contextId)) {
            return [];
        }

        return DB::table('subeditor_submission_group')
            ->where('context_id', '=', $contextId)
            ->where('assoc_type', '=', PKPApplication::ASSOC_TYPE_SECTION)
            ->where('user_id', '=', (int) $user->getId())
            ->pluck('assoc_id')
            ->map(fn ($sectionId) => (int) $sectionId)
            ->unique()
            ->values()
            ->all();
    }
}
