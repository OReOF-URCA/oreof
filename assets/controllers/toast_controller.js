/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/toast_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/01/2026 11:38
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    timeout: Number,
    undoUrl: String,
    undoPayload: Object
  }

  connect () {
    // Keep modal errors inside the active dialogue, where they remain visible and reachable.
    const modal = document.getElementById('modal')
    const body = modal?.querySelector('#modal_body')
    if (this.element.dataset.toastType === 'danger' && body && !modal.classList.contains('hidden') && !body.contains(this.element)) {
      body.prepend(this.element)
      return // Moving the node reconnects this controller through Stimulus.
    }
    requestAnimationFrame(() => {
      this.element.classList.remove('translate-x-full', 'opacity-0')
      this.element.classList.add('translate-x-0', 'opacity-100')
    })

    const t = this.hasTimeoutValue ? this.timeoutValue : 3500
    if (t <= 0) this.element.querySelector('[data-toast-progress-target]')?.classList.add('hidden')
    this.pause = () => window.clearTimeout(this._timer)
    this.resume = () => {
      window.clearTimeout(this._timer)
      queueMicrotask(() => {
      if (this.element.isConnected && t > 0 && !this.element.contains(document.activeElement) && !this.element.matches(':hover')) {
        this._timer = window.setTimeout(() => this.close(), t)
      }
      })
    }
    this.element.addEventListener('mouseenter', this.pause)
    this.element.addEventListener('mouseleave', this.resume)
    this.element.addEventListener('focusin', this.pause)
    this.element.addEventListener('focusout', this.resume)
    if (t > 0) this._timer = window.setTimeout(() => this.close(), t)
  }

  disconnect () {
    this.element.removeEventListener('mouseenter', this.pause)
    this.element.removeEventListener('mouseleave', this.resume)
    this.element.removeEventListener('focusin', this.pause)
    this.element.removeEventListener('focusout', this.resume)
    if (this._timer) window.clearTimeout(this._timer)
  }

  close () {
    this.element.classList.remove('translate-x-0', 'opacity-100')
    this.element.classList.add('translate-x-full', 'opacity-0')
    window.setTimeout(() => this.element.remove(), 300)
  }

  async undo (event) {
    event.preventDefault()

    // stop auto-close while undo runs
    if (this._timer) window.clearTimeout(this._timer)

    const url = this.undoUrlValue
    const payload = this.undoPayloadValue

    if (!url) {
      this.close()
      return
    }

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/vnd.turbo-stream.html'
      },
      body: JSON.stringify(payload ?? {})
    })

    if (res.ok) {
      Turbo.renderStreamMessage(await res.text())
    } else {
      // si undo échoue, on ferme quand même le toast
      this.close()
    }
  }
}
