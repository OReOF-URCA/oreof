/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/textarea_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 22:10
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['input', 'texte']

  static values = { maxLength: Number }

  initialize() {
    this.update = this.update.bind(this)
  }

  connect() {
    this.update()
    this.inputTarget.addEventListener('input', this.update)
    this.inputTarget.addEventListener('change', this.update)
    this.inputTarget.addEventListener('paste', this.update)
  }

  disconnect() {
    this.inputTarget.removeEventListener('input', this.update)
    this.inputTarget.removeEventListener('change', this.update)
    this.inputTarget.removeEventListener('paste', this.update)
  }

  update() {
    // La cible « texte » (compteur de caractères) n'est rendue que lorsque le
    // champ possède un help. Sans help, on ne fait rien plutôt que de planter.
    if (!this.hasTexteTarget) {
      return
    }
    this.texteTarget.innerHTML = `${this.count.toString()} caractères restants`
  }

  get count() {
    const value = this.inputTarget.value.length
    return this.maxLengthValue - value
  }
}
