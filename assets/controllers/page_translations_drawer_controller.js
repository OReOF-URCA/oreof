/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/page_translations_drawer_controller.js
 * @author davidannebicque
 * @project oreofv2
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

const WIDTH_STORAGE_KEY = 'oreof:page-translations-drawer:width'
const MIN_WIDTH = 340
const MAX_WIDTH_RATIO = 0.9

export default class extends Controller {
  static targets = [
    'drawer',
    'backdrop',
    'fab',
    'searchInput',
    'domainFilter',
    'itemList',
    'item',
    'countBadge',
  ]

  connect () {
    this.drawer = document.getElementById('pageTranslationsOffcanvas')
    this.backdrop = document.getElementById('pageTranslationsBackdrop')
    this.fab = document.getElementById('pageTranslationsFabToggle')
    this.resizeHandle = document.getElementById('pageTranslationsResizeHandle')

    this._onPointerMove = this._onPointerMove.bind(this)
    this._onPointerUp = this._onPointerUp.bind(this)
    this._onGlobalToggle = this._onGlobalToggle.bind(this)
    window.oreofToggleTranslations = () => this.toggle()
    window.addEventListener('oreof:toggle-translations', this._onGlobalToggle)

    this._restoreWidth()

    // Si le tiroir était ouvert avant un rechargement / sauvegarde, le réouvrir
    if (sessionStorage.getItem('oreof:page-translations-drawer:open') === 'true') {
      this.open()
      const savedSearch = sessionStorage.getItem('oreof:page-translations-drawer:search')
      if (savedSearch && this.hasSearchInputTarget) {
        this.searchInputTarget.value = savedSearch
        this.filter()
      }
    }
  }

  disconnect () {
    window.oreofToggleTranslations = null
    window.removeEventListener('oreof:toggle-translations', this._onGlobalToggle)
    this._onPointerUp()
  }

  _onGlobalToggle (event) {
    this.toggle(event)
  }

  get drawerElement () {
    return this.drawer || (this.drawer = document.getElementById('pageTranslationsOffcanvas'))
  }

  get backdropElement () {
    return this.backdrop || (this.backdrop = document.getElementById('pageTranslationsBackdrop'))
  }

  get fabElement () {
    return this.fab || (this.fab = document.getElementById('pageTranslationsFabToggle'))
  }

  toggle (event) {
    event?.preventDefault()
    if (this.isOpen()) {
      this.close()
    } else {
      this.open()
    }
  }

  open (event) {
    event?.preventDefault()
    const drawer = this.drawerElement
    if (!drawer) return

    sessionStorage.setItem('oreof:page-translations-drawer:open', 'true')

    drawer.classList.remove('translate-x-full')
    drawer.classList.add('translate-x-0')
    drawer.setAttribute('aria-hidden', 'false')

    const fab = this.fabElement
    if (fab) {
      fab.setAttribute('aria-expanded', 'true')
    }

    const backdrop = this.backdropElement
    if (backdrop) {
      backdrop.classList.remove('hidden')
      backdrop.classList.add('block')
      backdrop.setAttribute('aria-hidden', 'false')
    }

    // Auto focus search input
    if (this.hasSearchInputTarget) {
      setTimeout(() => this.searchInputTarget.focus(), 150)
    }
  }

  close (event) {
    event?.preventDefault()
    sessionStorage.removeItem('oreof:page-translations-drawer:open')

    const drawer = this.drawerElement
    if (!drawer) return

    drawer.classList.remove('translate-x-0')
    drawer.classList.add('translate-x-full')
    drawer.setAttribute('aria-hidden', 'true')

    const fab = this.fabElement
    if (fab) {
      fab.setAttribute('aria-expanded', 'false')
    }

    const backdrop = this.backdropElement
    if (backdrop) {
      backdrop.classList.add('hidden')
      backdrop.classList.remove('block')
      backdrop.setAttribute('aria-hidden', 'true')
    }
  }

  isOpen () {
    const drawer = this.drawerElement
    return drawer && !drawer.classList.contains('translate-x-full')
  }

  onBackdropClick (event) {
    event?.preventDefault()
    this.close()
  }

  onEscape (event) {
    if (event.key === 'Escape' && this.isOpen()) {
      this.close()
    }
  }

  // ── Resize ───────────────────────────────────────────────────────────────

  startResize (event) {
    event?.preventDefault()
    if (!this.drawer) return

    this._resizing = true
    document.body.classList.add('select-none', 'cursor-ew-resize')
    window.addEventListener('mousemove', this._onPointerMove)
    window.addEventListener('mouseup', this._onPointerUp)
  }

  _onPointerMove (event) {
    if (!this._resizing || !this.drawer) return

    const maxWidth = Math.round(window.innerWidth * MAX_WIDTH_RATIO)
    const width = Math.min(
      maxWidth,
      Math.max(MIN_WIDTH, window.innerWidth - event.clientX)
    )
    this.drawer.style.width = `${width}px`
  }

  _onPointerUp () {
    if (!this._resizing) return

    this._resizing = false
    document.body.classList.remove('select-none', 'cursor-ew-resize')
    window.removeEventListener('mousemove', this._onPointerMove)
    window.removeEventListener('mouseup', this._onPointerUp)
    this._saveWidth()
  }

  _saveWidth () {
    if (!this.drawer) return
    try {
      localStorage.setItem(WIDTH_STORAGE_KEY, this.drawer.style.width)
    } catch (e) {
      // Ignore
    }
  }

