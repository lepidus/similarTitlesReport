describe('Similar titles report — incomplete submissions', function () {
	const reportUrl = '/index.php/publicknowledge/similarTitlesReport';
	const incompleteTitle = 'Cypress Incomplete Duplicate Title Excluded ZZQ';

	// Drives the submission wizard up to "Begin Submission" and stops there, so
	// the submission stays in progress (non-empty submission_progress) instead
	// of being finished. It is still STATUS_QUEUED, so only the
	// submission_progress filter keeps it out of the report.
	function beginIncompleteSubmission(title) {
		cy.visit('/index.php/publicknowledge/dashboard/mySubmissions');
		cy.get('a:contains("New Submission")').first().click();
		cy.get('label:contains("English")').click();
		cy.setTinyMceContent('startSubmission-title-control', title);
		cy.get('label:contains("Articles")').click();
		cy.get('label:contains("Yes, my submission meets all of these requirements.")').click();
		cy.get('label:contains("Yes, I agree to have my data collected")').click();
		cy.get('button:contains("Begin Submission")').click();
		cy.contains('Submitting to the Articles section in English');
	}

	it('Keeps submissions still in the wizard out of the pairs', function () {
		// Two drafts share an identical title: were they finished they would
		// form a 100% similar pair. Because both stay in the wizard, the report
		// must not list the title at all.
		cy.login('ccorino', null, 'publicknowledge');
		beginIncompleteSubmission(incompleteTitle);
		beginIncompleteSubmission(incompleteTitle);

		cy.logout();
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(reportUrl);
		cy.get('h1.app__pageHeading').should('be.visible');
		cy.get('.app__contentPanel').should('not.contain', incompleteTitle);
	});
});
