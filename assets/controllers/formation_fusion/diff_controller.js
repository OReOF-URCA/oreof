import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['submitBtn', 'status']
    static values  = { differentKeys: Array }

    connect() {
        this.#updateStatus()
    }

    selectCard(event) {
        const label = event.currentTarget
        const radio = label.querySelector('.field-radio')
        if (!radio) return
        radio.checked = true
        this.element.querySelectorAll(`input[name="${radio.name}"]`).forEach(r => {
            r.closest('.field-choice').classList.toggle('selected', r === radio)
        })
        this.#updateStatus()
    }

    selectAll(event) {
        const side = event.currentTarget.dataset.side
        this.element.querySelectorAll(`.field-radio[value="${side}"]`).forEach(radio => {
            radio.checked = true
            this.element.querySelectorAll(`input[name="${radio.name}"]`).forEach(r => {
                r.closest('.field-choice').classList.toggle('selected', r === radio)
            })
        })
        this.#updateStatus()
    }

    confirmSubmit(event) {
        const unselected = this.differentKeysValue.filter(key =>
            !this.element.querySelector(`input[name="fields[${key}]"]:checked`)
        )
        if (unselected.length > 0) {
            event.preventDefault()
            alert(`Veuillez sélectionner une valeur pour tous les champs différents (${unselected.length} restant(s)).`)
            return
        }
        if (!confirm('Êtes-vous sûr de vouloir fusionner ces deux formations ? Cette action est irréversible.')) {
            event.preventDefault()
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    #updateStatus() {
        const total    = this.differentKeysValue.length
        const selected = this.differentKeysValue.filter(key =>
            this.element.querySelector(`input[name="fields[${key}]"]:checked`)
        ).length

        if (this.hasStatusTarget) {
            this.statusTarget.textContent = total > 0 ? `${selected} / ${total} champs sélectionnés` : ''
        }
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = selected < total
        }
    }
}
