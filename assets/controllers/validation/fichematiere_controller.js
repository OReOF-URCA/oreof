/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/validation/fichematiere_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/09/2026
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = ['action']

  connect() {
  }

  detail(event) {
    const btn = event.currentTarget || event.target.closest('button') || event.target
    const parcoursId = event.params?.parcours
    if (!parcoursId) return

    const detailEl = document.getElementById(`detail_parcours_${parcoursId}`)
    if (!detailEl) return

    const isHidden = detailEl.classList.contains('hidden') || detailEl.classList.contains('d-none')

    if (isHidden) {
      detailEl.classList.remove('hidden')
      detailEl.classList.remove('d-none')
      btn.dataset.state = 'open'
      const icon = btn.querySelector('svg, i')
      if (icon) {
        icon.classList.add('rotate-180')
      }
    } else {
      detailEl.classList.add('hidden')
      detailEl.classList.add('d-none')
      btn.dataset.state = 'close'
      const icon = btn.querySelector('svg, i')
      if (icon) {
        icon.classList.remove('rotate-180')
      }
    }
  }

  async valide(event) {
    const liste = document.querySelectorAll('input[name="formations[]"]:checked, .check-all:checked:not([id^="check-all"])')
    if (liste.length === 0) {
      callOut('Veuillez sélectionner au moins une fiche EC/matière à valider', 'warning')
      return
    }

    const btn = event.currentTarget || event.target.closest('button') || event.target
    const originalDisabled = btn.disabled
    btn.disabled = true

    const fiches = []
    liste.forEach((item) => {
      if (item.value) {
        fiches.push(item.value)
      }
    })

    try {
      const response = await fetch(`${event.params.url}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          fiches: fiches.join(','),
        }),
      })

      if (response.ok) {
        callOut('Fiches validées avec succès', 'success')
        window.location.reload()
      } else {
        callOut('Une erreur est survenue lors de la validation', 'danger')
        btn.disabled = originalDisabled
      }
    } catch {
      callOut('Une erreur réseau est survenue', 'danger')
      btn.disabled = originalDisabled
    }
  }
}


