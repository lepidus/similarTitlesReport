describe('Similar titles report — plugin setup', function () {
	it('Enables the "Similar titles report" plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('nav').contains('Settings').click();
		cy.get('nav').contains('Website').click({ force: true });

		cy.waitJQuery();
		cy.get('button[id="plugins-button"]').click();

		cy.get('input[id^="select-cell-similartitlesreportplugin-enabled"]').click();
		cy.waitJQuery();
		cy.get('input[id^="select-cell-similartitlesreportplugin-enabled"]').should('be.checked');
	});
});
