import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['trigger', 'container']
  static values = { expectedValues: Array }

  connect () {
    this.toggle()
  }

  toggle () {
    const expected = this.expectedValuesValue.map(String)
    const visible = this.triggerTargets.some(input =>
      (input.type === 'checkbox' || input.type === 'radio') &&
      input.checked &&
      expected.includes(input.value)
    )

    // Tailwind V2 does not provide Bootstrap's d-none utility.
    // Keep both the semantic hidden property and the Tailwind class in sync.
    this.containerTargets.forEach(container => {
      container.hidden = !visible
      container.classList.toggle('hidden', !visible)
    })
  }
}
