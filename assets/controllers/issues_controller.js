import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  scroll (e) {
    e.preventDefault()

    const field = e.currentTarget.dataset.field
    console.log('[issues] click field =', field)
    if (!field) return

    const sel = `[name="${this.cssEscape(field)}"]`
    let el =
      document.querySelector(sel) ||
      document.getElementById(field)

    console.log('[issues] selector =', sel, 'found =', !!el, el)
    if (!el) return

    // ✅ Cas Trix : input hidden -> on scroll vers le trix-editor lié
    el = this.resolveVisibleTarget(el)

    // Scroll
    const container = this.findScrollParent(el) || window
    if (container === window) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    } else {
      const top = el.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop
      container.scrollTo({ top: top - 80, behavior: 'smooth' })
    }

    // Highlight
    el.classList.add('ring-2', 'ring-warning-400', 'rounded')
    setTimeout(() => el.classList.remove('ring-2', 'ring-warning-400', 'rounded'), 1500)

    // Focus (trix-editor a focus())
    el.focus?.({ preventScroll: true })
  }

  resolveVisibleTarget (el) {
    // 1) Si c’est un input hidden, essaie de trouver un élément visible lié
    if (el.tagName === 'INPUT' && el.type === 'hidden') {
      // Trix: <trix-editor input="trix-input-id">
      if (el.id) {
        const trix = document.querySelector(`trix-editor[input="${this.cssEscape(el.id)}"]`)
        if (trix) return trix
      }

      // Fallback: chercher un wrapper parent plus visible
      const wrapper = el.closest('.trix-content, .form-row, .field, .mb-4')
      if (wrapper) return wrapper
    }

    // 2) Si c’est un input radio/checkbox, préfère le premier visible du groupe (optionnel)
    // (à ajouter si besoin)

    return el
  }

  cssEscape (str) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(str)
    return String(str).replace(/["\\]/g, '\\$&')
  }

  findScrollParent (el) {
    let p = el.parentElement
    while (p) {
      const oy = getComputedStyle(p).overflowY
      if (oy === 'auto' || oy === 'scroll') return p
      p = p.parentElement
    }
    return null
  }
}
