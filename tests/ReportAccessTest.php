<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/ReportAccessTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\core\Request;
use APP\journal\Journal;
use APP\plugins\generic\similarTitlesReport\classes\ReportAccess;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlePairFinder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PKP\core\PKPApplication;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\user\User;

class ReportAccessTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private object $databaseManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseManager = app('db');
    }

    protected function tearDown(): void
    {
        app()->instance('db', $this->databaseManager);
        DB::clearResolvedInstance('db');
        parent::tearDown();
    }

    public function testShouldNotRestrictManagersToAssignedSubmissions(): void
    {
        $access = new ReportAccess($this->requestForUserWithRoles(
            manager: true,
            siteAdmin: false,
            subEditor: false
        ));

        DB::shouldReceive('table')->never();

        $this->assertNull($access->getAllowedSubmissionIds());
    }

    public function testShouldNotRestrictSiteAdministratorsToAssignedSubmissions(): void
    {
        $access = new ReportAccess($this->requestForUserWithRoles(
            manager: false,
            siteAdmin: true,
            subEditor: false
        ));

        DB::shouldReceive('table')->never();

        $this->assertNull($access->getAllowedSubmissionIds());
    }

    public function testShouldDenyUsersWithoutEditorialRoles(): void
    {
        $access = new ReportAccess($this->requestForUserWithRoles(
            manager: false,
            siteAdmin: false,
            subEditor: false
        ));

        DB::shouldReceive('table')->never();

        $this->assertSame([], $access->getAllowedSubmissionIds());
    }

    public function testShouldRestrictSectionEditorsToSubmissionsAssignedInTheirRole(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('join')
            ->once()
            ->with('user_groups as ug', 'sa.user_group_id', '=', 'ug.user_group_id')
            ->andReturnSelf();
        $query->shouldReceive('join')
            ->once()
            ->with('submissions as s', 'sa.submission_id', '=', 's.submission_id')
            ->andReturnSelf();
        $query->shouldReceive('join')
            ->once()
            ->with('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->andReturnSelf();
        $query->shouldReceive('where')->once()->with('s.context_id', '=', 42)->andReturnSelf();
        $query->shouldReceive('where')
            ->once()
            ->with('s.status', '=', PKPSubmission::STATUS_QUEUED)
            ->andReturnSelf();
        $query->shouldReceive('where')->once()->with('s.submission_progress', '=', '')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('sa.user_id', '=', 7)->andReturnSelf();
        $query->shouldReceive('where')->once()->with('ug.context_id', '=', 42)->andReturnSelf();
        $query->shouldReceive('where')
            ->once()
            ->with('ug.role_id', '=', Role::ROLE_ID_SUB_EDITOR)
            ->andReturnSelf();
        $query->shouldReceive('distinct')->once()->andReturnSelf();
        $query->shouldReceive('orderByDesc')->once()->with('sa.submission_id')->andReturnSelf();
        $query->shouldReceive('limit')
            ->once()
            ->with(SimilarTitlePairFinder::MAX_SUBMISSIONS + 1)
            ->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('sa.submission_id')->andReturn(collect([101, 102]));

        DB::shouldReceive('table')->once()->with('stage_assignments as sa')->andReturn($query);

        $access = new ReportAccess($this->requestForUserWithRoles(
            manager: false,
            siteAdmin: false,
            subEditor: true
        ));

        $this->assertSame([101, 102], $access->getAllowedSubmissionIds());
    }

    private function requestForUserWithRoles(bool $manager, bool $siteAdmin, bool $subEditor): Request
    {
        $context = Mockery::mock(Journal::class);
        $context->shouldReceive('getId')->andReturn(42);

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getId')->andReturn(7);
        $user->shouldReceive('hasRole')->with(Role::ROLE_ID_MANAGER, 42)->andReturn($manager);
        $user->shouldReceive('hasRole')
            ->with(Role::ROLE_ID_SITE_ADMIN, PKPApplication::SITE_CONTEXT_ID)
            ->andReturn($siteAdmin);
        $user->shouldReceive('hasRole')->with(Role::ROLE_ID_SUB_EDITOR, 42)->andReturn($subEditor);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getContext')->andReturn($context);
        $request->shouldReceive('getUser')->andReturn($user);

        return $request;
    }
}
