/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/ec/step1_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/03/2023 21:14
 */

import { Controller } from '@hotwired/stimulus'
import { Modal } from 'bootstrap'
import { saveData } from '../../js/saveData'
import { updateEtatOnglet } from '../../js/updateEtatOnglet'
import callOut from '../../js/callOut'
import { calculEtatStep } from '../../js/calculEtatStep'

export default class extends Controller {
  static targets = [
    'content',
    'zoneMutualise',
  ]

  static values = {
    url: String,
    urlMutualise: String,
    urlImpacts: String,
    urlRemoveAll: String,
  }

  connect() {
    this._loadMutualise()
  }

  refreshListe() {
    this._loadMutualise()
  }

  async _loadMutualise() {
    this.zoneMutualiseTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlMutualiseValue)
    this.zoneMutualiseTarget.innerHTML = await response.text()
  }

  delete(event) {
    event.preventDefault()
    const { url } = event.params
    const { fiche } = event.params
    let modal = new Modal(document.getElementById('modal-delete'))
    modal.show()
    const btn = document.getElementById('btn-confirm-supprimer')
    btn.replaceWith(btn.cloneNode(true));
    document.getElementById('btn-confirm-supprimer').addEventListener('click', async () => {
      const body = {
        method: 'DELETE',
        body: JSON.stringify({
          field: 'delete',
          fiche,
        }),
      }
      modal = null
      await fetch(url, body).then((e) => {
        if (e.status === 200) {
          callOut('Suppression effectuée', 'success')
          this._loadMutualise()
        } else {
          callOut('Erreur lors de la suppression', 'danger')
        }
      })
    })
  }

  changeResponsableEc(event) {
    this._save({
      field: 'responsableFicheMatiere',
      action: 'responsableFicheMatiere',
      value: event.target.value,
    }).then((data) => {
      if (data.responsableFicheMatiere !== null) {
        document.getElementById('fiche_matiere_resp_dd').innerHTML = `${data.responsableFicheMatiere.display}<br><a href="mailto:${data.responsableFicheMatiere.email}">${data.responsableFicheMatiere.email}</a>`
      } else {
        document.getElementById('fiche_matiere_resp_dd').innerHTML = '<span class="badge bg-danger">Aucun responsable</span>'
      }
    })
  }

  saveSigle(event) {
    this._save({
      field: 'sigle',
      action: 'textarea',
      value: event.target.value,
    }).then((data) => {
      document.getElementById('fiche_matiere_libelle').innerText = data.display
      document.getElementById('fiche_matiere_libelle_dd').innerText = data.display
    })
  }

  saveContenuFr() {
    const { value } = document.getElementById('fiche_matiere_step1_libelle')
    this._save({
      field: 'libelle',
      action: 'textarea',
      value,
    }).then((data) => {
      document.getElementById('fiche_matiere_libelle').innerText = data.display
      document.getElementById('fiche_matiere_libelle_dd').innerText = data.display
    })
  }

  saveCodeApogee() {
    const { value } = document.getElementById('fiche_matiere_step1_codeApogee')
    this._save({
      field: 'codeApogee',
      action: 'textarea',
      value,
    })
  }

  saveContenuEn() {
    this._save({
      field: 'libelleAnglais',
      action: 'textarea',
      value: document.getElementById('fiche_matiere_step1_libelleAnglais').value,
    })
  }

  changeTypeMatiere(event) {
    this._save({
      field: 'typeMatiere',
      action: 'typeMatiere',
      value: event.target.value,
    })
  }

  async changeEnseignementMutualise (event) {
    const newValue = parseInt(event.target.value, 10)

    if (newValue === 0) {
      // Récupérer la liste des impacts
      const response = await fetch(this.urlImpactsValue)
      const impactsHtml = await response.text()

      // Configurer et afficher la modale de confirmation
      const modal = new Modal(document.getElementById('modal-confirm'))
      document.getElementById('modal-confirm-title').innerHTML = 'Désactiver la mutualisation'
      document.getElementById('modal-confirm-body').innerHTML = impactsHtml

      // Mettre à jour le libellé du bouton de confirmation
      const btnConfirm = document.getElementById('btn-confirm-valide')
      btnConfirm.replaceWith(btnConfirm.cloneNode(true))
      const freshBtn = document.getElementById('btn-confirm-valide')
      freshBtn.textContent = 'Confirmer et supprimer les liaisons'
      freshBtn.classList.remove('btn-danger')
      freshBtn.classList.add('btn-warning')

      let confirmed = false

      freshBtn.addEventListener('click', async () => {
        confirmed = true
        // Appel DELETE pour supprimer toutes les liaisons et notifier
        const res = await fetch(this.urlRemoveAllValue, { method: 'DELETE' })
        if (res.status === 200) {
          const data = await res.json()
          if (data.mailErrors > 0) {
            callOut('Mutualisation désactivée. Les notifications in-app ont été créées, mais certains emails n\'ont pas pu être envoyés.', 'warning')
          } else {
            callOut('Mutualisation désactivée. Les responsables de parcours ont été notifiés par email et dans l’application.', 'success')
          }
          document.getElementById('coursMutualises').style.display = 'none'
          // La mise à false est déjà effectuée côté serveur dans remove-all
        } else {
          confirmed = false
          callOut('Erreur lors de la désactivation de la mutualisation', 'danger')
          const ouiRadio = document.querySelector(`input[name="${event.target.name}"][value="1"]`)
          if (ouiRadio) {
            ouiRadio.checked = true
          }
        }
      }, { once: true })

      // Si l'utilisateur ferme la modale sans confirmer, remettre le radio sur "Oui"
      document.getElementById('modal-confirm').addEventListener('hidden.bs.modal', () => {
        if (!confirmed) {
          const ouiRadio = document.querySelector(`input[name="${event.target.name}"][value="1"]`)
          if (ouiRadio) {
            ouiRadio.checked = true
          }
        }
        // Remettre le bouton dans son état d'origine
        const btn = document.getElementById('btn-confirm-valide')
        if (btn) {
          btn.textContent = 'Confirmer'
          btn.classList.remove('btn-warning')
          btn.classList.add('btn-danger')
        }
      }, { once: true })

      modal.show()
      return
    }

    // Passage à true : comportement normal
    this._save({
      field: 'enseignementMutualise',
      action: 'yesNo',
      value: event.target.value,
    })
    document.getElementById('coursMutualises').style.display = 'block'
    this._loadMutualise()
  }

  isMutualise(event) {
    this._save({
      field: event.params.type,
      action: 'yesNo',
      value: event.target.value,
    })
  }

  etatStep(event) {
    calculEtatStep(this.urlValue, 1, event, 'ec')
  }

  async _save(options) {
    return saveData(this.urlValue, options).then(async (data) => {
      await updateEtatOnglet(this.urlValue, 'onglet1', 'ec')
      return data
    })
  }
}
