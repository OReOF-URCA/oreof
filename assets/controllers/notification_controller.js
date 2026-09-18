/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/notification_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 21/05/2023 14:08
 */

import { Controller } from '@hotwired/stimulus'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    url: String,
  }

  static targets = ['liste']

  async lu(event) {
    const button = event.currentTarget
    const params = new URLSearchParams({ id: event.params.id })

    try {
      const response = await fetch(`${this.urlValue}?${params.toString()}`)
      if (response.ok) {
        this._markItemAsRead(button)
        this._updateBadge()
      }
    } catch (e) {
      console.error('Erreur lors du marquage comme lu', e)
    }
  }

  async toutLu(event) {
    const url = event.params?.url || event.currentTarget.dataset.notificationUrlParam || event.currentTarget.dataset.notificationUrlValue
    if (!url) {
      return
    }

    try {
      const response = await fetch(url)
      if (response.ok) {
        callOut('Toutes les notifications ont été marquées comme lues', 'success')
        const items = this.hasListeTarget
          ? this.listeTarget.querySelectorAll('.non-lu')
          : this.element.querySelectorAll('.non-lu')

        items.forEach((item) => this._markItemAsRead(item))
        this._removeBadge()
      } else {
        callOut('Erreur lors de la mise à jour des notifications', 'error')
      }
    } catch (e) {
      console.error('Erreur lors du tout lu', e)
      callOut('Erreur lors de la mise à jour', 'error')
    }
  }

  async toutSupprimer(event) {
    if (!confirm('Voulez-vous vraiment supprimer toutes les notifications ?')) {
      return
    }

    const url = event.params?.url || event.currentTarget.dataset.notificationUrlParam || event.currentTarget.dataset.notificationUrlValue
    if (!url) {
      return
    }

    try {
      const response = await fetch(url)
      if (response.ok) {
        callOut('Notifications supprimées', 'success')
        this._removeBadge()

        const container = this.hasListeTarget ? this.listeTarget : this.element.querySelector('.space-y-3')
        if (container) {
          const items = container.querySelectorAll('button')
          items.forEach((btn) => {
            if (!btn.textContent.includes('Validation obligatoire')) {
              btn.remove()
            }
          })

          if (container.querySelectorAll('button').length === 0) {
            container.innerHTML = `
              <div class="rounded-xl border border-secondary-200 bg-secondary-50 p-4 text-center text-sm text-secondary-600 dark:border-secondary-700 dark:bg-secondary-800 dark:text-secondary-300">
                Aucune notification.
              </div>
            `
          }
        }
      } else {
        callOut('Erreur lors de la suppression', 'error')
      }
    } catch (e) {
      console.error('Erreur lors de la suppression', e)
      callOut('Erreur lors de la suppression', 'error')
    }
  }

  _markItemAsRead(item) {
    item.classList.remove('non-lu')

    // Conteneur de l'icône
    const iconContainer = item.querySelector('.shrink-0')
    if (iconContainer) {
      iconContainer.classList.remove('border-warning-200', 'bg-warning-100', 'text-warning-700', 'border-warning')
      iconContainer.classList.add('border-emerald-200', 'bg-emerald-100', 'text-emerald-700')
    }

    // Icône fontawesome
    const icon = item.querySelector('i')
    if (icon) {
      icon.classList.remove('fa-exclamation')
      icon.classList.add('fa-check')
    }
  }

  _updateBadge() {
    const remainingUnread = this.element.querySelectorAll('.non-lu').length
    if (remainingUnread === 0) {
      this._removeBadge()
    }
  }

  _removeBadge() {
    const badge = document.getElementById('notification-badge') || document.getElementById('indicNotif')
    if (badge) {
      badge.remove()
    }
  }
}
