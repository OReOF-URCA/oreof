/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/cypress/e2e/dashboard.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/06/2025 14:17
 */
import './support/commands'

describe('Page d\'accueil', () => {
  beforeEach(() => {
    cy.login()
  })

  it('affiche le tableau de bord', () => {
    cy.get('#title').contains('Bienvenue sur ORéOF!')
  })
})
