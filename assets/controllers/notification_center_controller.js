/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file assets/controllers/notification_center_controller.js
 * @author davidannebicque
 * @project oreofv2
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
    static targets = ['step', 'searchInput', 'filterBtn', 'customCount', 'workflowTab', 'workflowPanel']
  static values = {
    toggleUrl: String,
    resetUrl: String,
  }

    connect() {
        this.updateCustomCounter()
    }

    switchWorkflow(event) {
        const target = event.currentTarget
        const wfName = target.dataset.workflowName

        this.workflowTabTargets.forEach((tab) => {
            const isActive = tab === target
            tab.classList.toggle('bg-primary', isActive)
            tab.classList.toggle('text-white', isActive)
            tab.classList.toggle('border-primary', isActive)
            tab.classList.toggle('shadow-xs', isActive)

            tab.classList.toggle('bg-white', !isActive)
            tab.classList.toggle('text-secondary-700', !isActive)
            tab.classList.toggle('border-secondary-200', !isActive)
            tab.classList.toggle('dark:bg-secondary-800', !isActive)
            tab.classList.toggle('dark:text-secondary-300', !isActive)
            tab.classList.toggle('dark:border-secondary-700', !isActive)

            const badge = tab.querySelector('[data-tab-count]')
            if (badge) {
                badge.classList.toggle('bg-white/20', isActive)
                badge.classList.toggle('text-white', isActive)
                badge.classList.toggle('bg-secondary-100', !isActive)
                badge.classList.toggle('text-secondary-600', !isActive)
                badge.classList.toggle('dark:bg-secondary-700', !isActive)
                badge.classList.toggle('dark:text-secondary-300', !isActive)
            }
        })

        this.workflowPanelTargets.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.workflowName !== wfName)
        })
    }

  async toggle(event) {
    const input = event.target
    const channel = input.dataset.channel
    const workflow = input.dataset.workflow || null
    const place = input.dataset.place || null
    const transition = input.dataset.transition || null
    const enabled = input.checked

    try {
      const response = await fetch(this.toggleUrlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          workflow,
          place,
          transition,
          channel,
          enabled,
        }),
      })

      if (!response.ok) {
        throw new Error('Network error')
      }

      const data = await response.json()
      if (data.success) {
        callOut(data.message || 'Préférence enregistrée', 'success')

        const row = input.closest('[data-setting-row]')
        if (row && workflow) {
            row.dataset.isCustom = 'true'
          const badge = row.querySelector('[data-badge]')
          if (badge) {
            badge.textContent = 'Personnalisé'
            badge.className = 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
          }
          const resetBtn = row.querySelector('[data-reset-btn]')
          if (resetBtn) {
            resetBtn.classList.remove('hidden')
          }
            this.updateCustomCounter()
        }
      } else {
        throw new Error('Save failed')
      }
    } catch {
      input.checked = !enabled
      callOut('Erreur lors de la sauvegarde de la préférence', 'danger')
    }
  }

  async reset(event) {
    const button = event.currentTarget
    const workflow = button.dataset.workflow
    const place = button.dataset.place || null
    const transition = button.dataset.transition || null

    try {
      const response = await fetch(this.resetUrlValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          workflow,
          place,
          transition,
        }),
      })

      if (!response.ok) {
        throw new Error('Network error')
      }

      const data = await response.json()
      if (data.success) {
        callOut(data.message || 'Héritage rétabli', 'info')

        const row = button.closest('[data-setting-row]')
        if (row) {
            row.dataset.isCustom = 'false'
          const emailInput = row.querySelector('input[data-channel="email"]')
          if (emailInput && data.channels) {
            emailInput.checked = !!data.channels.email
          }
          const inappInput = row.querySelector('input[data-channel="inapp"]')
          if (inappInput && data.channels) {
            inappInput.checked = !!data.channels.inapp
          }

          const badge = row.querySelector('[data-badge]')
          if (badge) {
            badge.textContent = 'Hérité : ' + (data.source || 'défaut')
            badge.className = 'inline-flex items-center rounded-full bg-secondary-100 px-2 py-0.5 text-[11px] font-medium text-secondary-600 dark:bg-secondary-800 dark:text-secondary-400'
          }

          button.classList.add('hidden')
            this.updateCustomCounter()
        }
      } else {
        throw new Error('Reset failed')
      }
    } catch {
      callOut('Erreur lors du rétablissement de l\'héritage', 'danger')
    }
  }

    expandAll(event) {
        const panel = event?.currentTarget?.closest('[data-notification-center-target="workflowPanel"]')
        const steps = panel ? panel.querySelectorAll('details[data-notification-center-target="step"]') : this.stepTargets
        steps.forEach((step) => {
            step.open = true
        })
    }

    collapseAll(event) {
        const panel = event?.currentTarget?.closest('[data-notification-center-target="workflowPanel"]')
        const steps = panel ? panel.querySelectorAll('details[data-notification-center-target="step"]') : this.stepTargets
        steps.forEach((step) => {
            step.open = false
        })
    }

    filter(event) {
        const query = (event.target.value || '').trim().toLowerCase()
        const panel = event.target.closest('[data-notification-center-target="workflowPanel"]')
        const steps = panel ? panel.querySelectorAll('details[data-notification-center-target="step"]') : this.stepTargets

        steps.forEach((step) => {
            const stepName = (step.dataset.stepName || '').toLowerCase()
            const stepLabel = (step.dataset.stepLabel || '').toLowerCase()
            const stepMatches = !query || stepName.includes(query) || stepLabel.includes(query)

            let anyTransitionMatches = false
            const transitionRows = step.querySelectorAll('[data-transition-row]')
            transitionRows.forEach((row) => {
                const tName = (row.dataset.transitionName || '').toLowerCase()
                const tLabel = (row.dataset.transitionLabel || '').toLowerCase()
                const matches = !query || tName.includes(query) || tLabel.includes(query)
                if (matches) {
                    anyTransitionMatches = true
                    row.classList.remove('hidden')
                } else {
                    row.classList.toggle('hidden', !stepMatches)
                }
            })

            if (stepMatches || anyTransitionMatches) {
                step.classList.remove('hidden')
                if (query) {
                    step.open = true
                }
            } else {
                step.classList.add('hidden')
            }
        })
    }

    filterStatus(event) {
        const target = event.currentTarget
        const status = target.dataset.status // 'all' | 'custom' | 'inherited'
        const panel = target.closest('[data-notification-center-target="workflowPanel"]')

        // Update active button state within panel
        const filterBtns = panel ? panel.querySelectorAll('[data-notification-center-target="filterBtn"]') : this.filterBtnTargets
        filterBtns.forEach((btn) => {
            const isActive = btn === target
            btn.classList.toggle('bg-primary-50', isActive)
            btn.classList.toggle('text-primary-700', isActive)
            btn.classList.toggle('border-primary-300', isActive)
            btn.classList.toggle('dark:bg-primary-950/40', isActive)
            btn.classList.toggle('dark:text-primary-300', isActive)
            btn.classList.toggle('dark:border-primary-700', isActive)

            btn.classList.toggle('bg-white', !isActive)
            btn.classList.toggle('text-secondary-600', !isActive)
            btn.classList.toggle('border-secondary-200', !isActive)
            btn.classList.toggle('dark:bg-secondary-800', !isActive)
            btn.classList.toggle('dark:text-secondary-400', !isActive)
            btn.classList.toggle('dark:border-secondary-700', !isActive)
        })

        const steps = panel ? panel.querySelectorAll('details[data-notification-center-target="step"]') : this.stepTargets
        steps.forEach((step) => {
            const stepRow = step.querySelector('[data-setting-row]')
            const isStepCustom = stepRow?.dataset.isCustom === 'true'

            let hasMatchingTransition = false
            const transitionRows = step.querySelectorAll('[data-transition-row]')
            transitionRows.forEach((row) => {
                const isCustom = row.dataset.isCustom === 'true'
                let showRow = true
                if (status === 'custom') {
                    showRow = isCustom
                } else if (status === 'inherited') {
                    showRow = !isCustom
                }

                row.classList.toggle('hidden', !showRow)
                if (showRow) {
                    hasMatchingTransition = true
                }
            })

            if (status === 'all') {
                step.classList.remove('hidden')
            } else if (status === 'custom') {
                const show = isStepCustom || hasMatchingTransition
                step.classList.toggle('hidden', !show)
                if (show) step.open = true
            } else if (status === 'inherited') {
                const show = !isStepCustom || hasMatchingTransition
                step.classList.toggle('hidden', !show)
            }
        })
    }

    updateCustomCounter() {
        if (!this.hasCustomCountTarget) return
        const customRows = this.element.querySelectorAll('[data-setting-row][data-is-custom="true"]')
        this.customCountTarget.textContent = customRows.length.toString()
    }
}
