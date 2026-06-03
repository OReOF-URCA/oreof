import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['row', 'submitBtn', 'status', 'targetSelect', 'followingSwitch']

    connect() {
        this.#updateStatus()
    }

    toggle(event) {
        const row = event.target.closest('.field-row')
        if (row) {
            row.classList.toggle('is-selected', event.target.checked)
        }
        this.#updateStatus()
    }

    toggleFollowing(event) {
        // Diffuser à toutes les années plus récentes désactive le choix d'une cible unique
        if (this.hasTargetSelectTarget) {
            this.targetSelectTarget.disabled = event.target.checked
            this.targetSelectTarget.closest('.card-body')?.classList.toggle('target-card-disabled', event.target.checked)
        }
    }

    selectAll() {
        this.#setAll(true)
    }

    selectNone() {
        this.#setAll(false)
    }

    changeSource(event) {
        this.#navigate({ source: event.target.value })
    }

    changeTarget(event) {
        // Changer de cible réinitialise la source (recalculée par le serveur)
        this.#navigate({ target: event.target.value, source: null })
    }

    confirmSubmit(event) {
        const count = this.#checkedCount()
        if (count === 0) {
            event.preventDefault()
            return
        }
        if (!confirm(`Récupérer ${count} champ(s) depuis la version source ?`)) {
            event.preventDefault()
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    #navigate(params) {
        const url = new URL(window.location.href)
        for (const [key, value] of Object.entries(params)) {
            if (value === null) {
                url.searchParams.delete(key)
            } else {
                url.searchParams.set(key, value)
            }
        }
        window.location.href = url.toString()
    }

    #setAll(checked) {
        this.element.querySelectorAll('input[type="checkbox"][name^="fields["]').forEach((cb) => {
            cb.checked = checked
            cb.closest('.field-row')?.classList.toggle('is-selected', checked)
        })
        this.#updateStatus()
    }

    #checkedCount() {
        return this.element.querySelectorAll('input[type="checkbox"][name^="fields["]:checked').length
    }

    #updateStatus() {
        const count = this.#checkedCount()
        this.statusTargets.forEach((el) => {
            el.textContent = count > 0 ? `${count} champ(s) à récupérer` : 'Aucun champ sélectionné'
        })
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = count === 0
        }
    }
}
