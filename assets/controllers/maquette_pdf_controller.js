import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ['selectedFileName', 'inputPdf', 'submitButton'];

    onSelectFile() {
        this.selectedFileNameTarget.textContent = `Fichier sélectionné : ${this.inputPdfTarget.files[0].name}`;
    }
}