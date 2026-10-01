/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/assets/controllers/page_translations_drawer_controller.js
 * @author davidannebicque
 * @project oreofv2
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'
import Toast from '../components/Toast'

const WIDTH_STORAGE_KEY = 'oreof:page-translations-drawer:width'
const MIN_WIDTH = 340
const MAX_WIDTH_RATIO = 0.9
// Conteneurs masqués que « Repérer » sait ouvrir : sous-menus de la navbar (contrôleur navbar-dropdown) et <details>
const REVEALABLE_SELECTOR = '[data-navbar-dropdown-target="content"], details'

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
    this.locateClear()
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
    this.locateClear()
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

      // Onglet spécial « Manquantes » (data-domain="__missing__") : filtre sur l'état de l'élément, pas sur son domaine
      const matchesDomain = !activeDomain || activeDomain === 'all' ||
        (activeDomain === '__missing__' ? item.dataset.missing === 'true' : domain === activeDomain)
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

  // ── Repérage du texte sur la page ─────────────────────────────────────────
  // Surbrillance via l'API CSS Custom Highlight (aucune modification du DOM, donc sans effet sur Turbo/Stimulus).
  // Styles : ::highlight(translation-match) et ::highlight(translation-current) dans assets/styles/app.css.

  locate (event) {
    event.preventDefault()
    const item = event.currentTarget.closest('[data-page-translations-drawer-target="item"]')
    if (!item) return

    this.locateClear()

    // Valeur traduite, ou clé brute si le mode clés affiche les clés dans la page
    const candidates = [item.dataset.originalValue, item.dataset.translationKey]
    let ranges = []
    for (const text of candidates) {
      ranges = this._findRanges(text)
      if (ranges.length > 0) break
    }

    if (ranges.length === 0) {
      Toast.warning('Ce texte n\'apparaît pas dans le contenu visible de la page : il peut se trouver dans un attribut, un élément masqué, ou être découpé par du balisage.', 'Texte introuvable')
      return
    }

    this._locate = { item, ranges, index: 0 }
    this._applyHighlights()
    const nav = item.querySelector('[data-role="locate-nav"]')
    nav?.classList.remove('hidden')
    this._goTo(0)
  }

  locateNext (event) {
    event?.preventDefault()
    if (!this._locate) return
    this._goTo((this._locate.index + 1) % this._locate.ranges.length)
  }

  locatePrev (event) {
    event?.preventDefault()
    if (!this._locate) return
    this._goTo((this._locate.index - 1 + this._locate.ranges.length) % this._locate.ranges.length)
  }

  locateClear (event) {
    event?.preventDefault()
    if (window.CSS?.highlights) {
      window.CSS.highlights.delete('translation-match')
      window.CSS.highlights.delete('translation-current')
    }
    this._clearFlash()
    this._locate?.item.querySelector('[data-role="locate-nav"]')?.classList.add('hidden')
    this._locate = null
  }

  _goTo (index) {
    const state = this._locate
    if (!state) return
    state.index = index
    const range = state.ranges[index]

    if (window.CSS?.highlights && typeof window.Highlight !== 'undefined') {
      window.CSS.highlights.set('translation-current', new window.Highlight(range))
    } else {
      this._flash(range.startContainer.parentElement)
    }

    const count = state.item.querySelector('[data-role="locate-count"]')
    if (count) count.textContent = `${index + 1}/${state.ranges.length}`

    const target = range.startContainer.parentElement
    this._reveal(target)
    // Un texte dans la barre de navigation (collée en haut) n'a pas besoin de défilement
    if (!target?.closest('[data-navbar-dropdown-target="content"]')) {
      target?.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
    }
  }

  // Ouvre les sous-menus / <details> qui contiennent l'élément pour le rendre visible.
  // Les sous-menus de la navbar se referment au moindre clic hors du menu (dont celui sur « Repérer ») :
  // on les rouvre donc juste après la fin du clic.
  _reveal (element) {
    if (!element) return

    // Sous-menu de la navbar : toujours rouvert après le clic, même s'il est ouvert à cet instant, car ce même clic
    // va le refermer (écouteur global click@window->navbar-dropdown#close)
    const content = element.closest('[data-navbar-dropdown-target="content"]')
    if (content) {
      window.setTimeout(() => content.classList.remove('hidden'), 0)
    }

    let details = element.closest('details')
    while (details) {
      details.open = true
      details = details.parentElement?.closest('details')
    }
  }

  _applyHighlights () {
    if (!window.CSS?.highlights || typeof window.Highlight === 'undefined') return
    window.CSS.highlights.set('translation-match', new window.Highlight(...this._locate.ranges))
  }

  // Repli sans CSS Custom Highlight : contour temporaire sur l'élément
  _flash (element) {
    this._clearFlash()
    if (!element) return
    this._flashed = element
    this._flashedOutline = element.style.outline
    element.style.outline = '3px solid #facc15'
    element.style.outlineOffset = '2px'
  }

  _clearFlash () {
    if (this._flashed) {
      this._flashed.style.outline = this._flashedOutline || ''
      this._flashed.style.outlineOffset = ''
      this._flashed = null
    }
  }

  // Retrouve toutes les occurrences visibles de `text` dans la page, hors panneau (espaces insensibles à la casse de mise en forme)
  _findRanges (text) {
    const needle = (text || '').trim()
    if (needle === '') return []

    const drawer = this.drawerElement
    const nodes = []
    let full = ''
    const walker = document.createTreeWalker(document.body, window.NodeFilter.SHOW_TEXT, {
      acceptNode: (node) => {
        const parent = node.parentElement
        if (!parent || drawer?.contains(parent)) return window.NodeFilter.FILTER_REJECT
        if (parent.closest('script, style, noscript, template, textarea, [hidden], [aria-hidden="true"]')) return window.NodeFilter.FILTER_REJECT
        // Texte masqué : conservé seulement si on sait le révéler (sous-menu de la barre de navigation, <details> fermé)
        if (typeof parent.checkVisibility === 'function' && !parent.checkVisibility() && !parent.closest(REVEALABLE_SELECTOR)) return window.NodeFilter.FILTER_REJECT
        return node.nodeValue ? window.NodeFilter.FILTER_ACCEPT : window.NodeFilter.FILTER_REJECT
      },
    })
    while (walker.nextNode()) {
      const node = walker.currentNode
      nodes.push({ node, start: full.length })
      full += node.nodeValue
    }

    const pattern = needle.split(/\s+/).map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('\\s+')
    // Mots entiers uniquement : « Traductions » ne doit pas correspondre à « Traductions et textes » par simple préfixe
    const regex = new RegExp(`(?<![\\p{L}\\p{N}])${pattern}(?![\\p{L}\\p{N}])`, 'gu')
    const ranges = []
    let match
    while ((match = regex.exec(full)) !== null) {
      const start = this._locateOffset(nodes, match.index, false)
      const end = this._locateOffset(nodes, match.index + match[0].length, true)
      if (start && end) {
        const range = document.createRange()
        range.setStart(start.node, start.offset)
        range.setEnd(end.node, end.offset)
        ranges.push(range)
      }
      if (match[0].length === 0) regex.lastIndex++
    }

    // Si le texte occupe à lui seul un élément (titre, libellé, description…), ne garder que ces occurrences :
    // elles sont bien plus probablement celles de la clé que les mêmes mots noyés dans une autre phrase.
    const normalize = (value) => value.replace(/\s+/g, ' ').trim()
    const target = normalize(needle)
    const exact = ranges.filter(range => {
      const container = range.commonAncestorContainer
      const element = container.nodeType === 1 ? container : container.parentElement
      return element && normalize(element.textContent) === target
    })
    return exact.length > 0 ? exact : ranges
  }

  // Convertit un index dans le texte concaténé en (nœud, décalage). Un début appartient au nœud qui le contient,
  // une fin au nœud qu'elle termine (et non au suivant), pour ne pas déborder sur le nœud voisin.
  _locateOffset (nodes, index, isEnd) {
    let low = 0
    let high = nodes.length - 1
    while (low <= high) {
      const mid = (low + high) >> 1
      const { node, start } = nodes[mid]
      const end = start + node.nodeValue.length
      const afterStart = isEnd ? index > start : index >= start
      const beforeEnd = isEnd ? index <= end : index < end
      if (!afterStart) {
        high = mid - 1
      } else if (!beforeEnd) {
        low = mid + 1
      } else {
        return { node, offset: index - start }
      }
    }
    return null
  }
}
