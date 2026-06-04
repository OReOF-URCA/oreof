import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        templateName: String,
        textAreaFormName: String,
        minlength: Number,
        hasJustification: String,
        justificationText: String,
    }

    static targets = ["displayDiv", "textarea", "invalidFieldText"];

    /**
     * Démarré lors de l'instanciation du contrôleur
     */
    connect(){
        this.justificationTextValue = this.justificationTextValue ?? "";
        this.displayDivTarget.innerHTML = this.getFullDisplay(this.templateNameValue);
    }

    /**
     * Vérifie que le texte saisi dans la <textarea> possède
     * bien une longueur supérieure à la taille minimum : minlength
     * Si la taille est inférieure, affiche le contenu de : invalidFieldText
     */
    checkTextAreaLength() {
        if(this.textareaTarget.value.length >= this.minlengthValue){
            this.invalidFieldTextTarget.classList.add('d-none');
        }
        if(this.textareaTarget.value.length < this.minlengthValue){
            this.invalidFieldTextTarget.classList.remove('d-none');
        }
    }

    /**
     * Retourne le template HTML que l'on souhaite afficher.
     * On peut choisir le nom du template en paramètre,
     * pour pouvoir adapter l'affichage selon l'usage
     */
    getFullDisplay(templateName){
        let fullDisplay = "";
        switch(templateName){
            case "justificationSaisieMccc":
                fullDisplay = `<div class="col-12">
                                    <textarea class="form-control my-2" 
                                        id="${this.textAreaFormNameValue}" 
                                        name="${this.textAreaFormNameValue}"
                                        data-mccc-with-justification-target="textarea"
                                        data-action="input->mccc-with-justification#checkTextAreaLength"
                                        ${this.hasJustificationValue === 'true' ? 'required' : ''}
                                        data-minlength="${this.minlengthValue}"
                                        rows="4"
                                        placeholder="Argumentaire pour ce type d'épreuve"
                                    >${this.justificationTextValue}</textarea>
                                    <div class="text-danger small my-2 
                                        ${this.justificationTextValue.length >= this.minlengthValue ? "d-none" : ""}"
                                        data-mccc-with-justification-target="invalidFieldText"
                                    >
                                        L'argumentaire doit faire au moins ${this.minlengthValue} caractères
                                    </div>
                                </div>`;
                break;
        }

        return fullDisplay;
    }

    updateJustification(event){
        this.hasJustificationValue = event.target[event.target.selectedIndex].dataset.hasJustification;
        if(this.hasJustificationValue === "false"){
            this.displayDivTarget.classList.add('d-none');
            this.textareaTarget.required = false;
        }
        else if (this.hasJustificationValue === "true") {
            this.displayDivTarget.classList.remove('d-none');
            this.textareaTarget.required = true;
        }
    }
}