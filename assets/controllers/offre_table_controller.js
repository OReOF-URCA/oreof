import { Controller } from '@hotwired/stimulus'

/*
 * Tableau de l'offre de formation (offre_v2/index.html.twig).
 *  - composantes : un <tbody> par composante ; l'attribut data-collapsed masque ses lignes enfants
 *    (classe group-data-[collapsed]:hidden) ;
 *  - parcours : le bouton du parcours (data-parcours-id) affiche / masque ses lignes d'années
 *    (tr[data-parcours-annee]).
 * L'état est porté par aria-expanded sur les boutons (lecteurs d'écran, rotation du chevron).
 */
export default class extends Controller {
  static targets = ['composante']

  toggleComposante (event) {
    const tbody = event.currentTarget.closest('tbody')
    this.setComposante(tbody, tbody.hasAttribute('data-collapsed'))
  }

  expandComposantes () {
    this.composanteTargets.forEach(tbody => this.setComposante(tbody, true))
  }

  collapseComposantes () {
    this.composanteTargets.forEach(tbody => this.setComposante(tbody, false))
  }

  setComposante (tbody, expanded) {
    tbody.toggleAttribute('data-collapsed', !expanded)
    tbody.querySelectorAll('[data-action~="offre-table#toggleComposante"]').forEach(button => {
      button.setAttribute('aria-expanded', String(expanded))
    })
  }

  toggleParcours (event) {
    const button = event.currentTarget
    this.setParcours(button, button.getAttribute('aria-expanded') !== 'true')
  }

  expandParcours () {
    this.parcoursButtons().forEach(button => this.setParcours(button, true))
  }

  collapseParcours () {
    this.parcoursButtons().forEach(button => this.setParcours(button, false))
  }

  parcoursButtons () {
    return this.element.querySelectorAll('[data-action~="offre-table#toggleParcours"]')
  }

  setParcours (button, expanded) {
    button.setAttribute('aria-expanded', String(expanded))
    this.element.querySelectorAll(`tr[data-parcours-annee="${button.dataset.parcoursId}"]`).forEach(row => {
      row.classList.toggle('hidden', !expanded)
    })
  }
}
