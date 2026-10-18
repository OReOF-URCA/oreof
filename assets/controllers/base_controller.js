/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/base_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/03/2023 14:53
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'

import JsonResponse from '../js/JsonResponse'
import callOut from '../js/callOut'

export default class extends Controller {
  static targets = ['modal', 'modalBody', 'modalTitle', 'size', 'btnClose', 'liste']

  modal = null

  nomEvenement = 'refreshListe'

  details = {}

  _validForm(form) {
    // fonction de validation du formulaire. Parcourir les objets de formulaire et vérifier si les champs "required" sont non vides
    let valid = true
    form.querySelectorAll('input, select, textarea').forEach((element) => {
      element.classList.remove('error')
      if (element.required && element.value.trim() === '') {
        valid = false
        // ajouter la classe is-invalid à l'élément
        element.classList.add('error')
      }
      // tester si un élément a une longueur minimale : data-minlength
      if (element.required && element.dataset.minlength !== undefined) {
        if (element.dataset.minlength > element.value.length) {
          console.log('minlength')
          valid = false;
        }
      }

      if (element.required && element.dataset.minlength && element.value.trim().length < element.dataset.minlength) {
        valid = false
        element.classList.add('error')
      }

      // tester si un radio est coché
      if (element.type === 'radio' && element.required) {
        let radioChecked = false
        form.querySelectorAll(`input[name="${element.name}"]`).forEach((radio) => {
          // vérifier s'il est required

          if (radio.checked) {
            radioChecked = true
          }
        })
        if (!radioChecked) {
          valid = false
          form.querySelectorAll(`input[name="${element.name}"]`).forEach((radio) => {
            radio.classList.add('error')
          })
        }
      }
    })
    return valid
  }

  sauvegardeFormModal(event) {
    event.preventDefault()

    const form = event.currentTarget.closest('form') || this.element.getElementsByTagName('form')[0]
    if (!form || !form.reportValidity()) return

    fetch(form.action, {
      method: form.method,
      body: new FormData(form),
    })
      .then(async (response) => {
        const result = await JsonResponse(response)
        return response.ok && result.success === true
      })
      .then(async (success) => {
        if (!success) return
        this.modal.hide()
        document.querySelectorAll('.modal-backdrop').forEach((e) => {
          e.remove()
        })

        // tester si l'objet json updateComponent n'est pas vide et contient les clés id et event
        if (Object.keys(this.updateComponent).length > 0 && this.updateComponent.id && this.updateComponent.event) {
          // this.refreshPage()
          const component = document.getElementById(this.updateComponent.id).__component
          component.emit(this.updateComponent.event)
        }

        this.dispatch(this.nomEvenement, { detail: this.details })
      })
      .catch(() => {
        callOut('Impossible de confirmer l’enregistrement. Vérifiez les données avant de renvoyer le formulaire.', 'danger')
      })
  }

  async openModal(event) {
    this.modalBodyTarget.innerHTML = window.da.loaderStimulus
    this.modalTitleTarget.innerHTML = event.detail.title
    this.btnCloseTarget.innerHTML = event.detail.btnClose
    this.nomEvenement = event.detail.nomEvenement
    this.updateComponent = event.detail.updateComponent
    this.details = event.detail.details
    if (event.detail.form === true) {
      document.getElementById('btn_modal_submit').classList.remove('d-none')
      this.element.getElementsByTagName('form')[0].action = event.detail.formAction
    }

    if (event.detail.right === true) {
      document.getElementById('stimulus_modal').parentElement.classList.add('modal-right', event.detail.size)
    } else {
      document.getElementById('stimulus_modal').parentElement.classList.remove('modal-right')
      document.getElementById('stimulus_modal').classList.add(`modal-${event.detail.size}`)
    }
    this.modal = new Modal(this.modalTarget)
    this.modal.show()
    let response
    if (event.detail.params.length > 0) {
      const params = new URLSearchParams(event.detail.params)
      response = await fetch(`${event.detail.url}?${params.toString()}`)
    } else {
      response = await fetch(`${event.detail.url}`)
    }
    this.modalBodyTarget.innerHTML = await response.text()
  }

  modalHide() {
    this.modal.hide()
    this.modalBodyTarget.innerHTML = ''
    document.querySelectorAll('.modal-backdrop').forEach((e) => {
      e.remove()
    })
    this.dispatch('modalClose')
  }

  modalClose() {
    this.modalBodyTarget.innerHTML = ''
    document.querySelectorAll('.modal-backdrop').forEach((e) => {
      e.remove()
    })
    this.dispatch('modalClose')
  }

  async refreshModale(event) {
    this.modalBodyTarget.innerHTML = ''
    const response = await fetch(`${event.detail.url}`)
    this.modalBodyTarget.innerHTML = await response.text()
  }

  refreshPage() {
    window.location.reload()
  }

  redirectEdit() {
    if (!window.location.href.includes('/edit')) {
      window.location.assign(`${window.location.href}/edit`);
    }

    window.location.reload()
  }
}
