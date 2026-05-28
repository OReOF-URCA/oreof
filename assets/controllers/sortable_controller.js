/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/assets/controllers/sortable_controller.js
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/01/2026 08:44
 */

import { Controller } from '@hotwired/stimulus'
import Sortable from 'sortablejs'

export default class extends Controller {
  static values = {
    url: String,      // endpoint API
    type: String,     // "ue" | "ec"
    group: String     // pour EC: "ec"
  }

  connect () {
    this.snapshot = this.serialize() // pour undo local
    this.sortable = Sortable.create(this.element, {
      animation: 150,
      handle: '[data-sortable-handle]',
      draggable: '[data-sortable-item]',
      ghostClass: 'is-ghost',
      chosenClass: 'is-chosen',
      dragClass: 'is-dragging',
      group: this.hasGroupValue ? this.groupValue : undefined,
      onStart: () => { this.snapshot = this.serialize() },
      onEnd: (evt) => this.persist(evt)
    })
  }

  disconnect () {
    this.sortable?.destroy()
  }

  serialize () {
    return Array.from(this.element.querySelectorAll('[data-sortable-item]'))
      .map((el, index) => ({ id: el.dataset.id, position: index + 1 }))
  }

  async persist (evt) {
    const payload = {
      type: this.typeValue,
      items: this.serialize(),
      // utile quand on déplace un EC vers une autre UE:
      context: {
        fromContainerId: evt.from?.closest('[data-sortable-url-value]')?.dataset?.sortableUrlValue ?? null,
        toContainerId: evt.to?.closest('[data-sortable-url-value]')?.dataset?.sortableUrlValue ?? null
      },
      previousItems: this.snapshot // pour undo serveur
    }

    try {
      const res = await fetch(this.urlValue, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/vnd.turbo-stream.html'
        },
        body: JSON.stringify(payload)
      })

      if (!res.ok) throw new Error(`HTTP ${res.status}`)

      // Turbo-stream : affiche toast + bouton Annuler
      const stream = await res.text()
      Turbo.renderStreamMessage(stream)

      // update snapshot (nouvel état “valide”)
      this.snapshot = this.serialize()
    } catch (e) {
      // rollback en cas d’erreur
      this.rollback()
      Turbo.renderStreamMessage(`
        <turbo-stream action="append" target="flash_toasts">
          <template>
            <div class="rounded-lg bg-danger-50 px-4 py-3 text-sm font-semibold text-danger-800">
              ❌ Impossible d’enregistrer l’ordre. Modification annulée.
            </div>
          </template>
        </turbo-stream>
      `)
    }
  }

  rollback () {
    // remet le DOM dans l’ordre précédent (simple et efficace)
    const map = new Map()
    this.element.querySelectorAll('[data-sortable-item]').forEach(el => map.set(el.dataset.id, el))
    this.snapshot.forEach(({ id }) => {
      const el = map.get(id)
      if (el) this.element.appendChild(el)
    })
  }
}
