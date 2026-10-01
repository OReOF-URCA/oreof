/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/login_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/04/2023 09:23
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['toggleButton']

  showFormLogin() {
    const form = document.getElementById('formLogin')
    if (!form) {
      return
    }

    const isHidden = form.classList.toggle('hidden')
    if (this.hasToggleButtonTarget) {
      this.toggleButtonTarget.textContent = isHidden
        ? 'Connexion avec login/mot de passe'
        : 'Masquer le formulaire login/mot de passe'
    }
  }
}
