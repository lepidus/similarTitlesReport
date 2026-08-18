<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/ReportAccess.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class ReportAccess
 *
 * @brief Resolves which report submissions the current user can access.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

use APP\core\Request;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

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
    public function getAllowedSubmissionIds(): ?array
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

        return DB::table('stage_assignments as sa')
            ->join('user_groups as ug', 'sa.user_group_id', '=', 'ug.user_group_id')
            ->join('submissions as s', 'sa.submission_id', '=', 's.submission_id')
            ->join('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->where('s.context_id', '=', $contextId)
            ->where('s.status', '=', PKPSubmission::STATUS_QUEUED)
            ->where('s.submission_progress', '=', '')
            ->where('sa.user_id', '=', (int) $user->getId())
            ->where('ug.context_id', '=', $contextId)
            ->where('ug.role_id', '=', Role::ROLE_ID_SUB_EDITOR)
            ->distinct()
            ->orderByDesc('sa.submission_id')
            ->limit(SimilarTitlePairFinder::MAX_SUBMISSIONS + 1)
            ->pluck('sa.submission_id')
            ->map(fn ($submissionId) => (int) $submissionId)
            ->unique()
            ->values()
            ->all();
    }
}
