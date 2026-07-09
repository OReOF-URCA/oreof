import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static values = {
        searchUrl: String
    };

    static targets = ['resultList', 'searchInput', 'loadingSpinner'];

    connect(){}

    async onSearchInputChange() {
        // Au moins 4 caractères pour la recherche
        if(this.searchInputTarget.value.length < 4){
            return;
        }

        // Vidage du résultat actuel
        while(this.resultListTarget.firstChild){
            this.resultListTarget.removeChild(this.resultListTarget.firstChild);
        }

        this.loadingSpinnerTarget.classList.remove('d-none');
        this.resultListTarget.classList.add('d-none');

        let fetchUrl = `${this.searchUrlValue}?keyword=${this.searchInputTarget.value}`;
        await fetch(fetchUrl)
            .then(response => response.json())
            .then(parcoursList => {
                parcoursList.forEach(p => {
                    let name = `${p.nom_type_diplome ?? ''} - ${p.nom_formation ?? ''} - ${p.nom_parcours ?? ''}`;
                    this.resultListTarget.appendChild(this._createResultNode(name));
                });
                this.loadingSpinnerTarget.classList.add('d-none');
                this.resultListTarget.classList.remove('d-none');
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