/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/step3_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:15
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'
import JsonResponse from '../../js/JsonResponse'

export default class extends Controller {
  static targets = [
    'content',
  ]

  static values = {
    url: String,
    updateStep: { type: Boolean, default: true },
  }

  saveVolume(event) {
    this._save({
      field: event.params.type,
      action: 'float',
      value: event.target.value,
    })
  }

  saveEcts(event) {
    this._save({
      field: event.params.type,
      action: 'float',
      value: event.target.value,
    })
  }

  ectsImpose(event) {
    this._save({
      field: 'ectsImpose',
      action: 'checkbox',
      isChecked: event.target.checked,
    })
  }

  mcccImpose(event) {
    this._save({
      field: 'mcccImpose',
      action: 'checkbox',
      isChecked: event.target.checked,
    })
  }

  volumesHorairesImpose(event) {
    this._save({
      field: 'volumesHorairesImpose',
      action: 'checkbox',
      isChecked: event.target.checked,
    })
  }

  saveSansHeures(event) {
    this._save({
      field: 'sansHeures',
      action: 'checkbox',
      isChecked: event.target.checked,
    })
  }

  sauvegardeMccc(event) {
    event.preventDefault()
    event.stopPropagation()
    const form = document.getElementById('formMccc')
    fetch(form.action, {
      method: form.method,
      body: new FormData(form),
    })
      .then((response) => {
        JsonResponse(response)
      })
  }

  etatStep(event) {
    calculEtatStep(this.urlValue, 4, event, 'ec')
  }

  async _save(options) {
    await saveData(this.urlValue, options).then(async () => {
      if (this.updateStepValue) {
        await updateEtatOnglet(this.urlValue, 'onglet4', 'ec')
      }
    })
  }
}
