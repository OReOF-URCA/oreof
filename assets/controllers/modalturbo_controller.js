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
    this.requestController?.abort()
    const requestController = new AbortController()
    this.requestController = requestController
    if (!this.isOpen) {
      this.returnFocus = event?.currentTarget instanceof HTMLElement ? event.currentTarget : document.activeElement
      const sourceMenu = this.returnFocus?.closest?.('[role="menu"]')
      if (sourceMenu?.id) {
        this.returnFocus = Array.from(document.querySelectorAll('[aria-controls]')).find(trigger =>
          trigger.getAttribute('aria-controls') === sourceMenu.id
        ) || this.returnFocus
      }
      this.hadScrollLock = document.documentElement.classList.contains('overflow-hidden')
      this.inertElements = new Map()
      // Make siblings of the modal and of its ancestors inert, without hiding the modal itself.
      const popups = [
        ...Array.from(this.wrapperTarget.querySelectorAll('select')).map(select => select.tomselect?.dropdown),
        ...Array.from(this.wrapperTarget.querySelectorAll('[aria-controls]')).map(trigger => document.getElementById(trigger.getAttribute('aria-controls')))
      ].filter(Boolean)
      let branch = this.wrapperTarget
      while (branch.parentElement && branch !== document.body) {
        for (const sibling of branch.parentElement.children) {
          if (sibling !== branch && !popups.includes(sibling) && !sibling.inert) {
            this.inertElements.set(sibling, false)
            sibling.inert = true
          }
        }
        branch = branch.parentElement
      }
    }
    this.isOpen = true
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
    if (titleFrame) titleFrame.textContent = 'Chargement…'
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
    this.wrapperTarget.focus()

    if (url) {
      try {
        const response = await fetch(url, {
          signal: requestController.signal,
          headers: {
            'Accept': 'text/vnd.turbo-stream.html, text/html',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        if (requestController.signal.aborted) return
        if (response.ok) {
          const text = await response.text()
          if (requestController.signal.aborted) return
          renderStreamMessage(text)
        } else {
          if (bodyFrame) {
            bodyFrame.innerHTML = `<div class="p-4 text-sm text-red-600">Erreur lors du chargement (${response.status} ${response.statusText})</div>`
          }
        }
      } catch (error) {
        if (requestController.signal.aborted) return
        console.error('Erreur chargement modal turbo:', error)
        if (bodyFrame) {
          bodyFrame.innerHTML = '<div class="p-4 text-sm text-red-600">Erreur de connexion au serveur</div>'
        }
      }
    }
  }

  close (event) {
    if (!this.isOpen) return
    if (event?.defaultPrevented) return
    this.isOpen = false
    this.requestController?.abort()
    for (const [element, previous] of this.inertElements || []) element.inert = previous
    this.inertElements?.clear()

    // clear modal frames content when closing to avoid leaking previous data
    const titleFrame = document.getElementById('modal_title')
    const bodyFrame = document.getElementById('modal_body')
    const footerFrame = document.getElementById('modal_footer')

    if (titleFrame) titleFrame.innerHTML = ''
    if (bodyFrame) bodyFrame.innerHTML = ''
    if (footerFrame) footerFrame.innerHTML = ''
    this.wrapperTarget.classList.add('hidden')
    if (!this.hadScrollLock) document.documentElement.classList.remove('overflow-hidden')
    if (this.returnFocus?.isConnected) this.returnFocus.focus()
  }

  focusableElements () {
    return Array.from(this.wrapperTarget.querySelectorAll(
      'a[href], button, input, select, textarea, [tabindex]'
    )).filter(el => !el.matches(':disabled') && el.tabIndex >= 0 && !el.closest('[inert]') && el.getClientRects().length > 0)
  }

  ownedPopup (target) {
    // Tom Select and action menus may render outside the modal, under body.
    return Array.from(this.wrapperTarget.querySelectorAll('select')).some(select =>
      select.tomselect?.isOpen && select.tomselect.dropdown?.contains(target)
    ) || Array.from(this.wrapperTarget.querySelectorAll('[aria-controls]')).some(trigger =>
      trigger.getAttribute('aria-expanded') === 'true' && document.getElementById(trigger.getAttribute('aria-controls'))?.contains(target)
    )
  }

  onFocus (event) {
    if (this.isOpen && !this.wrapperTarget.contains(event.target) && !this.ownedPopup(event.target)) {
      (this.focusableElements()[0] || this.wrapperTarget).focus()
    }
  }

  onKeydown (event) {
    if (!this.isOpen || event.defaultPrevented) return
    if (event.key === 'Escape') {
      // Let the active popup consume Escape before closing the whole modal.
      const selects = Array.from(this.wrapperTarget.querySelectorAll('select'))
      const select = selects.find(el => el.tomselect?.isOpen)
      if (select) {
        select.tomselect.close()
        event.preventDefault()
        return
      }
      const menuTrigger = this.wrapperTarget.querySelector('[aria-haspopup="menu"][aria-expanded="true"]')
      if (menuTrigger) return
      this.close(event)
      event.preventDefault()
    } else if (event.key === 'Tab') {
      const elements = this.focusableElements()
      const first = elements[0]
      const last = elements[elements.length - 1]
      if (!first) {
        event.preventDefault()
        this.wrapperTarget.focus()
      } else if (event.shiftKey && (document.activeElement === first || document.activeElement === this.wrapperTarget)) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === this.wrapperTarget)) {
        event.preventDefault()
        first.focus()
      }
    }
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
    this.isOpen = false
    this.boundKeydown = this.onKeydown.bind(this)
    this.boundFocus = this.onFocus.bind(this)
    document.addEventListener('keydown', this.boundKeydown)
    document.addEventListener('focusin', this.boundFocus)
    this.closeHandler = () => this.close()
    window.addEventListener('modal:close', this.closeHandler)
    // une navigation Turbo depuis la modal ne passe pas par close() : on la ferme (overlay + scroll)
    this.visitHandler = () => this.close()
    document.addEventListener('turbo:before-visit', this.visitHandler)
  }

  disconnect () {
    this.close()
    document.removeEventListener('keydown', this.boundKeydown)
    document.removeEventListener('focusin', this.boundFocus)
    window.removeEventListener('modal:close', this.closeHandler)
    document.removeEventListener('turbo:before-visit', this.visitHandler)
  }
}
