import { add, Controller } from "@hotwired/stimulus";

export default class extends Controller {

    #errorSearchText = {
        min4Char: 'La recherche doit faire au moins 4 caractères',
        noResultsFound: 'Aucun résultat trouvé pour cette recherche'
    };

    #stepCount = 0;

    #maxStepCount = 6;

    static targets = [
        'searchParcours', 'searchErrorArea', 'loadingSpinner', 
        'resultList', 'stepLinkRow'
    ];
    
    static values = {
        searchUrl: String,
        typesRamificationsJson: String
    };

    connect(){
        if(this.#stepCount > 0){
            this.stepLinkRowTarget.appendChild(this.#createStepButton('add-step-before'));
        }
        this.stepLinkRowTarget.appendChild(this.#createStepButton('add-step-after'));
    }

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

    #createStepButton(selectorId) {
        let div = document.createElement('div');
        div.classList.add('col-2', selectorId);

        let addStepButton = document.createElement('span');
        addStepButton.classList.add('badge', 'rounded-pill', 'text-bg-info', 'p-2', 'addLinkStepButton');
        let addIcon = document.createElement('i');
        addIcon.classList.add('fa-sharp-duotone', 'fa-thin', 'fa-circle-plus', 'mx-2', 'fa-xl');
        addStepButton.textContent = 'Ajouter un niveau';
        addStepButton.appendChild(addIcon);
        div.appendChild(addStepButton);

        this.#onStepButtonClick(addStepButton);

        return div;
    }

    #onStepButtonClick(button) {
        button.addEventListener('click', e => {
            if(this.#stepCount < this.#maxStepCount) {
                let stepColumn = this.#addStepColumn(this.#stepCount + 1);
                ++this.#stepCount;
                
                this.stepLinkRowTarget.appendChild(stepColumn);
                this.stepLinkRowTarget.appendChild(document.querySelector('.add-step-after'));
            }
            if (this.#stepCount === this.#maxStepCount){
                ['.add-step-after'].forEach(s => {
                    document.querySelector(s).classList.add('d-none');
                });
            }
        });
    }

    #addStepColumn(columnNumber) {
        let col = document.createElement('div');
        col.classList.add('col-2');
        let selectTypeRamification = document.createElement('select');
        selectTypeRamification.classList.add('form-select');
        [{id: "", libelle: "Choisir..."}, ...this.#getListeTypesRamifications()].forEach(typeR =>{
            let opt = document.createElement('option');
            opt.textContent = typeR['libelle'];
            opt.value = typeR['id'];
            selectTypeRamification.appendChild(opt);
        });

        let titleWrapper = document.createElement('div');
        titleWrapper.classList.add('text-center', 'mb-3');
        let stepTitle = document.createElement('span');
        stepTitle.classList.add('badge', 'rounded-pill', 'text-bg-dark');
        stepTitle.textContent = `Niveau ${columnNumber}`;
        titleWrapper.appendChild(stepTitle);


        col.appendChild(titleWrapper);
        col.appendChild(selectTypeRamification);

        return col;
    }

    #getListeTypesRamifications() {
        return JSON.parse(this.typesRamificationsJsonValue);
    }
}