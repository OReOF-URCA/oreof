// /*
//  * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
//  * @file /Users/davidannebicque/Sites/oreof/assets/controllers/dropdown_controller.js
//  * @author davidannebicque
//  * @project oreof
//  * @lastUpdate 17/01/2026 08:34
//  */
//
// import { Controller } from '@hotwired/stimulus'
//
// export default class extends Controller {
//   static targets = ['menu', 'button']
//   static values = { confirm: String, zIndex: Number }
//
//   connect () {
//     this.boundClose = this.closeOnClickOutside.bind(this)
//     this.boundKey = this.onKeyDown.bind(this)
//
//     // références internes sécurisées (ne pas accéder directement à this.menuTarget si absent)
//     this._button = this.hasButtonTarget ? this.buttonTarget : (this.element.querySelector('[data-dropdown-target="button"]') || null)
//     this._menu = this.hasMenuTarget ? this.menuTarget : (this.element.querySelector('[data-dropdown-target="menu"]') || null)
//
//     if (!this._menu) {
//       return
//     }
//
//     // sauvegarde pour restore
//     this._originalParent = this._menu.parentNode
//     this._nextSibling = this._menu.nextSibling
//
//     // si pas déjà dans body, déplacer
//     if (this._menu.parentNode !== document.body) {
//       document.body.appendChild(this._menu)
//     }
//
//     // styles de base fiables
//     this._menu.style.position = 'absolute'
//     this._menu.style.zIndex = String(this.hasZIndexValue ? this.zIndexValue : 99999)
//     this._menu.style.pointerEvents = 'auto'
//     this._menu.style.display = 'none'
//     if (!this._menu.classList.contains('hidden')) {
//       this._menu.classList.add('hidden')
//     }
//
//     document.addEventListener('click', this.boundClose)
//     document.addEventListener('keydown', this.boundKey)
//   }
//
//   disconnect () {
//     if (this._originalParent && this._menu) {
//       if (this._nextSibling) {
//         this._originalParent.insertBefore(this._menu, this._nextSibling)
//       } else {
//         this._originalParent.appendChild(this._menu)
//       }
//     }
//     document.removeEventListener('click', this.boundClose)
//     document.removeEventListener('keydown', this.boundKey)
//   }
//
//   onKeyDown (event) {
//     if (event.key === 'Escape') this.close()
//   }
//
//   toggle (event) {
//     event.stopPropagation()
//     if (!this._menu || !this._button) return
//     if (this._menu.style.display === 'none' || this._menu.classList.contains('hidden')) {
//       this.open()
//     } else {
//       this.close()
//     }
//   }
//
//   closeOnClickOutside (event) {
//     if (!this._menu || !this._button) return
//     const target = event.target
//     if (this._button.contains(target) || this._menu.contains(target)) return
//     this.close()
//   }
//
//   open () {
//     if (!this._menu || !this._button) return
//
//     const btnRect = this._button.getBoundingClientRect()
//     this._menu.style.visibility = 'hidden'
//     this._menu.style.display = 'block'
//     this._menu.classList.remove('hidden')
//
//     const menuWidth = this._menu.offsetWidth
//     const top = btnRect.bottom + window.scrollY
//     const preferredRight = btnRect.right + window.scrollX - menuWidth
//     const preferredLeft = btnRect.left + window.scrollX
//
//     let left = preferredRight
//     if (preferredRight < 0 || preferredRight + menuWidth > window.innerWidth + window.scrollX) {
//       left = Math.max(0, Math.min(preferredLeft, window.innerWidth + window.scrollX - menuWidth))
//     }
//
//     this._menu.style.top = `${Math.round(top)}px`
//     this._menu.style.left = `${Math.round(left)}px`
//     this._menu.style.visibility = 'visible'
//     this._button.setAttribute('aria-expanded', 'true')
//   }
//
//   close () {
//     if (!this._menu || !this._button) return
//     this._menu.classList.add('hidden')
//     this._menu.style.display = 'none'
//     this._menu.style.visibility = ''
//     this._button.setAttribute('aria-expanded', 'false')
//   }
//
//   confirm (event) {
//     console.log('confirm')
//     const msg = this.hasConfirmValue ? this.confirmValue : (this.element.dataset.dropdownConfirmValue || null)
//     const text = event.currentTarget?.dataset?.dropdownConfirmValue || msg
//     if (text && !window.confirm(text)) {
//       event.preventDefault()
//       event.stopPropagation()
//       this.close()
//       return
//     }
//     this.close()
//   }
// }

