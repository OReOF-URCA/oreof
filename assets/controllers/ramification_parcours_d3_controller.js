import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    #errorSearchText = {
        min4Char: 'La recherche doit faire au moins 4 caractères',
        noResultsFound: 'Aucun résultat trouvé pour cette recherche'
    };

    static targets = [
        'searchParcours', 'searchErrorArea', 'loadingSpinner', 'resultList'
    ];
    
    static values = {
        searchUrl: String
    };

    connect(){}

    async onSearchInputClick() {
        this.loadingSpinnerTarget.classList.add('d-none');
        this.searchErrorAreaTarget.classList.add('d-none');
        this.resultListTarget.classList.add('d-none');

        if(this.searchParcoursTarget.value.length < 4) {
            this.searchErrorAreaTarget.textContent = this.#errorSearchText.min4Char;
            this.searchErrorAreaTarget.classList.remove('d-none');
            return;
        }

        this.loadingSpinnerTarget.classList.remove('d-none');

        let urlFetchParcours = `${this.searchUrlValue}?keyword=${this.searchParcoursTarget.value}`;
        await fetch(urlFetchParcours)
            .then(response => response.json())
            .then(jsonParcoursArray => {
                if(jsonParcoursArray.length === 0) {
                    this.searchErrorAreaTarget.textContent = this.#errorSearchText.noResultsFound;
                    this.searchErrorAreaTarget.classList.remove('d-none');
                    this.loadingSpinnerTarget.classList.add('d-none');
                    return;
                }
                else {
                    jsonParcoursArray.forEach(p => {
                        this.resultListTarget.appendChild(
                            this.#createResultNode(p)
                        );
                    });

                    this.resultListTarget.classList.remove('d-none');
                    this.loadingSpinnerTarget.classList.add('d-none');
                }
            })
            .catch(error => console.log(error));
    }

    #createResultNode(p) {
        let node = document.createElement('div');
        node.classList.add('col-12', 'search-result-node', 'p-2');
        node.textContent = this.#decodeResultName(p);
        node.dataset.idParcours = p.id_parcours;
        node.dataset.nomParcours = this.#decodeResultName(p);

        return node;
    }

    #decodeResultName(resultJson) {
        let name = `${resultJson.nom_type_diplome ?? ''} - ${resultJson.nom_formation ?? ''} - ${resultJson.nom_parcours ?? ''}`;
        if(typeof resultJson.type_parcours === 'string') {
            let typeTxt = {
                'las1': " - LAS1",
                'las23': " - LAS2/LAS3",
                'las123': " - LAS 1/2/3",
                'cpi': ' - CPI',
                'alternance' : ' - En Alternance',
                'classique' : ''
            };

            name += typeTxt[`${resultJson.type_parcours}`];
        }

        return name;
    }
}