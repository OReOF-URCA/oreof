/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/structure_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/03/2023 21:49
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'
import JsonResponse from '../../js/JsonResponse'

export default class extends Controller {
  static values = {
    urlSave: String,
    urlUpdate: String,
  }

  async synchroHeures(event) {
    const body = new FormData()
    body.append('value', event.target.checked)
    body.append('field', 'synchroHeures')
    body.append('ec', event.params.ec)

    await fetch(this.urlUpdateValue, {
      method: 'POST',
      body,
    }).then((response) => {
      if (response.ok) {
        const inputs = document.querySelectorAll('input[name^="ec_step4"]')
        inputs.forEach((input) => {
          if (event.target.checked) {
            input.setAttribute('disabled', 'disabled')
          } else {
            input.removeAttribute('disabled')
          }
        })
      }
      JsonResponse(response)
    })
  }

  async heuresSpecifiques(event) {
    const body = new FormData()
    body.append('value', event.target.checked)
    body.append('field', 'heuresSpecifiques')
    body.append('ec', event.params.ec)

    await fetch(this.urlUpdateValue, {
      method: 'POST',
      body,
    }).then((response) => {
      if (response.ok) {
        const inputs = document.querySelectorAll('input[name^="ec_step4"]')
        inputs.forEach((input) => {
          if (event.target.checked) {
            input.removeAttribute('disabled')
          } else {
            input.setAttribute('disabled', 'disabled')
          }
        })
      }
      JsonResponse(response)
    })
  }

  saveModaliteEnseignement(event) {
    const valeur = event.target.value
    this._save({
      action: 'modalitesEnseignement',
      value: valeur,
    })

    const blocPresentiel = document.getElementById('bloc_presentiel')
    const blocDistanciel = document.getElementById('bloc_distanciel')

    if (valeur === '0' || valeur === 'presentielle') {
      // présentiel
      blocPresentiel.style.display = 'block'
      blocDistanciel.style.display = 'none'
    }

    if (valeur === '1' || valeur === 'hybride') {
      // hybride
      blocPresentiel.style.display = 'block'
      blocDistanciel.style.display = 'block'
    }

    if (valeur === '2' || valeur === 'distancielle') {
      // distanciel
      blocPresentiel.style.display = 'none'
      blocDistanciel.style.display = 'block'
    }
  }

  saveEcts(event) {
    this._save({
      field: 'ects',
      action: 'float',
      value: event.target.value,
    })
  }

  saveVolume(event) {
    this._save({
      field: event.params.type,
      action: 'float',
      value: event.target.value,
    })
  }

  async _save(options) {
    return saveData(this.urlSaveValue, options).then(async (data) => {
      if (data === false || data?.success === false || data?.error?.length) return false
      await updateEtatOnglet(this.urlSaveValue, 'onglet4', 'ec')
      return data
    })
  }

  async etatStep(event) {
    await calculEtatStep(this.urlSaveValue, 4, event, 'ec')
  }
}
