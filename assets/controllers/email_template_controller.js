/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/email_template_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/10/2025 19:00
 */

import { Controller } from '@hotwired/stimulus'

// Simple debounce
function debounce (fn, delay = 350) {
  let t
  return (...args) => {
    clearTimeout(t)
    t = setTimeout(() => fn(...args), delay)
  }
}

export default class extends Controller {
  static targets = [
    'subject', 'bodyHtml', 'bodyText', 'previewSubject', 'previewIframe', 'previewText', 'workflow', 'subjectsJson', 'subjectVariant'
  ]

  get previewUrl () {
    // même domaine, route définie côté contrôleur
    return '/administration/config/email/preview/render'
  }

  connect () {
    this.updatePreview = debounce(this.updatePreview.bind(this), 300);
    // Live preview on input
    ['subject', 'bodyHtml', 'bodyText', 'workflow', 'subjectsJson', 'subjectVariant'].forEach(t => {
      this[`${t}Target`].addEventListener('input', this.updatePreview)
      this[`${t}Target`].addEventListener('change', this.updatePreview)
    })

    this.rebuildVariantOptions()
    this.updatePreview()
  }

  rebuildVariantOptions () {
    if (!this.subjectsJsonTarget || !this.subjectVariantTarget) return
    let variants = []
    try {
      const obj = JSON.parse(this.subjectsJsonTarget.value || '{}')
      if (obj && typeof obj === 'object') {
        variants = Object.keys(obj).filter(k => typeof obj[k] === 'string')
      }
    } catch (e) { /* JSON invalide -> pas d’options */ }

    // Conserver la sélection actuelle si possible
    const current = this.subjectVariantTarget.value || ''

    // Clear + rebuild
    this.subjectVariantTarget.innerHTML = ''
    const optDefault = document.createElement('option')
    optDefault.value = ''
    optDefault.textContent = '(par défaut)'
    this.subjectVariantTarget.appendChild(optDefault)

    variants.forEach(v => {
      const opt = document.createElement('option')
      opt.value = v
      opt.textContent = v
      this.subjectVariantTarget.appendChild(opt)
    })

    // Rétablir sélection si toujours valide
    if (variants.includes(current)) {
      this.subjectVariantTarget.value = current
    } else {
      this.subjectVariantTarget.value = ''
    }
  }

  insertToken (e) {
    const token = e.currentTarget.dataset.tokenValue
    // insère au curseur dans bodyHtml
    const ta = this.bodyHtmlTarget
    const start = ta.selectionStart ?? ta.value.length
    const end = ta.selectionEnd ?? ta.value.length
    ta.value = ta.value.slice(0, start) + token + ta.value.slice(end)
    ta.focus()
    ta.selectionStart = ta.selectionEnd = start + token.length
    this.updatePreview()
  }

  async updatePreview () {
    this.rebuildVariantOptions()
    const payload = new FormData()
    payload.set('subject', this.subjectTarget?.value ?? '')
    payload.set('bodyHtml', this.bodyHtmlTarget?.value ?? '')
    payload.set('bodyText', this.bodyTextTarget?.value ?? '')
    payload.set('workflow', this.workflowTarget?.value ?? 'preview.workflow')
    // NEW: passer le JSON des sujets + la variante choisie
    payload.set('subjects', this.subjectsJsonTarget?.value ?? '')
    payload.set('subjectVariant', this.subjectVariantTarget?.value ?? '')

    try {
      const res = await fetch(this.previewUrl, { method: 'POST', body: payload })
      if (!res.ok) throw new Error('Preview HTTP ' + res.status)
      const data = await res.json()
      this.previewSubjectTarget.textContent = data.subject ?? ''

      this.previewIframeTarget.innerHTML = data.html ?? ''

      this.previewTextTarget.textContent = data.text ?? ''
    } catch (e) {
      this.previewSubjectTarget.textContent = 'Erreur de preview'
      this.previewTextTarget.textContent = String(e)
    }
  }
}
