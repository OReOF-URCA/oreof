document.addEventListener('DOMContentLoaded', (e) => {
  const ficheMatiereUrl = document.querySelector('#rootListElement').getAttribute('data-fiche-matiere-url');

  const links = document.querySelectorAll('[data-bs-toggle="modal"][data-fetch-url]')

  links.forEach((link) => {
    link.addEventListener('click', (event) => {
      const { currentTarget } = event
      const fetchUrl = currentTarget.getAttribute('data-fetch-url')
      const modalTitleElement = document.querySelector('#titre-modale-recherche');

      modalTitleElement.textContent = ''

      const listNode = document.querySelector('#associated-fiche-matiere-modal-body')
      // Empty node list
      while (listNode.hasChildNodes()) {
        listNode.removeChild(listNode.firstChild);
      }

      // loading icon
      const loadingIcon = document.createElement('div')
      loadingIcon.className = 'search-spinner inline-flex h-12 w-12 animate-spin rounded-full border-4 border-secondary-200 border-t-primary-600'
      listNode.appendChild(loadingIcon);

      // fetching result
      fetch(fetchUrl)
        .then((response) => response.json())
        .then((jsonArray) => {
          loadingIcon.remove();

          const fichesNumber = Number(currentTarget.getAttribute('data-number-associated-fiches-matieres'))
          if (fichesNumber >= 2) {
            modalTitleElement.textContent = `${fichesNumber} fiches matières associées`
          } else if (fichesNumber === 1) {
            modalTitleElement.textContent = '1 fiche matière associée'
          } else {
            modalTitleElement.textContent = 'Aucune fiche matière associée'
          }

          if (jsonArray.length === 0) {
            const emptyState = document.createElement('p')
            emptyState.className = 'text-sm text-secondary-600 dark:text-secondary-400'
            emptyState.textContent = 'Aucune fiche matière associée trouvée.'
            listNode.appendChild(emptyState)
            return
          }

          const listParent = document.createElement('ul');
          jsonArray.forEach((ficheMatiere) => {
            const listElement = document.createElement('li');
            listElement.className = 'my-2 text-left'

            const link = document.createElement('a');
            link.textContent = ficheMatiere.libelle
            link.href = ficheMatiereUrl.replace('%C2%B5%23+', ficheMatiere.slug);
            link.target = '_blank';
            link.rel = 'noreferrer'
            link.className = 'text-primary-700 hover:underline dark:text-primary-300 dark:hover:text-primary-200'

            listElement.appendChild(link);
            listParent.appendChild(listElement);
          });
          listNode.appendChild(listParent);
        })
      // handling AJAX error
        .catch((error) => {
          loadingIcon.remove();
          const textErrorModal = document.createElement('h3')
          textErrorModal.textContent = 'Un problème est survenu lors de la récupération des données.'
          textErrorModal.className = 'text-center text-primary-700 dark:text-primary-300'
          listNode.appendChild(textErrorModal);
        });
    });
  })
});