// javascript
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['menu', 'button']
  static values = { confirm: String, zIndex: Number }

  connect () {
    this.boundClose = this.closeOnClickOutside.bind(this)
    this.boundKey = this.onKeyDown.bind(this)
    this.boundMenuClick = this.onMenuClick.bind(this)
    this.boundMenuSubmit = this.onMenuSubmit.bind(this)

    // références internes sécurisées
    this._button = this.hasButtonTarget ? this.buttonTarget : (this.element.querySelector('[data-dropdown-target="button"]') || null)
    this._menu = this.hasMenuTarget ? this.menuTarget : (this.element.querySelector('[data-dropdown-target="menu"]') || null)

    if (!this._menu) return

    this._menu.id ||= `actions-menu-${crypto.randomUUID()}`
    this._button?.setAttribute('aria-controls', this._menu.id)
    this._button?.setAttribute('aria-haspopup', 'menu')
    this._button?.setAttribute('aria-expanded', 'false')
    this._menu.querySelectorAll('[role="menuitem"]').forEach(item => item.setAttribute('tabindex', '-1'))
    this._originalParent = this._menu.parentNode
    this._nextSibling = this._menu.nextSibling

    if (this._menu.parentNode !== document.body) {
      document.body.appendChild(this._menu)
    }

    this._menu.style.position = 'absolute'
    this._menu.style.zIndex = String(this.hasZIndexValue ? this.zIndexValue : 99999)
    this._menu.style.pointerEvents = 'auto'
    this._menu.style.display = 'none'
    if (!this._menu.classList.contains('hidden')) {
      this._menu.classList.add('hidden')
    }

    // écoute globale pour fermer, et écoute locale sur le menu pour les confirm
    document.addEventListener('click', this.boundClose)
    document.addEventListener('keydown', this.boundKey)
    this._menu.addEventListener('click', this.boundMenuClick)
    this._menu.addEventListener('submit', this.boundMenuSubmit)
  }

  disconnect () {
    if (this._originalParent && this._menu) {
      if (this._nextSibling) {
        this._originalParent.insertBefore(this._menu, this._nextSibling)
      } else {
        this._originalParent.appendChild(this._menu)
      }
    }

    document.removeEventListener('click', this.boundClose)
    document.removeEventListener('keydown', this.boundKey)
    if (this._menu) {
      this._menu.removeEventListener('click', this.boundMenuClick)
      this._menu.removeEventListener('submit', this.boundMenuSubmit)
    }
  }

  isOpen () {
    return this._menu && this._menu.style.display !== 'none' && !this._menu.classList.contains('hidden')
  }

  enabledItems () {
    return Array.from(this._menu.querySelectorAll('[role="menuitem"]')).filter(item =>
      !item.disabled && item.getAttribute('aria-disabled') !== 'true' && item.getClientRects().length > 0
    )
  }

  onKeyDown (event) {
    const onButton = this._button?.contains(event.target)
    const inMenu = this._menu?.contains(event.target)
    if (!onButton && !inMenu) return
    if (onButton && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
      event.preventDefault()
      this.open(event.key === 'ArrowUp' ? 'last' : 'first')
      return
    }
    if (!this.isOpen()) return
    if (event.key === 'Escape') {
      event.preventDefault()
      event.stopPropagation()
      this.close(true)
      return
    }
    if (event.key === 'Tab') {
      // The menu is portalled to body: continue tabbing from its trigger.
      this._button.focus()
      this.close()
      return
    }
    if (!inMenu) return
    const items = this.enabledItems()
    if (!items.length) return
    const index = items.indexOf(document.activeElement)
    let next
    if (event.key === 'Home') next = 0
    else if (event.key === 'End') next = items.length - 1
    else if (event.key === 'ArrowDown') next = (index + 1) % items.length
    else if (event.key === 'ArrowUp') next = (index - 1 + items.length) % items.length
    else if (event.key === ' ' && document.activeElement?.matches('a[role="menuitem"]')) {
      event.preventDefault()
      document.activeElement.click()
      return
    } else return
    event.preventDefault()
    items[next].focus()
  }

  toggle (event) {
    event.stopPropagation()
    if (!this._menu || !this._button) return
    if (this._menu.style.display === 'none' || this._menu.classList.contains('hidden')) {
      this.open()
    } else {
      this.close()
    }
  }

  closeOnClickOutside (event) {
    if (!this._menu || !this._button) return
    const target = event.target
    if (this._button.contains(target) || this._menu.contains(target)) return
    this.close()
  }

  // Intercepte les clicks dans le menu pour gérer les confirm sur les liens/boutons
  // Intercepte les clicks dans le menu pour gérer les confirm sur les liens/boutons
  onMenuClick (event) {
    if (!this._menu) return
    // on cible aussi les liens, boutons et éléments avec data-action (ex: modalturbo#open)
    const el = event.target.closest('[data-dropdown-confirm-value], a, button, [data-action]')
    if (!el || !this._menu.contains(el)) return

    // si l'élément demande un confirm, on intercepte et délègue à confirm
    if (el.dataset && el.dataset.dropdownConfirmValue) {
      const wrapperEvent = {
        currentTarget: el,
        target: event.target,
        preventDefault: () => event.preventDefault(),
        stopPropagation: () => event.stopPropagation()
      }
      this.confirm(wrapperEvent)
      return
    }

    // sinon, ne pas empêcher le comportement (Turbo ou autre) ; fermer le menu après un tick
    setTimeout(() => this.close(), 0)
  }

  // Intercepte les submit de formulaires à l'intérieur du menu
  onMenuSubmit (event) {
    if (!this._menu) return
    const form = event.target.closest('form')
    if (!form || !this._menu.contains(form)) return

    // si le form a un data-dropdown-confirm-value, déléguer à confirm (confirm gère preventDefault)
    if (form.dataset && form.dataset.dropdownConfirmValue) {
      this.confirm(event)
      return
    }

    // sinon laisser le submit poursuivre normalement et fermer le menu après un tick
    setTimeout(() => this.close(), 0)
  }

  open (focus = 'first') {
    if (!this._menu || !this._button) return

    const btnRect = this._button.getBoundingClientRect()
    this._menu.style.visibility = 'hidden'
    this._menu.style.display = 'block'
    this._menu.classList.remove('hidden')

    const menuWidth = this._menu.offsetWidth
    const top = btnRect.bottom + window.scrollY
    const preferredRight = btnRect.right + window.scrollX - menuWidth
    const preferredLeft = btnRect.left + window.scrollX

    let left = preferredRight
    if (preferredRight < 0 || preferredRight + menuWidth > window.innerWidth + window.scrollX) {
      left = Math.max(0, Math.min(preferredLeft, window.innerWidth + window.scrollX - menuWidth))
    }

    this._menu.style.top = `${Math.round(top)}px`
    this._menu.style.left = `${Math.round(left)}px`
    this._menu.style.visibility = 'visible'
    this._button.setAttribute('aria-expanded', 'true')
    const items = this.enabledItems()
    const item = focus === 'last' ? items[items.length - 1] : items[0]
    item?.focus()
  }

  close (restoreFocus = this._menu?.contains(document.activeElement)) {
    if (!this._menu || !this._button) return
    this._menu.classList.add('hidden')
    this._menu.style.display = 'none'
    this._menu.style.visibility = ''
    this._button.setAttribute('aria-expanded', 'false')
    if (restoreFocus) this._button.focus()
  }

  confirm (event) {
    const msg = this.hasConfirmValue ? this.confirmValue : (this.element.dataset.dropdownConfirmValue || null)
    const elValue = event.currentTarget?.dataset?.dropdownConfirmValue || null
    const text = elValue || msg
    if (text && !window.confirm(text)) {
      if (typeof event.preventDefault === 'function') event.preventDefault()
      if (typeof event.stopPropagation === 'function') event.stopPropagation()
      this.close()
      return
    }
    this.close()
  }
}
