/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file assets/controllers/parcours/rome_controller.js
 * @project oreof
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = [
    'listeCodes',
    'codeInput',
    'codesText',
    'searchResults',
    'verifyLoader',
  ]

  static values = {
    url: String,
    urlCodeRome: String,
    urlCodeRomeGere: String,
    initialCodes: Array,
    apiConnected: { type: Boolean, default: false },
    searchUrl: String,
    verifyUrl: String,
  }

  connect () {
    this.verifiedData = {}
    this.debounceTimer = null

    // Fermer les résultats de recherche au clic en dehors
    this.onDocumentClick = this.handleDocumentClick.bind(this)
    document.addEventListener('click', this.onDocumentClick)

    // Mode v2: collection locale synchronisée avec un champ texte unique (CSV)
    if (this.hasCodesTextTarget) {
      this.codes = this._readCodesFromField()
      if (this.codes.length === 0 && this.hasInitialCodesValue) {
        this.codes = this._normalizeCodes(this.initialCodesValue)
      }
      this._syncFieldAndRender(false)

      // Si l'API est connectée et qu'il y a des codes déjà saisis, vérifier la cohérence
      if (this.apiConnectedValue && this.codes.length > 0) {
        this.verifyConsistency()
      }
      return
    }

    // Mode legacy wizard (déprécié): gestion distante via endpoints dédiés
    this._loadRome()
  }

  disconnect () {
    if (this.onDocumentClick) {
      document.removeEventListener('click', this.onDocumentClick)
    }
  }

  handleDocumentClick (event) {
    if (this.hasSearchResultsTarget && !this.element.contains(event.target)) {
      this.hideSearchResults()
    }
  }

  /**
   * Recherche en temps réel via l'API ROME 4.0 (autocomplétion)
   */
  onSearchInput (event) {
    if (!this.apiConnectedValue || !this.hasSearchUrlValue) {
      return
    }

    const query = (event.target.value || '').trim()
    clearTimeout(this.debounceTimer)

    if (query.length < 2) {
      this.hideSearchResults()
      return
    }

    this.debounceTimer = setTimeout(() => {
      this.performSearch(query)
    }, 280)
  }

  async performSearch (query) {
    if (!this.hasSearchResultsTarget) {
      return
    }

    try {
      const url = `${this.searchUrlValue}?q=${encodeURIComponent(query)}`
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
      })

      if (!response.ok) {
        return
      }

      const data = await response.json()
      const results = data.results || []

      this.renderSearchResults(results)
    } catch (e) {
      console.warn('Erreur de recherche ROME:', e)
    }
  }

  renderSearchResults (results) {
    if (!this.hasSearchResultsTarget) {
      return
    }

    if (results.length === 0) {
      this.searchResultsTarget.innerHTML = `
        <div class="p-3 text-xs text-secondary-500 italic">
          Aucun métier correspondant trouvé dans le ROME 4.0.
        </div>
      `
      this.searchResultsTarget.classList.remove('hidden')
      return
    }

    const items = results.map((item) => {
      const isAlreadyAdded = this.codes.includes(item.code)
      return `
        <button
          type="button"
          class="w-full text-left p-2.5 hover:bg-primary-50 dark:hover:bg-primary-950/40 flex items-start justify-between gap-2 border-b border-secondary-100 dark:border-secondary-800 last:border-b-0 transition cursor-pointer ${isAlreadyAdded ? 'opacity-50 cursor-not-allowed bg-secondary-50 dark:bg-secondary-800/40' : ''}"
          data-action="click->parcours--rome#selectSearchResult"
          data-code="${item.code}"
          data-libelle="${this._escapeHtml(item.libelle)}"
          ${isAlreadyAdded ? 'disabled' : ''}
        >
          <div class="min-w-0">
            <div class="flex items-center gap-1.5">
              <span class="font-mono font-bold text-xs text-primary-700 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/60 px-1.5 py-0.5 rounded">
                ${item.code}
              </span>
              <span class="text-xs font-semibold text-secondary-900 dark:text-secondary-100 truncate">
                ${this._escapeHtml(item.libelle)}
              </span>
            </div>
            ${item.famille ? `<div class="text-[11px] text-secondary-500 dark:text-secondary-400 mt-0.5">${this._escapeHtml(item.famille)}</div>` : ''}
          </div>
          <span class="text-[10px] font-bold ${isAlreadyAdded ? 'text-secondary-400' : 'text-primary-600'} shrink-0 mt-0.5">
            ${isAlreadyAdded ? 'Déjà ajouté' : '+ Ajouter'}
          </span>
        </button>
      `
    })

    this.searchResultsTarget.innerHTML = items.join('')
    this.searchResultsTarget.classList.remove('hidden')
  }

  selectSearchResult (event) {
    event.preventDefault()
    const target = event.currentTarget
    const code = (target.dataset.code || '').toUpperCase()
    const libelle = target.dataset.libelle || ''

    if (!code) {
      return
    }

    if (!this.codes.includes(code)) {
      this.codes.push(code)
      this.codes.sort()
      this.verifiedData[code] = {
        code,
        libelle,
        valid: true,
        message: 'Sélectionné depuis le ROME 4.0',
      }
      this._syncFieldAndRender()
      callOut(`Code ROME ${code} ajouté`, 'success')
    }

    this.hideSearchResults()
    if (this.hasCodeInputTarget) {
      this.codeInputTarget.value = ''
      this.codeInputTarget.focus()
    }
  }

  hideSearchResults () {
    if (this.hasSearchResultsTarget) {
      this.searchResultsTarget.classList.add('hidden')
      this.searchResultsTarget.innerHTML = ''
    }
  }

  /**
   * Vérifie la cohérence des codes auprès de l'API ROME 4.0
   */
  async verifyConsistency () {
    if (!this.hasVerifyUrlValue || this.codes.length === 0) {
      return
    }

    if (this.hasVerifyLoaderTarget) {
      this.verifyLoaderTarget.classList.remove('hidden')
    }

    try {
      const response = await fetch(this.verifyUrlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ codes: this.codes }),
      })

      if (response.ok) {
        const data = await response.json()
        const verifiedList = data.codes || []
        verifiedList.forEach((item) => {
          this.verifiedData[item.code] = item
        })
        this._renderCodesList()
      }
    } catch (e) {
      console.warn('Erreur vérification cohérence ROME:', e)
    } finally {
      if (this.hasVerifyLoaderTarget) {
        this.verifyLoaderTarget.classList.add('hidden')
      }
    }
  }

  async removeCode (event) {
    event.preventDefault()

    if (this.hasCodesTextTarget) {
      const code = String(event.params.code || '').toUpperCase()
      this.codes = this.codes.filter((item) => item !== code)
      delete this.verifiedData[code]
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
      callOut('Code ROME invalide (format attendu : 1 lettre + 4 chiffres, ex: M1805)', 'danger')
      return
    }

    if (this.hasCodesTextTarget) {
      if (!this.codes.includes(code)) {
        this.codes.push(code)
        this.codes.sort()
        this._syncFieldAndRender()

        // Si l'API est connectée, déclencher la vérification du nouveau code
        if (this.apiConnectedValue) {
          this.verifyConsistency()
        }
      }
      if (this.hasCodeInputTarget) {
        this.codeInputTarget.value = ''
        this.codeInputTarget.focus()
      }
      this.hideSearchResults()
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
      this.listeCodesTarget.innerHTML = '<p class="text-xs text-secondary-500 italic mt-2">Aucun code ROME associé à ce parcours.</p>'
      return
    }

    const badges = this.codes.map((code) => {
      const verified = this.verifiedData[code]
      const isValid = verified ? verified.valid : null
      const libelle = verified && verified.libelle ? verified.libelle : ''
      const tooltip = verified && verified.message ? verified.message : (this.apiConnectedValue ? 'Vérification en cours...' : 'Saisi manuellement')

      // Styles visuels selon cohérence API
      let statusIndicator = ''
      let badgeClasses = 'border-secondary-200 bg-secondary-50 text-secondary-800 dark:border-secondary-700 dark:bg-secondary-800/80 dark:text-secondary-100'

      if (this.apiConnectedValue) {
        if (isValid === true) {
          statusIndicator = `
            <span class="inline-flex items-center text-success-600 dark:text-success-400" title="${this._escapeHtml(tooltip)}">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 256 256" fill="currentColor"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>
            </span>
          `
          badgeClasses = 'border-success-200 bg-success-50/70 text-success-900 dark:border-success-800 dark:bg-success-950/40 dark:text-success-200'
        } else if (isValid === false) {
          statusIndicator = `
            <span class="inline-flex items-center text-danger-600 dark:text-danger-400" title="${this._escapeHtml(tooltip)}">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-80V80a8,8,0,0,1,16,0v56a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,172Z"/></svg>
            </span>
          `
          badgeClasses = 'border-danger-200 bg-danger-50/80 text-danger-900 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-200'
        }
      }

      return `
        <div class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-xs transition ${badgeClasses}">
          <div class="flex items-center gap-1.5 min-w-0">
            ${statusIndicator}
            <span class="font-mono font-bold">${code}</span>
            ${libelle ? `<span class="text-[11px] font-normal text-secondary-600 dark:text-secondary-300 truncate max-w-[220px]" title="${this._escapeHtml(libelle)}">— ${this._escapeHtml(libelle)}</span>` : ''}
          </div>
          <button
            type="button"
            class="inline-flex h-4 w-4 items-center justify-center rounded-full text-secondary-400 hover:bg-secondary-200 hover:text-secondary-700 dark:hover:bg-secondary-700 dark:hover:text-secondary-200 cursor-pointer"
            data-action="click->parcours--rome#removeCode"
            data-parcours--rome-code-param="${code}"
            aria-label="Supprimer ${code}"
            title="Retirer ce code"
          >
            &times;
          </button>
        </div>
      `
    })

    this.listeCodesTarget.innerHTML = `<div class="flex flex-wrap gap-2 mt-2">${badges.join('')}</div>`
  }

  _escapeHtml (str) {
    if (!str) return ''
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }
}
