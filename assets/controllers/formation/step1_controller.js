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

  saveRespFormation(event) {
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

  changeComposantePorteuse(event) {
    this._save({
      action: 'composantePorteuse',
      value: event.target.value,
    })
  }

  changeHasParcours(event) {
    // Seul le passage en multi-parcours (« Oui ») a un effet : la formation mono devient
    // multi, ce qui change toute la mise en page (onglets) → on recharge après sauvegarde.
    if (parseInt(event.target.value, 10) !== 1) {
      return
    }
    if (confirm('Cette formation va comporter plusieurs parcours. La structure va être modifiée. Voulez-vous continuer ?')) {
      this._save({
        field: 'hasParcours',
        action: 'yesNo',
        value: event.target.value,
      }).then(() => {
        window.location.reload()
      })
    } else {
      // annulation : recocher « Non »
      document.getElementById('formation_step1_parcours_0').checked = true
      event.target.checked = false
    }
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
