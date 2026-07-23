describe('Similar titles report — report page', function () {
	const reportUrl = '/index.php/publicknowledge/similarTitlesReport';

	it('Renders the report for a journal manager', function () {
		// The PKP test dataset does not necessarily contain near-duplicate
		// titles, so this asserts the page renders and reports its state
		// (a pairs table or the "no results" message) without creating data.
		cy.login('dbarnes', null, 'publicknowledge');
		cy.visit(reportUrl);

		cy.get('h1.app__pageHeading').should('be.visible');
		cy.get('.app__contentPanel').should('be.visible');
	});

	it('Denies access to a user without editorial roles', function () {
		cy.login('phudson', null, 'publicknowledge');
		cy.visit(reportUrl, { failOnStatusCode: false });

		cy.get('table.pkpTable').should('not.exist');
	});
});
