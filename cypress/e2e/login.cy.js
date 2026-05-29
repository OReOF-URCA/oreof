/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/cypress/e2e/login.cy.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/06/2025 13:59
 */

describe('Login', () => {
  it('should find the login link', () => {
    cy.visit('/connexion')
    cy.contains('a', 'Se connecter avec votre compte URCA').should('exist')
  })
})
