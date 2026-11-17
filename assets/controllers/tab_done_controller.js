/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/tab_done_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/01/2026 09:24
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = { url: String }

  async toggle () {
      const autosaveForm = document.querySelector('[data-controller~="autosave"]')
      if (autosaveForm) {
          const autosaveController = this.application.getControllerForElementAndIdentifier(autosaveForm, 'autosave')
          if (autosaveController && typeof autosaveController.savePendingFields === 'function') {
              await autosaveController.savePendingFields()
          }
      }

    const formData = new FormData(this.element)

    const response = await fetch(this.urlValue, {
      method: 'POST',
      headers: { 'Accept': 'text/vnd.turbo-stream.html' },
      body: formData,
    })

    const stream = await response.text()
    Turbo.renderStreamMessage(stream)
  }
}
