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
    } catch (e) {}

    this.applyTab(tab)

    try {
      const isCollapsed = sessionStorage.getItem('mutualisation_collapsed_' + this.currentFormationIdValue) === '1'
      this.applyCollapse(isCollapsed)
    } catch (e) {}

    try {
      if (sessionStorage.getItem('mutualisation_just_saved_' + this.currentFormationIdValue) === '1') {
        sessionStorage.removeItem('mutualisation_just_saved_' + this.currentFormationIdValue)
        this.setSaveStatus('success', '✓ Enregistré avec succès')
        setTimeout(() => this.setSaveStatus('idle', ''), 3000)
      }
    } catch (e) {}
  }

  toggleCollapse() {
    const isCurrentlyCollapsed = this.isCollapsedState()
    const nextState = !isCurrentlyCollapsed
    try {
      sessionStorage.setItem('mutualisation_collapsed_' + this.currentFormationIdValue, nextState ? '1' : '0')
    } catch (e) {}
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
    } catch (e) {}

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
    this.notifyDirty()
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

    this.updatePortailsEmptyState()
    this.notifyDirty()
  }

  removePortail(event) {
    const card = event.currentTarget.closest('[data-portail-card]')
    if (!card) return

    const portailId = card.dataset.portailId
    if (portailId && !portailId.startsWith('new_')) {
      const form = this.element.closest('form')
      if (form) {
        const hidden = document.createElement('input')
        hidden.type = 'hidden'
        hidden.name = 'portails_supprimes[]'
        hidden.value = portailId
        form.appendChild(hidden)
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

    this.notifyDirty()
  }

  removeFormationFromPortail(event) {
    const fCard = event.currentTarget.closest('[data-formation-card]')
    if (!fCard) return

    fCard.remove()
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
    }, 800)
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
        } catch (e) {}

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
                } catch (e) {}
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
    } catch (err) {
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
