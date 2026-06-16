/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours_wizard_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/02/2023 22:20
 */

import { Controller } from '@hotwired/stimulus'
import updateUrl from '../js/updateUrl'

export default class extends Controller {
  static targets = [
    'content',
  ]

  static values = {
    url: String,
    step: String,
    dpeParcours: String,
  }

  connect() {
    this._loadStep(this.stepValue)
  }

  changeStep(event) {
    // mise à jour URL
    updateUrl({ step: event.params.step })
    updateUrl({ semestre: 0, ue: 0 }, 'remove') // on supprime les paramètres qui serait d'une step précédente
    this._loadStep(event.params.step)
  }

  // Recharge l'onglet actuellement affiché (déclenché par l'évènement base:refreshStep).
  refreshStep() {
    this._loadStep(this._currentStep ?? this.stepValue)
  }

  async _loadStep(step) {
    this._currentStep = step
    const response = await fetch(`${this.urlValue + this.dpeParcoursValue}/${step}`)
    this.contentTarget.innerHTML = await response.text()
  }
}
