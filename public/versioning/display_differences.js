let idDiffLastVersion = "diffGlobalParcours";
let idDiffLastYear = "diffGlobalParcoursCampagne";

let currentDisplay = undefined;
let maquetteDifferences =  {
    'undefined': 'show_current',
    'diffGlobalParcours': 'show_versioning',
    'diffGlobalParcoursCampagne': 'show_versioning_campagne'
};

function registerShowDifferencesClick(buttonElement, idToShow, idToHide) {
    if(buttonElement){
        buttonElement.addEventListener('click', (event) => {
            event.preventDefault();
            if(currentDisplay !== idToShow){
                document.querySelectorAll(`#${idToShow}`)
                    .forEach(elt => {
                        elt.classList.remove('d-none');
                    });
                document.querySelectorAll(`#${idToHide}`)
                    .forEach(elt => {
                        elt.classList.add('d-none');
                    });
                currentDisplay = idToShow;
                toggleMaquetteDifferences(currentDisplay);
            }
            else {
                document.querySelectorAll(`#${idToShow}`)
                    .forEach(elt => {
                        elt.classList.add('d-none');
                    });
                currentDisplay = undefined;
                toggleMaquetteDifferences(currentDisplay);
            }
        });
    }
};

function registerShowCurrentDataClick(buttonElement) {
    buttonElement.addEventListener('click', e => {
        e.preventDefault();
        [idDiffLastVersion, idDiffLastYear].forEach(
            id => {
                document.querySelectorAll(`#${id}`).forEach(elt => { elt.classList.add('d-none')});
            }
        );
        toggleMaquetteDifferences('undefined');
        currentDisplay = undefined;
    })
}

function toggleMaquetteDifferences(elementToShow) {
    for(key in maquetteDifferences){
        if(`${elementToShow}` === key){
            document.querySelectorAll(`.${maquetteDifferences[key]}`)
                .forEach(elt => { elt.classList.remove('d-none'); });
        }
        else {
            document.querySelectorAll(`.${maquetteDifferences[key]}`)
                .forEach(elt => { elt.classList.add('d-none'); })
        }
    }
}

document.addEventListener('DOMContentLoaded', (e) => {
    let urlParam = (new URLSearchParams(document.location.search)).get('optionDisplay');
    if(urlParam !== null){
        currentDisplay = urlParam;
        toggleMaquetteDifferences(currentDisplay);
    }

    let buttonShowDiffLastYear = document.querySelector("#showDiffWithLastYear");
    let buttonShowDiffLastVersion = document.querySelector("#showDiffWithLastVersion");
    let buttonShowCurrentData = document.querySelector('#showCurrentData');
    registerShowDifferencesClick(buttonShowDiffLastVersion, idDiffLastVersion, idDiffLastYear);
    registerShowDifferencesClick(buttonShowDiffLastYear, idDiffLastYear, idDiffLastVersion);
    registerShowCurrentDataClick(buttonShowCurrentData);
})