  _restoreWidth () {
    if (!this.drawer) return
    try {
      const saved = localStorage.getItem(WIDTH_STORAGE_KEY)
      if (saved) {
        this.drawer.style.width = saved
      }
    } catch (e) {
      // Ignore
    }
  }

  // ── Recherche & Filtre de domaine ────────────────────────────────────────

  filter () {
    const query = (this.hasSearchInputTarget ? this.searchInputTarget.value : '').toLowerCase().trim()
    const activeDomain = this._getActiveDomain()

    let visibleCount = 0

    this.itemTargets.forEach(item => {
      const key = (item.dataset.translationKey || '').toLowerCase()
      const value = (item.dataset.originalValue || '').toLowerCase()
      const domain = item.dataset.translationDomain || ''

      const matchesDomain = !activeDomain || activeDomain === 'all' || domain === activeDomain
      const matchesQuery = !query || key.includes(query) || value.includes(query)

      if (matchesDomain && matchesQuery) {
        item.classList.remove('hidden')
        visibleCount++
      } else {
        item.classList.add('hidden')
      }
    })

    if (this.hasCountBadgeTarget) {
      this.countBadgeTarget.textContent = `${visibleCount}`
    }
  }

  selectDomain (event) {
    event.preventDefault()
    const clickedBtn = event.currentTarget
    const domain = clickedBtn.dataset.domain

    this.domainFilterTargets.forEach(btn => {
      if (btn === clickedBtn) {
        btn.classList.add('bg-primary', 'text-white', 'border-primary')
        btn.classList.remove('bg-white', 'text-secondary-700', 'border-secondary-200', 'dark:bg-secondary-800', 'dark:text-secondary-200')
      } else {
        btn.classList.remove('bg-primary', 'text-white', 'border-primary')
        btn.classList.add('bg-white', 'text-secondary-700', 'border-secondary-200', 'dark:bg-secondary-800', 'dark:text-secondary-200')
      }
    })

    this.filter()
  }

  _getActiveDomain () {
    const activeBtn = this.domainFilterTargets.find(btn => btn.classList.contains('bg-primary'))
    return activeBtn ? activeBtn.dataset.domain : 'all'
  }

  // ── Édition & Sauvegarde ──────────────────────────────────────────────────

  edit (event) {
    event.preventDefault()
    const item = event.currentTarget.closest('[data-page-translations-drawer-target="item"]')
    if (!item) return

    const displayBox = item.querySelector('[data-role="value-display-box"]')
    const editBox = item.querySelector('[data-role="value-edit-box"]')
    const input = item.querySelector('[data-role="value-input"]')

    displayBox.classList.add('hidden')
    editBox.classList.remove('hidden')

    input.focus()
    input.select()
  }

  cancel (event) {
    event.preventDefault()
    const item = event.currentTarget.closest('[data-page-translations-drawer-target="item"]')
    if (!item) return

    const displayBox = item.querySelector('[data-role="value-display-box"]')
    const editBox = item.querySelector('[data-role="value-edit-box"]')
    const input = item.querySelector('[data-role="value-input"]')

    input.value = item.dataset.originalValue || ''
    editBox.classList.add('hidden')
    displayBox.classList.remove('hidden')
  }

  async save (event) {
    event.preventDefault()
    const item = event.currentTarget.closest('[data-page-translations-drawer-target="item"]')
    if (!item) return

    const key = item.dataset.translationKey
    const saveUrl = item.dataset.saveUrl
    const input = item.querySelector('[data-role="value-input"]')
    const value = input.value
    const saveBtn = item.querySelector('[data-role="save-btn"]')
    const displaySpan = item.querySelector('[data-role="value-display"]')

    if (!saveUrl) {
      callOut('URL de sauvegarde introuvable', 'danger')
      return
    }

    saveBtn.disabled = true

    try {
      const res = await fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ key, value }),
      })

      const json = await res.json()

      if (!json.success) {
        throw new Error(json.error || 'Erreur lors de la sauvegarde')
      }

      item.dataset.originalValue = value
      displaySpan.textContent = value

      const displayBox = item.querySelector('[data-role="value-display-box"]')
      const editBox = item.querySelector('[data-role="value-edit-box"]')
      editBox.classList.add('hidden')
      displayBox.classList.remove('hidden')

      callOut('Traduction enregistrée avec succès !', 'success')

      // Mémoriser l'état ouvert et la recherche pour le rechargement Turbo
      sessionStorage.setItem('oreof:page-translations-drawer:open', 'true')
      if (this.hasSearchInputTarget && this.searchInputTarget.value.trim() !== '') {
        sessionStorage.setItem('oreof:page-translations-drawer:search', this.searchInputTarget.value.trim())
      }

      // Recharger le contenu de la page via Turbo de manière fluide
      setTimeout(() => {
        if (window.Turbo && typeof window.Turbo.visit === 'function') {
          window.Turbo.visit(window.location.href, { action: 'replace' })
        } else {
          window.location.reload()
        }
      }, 300)
    } catch (e) {
      callOut(e.message, 'danger')
    } finally {
      saveBtn.disabled = false
    }
  }

  copyKey (event) {
    event.preventDefault()
    const key = event.currentTarget.dataset.key
    if (!key) return

    navigator.clipboard.writeText(key).then(() => {
      callOut(`Clé copiée : ${key}`, 'info')
    })
  }
}
