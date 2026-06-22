/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/formation/step1_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:03
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
    document.getElementById('formation_step1_modalitesAlternance').addEventListener('trix-blur', this.saveModalitesAlternance.bind(this))
    this._checkIfAlternance()
  }

  changeSigle(event) {
    this._save({
      field: 'sigle',
      action: 'textarea',
      value: event.target.value,
    }).then((data) => {
      document.getElementById('synthese_formation_libelle').innerText = data.display
      document.getElementById('synthese_formation_libelle_dd').innerText = data.display
    })
  }

  changeVille(event) {
    this._save({
      action: 'ville',
      value: event.target.value,
      isChecked: event.target.checked,
    })
  }

  saveRespFormation (event) {
    this._save({
      action: 'respFormation',
      value: event.target.value,
    })
  }

  saveCoRespFormation(event) {
    this._save({
      action: 'coRespFormation',
      value: event.target.value,
    })
  }

  changeRegimeInscription(event) {
    this._save({
      action: 'array',
      field: 'regimeInscription',
      value: event.target.value,
      isChecked: event.target.checked,
    })
    this._checkIfAlternance()
  }

  _checkIfAlternance() {
    let hasAlternance = false
    document.querySelectorAll('input[name="formation_step1[regimeInscription][]"]').forEach((element) => {
      if (element.checked) {
        if (element.value === 'Formation Initiale en apprentissage' || element.value === 'Formation Continue Contrat Professionnalisation') {
          hasAlternance = true
        }
      }
    })

    const trix = document.getElementById('formation_step1_modalitesAlternance')
    const _trixEditor = trix.editor
    if (!hasAlternance) {
      _trixEditor.element.removeAttribute('contentEditable')
      _trixEditor.element.classList.add('disabled')
    } else {
      _trixEditor.element.setAttribute('contentEditable', true)
      _trixEditor.element.classList.remove('disabled')
    }

    document.getElementById('formation_step1_modalitesAlternance').disabled = !hasAlternance
  }

  changeComposanteInscription(event) {
    this._save({
      action: 'composanteInscription',
      value: event.target.value,
      isChecked: event.target.checked,
    })
  }

  saveModalitesAlternance() {
    this._save({
      field: 'modalitesAlternance',
      action: 'textarea',
      value: trixEditor('formation_step1_modalitesAlternance'),
    })
  }

  async etatStep(event) {
    event.preventDefault()
    await calculEtatStep(this.urlValue, 1, event, 'formation')
  }

  async _save(options) {
    return saveData(this.urlValue, options).then(async (data) => {
      await updateEtatOnglet(this.urlValue, 'onglet1', 'formation')
      return data
    })
  }
}
