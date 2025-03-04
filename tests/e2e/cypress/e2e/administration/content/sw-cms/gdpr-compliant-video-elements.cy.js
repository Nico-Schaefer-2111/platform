/**
 * @package content
 */
const platforms = [{
    name: 'youtube',
    videoId: 'https://www.youtube.com/watch?v=Ds7c_AKSk7s',
}, {
    name: 'vimeo',
    videoId: 'https://vimeo.com/68765485',
}];

describe('CMS: Check GDPR compliant video elements', () => {
    beforeEach(() => {
        cy.createCmsFixture().then(() => {
            cy.viewport(1920, 1080);
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    platforms.forEach(({ name, videoId }) => {
        it(`use ${name} element with GDPR compliant options`, { tags: ['pa-content-management'] }, () => {
            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/cms-page/*`,
                method: 'PATCH',
            }).as('saveData');

            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/category/*`,
                method: 'PATCH',
            }).as('saveCategory');

            cy.get('.sw-cms-list-item--0').click();
            cy.get('.sw-cms-section__empty-stage').should('be.visible');

            // Add simple image block
            cy.get('.sw-cms-section__empty-stage').click();
            cy.get('#sw-field--currentBlockCategory').select('Video');
            cy.get(`.sw-cms-preview-${name}-video`).should('be.visible');
            cy.get(`.sw-cms-preview-${name}-video`).closest('.sw-cms-sidebar__block-preview')
                .dragTo('.sw-cms-section__empty-stage');
            cy.get('.sw-cms-block').should('be.visible');
            cy.get('.sw-cms-block__config-overlay').invoke('show');
            cy.get('.sw-cms-block__config-overlay').should('be.visible');
            cy.get('.sw-cms-block__config-overlay').click();
            cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
            cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
            cy.get('.sw-cms-slot .sw-cms-slot__settings-action').click();
            cy.get('.sw-cms-slot__config-modal').should('be.visible');

            // Fill out config modal form
            cy.get('input[name="sw-field--videoID"]').type(videoId);
            cy.get(`.sw-cms-el-config-${name}-video__confirmation label`).click();

            // Upload preview image
            cy.get('.sw-media-upload-v2__dropzone.is--droppable').should('be.visible');
            cy.get('.sw-cms-slot__config-modal .sw-media-upload-v2__file-input')
                .attachFile('img/sw-login-background.png');
            cy.awaitAndCheckNotification('File has been saved.');

            // Close config modal
            cy.get('.sw-cms-slot__config-modal .sw-modal__footer .sw-button--primary').click();

            // Save new page layout
            cy.get('.sw-cms-detail__save-action').click();
            cy.wait('@saveData')
                .its('response.statusCode').should('equal', 204);
            cy.get('.sw-cms-detail__back-btn').click();

            // Assign layout to root category
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
            cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
            cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
            cy.get('.sw-category-detail-layout__change-layout-action').click();
            cy.get('.sw-modal__dialog').should('be.visible');
            cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
            cy.get('.sw-modal .sw-button--primary').click();
            cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');
            cy.get('.sw-category-detail__save-action').click();

            cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

            // Verify layout in Storefront
            cy.visit('/');

            cy.get(`.cms-element-${name}-video__backdrop`).should('be.visible');

            // Check the privacy notice modal
            cy.get(`.cms-element-${name}-video__backdrop a[data-ajax-modal="true"]`).click();
            cy.get('.js-pseudo-modal .modal').should('exist');
            cy.contains('.js-pseudo-modal .modal .cms-element-text h2', 'Privacy');

            cy.get('.js-pseudo-modal').invoke('hide');
            cy.get('.modal-backdrop').invoke('hide');

            // Click agree button
            cy.contains(`.cms-element-${name}-video__backdrop .btn-outline-secondary`, 'Accept')
                .click();

            // Check if the video iframe will be displayed
            cy.get(`.cms-element-${name}-video__backdrop`).should('not.exist');
            cy.get(`.cms-element-${name}-video__video`).should('exist');
        });
    });
});
