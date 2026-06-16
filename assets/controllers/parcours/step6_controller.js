/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/step7_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:11
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import callOut from '../../js/callOut'
import { calculEtatStep } from '../../js/calculEtatStep'
import trixEditor from '../../js/trixEditor'

export default class extends Controller {
  static targets = [
    'listeCodes',
  ]

  static values = {
    url: String,
    urlCodeRome: String,
    urlCodeRomeGere: String,
  }

  connect() {
    document.getElementById('parcours_step6_poursuitesEtudes').addEventListener('trix-blur', this.savePoursuitesEtudes.bind(this))
    document.getElementById('parcours_step6_debouches').addEventListener('trix-blur', this.saveDebouches.bind(this))

    this._loadRome()
  }

  async removeCode(event) {
    event.preventDefault()
    const body = {
      method: 'POST',
      body: JSON.stringify({
        action: 'DELETE',
        code: event.params.code,
      }),
    }

    await fetch(this.urlCodeRomeGereValue, body).then((response) => response.json()).then((data) => {
      if (data === true) {
        callOut('Code supprimé', 'success')
        this._loadRome()
      } else {
        callOut('Erreur lors de la suppression', 'danger')
      }
    })
  }

  async addCode(event) {
    event.preventDefault()
    const body = {
      method: 'POST',
      body: JSON.stringify({
        action: 'ADD',
        code: document.getElementById('codeRomeToAdd').value,
      }),
    }

    await fetch(this.urlCodeRomeGereValue, body).then((response) => response.json()).then((data) => {
      if (data === true) {
        callOut('Code ajouté', 'success')
        this._loadRome()
      } else {
        callOut(data && data.error ? data.error : 'Erreur lors de la sauvegarde', 'danger')
      }
    })
  }

  async _loadRome() {
    this.listeCodesTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlCodeRomeValue)
    this.listeCodesTarget.innerHTML = await response.text()
  }

  async _save(options) {
    await saveData(this.urlValue, options).then(async () => {
      await updateEtatOnglet(this.urlValue, 'onglet6', 'parcours')
    })
  }

  savePoursuitesEtudes() {
    this._save({
      field: 'poursuitesEtudes',
      action: 'textarea',
      value: trixEditor('parcours_step6_poursuitesEtudes'),
    })
  }

  saveDebouches() {
    this._save({
      field: 'debouches',
      action: 'textarea',
      value: trixEditor('parcours_step6_debouches'),
    })
  }

  etatStep(event) {
    calculEtatStep(this.urlValue, 6, event, 'parcours')
  }
}
