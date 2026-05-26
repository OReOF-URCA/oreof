/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/modalturbo_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/01/2026 19:11
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['wrapper']

  open () {
    // show a loading state immediately and remove any previous content
    const titleFrame = document.getElementById('modal_title')
    const bodyFrame = document.getElementById('modal_body')
    const footerFrame = document.getElementById('modal_footer')

    // remove previous content to avoid flashing old data
    if (titleFrame) titleFrame.innerHTML = ''
    if (bodyFrame) bodyFrame.innerHTML = ''
    if (footerFrame) footerFrame.innerHTML = ''

    // If a global loader template exists, clone it into the modal body
    const loaderTemplate = document.getElementById('global-loader-stimulus')
    if (loaderTemplate && bodyFrame) {
      const clone = loaderTemplate.content.cloneNode(true)
      bodyFrame.appendChild(clone)
    }

    this.wrapperTarget.classList.remove('hidden')
    document.documentElement.classList.add('overflow-hidden')
  }

  close () {
    // clear modal frames content when closing to avoid leaking previous data
    const titleFrame = document.getElementById('modal_title')
    const bodyFrame = document.getElementById('modal_body')
    const footerFrame = document.getElementById('modal_footer')

    if (titleFrame) titleFrame.innerHTML = ''
    if (bodyFrame) bodyFrame.innerHTML = ''
    if (footerFrame) footerFrame.innerHTML = ''

    this.wrapperTarget.classList.add('hidden')
    document.documentElement.classList.remove('overflow-hidden')
  }

  connect () {
    this.closeHandler = () => this.close()
    window.addEventListener('modal:close', this.closeHandler)
  }

  disconnect () {
    window.removeEventListener('modal:close', this.closeHandler)
  }
}
