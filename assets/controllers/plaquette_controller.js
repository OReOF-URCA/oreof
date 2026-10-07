/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/centre_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/02/2023 08:50
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    urlRubriques: String,
    urlSauvegarde: String,
  }

  static targets = ['listeRubriques']

  connect() {
    this._updateListe()
  }

  async _updateListe() {
    this.listeRubriquesTarget.innerHTML = 'Chargement...'
    fetch(this.urlRubriquesValue)
      .then((response) => response.text())
      .then((html) => {
        this.listeRubriquesTarget.innerHTML = html
      })
  }

  // up et down pour gérer l'ordre des rubriques
  async up(event) {
    const id = event.params.rubrique
    fetch(`${this.urlSauvegardeValue}?rubrique=${id}&action=up`, {
      method: 'POST',
    })
      .then((response) => {
        // si OK on recharge la liste
        if (response.ok) {
          return this._updateListe()
        }
        callOut('Erreur lors de la sauvegarde', 'danger')
        return false
      })
  }

  async down(event) {
    const id = event.params.rubrique
    fetch(`${this.urlSauvegardeValue}?rubrique=${id}&action=down`, {
      method: 'POST',
    })
      .then((response) => {
        // si OK on recharge la liste
        if (response.ok) {
          return this._updateListe()
        }
        callOut('Erreur lors de la sauvegarde', 'danger')
        return false
      })
  }

  // bouton pour afficher ou masquer une rubrique
  async hide(event) {
    const id = event.params.rubrique
    fetch(`${this.urlSauvegardeValue}?rubrique=${id}&action=hide`, {
      method: 'POST',
    })
      .then((response) => {
        // si OK on recharge la liste
        if (response.ok) {
          return this._updateListe()
        }
        callOut('Erreur lors de la sauvegarde', 'danger')
        return false
      })
  }

  async reset() {
    fetch(`${this.urlSauvegardeValue}?rubrique=all&action=reset`, {
      method: 'POST',
    })
      .then((response) => {
        // si OK on recharge la liste
        if (response.ok) {
          return this._updateListe()
        }
        callOut('Erreur lors de la sauvegarde', 'danger')
        return false
      })
  }

  async show(event) {
    const id = event.params.rubrique
    const { place } = event.params
    fetch(`${this.urlSauvegardeValue}?rubrique=${id}&action=show&place=${place}`, {
      method: 'POST',
    })
      .then((response) => {
        // si OK on recharge la liste
        if (response.ok) {
          return this._updateListe()
        }
        callOut('Erreur lors de la sauvegarde', 'danger')
        return false
      })
  }
}
