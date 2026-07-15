/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/delete_confirm_controller.js
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 17/05/2026 19:58
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['modal']

  connect () {
    this.boundKeyDown = this.onKeyDown.bind(this)
  }

  open (event) {
    event.preventDefault()
    const modalId = event.currentTarget.dataset.deleteConfirmModalIdValue
    const modal = this.modalTargets.find((m) => m.id === modalId)

    if (!modal) return

    modal.classList.remove('hidden')
    document.documentElement.classList.add('overflow-hidden')
    document.addEventListener('keydown', this.boundKeyDown)
  }

  close (event) {
    if (event) {
      event.preventDefault()
    }

    this.modalTargets.forEach((modal) => {
      modal.classList.add('hidden')
    })

    document.documentElement.classList.remove('overflow-hidden')
    document.removeEventListener('keydown', this.boundKeyDown)
  }

  disconnect () {
    document.documentElement.classList.remove('overflow-hidden')
    document.removeEventListener('keydown', this.boundKeyDown)
  }

  onKeyDown (event) {
    if (event.key === 'Escape') {
      this.close()
    }
  }
}

