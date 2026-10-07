/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/manage_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/03/2023 12:37
 */

import { Controller } from '@hotwired/stimulus'
import TomSelect from 'tom-select'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = ['matieres', 'natureSection']

  code = 0

  matieres = []

  static values = {
    url: String,
    ue: Number,
    parcours: Number,
    enfant: Boolean,
    edit: Boolean,
  }

  natureEc = null

  tom = null

  /** @deprecated */
  async connect () {
    // Initialiser l'affichage et l'état (required/disabled) des sections
    try {
      this.updateNature()
    } catch {
      // ignore
    }
  }

  /** @deprecated */
  async changeNatureEc (event) {
    if (this.editValue === true) {
      if (!confirm('Attention, le changement de la nature d\'EC va supprimer les données préalablement saisies. Voulez vous continuer ?')) {
        event.preventDefault()
        return
      }
    }

    let url
    if (this.urlValue.includes('?')) {
      url = `${this.urlValue}&choix=${event.target.value}`
    } else {
      url = `${this.urlValue}?choix=${event.target.value}`
    }

    const response = await fetch(url)
    this.matieresTarget.innerHTML = await response.text()
    if (document.getElementById('ficheMatiere')) {
      this.tom = new TomSelect('#ficheMatiere', { allowEmptyOption: true })
      this.tom.settings.placeholder = 'Choisir une fiche matière'
      this.tom.inputState()
    }
  }

  /** @deprecated */
  ajoutTypeEc (event) {
    event.preventDefault()
    document.getElementById('typeEcTexte').classList.remove('d-none')
  }

  /** @deprecated */
  changeTypeEcTexte (event) {
    event.preventDefault()
    if (document.getElementById('element_constitutif_typeEc')) {
      document.getElementById('element_constitutif_typeEc').disabled = event.currentTarget.value.length > 0
    }

    if (document.getElementById('element_constitutif_edit_typeEc')) {
      document.getElementById('element_constitutif_edit_typeEc').disabled = event.currentTarget.value.length > 0
    }
  }

  /** @deprecated */
  addFromListe (event) {
    // ne pas soumettre le formulaire
    event.preventDefault()

    // récupérer le choix dans la liste déroulante #ficheMatiere et l'ajouter dans le tableau HTML
    const select = document.getElementById('ficheMatiere')
    const option = select.options[select.selectedIndex]
    this.matieres.push(`id_${option.value}`)

    const matiereLibelle = option.text
    const table = document.getElementById('tableFiches')
    let tbody = table.getElementsByTagName('tbody')[0] // sélectionne le tbody (ou crée-le s'il n'existe pas)
    if (!tbody) {
      tbody = document.createElement('tbody') // crée le tbody s'il n'existe pas
      table.appendChild(tbody) // ajoute le tbody à la table
    }
    const row = tbody.insertRow()
    const cell2 = row.insertCell(0)
    const cell3 = row.insertCell(1)
    cell2.innerHTML = matiereLibelle
    cell3.innerHTML = '<button class="btn text-danger"><i class="fas fa-trash"></i> Supprimer</button>'

    // ajouter un écouteur sur le bouton supprimer
    cell3.addEventListener('click', (ev) => {
      ev.preventDefault()
      const index = this.matieres.indexOf(`id_${option.value}`)
      if (index > -1) {
        this.matieres.splice(index, 1)
      }
      table.deleteRow(row.rowIndex)
    })
    select.value = ''
    this.tom.clear()
    this.code++
  }

  addNewFiche (event) {
    event.preventDefault()
    const matiereLibelle = document.getElementById('ficheMatiereLibelle').value
    const table = document.getElementById('tableFiches')
    let tbody = table.getElementsByTagName('tbody')[0] // sélectionne le tbody (ou crée-le s'il n'existe pas)
    if (!tbody) {
      tbody = document.createElement('tbody') // crée le tbody s'il n'existe pas
      table.appendChild(tbody) // ajoute le tbody à la table
    }
    const row = tbody.insertRow()
    const cell2 = row.insertCell(0)
    const cell3 = row.insertCell(1)

    this.matieres.push(`ac_${matiereLibelle}`)

    cell2.innerHTML = `${matiereLibelle} <i>(a créer)</i>`
    cell3.innerHTML = '<button class="btn text-danger"><i class="fas fa-trash"></i> Supprimer</button>'

    // ajouter un écouteur sur le bouton supprimer
    cell3.addEventListener('click', (ev) => {
      ev.preventDefault()
      const index = this.matieres.indexOf(`ac_${matiereLibelle}`)
      if (index > -1) {
        this.matieres.splice(index, 1)
      }
      table.deleteRow(row.rowIndex)
    })
    document.getElementById('ficheMatiereLibelle').value = ''
    this.code++
  }

  valider (event) {
    event.preventDefault()
    const isChoixEc = document.getElementById('element_constitutif_choixEc').value
    const form = document.getElementById('modal_form')
    if (isChoixEc === 'true') {
      // choix multiple
      // compter le nombre de matières dans le tableau #tableFiches, partie body
      const table = document.getElementById('tableFiches')
      const tbody = table.getElementsByTagName('tbody')[0]
      const lignes = tbody.getElementsByTagName('tr')

      if (this.matieres.length === 0 && lignes.length === 0) {
        callOut('Vous devez choisir ou créer au moins une matière', 'warning')
        return
      }

      const input = document.createElement('input')
      input.type = 'hidden'
      input.name = 'matieres'
      input.value = this.matieres.join(',')
      form.appendChild(input)
    } else if (isChoixEc === 'false') {
      // choix unique
      if (document.getElementById('ficheMatiere').value === '' && document.getElementById('ficheMatiereLibelle').value === '') {
        callOut('Vous devez choisir une matière ou créer en indiquant un libellé', 'error')
        return
      }
    }
    // ajouter le tableau matieres aux données du formulaire

    fetch(form.action, {
      method: form.method,
      body: new URLSearchParams(new FormData(form)),
    })
      .then((response) => response.json())
      .then(async () => {
        callOut('Sauvegarde effectuée', 'success')
        this.dispatch('modalHide', { detail: { ue: this.ueValue, parcours: this.parcoursValue } })
      })
  }

  validerEnfant (event) {
    event.preventDefault()
    const form = document.getElementById('modal_form')

    fetch(form.action, {
      method: form.method,
      body: new URLSearchParams(new FormData(form)),
    })
      .then((response) => response.json())
      .then(async () => {
        callOut('Sauvegarde effectuée', 'success')
        this.dispatch('modalHide', { detail: { ue: this.ueValue, parcours: this.parcoursValue } })
      })
  }

  nouvelleFiche (event) {
    event.preventDefault()
    if (document.getElementById('ficheMatiereLibelle').value !== '') {
      if (confirm(`Voulez-vous créer une nouvelle fiche de matière ${document.getElementById('ficheMatiereLibelle').value} ? Il faudra ensuite compléter les éléments de cette fiche EC/matière.`)) {
        document.getElementById('ficheMatiereLibelle').value = `${document.getElementById('ficheMatiereLibelle').value} (à compléter)`
      }
    } else {
      callOut('Vous devez indiquer un libellé pour créer une nouvelle fiche de matière', 'warning')
    }
  }

  /** @deprecated */
  async removeEcEnfant (event) {
    event.preventDefault()
    // suppression dans this.matieres
    const index = this.matieres.indexOf(`id_${event.params.fichematiere}`)
    if (index > -1) {
      this.matieres.splice(index, 1)
    }

    if (confirm('Voulez-vous vraiment supprimer cet EC enfant ?')) {
      await fetch(`${this.urlValue}?delete=${event.params.ecenfant}`)
      event.target.parentElement.parentElement.remove()
    }
  }

  updateNature (event) {
    const val = (event && event.target && event.target.value) ? event.target.value : (document.querySelector('input[name="element_constitutif[natureUeEc]"]:checked')?.value ?? (document.getElementById('element_constitutif_natureUeEc') ? document.getElementById('element_constitutif_natureUeEc').value : '9'))
    console.log(val)
    this.natureSectionTargets.forEach(el => {
      const matches = (el.dataset.nature === 'nature_' + val)
      // toggle visibility
      el.classList.toggle('hidden', !matches)

      // find form controls inside the section
      const controls = el.querySelectorAll('input, select, textarea')
      controls.forEach((ctrl) => {
        try {
          if (matches) {
            console.log('matching: ' + ctrl.id + '')
            // enable and mark required
            ctrl.removeAttribute('disabled')
            // set required only for meaningful inputs (ignore buttons)
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(ctrl.tagName)) {
              console.log('set required')
              // avoid setting required on hidden or readonly controls
              if (ctrl.type !== 'hidden' && !ctrl.readOnly && ctrl.name !== 'element_constitutif[ficheMatiere][new]') {
                ctrl.required = true
                ctrl.setAttribute('aria-required', 'true')
              }
            }
          } else {
            // disable and remove required
            ctrl.setAttribute('disabled', 'disabled')
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(ctrl.tagName)) {
              ctrl.required = false
              ctrl.removeAttribute('aria-required')
            }
          }
        } catch {
          // ignore control manipulation errors
        }
      })
    })
  }
}
