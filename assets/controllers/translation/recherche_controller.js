/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/translation/recherche_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/10/2025 11:30
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

  static targets = ['resultats', 'search']
  static values = {
    url: String,
  }

  recherche () {
    const valeur = this.searchTarget.value
    if (valeur.length > 2) {
      this.resultatsTarget.innerHTML = window.da.loaderStimulus
      fetch(`${this.urlValue}?search=${valeur}`)
        .then((response) => response.text())
        .then((html) => {
          this.resultatsTarget.innerHTML = html
        })
    }
  }
}
