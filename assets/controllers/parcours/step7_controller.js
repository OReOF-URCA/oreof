/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/step7_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:11
 */

import { Controller } from '@hotwired/stimulus'
import { saveData } from '../../js/saveData'
import trixEditor from '../../js/trixEditor'

export default class extends Controller {
  static values = {
    url: String,
  }

  connect() {
    document.getElementById('parcours_step7_descriptifHautPage').addEventListener('trix-blur', this.saveDescriptifHautPage.bind(this))
    document.getElementById('parcours_step7_descriptifBasPage').addEventListener('trix-blur', this.saveDescriptifBasPage.bind(this))
    document.getElementById('parcours_step7_descriptifHautPageAutomatique').addEventListener('trix-blur', this.saveDescriptifHautPageAutomatique.bind(this))
  }

  async _save(options) {
    await saveData(this.urlValue, options).then(async () => {
      // await updateEtatOnglet(this.urlValue, 'onglet7', 'parcours')
    })
  }

  saveDescriptifHautPage() {
    this._save({
      field: 'descriptifHautPage',
      action: 'textarea',
      value: trixEditor('parcours_step7_descriptifHautPage'),
    })
  }

  saveDescriptifHautPageAutomatique() {
    this._save({
      field: 'descriptifHautPageAutomatique',
      action: 'textarea',
      value: trixEditor('parcours_step7_descriptifHautPageAutomatique'),
    })
  }

  saveDescriptifBasPage() {
    this._save({
      field: 'descriptifBasPage',
      action: 'textarea',
      value: trixEditor('parcours_step7_descriptifBasPage'),
    })
  }

  saveCodeRNCP(event) {
    this._save({
      action: 'textarea',
      field: 'codeRNCP',
      value: event.target.value,
    })
  }
}
