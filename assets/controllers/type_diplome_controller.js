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

        const modeleMcc = document.querySelector('#type_diplome_ModeleMcc')
        if (modeleMcc) {
            if (!enabled) {
                modeleMcc.value = 'App\\TypeDiplome\\NonClassique\\NonClassiqueHandler'
                modeleMcc.disabled = true
                modeleMcc.closest('.mb-3, .form-group, div').classList.add('opacity-50')
            } else {
                modeleMcc.disabled = false
                modeleMcc.closest('.mb-3, .form-group, div').classList.remove('opacity-50')
            }
        }
    }
}