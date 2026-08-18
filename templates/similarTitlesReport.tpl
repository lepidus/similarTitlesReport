{**
 * templates/similarTitlesReport.tpl
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.generic.similarTitlesReport.displayName"}
	</h1>

	<div class="app__contentPanel">
		<p>
			{translate key="plugins.generic.similarTitlesReport.description" threshold=$threshold}
		</p>

		{if $submissionsTruncated}
			<p role="status">
				{translate key="plugins.generic.similarTitlesReport.limit.submissions" maxSubmissions=$maxSubmissions}
			</p>
		{/if}

		{if $pairsTruncated}
			<p role="status">
				{translate key="plugins.generic.similarTitlesReport.limit.pairs" maxPairs=$maxPairs}
			</p>
		{/if}

		{if $similarTitlePairs|@count > 0}
			<table class="pkpTable">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.similarTitlesReport.column.similarity"}</th>
						<th>{translate key="plugins.generic.similarTitlesReport.column.submission"}</th>
						<th>{translate key="plugins.generic.similarTitlesReport.column.authors"}</th>
						<th>{translate key="plugins.generic.similarTitlesReport.column.similarSubmission"}</th>
						<th>{translate key="plugins.generic.similarTitlesReport.column.similarAuthors"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$similarTitlePairs item=pair}
						<tr>
							<td>{$pair.similarity|escape}%</td>
							<td>
								<a href="{$pair.first.submissionUrl|escape}">
									#{$pair.first.submissionId|escape} {$pair.first.title|escape}
								</a>
							</td>
							<td>{$pair.first.authors|escape}</td>
							<td>
								<a href="{$pair.second.submissionUrl|escape}">
									#{$pair.second.submissionId|escape} {$pair.second.title|escape}
								</a>
							</td>
							<td>{$pair.second.authors|escape}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p>{translate key="plugins.generic.similarTitlesReport.noResults" threshold=$threshold}</p>
		{/if}
	</div>
{/block}
