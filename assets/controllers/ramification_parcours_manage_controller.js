import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    #no_result_found_text = "Aucun résultat trouvé pour cette recherche";

    #search_not_long_enough_text = "La recherche doit faire au moins 4 caractères";

    #added_parcours = [];

    static values = {
        searchUrl: String
    };

    static targets = [
        'resultList', 'searchInput', 'loadingSpinner', 
        'searchErrorArea', 'choiceArea'
    ];

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
                        this.resultListTarget.appendChild(
                            this._createResultNode(p)
                        );
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

    _createResultNode(p) {
        let node = document.createElement('div');
        node.classList.add('col-12', 'search-result-node', 'p-2');
        node.textContent = this._decodeResultName(p);
        node.dataset.idParcours = p.id_parcours;
        node.dataset.nomParcours = this._decodeResultName(p);

        this._onResultClick(node);

        return node;
    }

    _decodeResultName(resultJson) {
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

    _onResultClick(resultNode) {
        resultNode.addEventListener('click', event => {
            if(this._isParcoursPresentInData(resultNode.dataset.idParcours) === false) {
                let badge = this._createChoiceBadge(
                    resultNode.dataset.nomParcours, 
                    resultNode.dataset.idParcours
                );
                this.choiceAreaTarget.appendChild(badge);
                this._addParcoursData(resultNode.dataset.idParcours, resultNode.dataset.nomParcours);
            }
        });

    }

    _createChoiceBadge(name, id) {
        let badgeWrapper = document.createElement('div');
        badgeWrapper.classList.add('row', 'justify-content-center', 'choice-badge');

        let badgeTxt = document.createElement('div');
        badgeTxt.classList.add('choice-badge-txt');
        badgeTxt.innerText = name;

        let badgeCrossIcon = document.createElement('div');
        badgeCrossIcon.classList.add('d-flex', 'col-1', 'align-items-center', 'justify-content-center', 'choice-badge-icon');
        let crossIcon = document.createElement('i');
        crossIcon.classList.add('fa-light', 'fa-xmark', 'mx-3', 'fa-xl', 'remove-choice-cross');
        badgeCrossIcon.appendChild(crossIcon);

        badgeWrapper.dataset.idParcours = id;
        badgeWrapper.dataset.nomParcours = name;

        crossIcon.addEventListener('click', e => {
            if(this._isParcoursPresentInData(id)) {
                this._removeParcoursData(id);
            }
            badgeWrapper.remove()
        });

        badgeWrapper.appendChild(badgeTxt);
        badgeWrapper.appendChild(badgeCrossIcon);

        return badgeWrapper;
    }

    _addParcoursData(id, fullName) {
        this.#added_parcours.push({id: Number(id), fullName: fullName});
    }

    _removeParcoursData(searchId) {
        let foundIndex = undefined;
        this.#added_parcours.forEach((elt, idx) => {
            if(Number(searchId) === elt.id){
                foundIndex = idx;
            }
        });

        this.#added_parcours.splice(foundIndex, 1);
    }

    _isParcoursPresentInData(searchId) {
        return this.#added_parcours
            .map(p => p.id)
            .filter(id => id === Number(searchId)).length > 0;
    }
}