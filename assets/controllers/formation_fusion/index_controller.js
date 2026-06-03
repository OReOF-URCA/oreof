import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['selectA', 'selectB', 'submitBtn']

    #allFormations = []
    #isUpdating = false

    connect() {
        this.#allFormations = Array.from(this.selectATarget.querySelectorAll('option')).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            typeDiplome: opt.getAttribute('data-type-diplome') || '',
        }))
        this.#validate()
    }

    changeA() {
        if (this.#isUpdating) return
        this.#isUpdating = true
        this.#rebuildSelect(this.selectBTarget, this.#getTypeDiplome(this.selectATarget), this.selectATarget.value)
        this.#validate()
        this.#isUpdating = false
    }

    changeB() {
        if (this.#isUpdating) return
        this.#isUpdating = true
        this.#rebuildSelect(this.selectATarget, this.#getTypeDiplome(this.selectBTarget), this.selectBTarget.value)
        this.#validate()
        this.#isUpdating = false
    }

    submit(event) {
        if (!this.#validate()) event.preventDefault()
    }

    // ── Private ───────────────────────────────────────────────────────────────

    #getTypeDiplome(select) {
        const opt = Array.from(select.options).find(o => o.value === select.value)
        return opt ? (opt.getAttribute('data-type-diplome') || '') : ''
    }

    #rebuildSelect(select, filterTypeDiplome, excludeValue) {
        const ts = select.tomselect
        const previousValue = ts ? ts.getValue() : select.value

        const filtered = this.#allFormations.filter(f =>
            (!filterTypeDiplome || !f.typeDiplome || f.typeDiplome === filterTypeDiplome) &&
            f.value !== excludeValue
        )

        select.innerHTML = ''
        filtered.forEach(f => {
            const opt = document.createElement('option')
            opt.value = f.value
            opt.textContent = f.text
            opt.setAttribute('data-type-diplome', f.typeDiplome)
            select.appendChild(opt)
        })

        if (ts) {
            ts.sync()
            ts.setValue(filtered.some(f => f.value === previousValue) ? previousValue : '')
        }
    }

    #validate() {
        const a = this.selectATarget.value
        const b = this.selectBTarget.value
        const invalid = !a || !b || a === b
        this.submitBtnTarget.disabled = invalid
        return !invalid
    }
}
