/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/modalturbo_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/01/2026 19:11
 */

import { Controller } from '@hotwired/stimulus'
import { renderStreamMessage } from '@hotwired/turbo'

export default class extends Controller {
  static targets = ['wrapper']

  async open (event) {
    let url = null
    if (event && event.currentTarget) {
      const target = event.currentTarget
      url = target.getAttribute('href') || target.dataset.url || target.dataset.modalUrl
      if (url && url !== '#' && !url.startsWith('javascript:')) {
        event.preventDefault()
      } else {
        url = null
      }
    }

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

    if (url) {
      try {
        const response = await fetch(url, {
          headers: {
            'Accept': 'text/vnd.turbo-stream.html, text/html',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        if (response.ok) {
          const text = await response.text()
          renderStreamMessage(text)
        } else {
          if (bodyFrame) {
            bodyFrame.innerHTML = `<div class="p-4 text-sm text-red-600">Erreur lors du chargement (${response.status} ${response.statusText})</div>`
          }
        }
      } catch (error) {
        console.error('Erreur chargement modal turbo:', error)
        if (bodyFrame) {
          bodyFrame.innerHTML = '<div class="p-4 text-sm text-red-600">Erreur de connexion au serveur</div>'
        }
      }
    }
  }

  close (event) {
    // If the close was triggered by Escape key, check if an input/select/TomSelect is currently active
    if (event && event.type === 'keydown' && (event.key === 'Escape' || event.key === 'Esc')) {
      const activeEl = document.activeElement
      if (activeEl && (
        activeEl.tagName === 'INPUT' ||
        activeEl.tagName === 'TEXTAREA' ||
        activeEl.tagName === 'SELECT' ||
        activeEl.closest('.ts-wrapper') ||
        activeEl.closest('.ts-dropdown')
      )) {
        activeEl.blur()
        return
      }
    }

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

  preventEnterSubmit (event) {
    if (event.key === 'Enter' && event.target && event.target.tagName === 'INPUT') {
      const type = (event.target.type || '').toLowerCase()
      if (type !== 'submit' && type !== 'button' && !event.target.closest('.ts-wrapper')) {
        event.preventDefault()
      }
    }
  }

  connect () {
    this.closeHandler = () => this.close()
    window.addEventListener('modal:close', this.closeHandler)
    // une navigation Turbo depuis la modal ne passe pas par close() : on la ferme (overlay + scroll)
    this.visitHandler = () => this.close()
    document.addEventListener('turbo:before-visit', this.visitHandler)
  }

  disconnect () {
    window.removeEventListener('modal:close', this.closeHandler)
    document.removeEventListener('turbo:before-visit', this.visitHandler)
  }
}
