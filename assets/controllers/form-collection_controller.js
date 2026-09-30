import { Controller } from '@hotwired/stimulus'
import Sortable from 'sortablejs'

export default class extends Controller {
  static targets = ['fields', 'field', 'addButton']

  static values = {
    prototype: String,
    maxItems: Number,
    itemsCount: Number,
    sortable: { type: Boolean, default: true }
  }

  connect () {
    this.index = this.fieldTargets.length
    this.itemsCountValue = this.fieldTargets.length

    if (this.sortableValue && this.hasFieldsTarget) {
      this.sortable = Sortable.create(this.fieldsTarget, {
        animation: 150,
        handle: '[data-sortable-handle]',
        draggable: '[data-form-collection-target="field"]',
        ghostClass: 'opacity-50',
        chosenClass: 'bg-primary-50',
        dragClass: 'shadow-lg',
        onEnd: () => this.updateIndexes()
      })
    }
    this.updateIndexes()
  }

  disconnect () {
    this.sortable?.destroy()
  }

  addItem (event) {
    event.preventDefault()
    if (this.maxItemsValue === 0 || this.itemsCountValue < this.maxItemsValue) {
      const prototype = JSON.parse(this.prototypeValue)
      const newField = prototype.replace(/__name__/g, this.index)
      this.fieldsTarget.insertAdjacentHTML('beforeend', newField)
      this.index++
      this.itemsCountValue++
      this.updateIndexes()
    }
  }

  removeItem (event) {
    event.preventDefault()
    this.fieldTargets.forEach((element) => {
      if (element.contains(event.target)) {
        element.remove()
        this.itemsCountValue--
        this.updateIndexes()
      }
    })
  }

  updateIndexes () {
    this.fieldTargets.forEach((element, i) => {
      const indexDisplay = element.querySelector('[data-collection-index]')
      if (indexDisplay) {
        indexDisplay.textContent = (i + 1).toString()
      }
      const orderInput = element.querySelector('[data-collection-order]')
      if (orderInput) {
        orderInput.value = (i + 1).toString()
      }
    })
  }

  updateTitle (event) {
    const field = event.target.closest('[data-form-collection-target="field"]')
    if (field) {
      const titleSpan = field.querySelector('[data-step-title]')
      if (titleSpan) {
        titleSpan.textContent = event.target.value.trim() || 'Nouvelle étape'
      }
    }
  }

  itemsCountValueChanged () {
    if (this.hasAddButtonTarget === false || this.maxItemsValue === 0) {
      return
    }
    const maxItemsReached = this.itemsCountValue >= this.maxItemsValue
    this.addButtonTarget.classList.toggle('hidden', maxItemsReached)
  }
}
