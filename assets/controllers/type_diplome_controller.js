import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["classique", "hasEcts"]

    connect() {
        this.update()
    }

    // Conservés : référencés par les data-action des radios « classique » et « hasEcts ».
    toggle() {
        this.update()
    }

    toggleEcts() {
        this.update()
    }

    update() {
        // Accréditée = classique. Radio « Type de formation ».
        const classiqueChecked = this.classiqueTargets.find(radio => radio.checked)
        const accreditee = classiqueChecked ? classiqueChecked.value === 'accreditee' : false

        // « Utilise les ECTS » est un YesNoType (radios) : "Oui" => value "1".
        const ectsChecked = this.hasEctsTargets.find(radio => radio.checked)
        const usesEcts = ectsChecked ? ectsChecked.value === '1' : false

        // Un champ peut appartenir aux deux groupes (ex. « Nombre maximum d'EC par UE ») :
        // il n'est actif que si TOUTES ses conditions sont remplies (logique OR de désactivation).
        document.querySelectorAll('.semestre-field, .ects-field').forEach(row => {
            const needsAccreditee = row.classList.contains('semestre-field')
            const needsEcts = row.classList.contains('ects-field')
            const enabled = (!needsAccreditee || accreditee) && (!needsEcts || usesEcts)
            row.querySelectorAll('input, select').forEach(input => {
                input.disabled = !enabled
            })
            row.classList.toggle('opacity-50', !enabled)
        })

        // Champs YesNo sans objet : basculés visuellement sur « Non » quand la condition
        // correspondante est cochée (la valeur réelle est aussi forcée côté serveur).
        this.setNoWhenDisabled('ectsObligatoireSurEc', !usesEcts)
        this.setNoWhenDisabled('mcccObligatoireSurEc', !accreditee)

        // « Modèle de MCCC prédéfini » : forcé au handler non classique et verrouillé
        // pour une formation non accréditée.
        const modeleMcc = document.querySelector('#type_diplome_ModeleMcc')
        if (modeleMcc) {
            if (!accreditee) {
                modeleMcc.value = 'App\\TypeDiplome\\NonClassique\\NonClassiqueHandler'
                modeleMcc.disabled = true
                modeleMcc.closest('.mb-3, .form-group, div').classList.add('opacity-50')
            } else {
                modeleMcc.disabled = false
                modeleMcc.closest('.mb-3, .form-group, div').classList.remove('opacity-50')
            }
        }
    }

    // Coche « Non » (valeur '0') sur un champ YesNo lorsqu'il est sans objet (désactivé).
    // Purement visuel : le champ étant désactivé, c'est le serveur qui fige la valeur.
    setNoWhenDisabled(fieldName, disabled) {
        if (!disabled) {
            return
        }
        document
            .querySelectorAll(`input[name="type_diplome[${fieldName}]"]`)
            .forEach(radio => { radio.checked = radio.value === '0' })
    }
}
