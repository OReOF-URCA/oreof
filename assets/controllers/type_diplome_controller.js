import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["classique"]

    connect() {
        this.toggle()
    }

    toggle() {
        const enabled = this.classiqueTarget.checked
        document.querySelectorAll('.semestre-field').forEach(row => {
            row.querySelectorAll('input, select').forEach(input => {
                input.disabled = !enabled
            })
            row.classList.toggle('opacity-50', !enabled)
        })
    }
}