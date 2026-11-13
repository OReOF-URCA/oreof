/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/profil/user_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

import { Controller } from '@hotwired/stimulus'
import {renderStreamMessage} from '@hotwired/turbo'
import callOut from '../../js/callOut'

export default class extends Controller {
  static targets = ['configProfil', 'liste']

  static values = {
    urlConfigProfil: String,
    urlListe: String,
    urlAdd: String,
  }

  hasChanges = false

  connect() {
    this._updateListe()
  }

  disconnect() {
    if (this.hasChanges) {
      this._reloadDataTable()
    }
  }

  _reloadDataTable() {
    if (typeof window.reloadAllDataTables === 'function') {
      window.reloadAllDataTables()
    } else {
      window.dispatchEvent(new CustomEvent('datatable:reload'))
    }
  }

  async _updateListe() {
    this.listeTarget.innerHTML = 'Chargement...'
    fetch(this.urlListeValue)
      .then((response) => response.text())
      .then((html) => {
        this.listeTarget.innerHTML = html
      })
  }

  async delete (event) {
    event.preventDefault()
    const { url, csrf } = event.params

    if (!confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')) {
      return
    }

    try {
      const response = await fetch(url, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          csrf,
        }),
      })

      const data = await response.json()
        if (data.redirectModal) {
            if (data.message) {
                callOut(data.message, 'info')
            }
            this._loadModalStream(data.redirectModal)
            return
        }

      if (response.ok && data.success) {
        this.hasChanges = true
        callOut(data.success || 'Suppression effectuée', 'success')
        this._updateListe()
        this._reloadDataTable()
      } else {
        callOut(data.error || 'Erreur lors de la suppression', 'danger')
      }
    } catch {
      callOut('Erreur lors de la suppression', 'danger')
    }
  }

  addProfil(event) {
    event.preventDefault()

    const centreType = document.getElementById('typeCentre')?.value
    const selectListe = document.getElementById('selectListe')
    const centreId = selectListe ? selectListe.value : ''

    if (!centreType || !centreId) {
      callOut('Veuillez sélectionner un profil et un centre', 'error')
      return
    }

    this._updateCentre(centreType, centreId, false)
  }

    _loadModalStream(url) {
        const bodyFrame = document.getElementById('modal_body')
        const loaderTemplate = document.getElementById('global-loader-stimulus')
        if (loaderTemplate && bodyFrame) {
            bodyFrame.innerHTML = ''
            bodyFrame.appendChild(loaderTemplate.content.cloneNode(true))
        }

        fetch(url, {
            headers: {
                'Accept': 'text/vnd.turbo-stream.html, text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`)
                }
                const html = await res.text()
                renderStreamMessage(html)
            })
            .catch(() => {
                callOut('Erreur lors du chargement de la demande de changement', 'danger')
            })
    }

  _updateCentre(centreType, centreId, force = false) {
    fetch(this.urlAddValue, {
      method: 'POST',
      body: JSON.stringify({
        centreType,
        centreId,
        force,
      }),
    })
      .then((response) => response.json())
      .then((json) => {
          if (json.redirectModal) {
              if (json.message) {
                  callOut(json.message, 'info')
              }
              this._loadModalStream(json.redirectModal)
              return
          }

        if (json.success) {
          this.hasChanges = true
          callOut('Centre ajouté', 'success')
          this._updateListe()
          this._reloadDataTable()

          const typeCentre = document.getElementById('typeCentre')
          if (typeCentre?.tomselect) {
            typeCentre.tomselect.clear()
          } else if (typeCentre) {
            typeCentre.value = ''
          }
          this.configProfilTarget.innerHTML = ''
        } else if (json.error === 'already_exist') {
          if (confirm('Le centre est déjà associé à un autre utilisateur, voulez-vous le remplacer ?')) {
            this._updateCentre(centreType, centreId, true)
          }
        } else {
            callOut(json.error || 'Une erreur est survenue lors de l\'enregistrement', 'danger')
        }
      })
        .catch(() => {
            callOut('Une erreur inattendue est survenue lors de l\'enregistrement', 'danger')
      })
  }

  async changeProfil(event) {
    // récupère le type de profil et affiche la liste pour les choix complémentaires dans la target
    const profilId = event.target.value
    const body = {
      method: 'POST',
      body: JSON.stringify({
        profilId,
      }),
    }
    this.configProfilTarget.innerHTML = window.da.loaderStimulus
    const response = await fetch(this.urlConfigProfilValue, body)
    this.configProfilTarget.innerHTML = await response.text()
  }
}
