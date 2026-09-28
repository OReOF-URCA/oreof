import { add, Controller } from "@hotwired/stimulus";

export default class extends Controller {

    #errorSearchText = {
        min4Char: 'La recherche doit faire au moins 4 caractères',
        noResultsFound: 'Aucun résultat trouvé pour cette recherche'
    };

    #stepCount = 0;

    #maxStepCount = 6;

    #selectedColumnIndex = undefined;

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
                    while(this.resultListTarget.firstChild) {
                        this.resultListTarget.removeChild(this.resultListTarget.firstChild);
                    }
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
        node.dataset.nomParcoursShort = this.#decodeResultNameShort(p);
        this.#onResultNodeClick(node);

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
        col.classList.add('col-2', 'step-column', 'px-0');
        let choiceDiv = document.createElement('div');
        choiceDiv.classList.add('col-12', 'step-column-info');
        let linksDiv = document.createElement('div');
        linksDiv.classList.add('col-12', 'step-column-links', 'p-1');
        linksDiv.dataset.stepIndex = columnNumber;

        let selectTypeRamification = document.createElement('select');
        selectTypeRamification.classList.add('form-select');
        [{id: "", libelle: "Choisir..."}, ...this.#getListeTypesRamifications()].forEach(typeR =>{
            let opt = document.createElement('option');
            opt.textContent = typeR['libelle'];
            opt.value = typeR['id'];
            selectTypeRamification.appendChild(opt);
        });
        this.#onSelectTypeRamificationChange(selectTypeRamification, columnNumber);

        let infoWrapper = document.createElement('div');
        infoWrapper.classList.add('text-center', 'p-1');
        let stepTitle = document.createElement('span');
        stepTitle.classList.add('badge', 'rounded-pill', 'text-bg-dark');
        stepTitle.textContent = `Niveau ${columnNumber}`;
        let titlePill = document.createElement('span');
        titlePill.dataset.columnIndex = columnNumber;
        titlePill.textContent = '';
        let libellePill = document.createElement('p');
        let libelleNiveau = document.createElement('p');
        libellePill.classList.add('text-center', 'p-1', 'my-1', 'border', 'rounded', 'bg-primary', 'text-white', 'd-none');
        libelleNiveau.classList.add('text-center', 'p-0', 'my-1');

        libellePill.appendChild(titlePill);
        libelleNiveau.appendChild(stepTitle);
        infoWrapper.appendChild(selectTypeRamification);
        infoWrapper.appendChild(libelleNiveau);
        infoWrapper.appendChild(libellePill);
        choiceDiv.appendChild(infoWrapper);

        col.appendChild(choiceDiv);
        col.appendChild(linksDiv);
        this.#onColumnClick(linksDiv);

        return col;
    }

    #getListeTypesRamifications() {
        return JSON.parse(this.typesRamificationsJsonValue);
    }

    #onColumnClick(colDiv) {
        colDiv.addEventListener('click', e => {
            if(this.#selectedColumnIndex !== undefined){
                document.querySelector(`.step-column-links[data-step-index="${this.#selectedColumnIndex}"]`)
                    .classList.remove('step-column-selected');
            }
            this.#selectedColumnIndex = colDiv.dataset.stepIndex;
            colDiv.classList.add('step-column-selected');
        });
    }

    #createParcoursNodeForStep(p) {
        let node = document.createElement('div');
        node.classList.add('col-12', 'bg-primary', 'text-white', 'rounded', 'my-4', 'text-center', 'py-2');
        node.dataset.bsToggle = 'tooltip';
        node.dataset.bsPlacement = 'bottom';
        node.title = p.dataset.nomParcours;
        node.textContent = p.dataset.nomParcoursShort;

        return node;
    }

    #decodeResultNameShort(p) {
        let typeTxt = {
            'las123': 'LAS',
            'las23': 'LAS',
            'las1': 'LAS',
            'alternance': 'ALT',
            'cpi': 'CPI',
            'classique': ''
        };

        let typeParcoursTxt = "";
        if(typeof p.type_parcours === 'string' && p.type_parcours !== 'classique') {
            typeParcoursTxt = ' - ' + typeTxt[p.type_parcours];
        }

        return `${p.type_diplome_court} - ${p.nom_formation} - ${p.nom_parcours ?? ' - '}${typeParcoursTxt}`;
    }

    #onResultNodeClick(n) {
        n.addEventListener('click', e => {
            if(this.#selectedColumnIndex !== undefined) {
                document.querySelector('.step-column-selected').appendChild(
                    this.#createParcoursNodeForStep(n)
                );
            }
        });
    }

    #onSelectTypeRamificationChange(selectNode, columnNumber) {
        selectNode.addEventListener('change', e => {
            let pill = document.querySelector(`span[data-column-index="${columnNumber}"]`);
            if(pill) {
                if(e.target.options[e.target.selectedIndex].value === "") {
                    pill.textContent = "";
                    pill.parentNode.classList.add('d-none');
                }
                else {
                    pill.textContent = e.target.options[e.target.selectedIndex].textContent;
                    pill.parentNode.classList.remove('d-none');
                }
            }
        });
    }
}