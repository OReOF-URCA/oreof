import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ['searchInput'];

    static values = {divClass: String, textClass: String};

    connect(){
        this.searchInputTarget.value = "";
    }

    _onSearchCardInput() {
        document.querySelectorAll(`.${this.divClassValue}`).forEach(card => {
            card.classList.add('d-none');
            let hasText = card.querySelector(`.${this.textClassValue}`)
                .textContent.toUpperCase().includes(this.searchInputTarget.value.toUpperCase());

            if(hasText) {
                card.classList.remove('d-none');
            }
        });
    }
}