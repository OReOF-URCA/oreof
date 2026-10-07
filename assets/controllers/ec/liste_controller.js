/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/liste_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2023 16:08
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import callOut from '../../js/callOut'

export default class extends Controller {
  async deplacerEc(event) {
    event.preventDefault()
    const { url } = event.params
    await fetch(url).then(() => {
      callOut('EC déplacé', 'success')
      this.dispatch('refreshListeEc', { detail: { ue: event.params.ue, parcours: event.params.parcours } })
    })
  }

  delete(event) {
    event.preventDefault()
    const { url } = event.params
    const { csrf } = event.params
    let modal = new Modal(document.getElementById('modal-delete'))
    modal.show()
    const btn = document.getElementById('btn-confirm-supprimer')
    btn.replaceWith(btn.cloneNode(true));
    document.getElementById('btn-confirm-supprimer').addEventListener('click', async () => {
      const body = {
        method: 'DELETE',
        body: JSON.stringify({
          csrf,
        }),
      }
      modal = null
      await fetch(url, body).then((e) => {
        if (e.status === 200) {
          callOut('Suppression effectuée', 'success')
          this.dispatch('refreshListeEc', { detail: { ue: event.params.ue, parcours: event.params.parcours } })
        } else {
          callOut('Erreur lors de la suppression', 'danger')
        }
      })
    })
  }
}
