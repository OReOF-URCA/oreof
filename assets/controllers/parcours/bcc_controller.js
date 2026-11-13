/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/parcours/bcc_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/04/2023 16:41
 */
import { Controller } from '@hotwired/stimulus'
import callOut from '../../js/callOut'
import JsonResponse from '../../js/JsonResponse'

export default class extends Controller {
  static values = {
    urlUpdateComptence: String,
  }

  changeBcc(event) {
      const bccId = event.params.id
      const isChecked = event.currentTarget.checked
      const competenceCheckboxes = this.element.querySelectorAll(`.bcc_${bccId}`)
      competenceCheckboxes.forEach((checkbox) => {
          checkbox.checked = isChecked
      })
  }

    changeCompetence(event) {
        const bccId = event.params.bccId
        const bccCheckbox = this.element.querySelector(`#bcc_${bccId}`)
        const competenceCheckboxes = this.element.querySelectorAll(`.bcc_${bccId}`)
        if (bccCheckbox && competenceCheckboxes.length > 0) {
            const anyChecked = Array.from(competenceCheckboxes).some((cb) => cb.checked)
            bccCheckbox.checked = anyChecked
    }
  }

    toggleAll(event) {
        event.preventDefault()
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]')
        const allChecked = Array.from(checkboxes).every((cb) => cb.checked)
        checkboxes.forEach((cb) => {
            cb.checked = !allChecked
        })
    }

  sauvegardeFormModal(event) {
    event.preventDefault()

    const form = this.element.getElementsByTagName('form')[0]
    fetch(form.action, {
      method: form.method,
      body: new URLSearchParams(new FormData(form)),
    })
      .then((response) => response.json())
      .then(async () => {
        callOut('Sauvegarde effectuée', 'success')
        this.dispatch('modalClose')
      })
  }

  async updateCompetence(event) {
    const body = new FormData()
    body.append('competence', event.params.competence)
    body.append('ec', event.params.ec)
    body.append('checked', event.currentTarget.checked)

    await fetch(this.urlUpdateComptenceValue, {
      method: 'POST',
      body,
    }).then((response) => {
      // response.json()
      JsonResponse(response)
    })
  }
}
