import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    #no_result_found_text = "Aucun résultat trouvé pour cette recherche";

    #search_not_long_enough_text = "La recherche doit faire au moins 4 caractères";

    static values = {
        searchUrl: String
    };

    static targets = ['resultList', 'searchInput', 'loadingSpinner', 'searchErrorArea'];

    connect(){}

    async onSearchInputChange() {
        // Au moins 4 caractères pour la recherche
        if(this.searchInputTarget.value.length < 4){
            this.resultListTarget.classList.add('d-none');
            this.searchErrorAreaTarget.textContent = this.#search_not_long_enough_text;
            this.searchErrorAreaTarget.classList.remove('d-none');
            return;
        }

        // Vidage du résultat actuel
        while(this.resultListTarget.firstChild){
            this.resultListTarget.removeChild(this.resultListTarget.firstChild);
        }

        this.searchErrorAreaTarget.classList.add('d-none');
        this.loadingSpinnerTarget.classList.remove('d-none');
        this.resultListTarget.classList.add('d-none');

        let fetchUrl = `${this.searchUrlValue}?keyword=${this.searchInputTarget.value}`;
        await fetch(fetchUrl)
            .then(response => response.json())
            .then(parcoursList => {
                this.loadingSpinnerTarget.classList.add('d-none');
                if(parcoursList.length > 0){
                    parcoursList.forEach(p => {
                        let name = `${p.nom_type_diplome ?? ''} - ${p.nom_formation ?? ''} - ${p.nom_parcours ?? ''}`;
                        this.resultListTarget.appendChild(this._createResultNode(name));
                    });
                    this.resultListTarget.classList.remove('d-none');
                }
                else {
                    this.searchErrorAreaTarget.textContent = this.#no_result_found_text;
                    this.searchErrorAreaTarget.classList.remove('d-none');
                }
            })
            .catch(error => console.log(error));
    }

    _createResultNode(fullName) {
        let node = document.createElement('div');
        node.classList.add('col-12', 'search-result-node', 'p-2');
        node.textContent = fullName;

        return node;
    }
}