/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/historique_controller.js
 * @author davidannebicque
 * @project oreof
 */

import { Controller } from '@hotwired/stimulus'
import { renderStreamMessage } from '@hotwired/turbo'

export default class extends Controller {
  static targets = ['entreeHistorique']

  async delete(event) {
    event.preventDefault()
    event.stopPropagation()

    const confirmMessage =
      event.params?.confirm ||
      event.currentTarget?.dataset?.historiqueConfirmParam ||
      'Voulez-vous vraiment supprimer cet enregistrement de l\'historique ?'

    if (!confirm(confirmMessage)) {
      return
    }

    const url =
      event.params?.url ||
      event.currentTarget?.dataset?.historiqueUrlParam ||
      event.currentTarget?.getAttribute('href')

    const csrf =
      event.params?.csrf ||
      event.currentTarget?.dataset?.historiqueCsrfParam ||
      event.currentTarget?.dataset?.csrf

    if (!url) {
      console.error('URL de suppression manquante')
      return
    }

    const body = new FormData()
    if (csrf) {
      body.append('_token', csrf)
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'text/vnd.turbo-stream.html, application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body
      })

      if (response.ok) {
        const contentType = response.headers.get('Content-Type') || ''
        if (contentType.includes('text/vnd.turbo-stream.html')) {
          const text = await response.text()
          renderStreamMessage(text)
        } else {
          if (this.hasEntreeHistoriqueTarget) {
            this.entreeHistoriqueTarget.remove()
          } else {
            this.element.remove()
          }
        }
      } else {
        alert('Erreur lors de la suppression de l\'enregistrement.')
      }
    } catch (error) {
      console.error('Erreur lors de la suppression de l\'historique:', error)
      alert('Erreur de connexion lors de la suppression.')
    }
  }
}
