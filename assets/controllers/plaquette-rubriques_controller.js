import { Controller } from '@hotwired/stimulus'
import Sortable from 'sortablejs'
import callOut from '../js/callOut'

export default class extends Controller {
  static values = {
    urlReorder: String,
    urlRefresh: String,
  }

  static targets = ['listFormation', 'listParcours']

  connect() {
    if (this.hasListFormationTarget) {
      this.sortableFormation = Sortable.create(this.listFormationTarget, {
        animation: 150,
        handle: '[data-sortable-handle]',
        draggable: '[data-sortable-item]',
        ghostClass: 'opacity-40',
        chosenClass: 'bg-indigo-50',
        onEnd: () => this.persistOrder('formation', this.listFormationTarget),
      })
    }

    if (this.hasListParcoursTarget) {
      this.sortableParcours = Sortable.create(this.listParcoursTarget, {
        animation: 150,
        handle: '[data-sortable-handle]',
        draggable: '[data-sortable-item]',
        ghostClass: 'opacity-40',
        chosenClass: 'bg-cyan-50',
        onEnd: () => this.persistOrder('parcours', this.listParcoursTarget),
      })
    }
  }

  disconnect() {
    this.sortableFormation?.destroy()
    this.sortableParcours?.destroy()
  }

  async persistOrder(section, container) {
    const items = Array.from(container.querySelectorAll('[data-sortable-item]'))
      .map((el, index) => ({
        id: el.dataset.id,
        visible: el.dataset.visible === '1',
        position: index + 1,
      }))

    // Mettre à jour immédiatement les badges visuels
    let visibleCount = 1
    container.querySelectorAll('[data-sortable-item]').forEach((el) => {
      const badge = el.querySelector('[data-position-badge]')
      if (badge && el.dataset.visible === '1') {
        badge.textContent = visibleCount++
      }
    })

    try {
      const response = await fetch(this.urlReorderValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ section, items, action: 'reorder' }),
      })

      if (!response.ok) {
        callOut('Erreur lors de la sauvegarde de l’ordre des rubriques', 'danger')
      }
    } catch {
      callOut('Erreur réseau lors de la réorganisation', 'danger')
    }
  }

  async toggle(event) {
    const btn = event.currentTarget
    const { section, rubrique } = btn.dataset

    try {
      const response = await fetch(this.urlReorderValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ section, rubrique, action: 'toggle' }),
      })

      if (response.ok) {
        await this.reloadPartial()
      } else {
        callOut('Erreur lors du changement de visibilité', 'danger')
      }
    } catch {
      callOut('Erreur réseau', 'danger')
    }
  }

  async reset(event) {
    const btn = event.currentTarget
    const { section } = btn.dataset

    if (!confirm('Réinitialiser l’ordre de ces rubriques à l’état par défaut ?')) {
      return
    }

    try {
      const response = await fetch(this.urlReorderValue, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ section, action: 'reset' }),
      })

      if (response.ok) {
        await this.reloadPartial()
        callOut('Ordre réinitialisé avec succès', 'success')
      } else {
        callOut('Erreur lors de la réinitialisation', 'danger')
      }
    } catch {
      callOut('Erreur réseau', 'danger')
    }
  }

  async reloadPartial() {
    try {
      const response = await fetch(this.urlRefreshValue, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      if (response.ok) {
        const html = await response.text()
        this.element.outerHTML = html
      }
    } catch {
      window.location.reload()
    }
  }
}
