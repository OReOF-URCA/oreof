import { Controller } from '@hotwired/stimulus'

function escapeHtml(str) {
  if (str === null || str === undefined) return ''
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

export default class extends Controller {
  static targets = [
    'tabButton',
    'tabPane',
    'activeTabInput',
    'saveStatus',
    'collapsedSaveStatus',
    'collapsedBar',
    'expandedContent',
    'troncsCommunsList',
    'tcTemplate',
    'emptyState',
    'portailsList',
    'portailTemplate',
    'portailsEmptyState',
    'portailsActionsBar',
  ]

  static values = {
    currentFormationId: Number,
    formationsData: Array,
    nextIndex: Number,
  }

  connect() {
    this.updateEmptyState()
    this.updatePortailsEmptyState()

    let tab = 'troncs-communs'
    if (this.hasActiveTabInputTarget && this.activeTabInputTarget.value) {
      tab = this.activeTabInputTarget.value
    }

    try {
      const saved = sessionStorage.getItem('mutualisation_active_tab_' + this.currentFormationIdValue)
      if (saved) {
        tab = saved
      }
    } catch {
      /* ignore */
    }

    this.applyTab(tab)

    try {
      const isCollapsed = sessionStorage.getItem('mutualisation_collapsed_' + this.currentFormationIdValue) === '1'
      this.applyCollapse(isCollapsed)
    } catch {
      /* ignore */
    }

    // Restaurer l'état déplié/replié de chaque carte portail
    const unfoldedIds = new Set(this.getUnfoldedPortailIds().map(String))
    if (this.hasPortailsListTarget) {
      this.portailsListTarget.querySelectorAll('[data-portail-card]').forEach(card => {
        const id = String(card.dataset.portailId || '')
        const body = card.querySelector('[data-portail-body]')
        const toggleText = card.querySelector('[data-portail-toggle-text]')
        const chevron = card.querySelector('[data-portail-chevron]')

        const shouldBeUnfolded = unfoldedIds.has(id) || id.startsWith('new_')
        if (body) {
          body.classList.toggle('hidden', !shouldBeUnfolded)
        }
        if (toggleText) {
          toggleText.textContent = shouldBeUnfolded ? 'Replier' : 'Déplier'
        }
        if (chevron) {
          chevron.classList.toggle('rotate-180', shouldBeUnfolded)
        }
        this.updatePortailState(card)
      })
    }

    try {
      if (sessionStorage.getItem('mutualisation_just_saved_' + this.currentFormationIdValue) === '1') {
        sessionStorage.removeItem('mutualisation_just_saved_' + this.currentFormationIdValue)
        this.setSaveStatus('success', '✓ Enregistré avec succès')
        setTimeout(() => this.setSaveStatus('idle', ''), 3000)
      }
    } catch {
      /* ignore */
    }
  }

  toggleCollapse() {
    const isCurrentlyCollapsed = this.isCollapsedState()
    const nextState = !isCurrentlyCollapsed
    try {
      sessionStorage.setItem('mutualisation_collapsed_' + this.currentFormationIdValue, nextState ? '1' : '0')
    } catch {
      /* ignore */
    }
    this.applyCollapse(nextState)
  }

  isCollapsedState() {
    return this.hasCollapsedBarTarget && !this.collapsedBarTarget.classList.contains('hidden')
  }

  applyCollapse(collapsed) {
    if (this.hasCollapsedBarTarget && this.hasExpandedContentTarget) {
      this.collapsedBarTarget.classList.toggle('hidden', !collapsed)
      this.expandedContentTarget.classList.toggle('hidden', collapsed)
    }
  }

  disconnect() {
    clearTimeout(this.autoSaveTimeout)
  }

  switchTab(event) {
    const targetTab = event.currentTarget.dataset.tabTarget
    if (!targetTab) return
    this.applyTab(targetTab)
  }

  applyTab(targetTab) {
    this.currentActiveTab = targetTab
    try {
      sessionStorage.setItem('mutualisation_active_tab_' + this.currentFormationIdValue, targetTab)
    } catch {
      /* ignore */
    }

    if (this.hasActiveTabInputTarget) {
      this.activeTabInputTarget.value = targetTab
    }

    this.tabButtonTargets.forEach(btn => {
      const isTarget = btn.dataset.tabTarget === targetTab
      btn.classList.toggle('bg-primary-600', isTarget)
      btn.classList.toggle('text-white', isTarget)
      btn.classList.toggle('shadow-xs', isTarget)
      btn.classList.toggle('text-secondary-600', !isTarget)
      btn.classList.toggle('dark:text-secondary-300', !isTarget)
      btn.setAttribute('aria-selected', isTarget ? 'true' : 'false')
    })

    this.tabPaneTargets.forEach(pane => {
      pane.classList.toggle('hidden', pane.dataset.tabPane !== targetTab)
    })
  }

  // --- TRONCS COMMUNS ---

  addTroncCommun() {
    const index = this.nextIndexValue || (this.troncsCommunsListTarget.querySelectorAll('[data-tc-card]').length + 1)
    this.nextIndexValue = index + 1

    const template = this.tcTemplateTarget
    if (!template) return

    let html = template.innerHTML.replace(/__INDEX__/g, index)
    html = html.replace(/__NUM__/g, index)

    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = html.trim()
    const newCard = tempDiv.firstElementChild

    this.troncsCommunsListTarget.appendChild(newCard)
    this.updateEmptyState()

    // Amener la nouvelle carte en vue et donner le focus
    newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    const nomInput = newCard.querySelector('input[type="text"]')
    if (nomInput) {
      nomInput.focus()
    }
  }

  removeTroncCommun(event) {
    const card = event.currentTarget.closest('[data-tc-card]')
    if (!card) return

    card.remove()
    this.updateEmptyState()
    this.notifyDirty()
  }

  selectAllParcours(event) {
    const card = event.currentTarget.closest('[data-tc-card]')
    if (!card) return
    const checkboxes = card.querySelectorAll('input[type="checkbox"][name$="[parcours][]"]')
    checkboxes.forEach(cb => {
      cb.checked = true
      this.updateCheckboxStyle(cb)
    })
    this.notifyDirty()
  }

  deselectAllParcours(event) {
    const card = event.currentTarget.closest('[data-tc-card]')
    if (!card) return
    const checkboxes = card.querySelectorAll('input[type="checkbox"][name$="[parcours][]"]')
    checkboxes.forEach(cb => {
      cb.checked = false
      this.updateCheckboxStyle(cb)
    })
    this.notifyDirty()
  }

  createQuickDefaultTc() {
    this.addTroncCommun()
    const lastCard = this.troncsCommunsListTarget.lastElementChild
    if (!lastCard) return

    const annee1Cb = lastCard.querySelector('input[type="checkbox"][name$="[annees][]"][value="1"]')
    if (annee1Cb) {
      annee1Cb.checked = true
      this.updateCheckboxStyle(annee1Cb)
    }

    const parcoursCbs = lastCard.querySelectorAll('input[type="checkbox"][name$="[parcours][]"]')
    parcoursCbs.forEach(cb => {
      cb.checked = true
      this.updateCheckboxStyle(cb)
    })

    this.notifyDirty()
  }

  updateEmptyState() {
    if (!this.hasEmptyStateTarget || !this.hasTroncsCommunsListTarget) return
    const count = this.troncsCommunsListTarget.querySelectorAll('[data-tc-card]').length
    this.emptyStateTarget.classList.toggle('hidden', count > 0)
  }

  // --- PORTAILS ---

  getUnfoldedPortailIds() {
    try {
      const raw = sessionStorage.getItem('mutualisation_unfolded_portails_' + this.currentFormationIdValue)
      return raw ? JSON.parse(raw) : []
    } catch {
      return []
    }
  }

  setPortailUnfolded(portailId, unfolded) {
    if (!portailId) return
    try {
      const key = 'mutualisation_unfolded_portails_' + this.currentFormationIdValue
      const ids = new Set(this.getUnfoldedPortailIds().map(String))
      if (unfolded) {
        ids.add(String(portailId))
      } else {
        ids.delete(String(portailId))
      }
      sessionStorage.setItem(key, JSON.stringify(Array.from(ids)))
    } catch {
      /* ignore */
    }
  }

  togglePortailCardCollapse(event) {
    const card = event.currentTarget.closest('[data-portail-card]')
    if (!card) return

    const portailId = card.dataset.portailId
    const body = card.querySelector('[data-portail-body]')
    if (!body) return

    const isCurrentlyHidden = body.classList.contains('hidden')
    body.classList.toggle('hidden', !isCurrentlyHidden)

    const toggleText = card.querySelector('[data-portail-toggle-text]')
    const chevron = card.querySelector('[data-portail-chevron]')

    if (toggleText) {
      toggleText.textContent = isCurrentlyHidden ? 'Replier' : 'Déplier'
    }

    if (chevron) {
      chevron.classList.toggle('rotate-180', isCurrentlyHidden)
    }

    if (portailId) {
      this.setPortailUnfolded(portailId, isCurrentlyHidden)
    }
  }

  updatePortailState(card) {
    if (!card) return

    const formationCards = card.querySelectorAll('[data-formation-card]')
    const count = formationCards.length

    // 1. Mettre à jour le texte du badge de comptage
    const countText = card.querySelector('[data-portail-count-text]')
    if (countText) {
      countText.textContent = `${count} formation${count > 1 ? 's' : ''}`
    }

    // 2. Mettre à jour l'état vide
    const emptyMsg = card.querySelector('[data-portail-formations-empty]')
    if (emptyMsg) {
      emptyMsg.classList.toggle('hidden', count > 0)
    }

    // 3. Détecter les types de diplôme distincts
    const types = Array.from(formationCards)
      .map(el => (el.dataset.typeDiplome || '').trim())
      .filter(t => t.length > 0)
    const uniqueTypes = Array.from(new Set(types))
    const hasMultiple = uniqueTypes.length > 1
    const typesStr = uniqueTypes.join(', ')

    // 4. Mettre à jour le badge d'avertissement dans l'en-tête
    const warningBadge = card.querySelector('[data-portail-warning-badge]')
    const warningBadgeTypes = card.querySelector('[data-portail-warning-badge-types]')
    if (warningBadge) {
      warningBadge.classList.toggle('hidden', !hasMultiple)
      warningBadge.classList.toggle('inline-flex', hasMultiple)
      if (hasMultiple) {
        warningBadge.setAttribute('title', `Ce portail contient des formations aux types de diplôme différents : ${typesStr}`)
      } else {
        warningBadge.removeAttribute('title')
      }
    }
    if (warningBadgeTypes) {
      warningBadgeTypes.textContent = hasMultiple ? typesStr : ''
    }

    // 5. Mettre à jour la bannière d'avertissement dans le corps
    const warningBanner = card.querySelector('[data-portail-warning-banner]')
    const warningBannerTypes = card.querySelector('[data-portail-warning-banner-types]')
    if (warningBanner) {
      warningBanner.classList.toggle('hidden', !hasMultiple)
      warningBanner.classList.toggle('flex', hasMultiple)
    }
    if (warningBannerTypes) {
      warningBannerTypes.textContent = hasMultiple ? typesStr : ''
    }
  }

  addPortail() {
    this.applyTab('portails')
    const template = this.hasPortailTemplateTarget ? this.portailTemplateTarget : null
    if (!template) return

    const portailId = 'new_' + Date.now()
    const html = template.innerHTML.replace(/__PORTAIL_ID__/g, portailId)

    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = html.trim()
    const newCard = tempDiv.firstElementChild

    if (this.hasPortailsListTarget) {
      this.portailsListTarget.appendChild(newCard)
    }

    this.setPortailUnfolded(portailId, true)
    this.updatePortailState(newCard)
    this.updatePortailsEmptyState()
    this.notifyDirty()
  }

  removePortail(event) {
    const card = event.currentTarget.closest('[data-portail-card]')
    if (!card) return

    const portailId = card.dataset.portailId
    if (portailId) {
      this.setPortailUnfolded(portailId, false)
      if (!portailId.startsWith('new_')) {
        const form = this.element.closest('form')
        if (form) {
          const hidden = document.createElement('input')
          hidden.type = 'hidden'
          hidden.name = 'portails_supprimes[]'
          hidden.value = portailId
          form.appendChild(hidden)
        }
      }
    }

    card.remove()
    this.updatePortailsEmptyState()
    this.notifyDirty()
  }

  addFormationToPortail(event) {
    this.applyTab('portails')
    const card = event.currentTarget.closest('[data-portail-card]')
    if (!card) return

    const portailId = card.dataset.portailId
    const select = card.querySelector('select[data-add-formation-select]')
    if (!select) return

    let formationIdStr = select.value
    if (select.tomselect && typeof select.tomselect.getValue === 'function') {
      formationIdStr = select.tomselect.getValue()
    }
    const formationId = parseInt(formationIdStr, 10)
    if (!formationId) return

    // Vérifier si cette formation est déjà présente dans ce portail
    const existing = card.querySelector(`[data-formation-card][data-formation-id="${formationId}"]`)
    if (existing) {
      existing.scrollIntoView({ behavior: 'smooth', block: 'center' })
      existing.classList.add('ring-2', 'ring-primary-500')
      setTimeout(() => existing.classList.remove('ring-2', 'ring-primary-500'), 1500)
      return
    }

    const formation = this.findFormation(formationId)
    if (!formation) return

    const formationsContainer = card.querySelector('[data-portail-formations]')
    if (!formationsContainer) return

    const fHtml = this.buildFormationHtml(portailId, formation, 1)
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = fHtml.trim()
    formationsContainer.appendChild(tempDiv.firstElementChild)

    if (select.tomselect && typeof select.tomselect.clear === 'function') {
      select.tomselect.clear()
    } else {
      select.value = ''
    }

    this.updatePortailState(card)
    this.notifyDirty()
  }

  removeFormationFromPortail(event) {
    const fCard = event.currentTarget.closest('[data-formation-card]')
    if (!fCard) return

    const portailCard = fCard.closest('[data-portail-card]')
    fCard.remove()

    if (portailCard) {
      this.updatePortailState(portailCard)
    }

    this.notifyDirty()
  }

  selectAnneeForAllParcours(event) {
    const fCard = event.currentTarget.closest('[data-formation-card]')
    if (!fCard) return
    const anneeOrdre = event.currentTarget.dataset.anneeOrdre
    if (!anneeOrdre) return

    const checkboxes = fCard.querySelectorAll(`input[type="checkbox"][data-annee-ordre="${anneeOrdre}"]`)
    checkboxes.forEach(cb => {
      cb.checked = true
      this.updateCheckboxStyle(cb)
    })
    this.notifyDirty()
  }

  selectAllPortailParcours(event) {
    const fCard = event.currentTarget.closest('[data-formation-card]')
    if (!fCard) return
    const checkboxes = fCard.querySelectorAll('input[type="checkbox"][name$="[annees][]"]')
    checkboxes.forEach(cb => {
      cb.checked = true
      this.updateCheckboxStyle(cb)
    })
    this.notifyDirty()
  }

  deselectAllPortailParcours(event) {
    const fCard = event.currentTarget.closest('[data-formation-card]')
    if (!fCard) return
    const checkboxes = fCard.querySelectorAll('input[type="checkbox"][name$="[annees][]"]')
    checkboxes.forEach(cb => {
      cb.checked = false
      this.updateCheckboxStyle(cb)
    })
    this.notifyDirty()
  }

  toggleCheckboxStyle(event) {
    const cb = event.target
    if (cb && cb.type === 'checkbox') {
      this.updateCheckboxStyle(cb)
      this.notifyDirty()
    }
  }

  updateCheckboxStyle(cb) {
    const label = cb.closest('label')
    if (!label) return
    const checked = cb.checked

    label.classList.toggle('bg-primary-50', checked)
    label.classList.toggle('border-primary-300', checked)
    label.classList.toggle('text-primary-800', checked)
    label.classList.toggle('dark:bg-primary-900/30', checked)
    label.classList.toggle('dark:border-primary-700', checked)
    label.classList.toggle('dark:text-primary-300', checked)

    label.classList.toggle('bg-surface', !checked)
    label.classList.toggle('border-secondary-200', !checked)
    label.classList.toggle('text-secondary-600', !checked)
    label.classList.toggle('dark:bg-secondary-900', !checked)
    label.classList.toggle('dark:border-secondary-700', !checked)
    label.classList.toggle('dark:text-secondary-400', !checked)
  }

  updatePortailsEmptyState() {
    if (!this.hasPortailsEmptyStateTarget || !this.hasPortailsListTarget) return
    const count = this.portailsListTarget.querySelectorAll('[data-portail-card]').length
    this.portailsEmptyStateTarget.classList.toggle('hidden', count > 0)
    if (this.hasPortailsActionsBarTarget) {
      this.portailsActionsBarTarget.classList.toggle('hidden', count === 0)
    }
  }

  findFormation(formationId) {
    const list = this.formationsDataValue || []
    return list.find(f => f.id === formationId) || null
  }

  buildFormationHtml(portailId, formation, defaultCheckedOrdre = 1) {
    const diplomeLib = escapeHtml(formation.typeDiplome || 'Année')

    const quickAnneesButtons = (formation.annees || []).map(ordre => {
      return `
        <button type="button"
                class="hover:underline cursor-pointer"
                data-action="click->mutualisation#selectAnneeForAllParcours"
                data-annee-ordre="${ordre}"
        >
          + Toute l'Année ${ordre}
        </button>
        <span>•</span>
      `
    }).join('')

    const parcoursRows = (formation.parcours || []).map(p => {
      const anneesBadges = (p.annees || []).map(a => {
        const isChecked = (parseInt(a.ordre, 10) === parseInt(defaultCheckedOrdre, 10)) ? 'checked' : ''
        return `
          <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-xs font-medium cursor-pointer transition-colors ${isChecked ? 'bg-primary-50 border-primary-300 text-primary-800 dark:bg-primary-900/30 dark:border-primary-700 dark:text-primary-300' : 'bg-surface border-secondary-200 text-secondary-600 hover:bg-secondary-50 dark:bg-secondary-900 dark:border-secondary-700 dark:text-secondary-400'}">
            <input type="checkbox"
                   name="portails[${escapeHtml(portailId)}][annees][]"
                   value="${a.id}"
                   data-annee-ordre="${a.ordre}"
                   data-action="change->mutualisation#toggleCheckboxStyle"
                   ${isChecked}
                   class="rounded text-primary-600 focus:ring-primary-500"
            >
            <span>${diplomeLib} ${a.ordre}</span>
          </label>
        `
      }).join('')

      const tpLibelle = p.typeParcoursLibelle
      const tpBadge = tpLibelle ? `
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-secondary-100 text-secondary-700 dark:bg-secondary-800 dark:text-secondary-300">
          ${escapeHtml(tpLibelle)}
        </span>
      ` : ''

      return `
        <div class="flex items-center justify-between gap-3 p-2 rounded-lg border border-secondary-200 dark:border-secondary-700/60 bg-surface dark:bg-secondary-900/40">
          <div class="flex items-center gap-1.5 flex-1 min-w-[180px]">
            <span class="text-xs font-medium text-secondary-900 dark:text-secondary-100">${escapeHtml(p.libelle)}</span>
            ${tpBadge}
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            ${anneesBadges}
          </div>
        </div>
      `
    }).join('')

    return `
      <div class="rounded-lg border border-secondary-200 dark:border-secondary-700/80 bg-secondary-50/40 dark:bg-secondary-900/30 p-3.5"
           data-formation-card="true"
           data-formation-id="${formation.id}"
           data-type-diplome="${escapeHtml(formation.typeDiplome || '')}"
      >
        <input type="hidden" name="portails[${escapeHtml(portailId)}][formations_presentes][]" value="${formation.id}">

        <div class="flex items-center justify-between border-b border-secondary-200 dark:border-secondary-700/60 pb-2 mb-2.5 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300">
              ${escapeHtml(formation.typeDiplome || 'Diplôme')}
            </span>
            <span class="text-xs font-bold text-secondary-900 dark:text-secondary-100">
              ${escapeHtml(formation.libelle)}
            </span>
            <span class="text-[11px] text-secondary-500 dark:text-secondary-400">
              (${escapeHtml(formation.composante)})
            </span>
          </div>

          <button type="button"
                  class="inline-flex items-center gap-1 text-xs text-danger-600 hover:text-danger-800 hover:bg-danger-50 dark:hover:bg-danger-900/30 px-2 py-0.5 rounded transition-colors cursor-pointer"
                  data-action="click->mutualisation#removeFormationFromPortail"
                  title="Retirer cette formation du portail"
          >
            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            <span>Retirer de ce portail</span>
          </button>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
            <label class="text-xs font-semibold text-secondary-700 dark:text-secondary-300">
              Parcours et années intégrés au portail :
            </label>
            <div class="inline-flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400 flex-wrap">
              ${quickAnneesButtons}
              <button type="button" class="hover:underline cursor-pointer" data-action="click->mutualisation#selectAllPortailParcours">Tout cocher</button>
              <span>•</span>
              <button type="button" class="hover:underline cursor-pointer" data-action="click->mutualisation#deselectAllPortailParcours">Tout décocher</button>
            </div>
          </div>

          <div class="space-y-1.5">
            ${parcoursRows}
          </div>
        </div>
      </div>
    `
  }

  notifyDirty() {
    this.setSaveStatus('dirty', 'Enregistrement automatique...')
    clearTimeout(this.autoSaveTimeout)
    this.autoSaveTimeout = setTimeout(() => {
      this.saveMutualisation()
    }, 1200)
  }

  async saveMutualisation(event) {
    if (event) event.preventDefault()
    clearTimeout(this.autoSaveTimeout)

    const form = this.element.closest('form')
    if (!form) return

    this.setSaveStatus('saving', 'Enregistrement en cours...')

    let activeInputName = null
    let activeInputId = null
    let selectionStart = null
    let selectionEnd = null
    if (document.activeElement && document.activeElement.tagName === 'INPUT' && document.activeElement.type === 'text') {
      activeInputName = document.activeElement.name
      activeInputId = document.activeElement.id
      selectionStart = document.activeElement.selectionStart
      selectionEnd = document.activeElement.selectionEnd
    }

    try {
      const url = form.getAttribute('action') || window.location.href
      const formData = new FormData(form)

      if (this.currentActiveTab) {
        formData.set('active_mutualisation_tab', this.currentActiveTab)
      }

      const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'text/vnd.turbo-stream.html',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })

      if (response.ok) {
        try {
          sessionStorage.setItem('mutualisation_just_saved_' + this.currentFormationIdValue, '1')
        } catch {
          /* ignore */
        }

        const text = await response.text()
        if (window.Turbo && typeof window.Turbo.renderStreamMessage === 'function') {
          await window.Turbo.renderStreamMessage(text)
          if (activeInputName || activeInputId) {
            const selector = activeInputId ? `#${activeInputId}` : `input[name="${activeInputName}"]`
            const restoredInput = document.querySelector(selector)
            if (restoredInput) {
              restoredInput.focus()
              if (selectionStart !== null && selectionEnd !== null) {
                try {
                  restoredInput.setSelectionRange(selectionStart, selectionEnd)
                } catch {
                  /* ignore */
                }
              }
            }
          }
        } else {
          this.setSaveStatus('success', '✓ Enregistré avec succès')
          setTimeout(() => this.setSaveStatus('idle', ''), 3000)
        }
      } else {
        this.setSaveStatus('error', 'Erreur lors de l’enregistrement')
      }
    } catch {
      this.setSaveStatus('error', 'Erreur réseau lors de l’enregistrement')
    }
  }

  setSaveStatus(state, message) {
    const targets = [
      this.hasSaveStatusTarget ? this.saveStatusTarget : null,
      this.hasCollapsedSaveStatusTarget ? this.collapsedSaveStatusTarget : null,
    ].filter(Boolean)

    if (targets.length === 0) return

    targets.forEach(el => {
      el.classList.remove(
        'hidden',
        'text-success-600',
        'dark:text-success-400',
        'text-danger-600',
        'dark:text-danger-400',
        'text-secondary-500',
        'text-warning-600',
        'dark:text-warning-400'
      )

      if (state === 'idle' || !message) {
        el.classList.add('hidden')
        el.textContent = ''
        return
      }

      el.classList.remove('hidden')
      el.textContent = message

      if (state === 'success') {
        el.classList.add('text-success-600', 'dark:text-success-400')
      } else if (state === 'error') {
        el.classList.add('text-danger-600', 'dark:text-danger-400')
      } else if (state === 'dirty') {
        el.classList.add('text-warning-600', 'dark:text-warning-400')
      } else {
        el.classList.add('text-secondary-500')
      }
    })
  }
}
