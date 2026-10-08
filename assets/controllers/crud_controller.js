/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/crud_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/03/2023 09:57
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import { useDebounce } from 'stimulus-use'
import callOut from '../js/callOut'
import updateUrl from '../js/updateUrl'

export default class extends Controller {
  static targets = ['liste']

  static values = {
    url: String,
    page: Number,
    storageKey: { type: String, default: 'crud_state' }, // Clé pour localStorage
  }

  static debounces = ['rechercher']

  fields = {}

  scrollPosition = 0

  connect() {
    useDebounce(this, { wait: 500 })

    // Restaurer l'état depuis localStorage si disponible
    const savedState = this.getSavedState()
    if (savedState) {
      this.fields = savedState

      // Restaurer la valeur du champ de recherche si elle existe
      if (savedState.q) {
        const searchInput = document.getElementById('filtre_crud') || document.getElementById('filter-quick-search')
        if (searchInput) {
          searchInput.value = savedState.q
        }
      }

      // Restaurer la valeur du sélecteur de limite si elle existe
      if (savedState.limit) {
        const limitSelect = document.querySelector('select[data-action*="crud#filter"]')
        if (limitSelect) {
          limitSelect.value = savedState.limit
        }
      }
    } else {
      this.fields = {
        page: this.pageValue ?? 1,
      }
    }

    this._updateListe(this.fields)
  }

  // Sauvegarde l'état actuel dans localStorage
  saveState() {
    localStorage.setItem(this.storageKeyValue, JSON.stringify(this.fields))
  }

  // Récupère l'état sauvegardé depuis localStorage
  getSavedState() {
    const savedState = localStorage.getItem(this.storageKeyValue)
    return savedState ? JSON.parse(savedState) : null
  }

  filter(event) {
    if (event.target.value === '') {
      delete this.fields[event.params.field]
    } else {
      this.fields[event.params.field] = event.target.value
    }
    this.saveState()
    this._updateListe(this.fields)
  }

  page(event) {
    this.fields.page = event.params.page
    updateUrl({ page: event.params.page })
    this.saveState()
    this._updateListe(this.fields)
  }

  rechercher(event) {
    if (event) {
      event.preventDefault()
      this.fields.q = event.target.value
    }
    this.saveState()
    this._updateListe(this.fields)
  }

  effaceFiltre(event) {
    if (event) {
      event.preventDefault()
    }
    this.fields = {}

    const searchInput = document.getElementById('filtre_crud')
    if (searchInput) {
      searchInput.value = ''
    }

    const quickSearchInput = document.getElementById('filter-quick-search')
    if (quickSearchInput) {
      quickSearchInput.value = ''
    }

    localStorage.removeItem(this.storageKeyValue)
    this._updateListe(this.fields)
  }

  delete(event) {
    event.preventDefault()
    const { url } = event.params
    const { csrf } = event.params
    let modal = new Modal(document.getElementById('modal-delete'))
    modal.show()
    const btn = document.getElementById('btn-confirm-supprimer')
    btn.replaceWith(btn.cloneNode(true))
    document.getElementById('btn-confirm-supprimer').addEventListener('click', async () => {
      const body = {
        method: 'DELETE',
        body: JSON.stringify({
          csrf,
        }),
      }
      modal = null
      await fetch(url, body).then(async (e) => {
        if (e.status === 200) {
          callOut('Suppression effectuée', 'success')
          // Après une suppression, on reste sur la même page si possible
          await this._updateListe(this.fields)
        } else {
          const data = await e.json()
          if (data.message !== undefined && data.message.trim() !== '') {
            callOut(data.message, 'danger')
          } else {
            callOut('Erreur lors de la suppression', 'danger')
          }
        }
      })
    })
  }

  async duplicate(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('Duplication effectuée', 'success')
      // Après une duplication, on reste sur la même page
      this._updateListe(this.fields)
    })
  }

  refreshListe() {
    // Lors d'un rafraîchissement, on utilise les paramètres sauvegardés
    const savedState = this.getSavedState()
    if (savedState) {
      this._updateListe(savedState)
    } else {
      this._updateListe(this.fields)
    }
  }

  async _updateListe(params) {
    this.scrollPosition = window.scrollY
    const activeElement = document.activeElement
    const activeId = activeElement?.id
    const isSearchInput = activeId === 'filter-quick-search' || activeId === 'filtre_crud'
    const selectionStart = isSearchInput ? activeElement.selectionStart : null
    const selectionEnd = isSearchInput ? activeElement.selectionEnd : null

    const _params = new URLSearchParams(params)
    this.listeTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(`${this.urlValue}?${_params.toString()}`)
    this.listeTarget.innerHTML = await response.text()

    if (isSearchInput && activeId) {
      const refreshedInput = document.getElementById(activeId)
      if (refreshedInput) {
        refreshedInput.focus()
        if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
          refreshedInput.setSelectionRange(selectionStart, selectionEnd)
        }
      }
    }

    window.scrollTo(0, this.scrollPosition)
  }

  sort(event) {
    this.fields.sort = event.params.sort
    this.fields.direction = event.params.direction
    this._updateListe(this.fields)
  }
}
