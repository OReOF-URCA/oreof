/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/cypress/e2e/support/commands.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/06/2025 14:15
 */

Cypress.Commands.add('login', () => {
  cy.visit('/connexion')
  console.log('username:', Cypress.env('username'))
//cimuler le clic sur le bouton contenant l'attribut data-action="login#showFormLogin"
  cy.get('button[data-action="login#showFormLogin"]').click()
  cy.get('input[id="inputUsername"]').type(Cypress.env('username'))
  cy.get('input[id="inputPassword"]').type(Cypress.env('password'), { log: false })
  cy.get('form').submit()
})
