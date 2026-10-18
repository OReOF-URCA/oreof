/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/formation/step2_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 22:11
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import { calculEtatStep } from '../../js/calculEtatStep'
import trixEditor from '../../js/trixEditor'

export default class extends Controller {
  static targets = [
    'content',
  ]

  static values = {
    url: String,
  }

  connect() {
    document.getElementById('formation_step2_objectifsFormation').addEventListener('trix-blur', this.saveObjectifsFormation.bind(this))
    document.getElementById('formation_step2_contenuFormation').addEventListener('trix-blur', this.saveContenu.bind(this))
    document.getElementById('formation_step2_resultatsAttendus').addEventListener('trix-blur', this.saveResultats.bind(this))
    document.getElementById('formation_step2_rythmeFormationTexte').addEventListener('trix-blur', this.saveRythme.bind(this))
  }

  saveContenu() {
    this._save({
      field: 'contenuFormation',
      action: 'textarea',
      value: trixEditor('formation_step2_contenuFormation'),
    })
  }

  saveResultats() {
    this._save({
      field: 'resultatsAttendus',
      action: 'textarea',
      value: trixEditor('formation_step2_resultatsAttendus'),
    })
  }

  changeRythme(event) {
    this._save({
      field: 'rythmeFormation',
      action: 'rythmeFormation',
      value: event.target.value,
    })
  }

  saveRythme() {
    this._save({
      field: 'rythmeFormationTexte',
      action: 'textarea',
      value: trixEditor('formation_step2_rythmeFormationTexte'),
    })
  }

  saveObjectifsFormation() {
    this._save({
      field: 'objectifsFormation',
      action: 'textarea',
      value: trixEditor('formation_step2_objectifsFormation'),
    })
  }

  etatStep(event) {
    calculEtatStep(this.urlValue, 2, event, 'formation')
  }

  async _save(options) {
    return saveData(this.urlValue, options).then(async (data) => {
      if (data === false || data?.success === false || data?.error?.length) return false
      await updateEtatOnglet(this.urlValue, 'onglet2', 'formation')
      return data
    })
  }
}
