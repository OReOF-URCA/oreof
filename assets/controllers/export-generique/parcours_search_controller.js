import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    
    static targets = [
        'displayDataParcours',
        'displayDataFicheMatiere',
        'displayDataExportTemplate',
        'selectedParcours',
        'searchForm',
        'searchInput',
        'searchResult',
        'emptySearchListButton',
        'selectAllParcoursButton',
        'loadingIcon',
        'needParcoursSelect',
        'needDataSelect'
    ];

    static values = {
        searchUrl: String,
        downloadParcoursPdfUrl: String,
        downloadParcoursXlsxUrl: String,
        downloadFicheMatiereXslxUrl: String,
        downloadFicheMatierePdfUrl: String,
        campagneCollecte: Number
    };

    _minLength = 4;

    _selectedParcours = {};

    _selectedFields = {};

    _typeExport = "";

    _templateTypeExport = {};

    _withFieldSorting = true;

    _isRequestPending = false;

    _withDefaultHeader = true;

    _predefinedTemplate = false;

    connect(){
        this.searchFormTarget.addEventListener('submit', async (event)=> {
            event.preventDefault();
            await this.loadResultList();
        })

        // Création des 'click' pour les données des parcours
        document.querySelectorAll('.textDivFieldChoice')
            .forEach(choice => this.createListenerForParcoursFieldChoice(choice));


        // Création des 'click' pour les données des fiches matières
        document.querySelectorAll('.fmTextDivFieldChoice')
            .forEach(choiceFm => this.createListenerForFicheMatiereFieldChoice(choiceFm));

        // Création des 'click' sur le choix de template
        document.querySelectorAll('.textDivExportTemplateData')
            .forEach(choiceTemplate => this.createListenerForExportTemplateFieldChoice(choiceTemplate));
    }

    async fetchResults(searchText){
        let url = `${this.searchUrlValue}?inputText=${searchText}&campagneCollecte=${this.campagneCollecteValue}`;
        return await fetch(url)
            .then(response => response.json())
            .then(jsonData => jsonData)
            .catch(error => console.log(error));
    }

    async sendExportRequest(event){
        let isParcoursSelected = Object.keys(this._selectedParcours).length > 0;
        let isDataSelected = Object.keys(this._selectedFields).length > 0;

        if(isParcoursSelected && isDataSelected){
            // Champs souhaités
            let parcoursIdPostName = 'id[]=';
            let fieldPostName = 'val[]=';

            let parcoursIdArray = [];
            // Construction des parcours voulus
            if(this._selectedParcours['all'] === true){
                parcoursIdArray.push("all");
            }
            else {
                for(const p in this._selectedParcours){
                    parcoursIdArray.push(p);
                }
            }
            // Construction des champs souhaités
            let fieldValueArray = [];
            for(const f in this._selectedFields){
                fieldValueArray.push(f);
            }

            let postParcours =  parcoursIdPostName + parcoursIdArray.join('&' + parcoursIdPostName);
            let postFields = fieldPostName + fieldValueArray.join('&' + fieldPostName)

            let type = event.params.type;

            let url = "#";
            let targetNewTab;

            let availableFormatForExport = this.checkCanExportData();

            let typeExportPdfUrl = this._typeExport === 'parcours'
                || (this._typeExport === 'template' && this._templateTypeExport.type === 'parcours')
                ? this.downloadParcoursPdfUrlValue  
                : this.downloadFicheMatierePdfUrlValue;

            let typeExportXlsxUrl = this._typeExport === 'parcours' 
                || (this._typeExport === 'template' && this._templateTypeExport.type === 'parcours')
                ? this.downloadParcoursXlsxUrlValue
                : this.downloadFicheMatiereXslxUrlValue;

            if(type === 'pdf'){
                url = typeExportPdfUrl + '?'
                    + postParcours + '&' + postFields
                    + '&campagne=' + this.campagneCollecteValue;
                targetNewTab = '_blank';
            }
            else if (type === 'xlsx'){
                url = typeExportXlsxUrl + '?'
                    + postParcours + '&' + postFields
                    + '&campagne=' + this.campagneCollecteValue;
                targetNewTab = '_blank';
            }

            if(this._predefinedTemplate){
                url = type === 'xlsx' ? typeExportXlsxUrl : typeExportPdfUrl;
                url += '?' + postParcours
                    + '&campagne=' + this.campagneCollecteValue
                    + `&predefinedTemplate=${this._predefinedTemplate}`
                    + '&templateName=' + this._templateTypeExport.name;
            }

            url += `&withFieldSorting=${this._withFieldSorting ? 'true' : 'false'}`;
            url += `&withHeader=${this._withDefaultHeader ? 'true' : 'false'}`
            this.needDataSelectTarget.classList.add('d-none');
            this.needParcoursSelectTarget.classList.add('d-none');

            if(parcoursIdArray[0] === 'all' || parcoursIdArray.length > 50){
                targetNewTab = "_self";
            }

            if(availableFormatForExport[type] === true){
                if(this._isRequestPending === false){
                    this._isRequestPending = true;
                    let resultWindow = window.open(url, targetNewTab);
                    resultWindow.onload = (event) => this._isRequestPending = false;
                }
            }
        }
        else {
            if(!isParcoursSelected){
                this.needParcoursSelectTarget.classList.remove('d-none');
            }
            else {
                this.needParcoursSelectTarget.classList.add('d-none');
            }
            if(!isDataSelected){
                this.needDataSelectTarget.classList.remove('d-none');
            }
            else {
                this.needDataSelectTarget.classList.add('d-none');
            }
        }
        
    }

    async loadResultList(){
        if(this.searchInputTarget.value.length >= this._minLength){    
            this.emptyResultList();
            this.loadingIconTarget.classList.remove('d-none');
            let searchResult = await this.fetchResults(this.searchInputTarget.value);
            this.createResultList(searchResult);
            this.loadingIconTarget.classList.add('d-none');
            this.emptySearchListButtonTarget.classList.remove('d-none');
        } else {

        }
    }

    createResultList(resultArray){
        resultArray.forEach(result => {
            this.searchResultTarget.appendChild(
                this.createSearchResultNode(result.libelle, result.id, result.typeParcours, result.valueType ?? 'none')
            );
        });
    }

    createSearchResultNode(resultName, resultId, typeParcours, valueType){
        let row = document.createElement('div');
        row.classList.add('row', 'my-1');
        let textDiv = document.createElement('div');
        textDiv.classList.add('resultSearchNode');
        textDiv.dataset.parcoursId = resultId;
        let infoLibelle = this.displayTypeParcoursLibelle(typeParcours);
        if(infoLibelle.length > 0){
            infoLibelle = ' - ' + infoLibelle;
        }
        textDiv.dataset.parcoursLibelle = resultName + infoLibelle;
        textDiv.textContent = resultName + infoLibelle;
        textDiv.dataset.valueType = valueType;
        this.createClickListenerForListParcoursItem(textDiv);
        row.appendChild(textDiv);

        return row;
    }

    emptyResultList(){
        while(this.searchResultTarget.hasChildNodes()){
            this.searchResultTarget.removeChild(this.searchResultTarget.firstChild);
        }
    }

    emptyResultListEvent(){
        this.emptyResultList();
        this.emptySearchListButtonTarget.classList.add('d-none');
        if(this._selectedParcours['all'] !== true){
            this.selectAllParcoursButtonTarget.classList.remove('d-none');
        }
    }

    selectAllParcours(){
        this.emptyResultList();
        let row = document.createElement('div');
        row.classList.add('row', 'mt-3', 'allParcoursDiv');
        let textDiv = document.createElement('div');
        textDiv.classList.add('text-center', 'text-primary');
        textDiv.textContent = "Tous les parcours ont été sélectionnés";
        row.appendChild(textDiv);
        this.selectAllParcoursButtonTarget.classList.add('d-none');
        this.selectedParcoursTarget.appendChild(row);

        document.querySelectorAll('.badgeSelectedDiv').forEach(div => div.remove());
        this.needParcoursSelectTarget.classList.add('d-none');
        this._selectedParcours = {'all': true};
    }

    createClickListenerForListParcoursItem(htmlNode){
        htmlNode.addEventListener('click', (event) => {
            let parcoursToAdd = [event.target.dataset.parcoursId];
            if(event.target.dataset.valueType === 'array'){
                parcoursToAdd = [...event.target.dataset.parcoursId.split(',')];
            }

            // Vérification que le badge n'existe pas déjà
            let alreadyExists = false;
            for(const key in this._selectedParcours){
                if(this._selectedParcours[key] === event.target.dataset.parcoursLibelle){
                    alreadyExists = true;
                }
            }

            parcoursToAdd.forEach(id => {
                if(this._selectedParcours[`${id}`] === undefined){
                    if(this._selectedParcours['all'] !== undefined){
                        delete this._selectedParcours['all'];
                    }
                }
                this._selectedParcours[`${id}`] = event.target.dataset.parcoursLibelle;
            });

            if(alreadyExists === false){
                this.selectedParcoursTarget.appendChild(
                    this.createSelectedItemBadge(
                        event.target.dataset.parcoursLibelle,
                        parcoursToAdd
                    )
                );
            }

            this.selectedParcoursTarget.querySelector('.allParcoursDiv')?.remove();
            this.displayNeedParcoursSelected();
        })
    }

    createSelectedItemBadge(name, id){
        let badgeDiv = document.createElement('div');
        badgeDiv.classList.add('col-auto', 'my-2', 'badgeSelectedDiv');
        let textDiv = document.createElement('span');
        textDiv.classList.add('badge', 'rounded-pill', 'fw-bold', 'text-white', 'bg-primary');
        textDiv.textContent = name;
        textDiv.dataset.parcoursId = id;
        textDiv.dataset.parcoursLibelle = name;
        let crossIcon = document.createElement('i');
        crossIcon.classList.add('fa-regular', 'fa-xmark', 'text-white', 'ms-3');
        crossIcon.addEventListener('click', e => {
            this.removeSelectedItemBadge(id)
            this.displayNeedParcoursSelected();  
        });
        textDiv.appendChild(crossIcon);
        badgeDiv.appendChild(textDiv);

        return badgeDiv;
    }

    removeSelectedItemBadge(id){
        if(id.length >= 2){
            document.querySelectorAll('.badgeSelectedDiv')
                .forEach(badge => {
                    if(badge.querySelector('.badge.rounded-pill').dataset.parcoursId === `${id.join(',')}`){
                        badge.remove();
                        id.forEach(id => delete this._selectedParcours[`${id}`]);
                    }
                });
        }
        else if(this._selectedParcours[`${id}`] !== undefined) {
            document.querySelectorAll('.badgeSelectedDiv')
                .forEach(badgeDiv => {
                    if(badgeDiv.querySelector('.badge.rounded-pill').dataset.parcoursId === `${id}`){
                        badgeDiv.remove();
                        delete this._selectedParcours[`${id}`];
                    }
                });
        }

    }

    createListenerForParcoursFieldChoice(node){
        node.addEventListener('click', (event) => {
            let badgeClassList = ['bg-info', 'text-white'];
            // Données du parcours
            if(this._typeExport === 'fiche_matiere'){
                this._selectedFields = {};
                this.deselectAllFicheMatiereButton();
            }
            else if (this._typeExport === 'template'){
                this._selectedFields = {};
                this._templateTypeExport = {};
                this._withFieldSorting = true;
                this.deselectAllTemplateExportButton();
            }
            this._typeExport = 'parcours';
            this._predefinedTemplate = false;
            this._withDefaultHeader = true;
            // Sélection
            if(this._selectedFields[event.target.dataset.exportField] === undefined){
                node.classList.add(...badgeClassList);
                this._selectedFields[event.target.dataset.exportField] = true;
            }
            // Déselection
            else if (this._selectedFields[event.target.dataset.exportField] !== undefined){
                node.classList.remove(...badgeClassList);
                delete this._selectedFields[event.target.dataset.exportField];
            }

            this.displayNeedDataSelected();
        });
    }

    createListenerForFicheMatiereFieldChoice(node){
        node.addEventListener('click', (event) => {
            let badgeClassList = ['bg-info', 'text-white'];
            // Données des fiches matières
            if(this._typeExport === 'parcours'){
                this._selectedFields = {};
                this.deselectAllParcoursButton();
            }
            else if (this._typeExport === 'template'){
                this._selectedFields = {};
                this._templateTypeExport = {};
                this._withFieldSorting = true;
                this.deselectAllTemplateExportButton();
            }
            this._typeExport = 'fiche_matiere';
            this._predefinedTemplate = false;
            this._withDefaultHeader = true;
            // Sélection
            if(this._selectedFields[event.target.dataset.exportFmField] === undefined){
                node.classList.add(...badgeClassList);
                this._selectedFields[event.target.dataset.exportFmField] = true;
            }
            // Désélection
            else if (this._selectedFields[event.target.dataset.exportFmField] !== undefined) {
                node.classList.remove(...badgeClassList);
                delete this._selectedFields[event.target.dataset.exportFmField];
            }

            this.displayNeedDataSelected();
        });
    }

    createListenerForExportTemplateFieldChoice(node){
        node.addEventListener('click', event => {
            let badgeClassList = ['bg-info', 'text-white'];
            // Données des parcours
            if(this._typeExport === 'parcours'){
                this._selectedFields = {};
                this.deselectAllParcoursButton();
            }
            else if (this._typeExport === 'fiche_matiere'){
                this._selectedFields = {};
                this.deselectAllFicheMatiereButton()
            }
            this._typeExport = 'template';
            // Sélection
            if(this._templateTypeExport.name !== event.target.dataset.exportTemplate){
                this.deselectAllTemplateExportButton();
                node.classList.add(...badgeClassList);
                this.setPredefinedTemplate(event.target.dataset.exportTemplate);
            }
            // Déselection
            else if (this._templateTypeExport.name === event.target.dataset.exportTemplate){
                node.classList.remove(...badgeClassList);
                this._selectedFields = {};
                this._templateTypeExport = {};
                this._withFieldSorting = true;
            }

            this.displayNeedDataSelected();
        });
    }

    displayTypeParcoursLibelle(type){
        let libelleType = { 
            las1: 'Accès santé',
            las23: 'Accès santé',
            cpi: 'CPI',
            alternance: 'En alternance'
        };

        if(type === 'classique' || type === null){
            return "";
        }
        else {
            return libelleType[type];
        }
    }

    displayNeedDataSelected() {
        // Affiche un message si aucune donnée sélectionnée
        if(Object.keys(this._selectedFields).length > 0){
            this.needDataSelectTarget.classList.add('d-none');
        } else {
            this.needDataSelectTarget.classList.remove('d-none');
        }
    }

    displayNeedParcoursSelected() {
        // Affiche un message si aucun parcours sélectionné
        if(Object.keys(this._selectedParcours).length > 0){
            this.needParcoursSelectTarget.classList.add('d-none');
        }else {
            this.needParcoursSelectTarget.classList.remove('d-none');
        } 
    }

    deselectAllParcoursButton() {
        document.querySelectorAll('.textDivFieldChoice')
            .forEach(e => e.classList.remove(...['bg-info', 'text-white']));
    }

    deselectAllFicheMatiereButton() {
        document.querySelectorAll('.fmTextDivFieldChoice')
            .forEach(e => e.classList.remove(...['bg-info', 'text-white']));
    }

    deselectAllTemplateExportButton(){
        document.querySelectorAll('.textDivExportTemplateData')
            .forEach(e => e.classList.remove(...['bg-info', 'text-white']));
    }

    displayFicheMatiereChoices() {
        this.hideAllFieldChoices('#ficheMatiereSelectData');
        this.deselectAllFieldButton();
    }

    displayParcoursChoices() {
        this.hideAllFieldChoices('#parcoursSelectData');
        this.deselectAllFieldButton();
    }

    displayExportTemplateChoices(){
        this.hideAllFieldChoices('#exportTemplateData');
        this.deselectAllFieldButton();
    }

    hideAllFieldChoices(selectedChoice){
        ['#parcoursSelectData', '#ficheMatiereSelectData', '#exportTemplateData']
            .forEach(selector => {
                if(selector === selectedChoice){
                    document.querySelector(selector).classList.toggle('d-none');
                }
                else {
                    document.querySelector(selector).classList.add('d-none');
                }
            });
    }

    deselectAllFieldButton(){
        [
            this.displayDataParcoursTarget, 
            this.displayDataFicheMatiereTarget, 
            this.displayDataExportTemplateTarget
        ].forEach(button => {
            button.classList.remove('btn-success');
        })
    }

    setPredefinedTemplate(templateName){
        switch (templateName){
            case 'templateSEIP':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateSEIP';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = true;
                this._selectedFields = {
                    modalitesEnseignement: true,
                    stageInfos: true,
                    projetInfos: true,
                    memoireInfos: true,
                };
                break;
            case 'templateFicheParcours':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateFicheParcours';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = true;
                this._selectedFields = {
                    identiteFormation: true,
                    respFormation: true,
                    objectifsFormation: true,
                    contenuParcours: true,
                    objectifsParcours: true,
                    resultatsAttendusParcours: true,
                    rythmeFormation: true,
                    modalitesEnseignement: true,
                    localisationParcours: true,
                    competencesAcquises: true,
                    admissionParcours: true,
                    informationsInscription: true,
                    poursuiteEtudes: true,
                    debouchesParcours: true,
                    codesRome: true,
                    contactsPedagogiques: true
                };
                break;
            case 'templateSemestresOuverts':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateSemestresOuverts';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    composantePorteuse: true,
                    typeDiplome: true,
                    nomFormation: true,
                    nomParcours: true,
                    respFormation: true,
                    respParcours: true,
                    etatDpeParcours: true,
                    semestresOuverts: true,
                    idFormation: true,
                    idParcours: true
                };
                break;
            case 'templateExportRegime':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateExportRegime';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    composantePorteuse: true,
                    typeDiplome: true,
                    nomFormation: true,
                    nomParcours: true,
                    villeParcours: true,
                    respFormation: true,
                    respParcours: true,
                    codeRNCP: true,
                    dateValidationCFVU: true
                }
                break;
            case 'templateResponsables':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateResponsables';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    composantePorteuse: true,
                    typeDiplome: true,
                    nomFormation: true,
                    nomParcours: true,
                    respFormation: true,
                    respParcours: true
                };
                break;
            case 'templateExportCapApogee':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateExportCapApogee';
                this._predefinedTemplate = true;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    none: true
                };
                break;
            case 'templateExportListeFicheMatiere':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'fiche_matiere';
                this._templateTypeExport.name = 'templateExportListeFicheMatiere';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    fmId: true,
                    fmLibelle: true,
                    fmReferent: true,
                    fmIsComplet: true,
                    fmNbUtilisee: true,
                    fmParcoursPorteur: true,
                    fmFormation: true
                };
                break;
            case 'templateExportCARIF':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateExportCARIF';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    composantePorteuse: true,
                    typeDiplome: true,
                    nomFormation: true,
                    nomParcours: true,
                    objectifsFormation: true,
                    contenuFormation: true,
                    respFormation: true,
                    objectifsParcours: true,
                    contenuParcours: true,
                    respParcours: true,
                    modalitesEnseignement: true,
                    niveauEntree: true,
                    niveauSortie: true,
                    prerequisRecommandes: true,
                    villeParcours: true
                };
                break;
            case 'templateExportCFVU':
                this._typeExport = 'template';
                this._templateTypeExport.type = 'parcours';
                this._templateTypeExport.name = 'templateExportCFVU';
                this._predefinedTemplate = false;
                this._withFieldSorting = false;
                this._withDefaultHeader = false;
                this._selectedFields = {
                    composantePorteuse: true,
                    typeDiplome: true,
                    nomFormation: true,
                    nomParcours: true,
                    villeParcours: true,
                    respFormation: true,
                    respParcours: true,
                    dateConseil: true,
                    formationHasPV: true
                };
                break;
        }
    }

    checkCanExportData() {
        if(this._templateTypeExport.name === 'templateExportCapApogee'){
            return {pdf: false, xlsx: true}
        }

        return {pdf: true, xlsx: true};
    }
}