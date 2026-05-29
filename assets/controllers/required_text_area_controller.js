import { Controller } from "@hotwired/stimulus";

export default class extends Controller{
    static values = {
        minlength: Number
    }

    static targets = ["displayDiv", "textarea", "invalidFieldText"];

    showDisplay() {
        this.displayDivTarget.classList.toggle('d-none');
        this.textareaTarget.required = !this.textareaTarget.required;
    }
    
    checkTextAreaLength() {
        if(this.textareaTarget.value.length > this.minlengthValue){
            this.invalidFieldTextTarget.classList.add('d-none');
        }
        if(this.textareaTarget.value.length < this.minlengthValue){
            this.invalidFieldTextTarget.classList.remove('d-none');
        }
    }
}