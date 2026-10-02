import { Controller } from '@hotwired/stimulus'
import { renderStreamMessage } from '@hotwired/turbo'

// Nombre de parcours par requête quand on déplie tout
const TAILLE_LOT = 50

/*
 * Tableau de l'offre de formation (offre_v2/index.html.twig).
 *  - composantes : un <tbody> par composante ; l'attribut data-collapsed masque ses lignes enfants
 *    (classe group-data-[collapsed]:hidden) ;
 *  - parcours : le bouton du parcours (data-parcours-id) affiche / masque ses lignes d'années
 *    (tr[data-parcours-annee]). Elles sont chargées au premier dépliage (Turbo Stream, route
 *    offre_v2_annees_parcours) et insérées après la ligne du parcours (tr#offre-parcours-{id}).
 * L'état est porté par aria-expanded sur les boutons (lecteurs d'écran, rotation du chevron).
 */
export default class extends Controller {
  static targets = ['composante']
  static values = { anneesUrl: String }

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

  async toggleParcours (event) {
    const button = event.currentTarget
    const expanded = button.getAttribute('aria-expanded') !== 'true'
    if (expanded) {
      await this.chargerAnnees([button])
    }
    this.setParcours(button, expanded)
  }

  async expandParcours () {
    const buttons = [...this.parcoursButtons()]
    await this.chargerAnnees(buttons)
    buttons.forEach(button => this.setParcours(button, true))
  }

  collapseParcours () {
    this.parcoursButtons().forEach(button => this.setParcours(button, false))
  }

  parcoursButtons () {
    return this.element.querySelectorAll('[data-action~="offre-table#toggleParcours"]')
  }

  // Charge (une seule fois) les lignes d'années des parcours qui ne les ont pas encore.
  async chargerAnnees (buttons) {
    const aCharger = buttons.filter(button => button.dataset.anneesChargees !== 'true')
    for (let i = 0; i < aCharger.length; i += TAILLE_LOT) {
      const lot = aCharger.slice(i, i + TAILLE_LOT)
      lot.forEach(button => { button.dataset.anneesChargees = 'true' })
      const ids = lot.map(button => button.dataset.parcoursId).join(',')
      try {
        const response = await fetch(`${this.anneesUrlValue}?parcours=${ids}`, {
          headers: { Accept: 'text/vnd.turbo-stream.html' }
        })
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        renderStreamMessage(await response.text())
      } catch (error) {
        // Nouvel essai possible au prochain clic
        lot.forEach(button => { delete button.dataset.anneesChargees })
        console.error('Chargement des années impossible :', error)
      }
    }
  }

  setParcours (button, expanded) {
    button.setAttribute('aria-expanded', String(expanded))
    this.element.querySelectorAll(`tr[data-parcours-annee="${button.dataset.parcoursId}"]`).forEach(row => {
      row.classList.toggle('hidden', !expanded)
    })
  }
}
