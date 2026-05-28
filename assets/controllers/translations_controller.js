/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/translations_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 17/05/2026 11:26
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

/**
 * Contrôleur Stimulus pour l'édition inline des traductions.
 *
 * Usage :
 *   <div data-controller="translations"
 *        data-translations-save-url-value="/translations/save-one/fichier.yaml"
 *        data-translations-delete-url-value="/translations/delete-one/fichier.yaml">
 */
export default class extends Controller {
  static values = {
    saveUrl: String,
    deleteUrl: String,
  }

  static targets = ['tbody', 'addRowBtn']

  // ── Actions publiques ────────────────────────────────────────────────────

  /**
   * Passe une ligne en mode édition.
   */
  edit (event) {
    const row = event.currentTarget.closest('tr')
    this._enterEditMode(row)
  }

  /**
   * Annule l'édition et restaure l'état affichage.
   */
  cancel (event) {
    const row = event.currentTarget.closest('tr')

    if (row.dataset.isNew === 'true') {
      row.remove()
      return
    }

    // Restaure la valeur originale dans l'input
    const input = row.querySelector('[data-role="value-input"]')
    input.value = row.dataset.originalValue ?? input.value

    this._exitEditMode(row)
  }

  /**
   * Sauvegarde la ligne en cours d'édition via AJAX.
   */
  async save (event) {
    const row = event.currentTarget.closest('tr')
    const isNew = row.dataset.isNew === 'true'
    const saveBtn = row.querySelector('[data-role="save-btn"]')

    const key = isNew
      ? row.querySelector('[data-role="key-input"]').value.trim()
      : row.dataset.translationKey
    const value = row.querySelector('[data-role="value-input"]').value
    const saveUrl = row.dataset.saveUrl || this.saveUrlValue

    if (!key) {
      callOut('La cle ne peut pas etre vide.', 'danger')
      return
    }

    if (!saveUrl) {
      callOut('URL de sauvegarde introuvable.', 'danger')
      return
    }

    saveBtn.disabled = true
    this._clearError(row)

    try {
      const res = await fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ key, value }),
      })

      const json = await res.json()

      if (!json.success) throw new Error(json.error ?? 'Erreur inconnue')

      // Mise à jour de l'affichage
      row.dataset.translationKey = key
      row.dataset.originalValue = value
      row.dataset.isNew = 'false'

      row.querySelector('[data-role="value-display"]').textContent = value

      if (isNew) {
        row.querySelector('[data-role="key-display"]').textContent = key
        row.querySelector('[data-role="key-input"]').classList.add('hidden')
        row.querySelector('[data-role="key-display"]').classList.remove('hidden')
      }

      this._exitEditMode(row)
      callOut('Traduction enregistrée.', 'success')
    } catch (e) {
      this._showError(row, e.message)
      callOut(e.message, 'danger')
    } finally {
      saveBtn.disabled = false
    }
  }

  /**
   * Ajoute une nouvelle ligne éditable en bas du tableau.
   */
  addRow () {
    const row = document.createElement('tr')
    row.dataset.isNew = 'true'
    row.dataset.translationKey = ''
    row.dataset.originalValue = ''
    row.classList.add('border-b', 'border-secondary-200', 'bg-primary-50/40')

    row.innerHTML = `
      <td class="px-4 py-2 align-top">
        <span data-role="key-display" class="hidden font-mono text-xs text-secondary-700"></span>
        <input
          data-role="key-input"
          type="text"
          placeholder="nouvelle.cle"
          class="w-full rounded-md border border-primary-400 bg-white px-2 py-1.5 font-mono text-xs text-secondary-800
                 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
        />
      </td>
      <td class="px-4 py-2 align-top">
        <span data-role="value-display" class="hidden text-sm text-secondary-700"></span>
        <div class="flex flex-col gap-1">
          <input
            data-role="value-input"
            type="text"
            placeholder="Valeur de la traduction"
            class="w-full rounded-md border border-primary-400 bg-white px-3 py-1.5 text-sm text-secondary-800
                   focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
          />
          <p data-role="error" class="hidden text-xs text-danger-600"></p>
        </div>
      </td>
      <td class="px-4 py-2 align-top text-right">
        <div data-role="view-actions" class="hidden gap-1 justify-end"></div>
        <div data-role="edit-actions" class="flex gap-1 justify-end">
          <button type="button"
                  data-role="save-btn"
                  data-action="translations#save"
                  class="inline-flex items-center gap-1 rounded-md border border-success-300 bg-success-50
                         px-2.5 py-1.5 text-xs font-medium text-success-700 hover:bg-success-100 transition-colors
                         disabled:opacity-50 disabled:cursor-not-allowed">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Enregistrer
          </button>
          <button type="button"
                  data-action="translations#cancel"
                  class="inline-flex items-center gap-1 rounded-md border border-secondary-300 bg-white
                         px-2.5 py-1.5 text-xs font-medium text-secondary-600 hover:bg-secondary-50 transition-colors">
            Annuler
          </button>
        </div>
      </td>
    `

    this.tbodyTarget.appendChild(row)
    row.querySelector('[data-role="key-input"]').focus()
  }

  // ── Helpers privés ───────────────────────────────────────────────────────

  _enterEditMode (row) {
    const valueDisplay = row.querySelector('[data-role="value-display"]')
    const valueInput = row.querySelector('[data-role="value-input"]')
    const viewActions = row.querySelector('[data-role="view-actions"]')
    const editActions = row.querySelector('[data-role="edit-actions"]')

    row.dataset.originalValue = valueDisplay.textContent
    valueInput.value = valueDisplay.textContent

    valueDisplay.classList.add('hidden')
    valueInput.classList.remove('hidden')

    viewActions.classList.add('hidden')
    viewActions.classList.remove('flex')

    editActions.classList.remove('hidden')
    editActions.classList.add('flex')

    valueInput.focus()
    valueInput.select()
  }

  _exitEditMode (row) {
    const valueDisplay = row.querySelector('[data-role="value-display"]')
    const valueInput = row.querySelector('[data-role="value-input"]')
    const viewActions = row.querySelector('[data-role="view-actions"]')
    const editActions = row.querySelector('[data-role="edit-actions"]')

    valueDisplay.classList.remove('hidden')
    valueInput.classList.add('hidden')

    viewActions.classList.remove('hidden')
    viewActions.classList.add('flex')

    editActions.classList.add('hidden')
    editActions.classList.remove('flex')
  }

  _showError (row, message) {
    const err = row.querySelector('[data-role="error"]')
    if (err) {
      err.textContent = message
      err.classList.remove('hidden')
    }
  }

  _clearError (row) {
    const err = row.querySelector('[data-role="error"]')
    if (err) err.classList.add('hidden')
  }
}

