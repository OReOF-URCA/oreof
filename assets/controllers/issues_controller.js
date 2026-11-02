import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  scroll (e) {
    e.preventDefault()

    const field = e.currentTarget.dataset.field
    if (!field) return

    const el = this.findFieldElement(field)
    if (!el) {
      console.warn('[issues] field element not found for:', field)
      return
    }

    const target = this.resolveVisibleTarget(el) || el

    this.scrollToTarget(target)
    this.highlight(target)
    this.focusTarget(target)
  }

  findFieldElement (field) {
    const raw = field.trim()
    if (!raw) return null

    // 1) Direct query with exact name
    let el = this.queryByName(raw)
    if (el) return el

    // 2) Handle array suffix "[]"
    if (raw.endsWith('[]')) {
      const without = raw.slice(0, -2)
      el = this.queryByName(without)
      if (el) return el
    } else {
      el = this.queryByName(`${raw}[]`)
      if (el) return el
    }

    // 3) Compound / nested fields (e.g. parcours_step1[respParcours] -> parcours_step1[respParcours][entity])
    const baseField = raw.replace(/\[\]$/, '')
    try {
      el = document.querySelector(`[name^="${this.cssEscape(baseField)}["]`)
      if (el) return el
    } catch {
      // Ignorer l'erreur de sélecteur
      return null
    }

    // 4) Direct ID lookup
    el = document.getElementById(raw)
    if (el) return el

    // 5) Symfony form ID normalization (brackets to underscores)
    // e.g. "fiche_matiere_step1[libelle]" -> "fiche_matiere_step1_libelle"
    const symfonyId = raw.replace(/[\][]+/g, '_').replace(/^_|_$/g, '')
    el = document.getElementById(symfonyId)
    if (el) return el

    // 6) ID prefix (e.g. radio buttons, checkbox collections, nested widgets)
    try {
      el = document.querySelector(`[id^="${this.cssEscape(symfonyId)}_"]`)
      if (el) return el
    } catch {
      // Ignorer l'erreur de sélecteur
      return null
    }

    // 7) Competences / apprentissages critiques
    if (raw.includes('competences') || raw.includes('apprentissagesCritiques')) {
      el = document.querySelector('[name^="fiche_matiere_step3[competences]"]') ||
           document.querySelector('[id^="ac_"]') ||
           document.querySelector('[id^="competence_"]') ||
           document.getElementById('tab_form_competences')
      if (el) return el
    }

    // 8) Fallback: match by field name suffix inside brackets, e.g. "[libelle]"
    const subfieldMatch = raw.match(/\[([^\]]+)\]$/)
    if (subfieldMatch) {
      const sub = subfieldMatch[1]
      try {
        el = document.querySelector(`[name$="[${this.cssEscape(sub)}]"]`) ||
             document.querySelector(`[name$="[${this.cssEscape(sub)}][]"]`) ||
             document.querySelector(`[id$="_${this.cssEscape(sub)}"]`)
        if (el) return el
      } catch {
        // Ignorer l'erreur de sélecteur
        return null
      }
    }

    return null
  }

  queryByName (name) {
    try {
      return document.querySelector(`[name="${this.cssEscape(name)}"]`)
    } catch {
      return null
    }
  }

  resolveVisibleTarget (el) {
    if (!el) return null

    // 1) Cas Trix : input hidden -> on cherche le trix-editor lié
    if (el.tagName === 'INPUT' && el.type === 'hidden') {
      if (el.id) {
        const trix = document.querySelector(`trix-editor[input="${this.cssEscape(el.id)}"]`)
        if (trix) return trix
      }
      const siblingTrix = el.parentElement?.querySelector('trix-editor')
      if (siblingTrix) return siblingTrix
    }

    // 2) Cas Symfony UX Autocomplete / TomSelect : select masqué (display: none)
    if (el.tagName === 'SELECT') {
      const tsWrapper =
        (el.nextElementSibling?.classList.contains('ts-wrapper') ? el.nextElementSibling : null) ||
        el.parentElement?.querySelector('.ts-wrapper')
      if (tsWrapper) return tsWrapper
    }

    // 3) Cas Radio / Checkbox : préférer le groupe ou le label pour la visibilité
    if (el.type === 'radio' || el.type === 'checkbox') {
      const groupWrapper = el.closest('[role="group"], fieldset, .form-group')
      if (groupWrapper) return groupWrapper

      const label = el.closest('label')
      if (label) return label
    }

    // 4) Si l'élément est masqué, remonter au parent visible
    if (el.offsetParent === null && el.type !== 'hidden') {
      const visibleParent = el.closest('.form-row, .form-group, .field, .mb-4') || el.parentElement
      if (visibleParent) return visibleParent
    }

    return el
  }

  scrollToTarget (el) {
    if (!el) return

    // 1) scrollIntoView natif (anime tous les conteneurs scrollables et la fenêtre)
    el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' })

    // 2) S'assurer que les conteneurs scrollables intermédiaires centrent aussi l'élément
    let container = el.parentElement
    while (container && container !== document.body && container !== document.documentElement) {
      const oy = window.getComputedStyle(container).overflowY
      if ((oy === 'auto' || oy === 'scroll') && container.scrollHeight > container.clientHeight) {
        const elRect = el.getBoundingClientRect()
        const containerRect = container.getBoundingClientRect()
        const offsetTop = elRect.top - containerRect.top + container.scrollTop - (container.clientHeight / 2) + (elRect.height / 2)
        container.scrollTo({ top: offsetTop, behavior: 'smooth' })
      }
      container = container.parentElement
    }
  }

  highlight (el) {
    if (!el) return

    el.classList.add('ring-2', 'ring-warning-500', 'ring-offset-2', 'rounded')
    setTimeout(() => {
      el.classList.remove('ring-2', 'ring-warning-500', 'ring-offset-2', 'rounded')
    }, 2500)
  }

  focusTarget (el) {
    if (!el) return

    if (typeof el.focus === 'function') {
      try {
        el.focus({ preventScroll: true })
        return
      } catch {
        // Ignorer si le focus échoue
        return
      }
    }

    const focusable = el.querySelector?.('input:not([type="hidden"]), textarea, select, [contenteditable="true"], .ts-control input')
    if (focusable && typeof focusable.focus === 'function') {
      try {
        focusable.focus({ preventScroll: true })
      } catch {
        // Ignorer si le focus échoue
      }
    }
  }

  cssEscape (str) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(str)
    return String(str).replace(/["\\]/g, '\\$&')
  }
}



