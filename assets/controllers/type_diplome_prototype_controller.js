/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/type_diplome_prototype_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/04/2026 16:41
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../js/callOut'

export default class extends Controller {
  static targets = ['list', 'search', 'stage', 'memoire', 'limit']

  static values = {
    listUrl: String,
    storageKey: { type: String, default: 'type_diplome_prototype_state' },
  }

  connect () {
    this.state = {
      q: '',
      hasStage: '',
      hasMemoire: '',
      sort: 'libelle',
      direction: 'asc',
      page: 1,
      limit: 25,
    }

    this.searchDebounce = null
    this.pendingDelete = null
    this.confirmDeleteHandler = this.confirmDelete.bind(this)

    const confirmBtn = document.getElementById('btn-confirm-valide')
    if (confirmBtn) {
      confirmBtn.addEventListener('click', this.confirmDeleteHandler)
    }

    this.hydrateState()
    this.syncInputsWithState()
    this.loadList()
  }

  disconnect () {
    const confirmBtn = document.getElementById('btn-confirm-valide')
    if (confirmBtn) {
      confirmBtn.removeEventListener('click', this.confirmDeleteHandler)
    }
  }

  search (event) {
    const value = event.target.value.trim()
    clearTimeout(this.searchDebounce)
    this.searchDebounce = setTimeout(() => {
      this.state.q = value
      this.state.page = 1
      this.persistState()
      this.loadList()
    }, 250)
  }

  filter (event) {
    const field = event.target.dataset.field
    this.state[field] = event.target.value
    this.state.page = 1
    this.persistState()
    this.loadList()
  }

  changeLimit (event) {
    this.state.limit = parseInt(event.target.value, 10)
    this.state.page = 1
    this.persistState()
    this.loadList()
  }

  sort (event) {
    const { field, direction } = event.currentTarget.dataset
    this.state.sort = field
    this.state.direction = direction
    this.state.page = 1
    this.persistState()
    this.loadList()
  }

  goToPage (event) {
    this.state.page = parseInt(event.currentTarget.dataset.page, 10)
    this.persistState()
    this.loadList()
  }

  resetFilters () {
    this.state = {
      ...this.state,
      q: '',
      hasStage: '',
      hasMemoire: '',
      sort: 'libelle',
      direction: 'asc',
      page: 1,
      limit: 25,
    }

    localStorage.removeItem(this.storageKeyValue)
    this.syncInputsWithState()
    this.loadList()
  }

  async duplicate (event) {
    const { url, csrf } = event.currentTarget.dataset
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf }),
    })

    const data = await response.json()
    if (!response.ok || data.success !== true) {
      callOut(data.message || 'Erreur lors de la duplication.', 'danger')
      return
    }

    callOut(data.message || 'Duplication effectuée.', 'success')
    this.loadList()
  }

  async prepareDelete (event) {
    const { impactUrl, url, csrf, label } = event.currentTarget.dataset

    const impactResponse = await fetch(impactUrl)
    if (!impactResponse.ok) {
      callOut('Impossible de calculer l\'impact de suppression.', 'danger')
      return
    }

    const impact = await impactResponse.json()
    const titleEl = document.getElementById('modal-confirm-title')
    const bodyEl = document.getElementById('modal-confirm-body')

    if (titleEl) {
      titleEl.innerText = `Supprimer "${label}" ?`
    }

    if (bodyEl) {
      const levelLabel = this.impactLevelLabel(impact.level)
      const blockerHtml = impact.blockers.length > 0
        ? `<ul>${impact.blockers.map((item) => `<li>${item}</li>`).join('')}</ul>`
        : '<p class="mb-2">Aucun blocage détecté.</p>'

      bodyEl.innerHTML = `
        <p class="mb-2">Score d'impact: <strong>${impact.score}</strong> (${levelLabel})</p>
        <p class="mb-2">Relations détectées:</p>
        <ul>
          <li>Formations: <strong>${impact.counts.formations}</strong></li>
          <li>Mentions: <strong>${impact.counts.mentions}</strong></li>
          <li>Fiches matière: <strong>${impact.counts.ficheMatieres}</strong></li>
          <li>Demandes formation: <strong>${impact.counts.formationDemandes}</strong></li>
          <li>Types EC: <strong>${impact.counts.typeEcs}</strong></li>
          <li>Types UE: <strong>${impact.counts.typeUes}</strong></li>
          <li>Types épreuve: <strong>${impact.counts.typeEpreuves}</strong></li>
        </ul>
        <div class="alert ${impact.canDelete ? 'alert-warning' : 'alert-danger'} mb-0">
          ${impact.canDelete ? 'Suppression possible, action irréversible.' : 'Suppression bloquée.'}
          ${blockerHtml}
        </div>
      `
    }

    this.pendingDelete = { url, csrf, canDelete: impact.canDelete }
    Modal.getOrCreateInstance(document.getElementById('modal-confirm')).show()
  }

  async confirmDelete () {
    if (!this.pendingDelete) {
      return
    }

    if (!this.pendingDelete.canDelete) {
      callOut('Suppression bloquée: résoudre les dépendances.', 'warning')
      return
    }

    const response = await fetch(this.pendingDelete.url, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: this.pendingDelete.csrf }),
    })

    const data = await response.json()
    if (!response.ok || data.success !== true) {
      callOut(data.message || 'Erreur lors de la suppression.', 'danger')
      return
    }

    this.pendingDelete = null
    callOut(data.message || 'Suppression effectuée.', 'success')
    this.loadList()
  }

  hydrateState () {
    const saved = localStorage.getItem(this.storageKeyValue)
    if (!saved) {
      return
    }

    try {
      const parsed = JSON.parse(saved)
      this.state = { ...this.state, ...parsed }
    } catch {
      localStorage.removeItem(this.storageKeyValue)
    }
  }

  syncInputsWithState () {
    if (this.hasSearchTarget) this.searchTarget.value = this.state.q
    if (this.hasStageTarget) this.stageTarget.value = this.state.hasStage
    if (this.hasMemoireTarget) this.memoireTarget.value = this.state.hasMemoire
    if (this.hasLimitTarget) this.limitTarget.value = this.state.limit.toString()
  }

  persistState () {
    localStorage.setItem(this.storageKeyValue, JSON.stringify(this.state))
  }

  async loadList () {
    const params = new URLSearchParams({
      q: this.state.q,
      hasStage: this.state.hasStage,
      hasMemoire: this.state.hasMemoire,
      sort: this.state.sort,
      direction: this.state.direction,
      page: this.state.page.toString(),
      limit: this.state.limit.toString(),
    })

    this.listTarget.innerHTML = '<div class="text-center py-4">Chargement...</div>'

    const response = await fetch(`${this.listUrlValue}?${params.toString()}`)
    this.listTarget.innerHTML = await response.text()
  }

  impactLevelLabel (level) {
    if (level === 'high') {
      return 'élevé'
    }

    if (level === 'medium') {
      return 'modéré'
    }

    return 'faible'
  }
}

