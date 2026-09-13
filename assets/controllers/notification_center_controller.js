/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file assets/controllers/notification_center_controller.js
 * @author davidannebicque
 * @project oreofv2
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    toggleUrl: String,
    resetUrl: String,
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
          const badge = row.querySelector('[data-badge]')
          if (badge) {
            badge.textContent = 'Personnalisé'
            badge.className = 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
          }
          const resetBtn = row.querySelector('[data-reset-btn]')
          if (resetBtn) {
            resetBtn.classList.remove('hidden')
          }
        }
      } else {
        throw new Error('Save failed')
      }
    } catch (err) {
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
        }
      } else {
        throw new Error('Reset failed')
      }
    } catch (err) {
      callOut('Erreur lors du rétablissement de l\'héritage', 'danger')
    }
  }
}
