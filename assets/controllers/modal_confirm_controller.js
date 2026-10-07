/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/crud_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/03/2023 09:57
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    titre: String,
    body: String,
    reload: { type: Boolean, default: false },
  }

  connect() {

  }

  confirm(event) {
    event.preventDefault()
    const url = event.currentTarget.href
    let modal = new Modal(document.getElementById('modal-confirm'))
    document.getElementById('modal-confirm-title').innerHTML = this.titreValue
    document.getElementById('modal-confirm-body').innerHTML = this.bodyValue
    modal.show()
    const btn = document.getElementById('btn-confirm-valide')
    btn.replaceWith(btn.cloneNode(true))
    document.getElementById('btn-confirm-valide').addEventListener('click', async () => {
      modal = null
      await fetch(url).then((e) => {
        if (e.status === 200) {
          if (this.reloadValue === true) {
            this.dispatch('refreshPage2', {})
          }
          callOut('Opération effectuée', 'success')
        } else {
          callOut('Erreur lors de l\'opération', 'danger')
        }
      })
    })
  }
}
