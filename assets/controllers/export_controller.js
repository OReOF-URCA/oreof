/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/mutualise_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/03/2023 09:25
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    listUrl: String,
  }

  static targets = ['composante', 'frame', 'submit', 'typeDocument', 'typeDocumentGlobal', 'generationState', 'pendingStateTemplate']

  connect() {
    this.enableSubmit()
  }

  refreshFrame () {
    if (!this.hasFrameTarget || !this.hasComposanteTarget) {
      return
    }

    this.resetGenerationState()

    const composante = this.composanteTarget.value
    const url = new window.URL(this.listUrlValue, window.location.origin)
    if (composante) {
      url.searchParams.set('composante', composante)
    }
    this.frameTarget.src = `${url.pathname}${url.search}`
  }

  resetGenerationState () {
    if (!this.hasGenerationStateTarget || !this.hasPendingStateTemplateTarget) {
      return
    }

    this.generationStateTarget.innerHTML = this.pendingStateTemplateTarget.innerHTML
  }

  onTypeGlobalChange () {
    if (!this.hasTypeDocumentGlobalTarget || !this.hasTypeDocumentTarget) {
      return
    }

    if (this.typeDocumentGlobalTarget.value !== '') {
      this.typeDocumentTarget.value = ''
    }
  }

  onTypeDocumentChange () {
    if (!this.hasTypeDocumentGlobalTarget || !this.hasTypeDocumentTarget) {
      return
    }

    if (this.typeDocumentTarget.value !== '') {
      this.typeDocumentGlobalTarget.value = ''
    }
  }

  disableSubmit () {
    if (this.hasSubmitTarget) {
      this.submitTarget.disabled = true
      this.submitTarget.classList.add('opacity-60', 'cursor-not-allowed')
    }
  }

  enableSubmit () {
    if (this.hasSubmitTarget) {
      this.submitTarget.disabled = false
      this.submitTarget.classList.remove('opacity-60', 'cursor-not-allowed')
    }
  }
}
