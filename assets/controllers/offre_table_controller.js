import { Controller } from '@hotwired/stimulus'
import { renderStreamMessage } from '@hotwired/turbo'

// Nombre de parcours par requête quand on déplie tout
const TAILLE_LOT = 50

/*
 * Tableau de l'offre de formation (offre_v2/index.html.twig).
 *  - composantes : un <tbody> par composante ; l'attribut data-collapsed masque ses lignes enfants
 *    (classe group-data-[collapsed]:hidden) ;
 *  - formations : bouton (data-formation-id) affiche / masque ses lignes de parcours (tr[data-formation-parcours]) ;
 *  - parcours : le bouton du parcours (data-parcours-id) affiche / masque ses lignes d'années
 *    (tr[data-parcours-annee]). Elles sont chargées au premier dépliage (Turbo Stream, route
 *    offre_v2_annees_parcours) et insérées après la ligne du parcours (tr#offre-parcours-{id}).
 * L'état est porté par aria-expanded sur les boutons (lecteurs d'écran, rotation du chevron).
 * Les préférences utilisateur sont persistées dans localStorage.
 */
export default class extends Controller {
  static targets = ['composante']
  static values = {
    anneesUrl: String,
    userId: { type: String, default: '' },
    context: { type: String, default: '' }
  }

  connect () {
    this.restorePreferences()
  }

  get storageKey () {
    const user = this.userIdValue || 'anon'
    const ctx = this.contextValue || 'global'
    return `oreof_offre_table_${user}_${ctx}`
  }

  loadPreferences () {
    try {
      const data = localStorage.getItem(this.storageKey)
      return data ? JSON.parse(data) : null
    } catch {
      return null
    }
  }

  savePreferences (patch) {
    try {
      const current = this.loadPreferences() || {}
      const updated = { ...current, ...patch }
      localStorage.setItem(this.storageKey, JSON.stringify(updated))
    } catch {
      // Ignorer si localStorage indisponible
    }
  }

  restorePreferences () {
    const prefs = this.loadPreferences()
    if (!prefs) return

    // 1. Restaurer l'état des composantes
    if (prefs.composantesAll === 'collapsed') {
      this.composanteTargets.forEach(tbody => this.setComposante(tbody, false))
    } else if (prefs.composantesAll === 'expanded') {
      this.composanteTargets.forEach(tbody => this.setComposante(tbody, true))
    } else if (prefs.composantes) {
      this.composanteTargets.forEach(tbody => {
        const id = tbody.dataset.composanteId
        if (id && Object.prototype.hasOwnProperty.call(prefs.composantes, id)) {
          this.setComposante(tbody, Boolean(prefs.composantes[id]))
        }
      })
    }

    // 2. Restaurer l'état des formations
    if (prefs.formationsAll === 'collapsed') {
      this.formationButtons().forEach(button => this.setFormation(button, false))
    } else if (prefs.formationsAll === 'expanded') {
      this.formationButtons().forEach(button => this.setFormation(button, true))
    } else if (prefs.formations) {
      this.formationButtons().forEach(button => {
        const id = button.dataset.formationId
        if (id && Object.prototype.hasOwnProperty.call(prefs.formations, id)) {
          this.setFormation(button, Boolean(prefs.formations[id]))
        }
      })
    }
  }

  // --- Composantes ---

  toggleComposante (event) {
    const tbody = event.currentTarget.closest('tbody')
    const expanded = tbody.hasAttribute('data-collapsed')
    this.setComposante(tbody, expanded)

    const id = tbody.dataset.composanteId
    if (id) {
      const prefs = this.loadPreferences() || {}
      const composantes = prefs.composantes || {}
      composantes[id] = expanded
      this.savePreferences({ composantes, composantesAll: null })
    }
  }

  expandComposantes () {
    this.composanteTargets.forEach(tbody => this.setComposante(tbody, true))
    this.savePreferences({ composantesAll: 'expanded', composantes: {} })
  }

  collapseComposantes () {
    this.composanteTargets.forEach(tbody => this.setComposante(tbody, false))
    this.savePreferences({ composantesAll: 'collapsed', composantes: {} })
  }

  setComposante (tbody, expanded) {
    tbody.toggleAttribute('data-collapsed', !expanded)
    tbody.querySelectorAll('[data-action~="offre-table#toggleComposante"]').forEach(button => {
      button.setAttribute('aria-expanded', String(expanded))
    })
  }

  // --- Formations (Mentions) ---

  toggleFormation (event) {
    const button = event.currentTarget
    const expanded = button.getAttribute('aria-expanded') !== 'true'
    this.setFormation(button, expanded)

    const id = button.dataset.formationId
    if (id) {
      const prefs = this.loadPreferences() || {}
      const formations = prefs.formations || {}
      formations[id] = expanded
      this.savePreferences({ formations, formationsAll: null })
    }
  }

  expandFormations () {
    this.formationButtons().forEach(button => this.setFormation(button, true))
    this.savePreferences({ formationsAll: 'expanded', formations: {} })
  }

  collapseFormations () {
    this.formationButtons().forEach(button => this.setFormation(button, false))
    this.savePreferences({ formationsAll: 'collapsed', formations: {} })
  }

  setFormation (button, expanded) {
    button.setAttribute('aria-expanded', String(expanded))
    const formationId = button.dataset.formationId
    if (!formationId) return

    const rows = this.element.querySelectorAll(`tr[data-formation-parcours="${formationId}"]`)
    rows.forEach(row => {
      row.classList.toggle('hidden', !expanded)
      // Si la formation est repliée, masquer aussi les lignes d'années enfants
      if (!expanded) {
        const parcoursId = row.id.replace('offre-parcours-', '')
        if (parcoursId) {
          this.element.querySelectorAll(`tr[data-parcours-annee="${parcoursId}"]`).forEach(anRow => {
            anRow.classList.add('hidden')
          })
          const parcBtn = row.querySelector('[data-action~="offre-table#toggleParcours"]')
          if (parcBtn) {
            parcBtn.setAttribute('aria-expanded', 'false')
          }
        }
      }
    })
  }

  formationButtons () {
    return this.element.querySelectorAll('[data-action~="offre-table#toggleFormation"]')
  }

  // --- Parcours (Années) ---

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
