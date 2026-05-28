/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/step7_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:11
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = [
    'listeCodes',
    'codeInput',
    'codesText',
  ]

  static values = {
    url: String,
    urlCodeRome: String,
    urlCodeRomeGere: String,
    initialCodes: Array,
  }

  connect () {
    // Mode v2: collection locale synchronisee avec un champ texte unique (CSV)
    if (this.hasCodesTextTarget) {
      this.codes = this._readCodesFromField()
      if (this.codes.length === 0 && this.hasInitialCodesValue) {
        this.codes = this._normalizeCodes(this.initialCodesValue)
      }
      this._syncFieldAndRender(false)
      return
    }

    // Mode legacy wizard (deprecie): gestion distante via endpoints dedies
    this._loadRome()
  }

  async removeCode (event) {
    event.preventDefault()

    if (this.hasCodesTextTarget) {
      const code = String(event.params.code || '').toUpperCase()
      this.codes = this.codes.filter((item) => item !== code)
      this._syncFieldAndRender()
      return
    }

    const body = {
      method: 'POST',
      body: JSON.stringify({
        action: 'DELETE',
        code: event.params.code,
      }),
    }

    await fetch(this.urlCodeRomeGereValue, body).then((response) => response.json()).then((data) => {
      if (data === true) {
        callOut('Code supprimé', 'success')
        this._loadRome()
      } else {
        callOut('Erreur lors de la suppression', 'danger')
      }
    })
  }

  async addCode (event) {
    event.preventDefault()

    const codeInput = this.hasCodeInputTarget
      ? this.codeInputTarget.value.trim()
      : (document.getElementById('codeRomeToAdd')?.value || '').trim()

    const code = codeInput.toUpperCase()
    const romeRegex = /^[A-Z]\d{4}$/
    if (!romeRegex.test(code)) {
      callOut('Code ROME invalide', 'danger')
      return
    }

    if (this.hasCodesTextTarget) {
      if (!this.codes.includes(code)) {
        this.codes.push(code)
        this.codes.sort()
        this._syncFieldAndRender()
      }
      if (this.hasCodeInputTarget) {
        this.codeInputTarget.value = ''
        this.codeInputTarget.focus()
      }
      return
    }

    const body = {
      method: 'POST',
      body: JSON.stringify({
        action: 'ADD',
        code,
      }),
    }

    await fetch(this.urlCodeRomeGereValue, body).then((response) => response.json()).then((data) => {
      if (data === true) {
        callOut('Code ajouté', 'success')
        if (this.hasCodeInputTarget) {
          this.codeInputTarget.value = ''
        }
        this._loadRome()
      } else {
        callOut('Erreur lors de la sauvegarde', 'danger')
      }
    })
  }

  async _loadRome () {
    this.listeCodesTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlCodeRomeValue)
    this.listeCodesTarget.innerHTML = await response.text()
  }

  addCodeFromEnter (event) {
    if (event.key !== 'Enter') {
      return
    }
    this.addCode(event)
  }

  _readCodesFromField () {
    const raw = this.codesTextTarget.value || ''
    if (raw.trim() === '') {
      return []
    }

    return this._normalizeCodes(raw.split(','))
  }

  _normalizeCodes (codes) {
    const romeRegex = /^[A-Z]\d{4}$/
    const set = new Set()

    codes.forEach((value) => {
      const code = String(value || '').trim().toUpperCase()
      if (romeRegex.test(code)) {
        set.add(code)
      }
    })

    return [...set].sort()
  }

  _syncFieldAndRender (triggerAutosave = true) {
    this.codesTextTarget.value = this.codes.join(',')
    this._renderCodesList()

    if (triggerAutosave) {
      this.codesTextTarget.dispatchEvent(new Event('input', { bubbles: true }))
    }
  }

  _renderCodesList () {
    if (!this.hasListeCodesTarget) {
      return
    }

    if (this.codes.length === 0) {
      this.listeCodesTarget.innerHTML = '<p class="text-sm text-secondary-500">Aucun code ROME ajoute.</p>'
      return
    }

    const badges = this.codes.map((code) => `
      <span class="inline-flex items-center gap-2 rounded-full border border-secondary-200 bg-secondary-50 px-3 py-1 text-sm font-medium text-secondary-700">
        <span>${code}</span>
        <button
          type="button"
          class="inline-flex h-5 w-5 items-center justify-center rounded-full text-secondary-500 hover:bg-secondary-200 hover:text-secondary-800"
          data-action="click->parcours--rome#removeCode"
          data-parcours--rome-code-param="${code}"
          aria-label="Supprimer ${code}"
        >
          &times;
        </button>
      </span>
    `)

    this.listeCodesTarget.innerHTML = `<div class="flex flex-wrap gap-2">${badges.join('')}</div>`
  }
}
