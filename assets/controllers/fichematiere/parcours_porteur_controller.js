/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/fichematiere/parcours_porteur_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/03/2026 20:40
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = ['formation', 'parcours', 'submit']

  static values = {
    url: String,
    currentFormation: Number,
    currentParcours: Number,
  }

  connect () {
    if (this.currentFormationValue > 0) {
      this.formationTarget.value = `${this.currentFormationValue}`
      void this._getParcours(this.currentFormationValue, this.currentParcoursValue)
      return
    }

    this.updateSubmitState()
  }

  async changeFormation (event) {
    const formationId = event.target.value
    const parcoursId = Number(formationId) === this.currentFormationValue ? this.currentParcoursValue : null
    await this._getParcours(formationId, parcoursId)
  }

  updateSubmitState () {
    const formationValue = this.formationTarget.value
    const parcoursValue = this.parcoursTarget.value
    const sameSelection = Number(formationValue) === this.currentFormationValue && Number(parcoursValue) === this.currentParcoursValue

    this.submitTarget.disabled = formationValue === '' || parcoursValue === '' || sameSelection
  }

  async deplacer (event) {
    event.preventDefault()

    this.submitTarget.disabled = true

    try {
      const response = await fetch(this.urlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          field: 'save',
          formation: this.formationTarget.value,
          parcours: this.parcoursTarget.value,
        }),
      })

      const data = await response.json()

      if (!response.ok || data.success === false) {
        callOut(data.message ?? 'Erreur lors du déplacement du parcours porteur.', 'danger')
        this.updateSubmitState()
        return
      }

      let message = data.message ?? 'Le parcours porteur a été modifié.'
      if (data.ancienParcoursMutualise === true) {
        message += ' L’ancien parcours porteur a été conservé dans les mutualisations car la fiche y est encore utilisée.'
      }

      callOut(message, 'success')
      this._closeModal()
      window.dispatchEvent(new CustomEvent('base:refreshStep'))
    } catch {
      callOut('Erreur lors du déplacement du parcours porteur.', 'danger')
      this.updateSubmitState()
    }
  }

  async _getParcours (formationId, selectedParcoursId = null) {
    this.parcoursTarget.innerHTML = ''

    if (formationId === '' || Number(formationId) === 0) {
      const option = document.createElement('option')
      option.value = ''
      option.text = 'Choisir d\'abord une mention'
      this.parcoursTarget.add(option)
      this.updateSubmitState()
      return
    }

    try {
      const response = await fetch(this.urlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          field: 'parcours',
          value: formationId,
        }),
      })

      if (!response.ok) {
        const option = document.createElement('option')
        option.value = ''
        option.text = 'Erreur lors du chargement des parcours'
        this.parcoursTarget.add(option)
        this.updateSubmitState()
        callOut('Impossible de charger la liste des parcours pour la mention sélectionnée.', 'danger')
        return
      }

      const data = await response.json()
      this._updateParcoursOptions(data, selectedParcoursId)
    } catch {
      const option = document.createElement('option')
      option.value = ''
      option.text = 'Erreur lors du chargement des parcours'
      this.parcoursTarget.add(option)
      this.updateSubmitState()
      callOut('Impossible de charger la liste des parcours pour la mention sélectionnée.', 'danger')
    }
  }

  _updateParcoursOptions (data, selectedParcoursId = null) {
    this.parcoursTarget.innerHTML = ''

    if (data.length === 0) {
      const option = document.createElement('option')
      option.value = ''
      option.text = 'Aucun parcours disponible'
      this.parcoursTarget.add(option)
      this.updateSubmitState()
      return
    }

    if (data.length > 1) {
      const option = document.createElement('option')
      option.value = ''
      option.text = 'Choisir dans la liste'
      this.parcoursTarget.add(option)
    }

    data.forEach((item) => {
      const option = document.createElement('option')
      option.value = item.id
      option.text = item.libelle
      this.parcoursTarget.add(option)
    })

    if (selectedParcoursId !== null && data.some((item) => Number(item.id) === Number(selectedParcoursId))) {
      this.parcoursTarget.value = `${selectedParcoursId}`
    } else if (data.length === 1) {
      this.parcoursTarget.value = `${data[0].id}`
    }

    this.updateSubmitState()
  }

  _closeModal () {
    const modalElement = this.element.closest('.modal')
    if (modalElement === null) {
      return
    }

    const modal = Modal.getInstance(modalElement) ?? new Modal(modalElement)
    modal.hide()
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
      backdrop.remove()
    })
  }
}


