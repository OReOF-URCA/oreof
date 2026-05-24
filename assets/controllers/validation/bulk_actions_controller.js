/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/validation/bulk_actions_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 22/05/2026 22:08
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'

export default class extends Controller {
  static values = {
    selectionClass: {
      type: String,
      default: '.check-all',
    },
  }

  static targets = ['counter', 'actions', 'trigger', 'recap']

  currentActionLabel = 'Action en lot'

  connect () {
    this.refresh()
  }

  refresh () {
    const selected = this._selectedValues()
    const selectedCount = selected.length

    if (this.hasCounterTarget) {
      this.counterTarget.innerText = `${selectedCount} element${selectedCount > 1 ? 's' : ''} selectionne${selectedCount > 1 ? 's' : ''}`
    }

    if (this.hasTriggerTarget) {
      this.triggerTargets.forEach((button) => {
        this._setTriggerState(button, selectedCount > 0)
      })
    }
  }

  async open (event) {
    event.preventDefault()

    const selected = this._selectedValues()
    if (selected.length === 0) {
      callOut('Veuillez selectionner au moins un parcours.', 'danger')
      window.dispatchEvent(new Event('modal:close'))
      this.refresh()
      return
    }

    const url = event.params.url
    if (!url) {
      callOut('Action indisponible.', 'danger')
      return
    }

    this.currentActionLabel = event.params.label || 'Action en lot'
    const body = new URLSearchParams()
    selected.forEach((id) => body.append('parcours[]', id))

    this._clearRecap()

    const response = await fetch(`${url}?${body.toString()}`, {
      headers: {
        Accept: 'text/vnd.turbo-stream.html',
      },
    })

    if (!response.ok) {
      callOut('Impossible de charger la modal de traitement en lot.', 'danger')
      window.dispatchEvent(new Event('modal:close'))
      return
    }

    const stream = await response.text()
    this._renderTurboStream(stream)
  }

  async submit (event) {
    const form = event.target
    if (!(form instanceof window.HTMLFormElement)) {
      return
    }

    if (form.dataset.bulkAction !== 'true') {
      return
    }

    event.preventDefault()

    const formData = new window.FormData(form)
    const response = await fetch(form.action, {
      method: (form.method || 'POST').toUpperCase(),
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/vnd.turbo-stream.html',
      },
    })

    if (!response.ok) {
      callOut('Une erreur est survenue lors du traitement en lot.', 'danger')
      return
    }

    const stream = await response.text()
    this._renderTurboStream(stream)
    this._clearSelection()
    this.refresh()
  }

  onBulkSuccess (event) {
    const count = Number(event?.detail?.count ?? 0)
    const message = `${this.currentActionLabel} executee sur ${count} element${count > 1 ? 's' : ''}.`
    this._showRecap(message)
  }

  _selectedValues () {
    return Array.from(this.element.querySelectorAll(`${this.selectionClassValue}:checked`)).map((checkbox) => checkbox.value)
  }

  _clearSelection () {
    this.element.querySelectorAll(this.selectionClassValue).forEach((checkbox) => {
      checkbox.checked = false
    })

    const allCheckbox = this.element.querySelector('#validation-dpe-all')
    if (allCheckbox) {
      allCheckbox.checked = false
    }
  }

  _showRecap (message) {
    if (!this.hasRecapTarget) {
      return
    }

    this.recapTarget.innerText = message
    this.recapTarget.classList.remove('hidden')
  }

  _clearRecap () {
    if (!this.hasRecapTarget) {
      return
    }

    this.recapTarget.innerText = ''
    this.recapTarget.classList.add('hidden')
  }

  _renderTurboStream (streamMarkup) {
    if (window.Turbo && typeof window.Turbo.renderStreamMessage === 'function') {
      window.Turbo.renderStreamMessage(streamMarkup)
      return
    }

    callOut('La réponse Turbo n\'a pas pu être rendue.', 'warning')
  }

  _setTriggerState (button, isEnabled) {
    button.classList.toggle('pointer-events-none', !isEnabled)
    button.classList.toggle('opacity-50', !isEnabled)
    button.classList.toggle('saturate-50', !isEnabled)
    button.setAttribute('aria-disabled', isEnabled ? 'false' : 'true')

    if (button instanceof window.HTMLButtonElement) {
      button.disabled = !isEnabled
    }
  }
}



