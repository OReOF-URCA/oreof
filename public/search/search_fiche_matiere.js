document.addEventListener('DOMContentLoaded', async (e) => {
  let currentPage = 1

  const dataObject = document.querySelector('#dataFicheMatiereSearch')

  const totalNumber = Number(dataObject.getAttribute('data-nb-fiches-total'))
  const totalPageNumber = Math.max(1, Math.ceil(totalNumber / 30))
  const keyword = dataObject.getAttribute('data-keyword')
  const fetchUrl = dataObject.getAttribute('data-fetch-url')
  const parcoursViewUrl = dataObject.getAttribute('data-parcours-view-url')
  const ficheMatiereViewUrl = dataObject.getAttribute('data-fiche-matiere-view-url')

  const buttonPageRight = document.querySelector('.js-search-page-right')
  const buttonPageLeft = document.querySelector('.js-search-page-left')
  const buttonGoToPage = document.querySelector('.js-search-go-page')

  const inputNumeroPage = document.querySelector('input[name=\'inputNumeroPage\']')

  if (totalNumber > 0) {
    // Affichage du résultat pour la page 1
    await displayResult(fetchUrl, currentPage, keyword, parcoursViewUrl, ficheMatiereViewUrl, totalPageNumber)
    if (buttonPageLeft && buttonPageRight && buttonGoToPage) {
      /**
       * Navigation vers la page souhaitée
       */
      buttonPageLeft.addEventListener('click', async (e) => {
        if (currentPage > 1) {
          currentPage -= 1
          await displayResult(fetchUrl, currentPage, keyword, parcoursViewUrl, ficheMatiereViewUrl, totalPageNumber)
        }
      })

      buttonPageRight.addEventListener('click', async (e) => {
        if (currentPage < totalPageNumber) {
          currentPage += 1
          await displayResult(fetchUrl, currentPage, keyword, parcoursViewUrl, ficheMatiereViewUrl, totalPageNumber)
        }
      })

      buttonGoToPage.addEventListener('click', async (e) => {
        let value = Number(inputNumeroPage.value)
        if (Number.isInteger(value) === false || value < 1) {
          value = 1
        }
        if (value > totalPageNumber) {
          value = totalPageNumber
        }
        currentPage = value
        inputNumeroPage.value = value
        await displayResult(fetchUrl, currentPage, keyword, parcoursViewUrl, ficheMatiereViewUrl, totalPageNumber)
      })
      /** ********************************** */
    }
  }
})

async function displayResult(fetchUrl, pageNumber, keyword, parcoursViewUrl, ficheMatiereViewUrl, totalPageNumber) {
  emptyResultList()
  displayLoadingIcon()
  const url = configureFetchUrl(fetchUrl, pageNumber, keyword)
  const result = await fetchResultPage(url)
  if (result) {
    hideLoadingIcon()
    updateDomWithResult(result, parcoursViewUrl, ficheMatiereViewUrl, keyword)
    updatePageLabel(pageNumber, totalPageNumber)
  } else {
    hideLoadingIcon()
    displayEmptyState('Un problème est survenu lors du chargement des résultats.')
  }
}

function updatePageLabel(pageNumber, totalPageNumber) {
  const label = document.querySelector('.numero-page')
  if (label) {
    label.textContent = `Page ${pageNumber} / ${totalPageNumber}`
  }
}

async function fetchResultPage(url) {
  return await fetch(url)
    .then((response) => response.json())
    .catch((error) => console.error(error))
}

function configureFetchUrl(baseUrl, pageNumber, keyword) {
  let url = baseUrl.replace(/1234567890/, pageNumber)
  url = url.replace('%C2%B5%23+', encodeURIComponent(keyword))

  return url
}

function updateDomWithResult(jsonResult, parcoursViewUrl, ficheMatiereViewUrl, keyword) {
  const rootNode = document.querySelector('.rootNodeForFicheMatiereList')

  let isParcoursParDefaut = (libelle) => libelle === 'Parcours par défaut'

  if (jsonResult.length === 0) {
    displayEmptyState('Aucun résultat trouvé pour cette page.')
    return
  }

  jsonResult.forEach((fiche) => {
    const row = document.createElement('article')
    row.classList.add('rounded-2xl', 'border', 'border-secondary-200', 'bg-surface', 'p-5', 'shadow-sm', 'transition', 'hover:border-primary-300', 'hover:shadow-md', 'dark:border-secondary-700', 'dark:bg-secondary-950/40')

    const ficheMatiereTitle = document.createElement('div')
    ficheMatiereTitle.classList.add('min-w-0', 'space-y-3')

    const ficheMatiereTitleDiv = document.createElement('div')
    ficheMatiereTitleDiv.classList.add('space-y-2')

    const libelleFicheDiv = document.createElement('div')
    libelleFicheDiv.classList.add('text-sm', 'font-medium', 'text-secondary-600', 'dark:text-secondary-400')
    libelleFicheDiv.textContent = 'Fiche matière'

    const ficheMatiereLibelle = document.createElement('a')
    ficheMatiereLibelle.textContent = fiche.fiche_matiere_libelle
    ficheMatiereLibelle.target = '_blank'
    ficheMatiereLibelle.rel = 'noreferrer'
    ficheMatiereLibelle.classList.add('block', 'text-lg', 'font-semibold', 'text-primary-700', 'transition', 'hover:text-primary-800', 'hover:underline', 'dark:text-primary-300', 'dark:hover:text-primary-200')
    ficheMatiereLibelle.setAttribute('href', ficheMatiereViewUrl.replace('%C2%B5%25%24%C2%A3', fiche.fiche_matiere_slug))
    ficheMatiereTitleDiv.appendChild(libelleFicheDiv)
    ficheMatiereTitleDiv.appendChild(ficheMatiereLibelle)

    const ficheMatierePillDiv = document.createElement('div')
    ficheMatierePillDiv.classList.add('flex', 'flex-wrap', 'gap-2')

    ficheMatiereTitle.appendChild(ficheMatiereTitleDiv)
    ficheMatiereTitle.appendChild(ficheMatierePillDiv)

    if (isStringContainingText(fiche.fiche_matiere_objectifs, keyword)) {
      const objectifsPill = displayBadge('Objectifs', 'warning')
      ficheMatierePillDiv.appendChild(objectifsPill)
    }
    if (isStringContainingText(fiche.fiche_matiere_description, keyword)) {
      const descriptionPill = displayBadge('Description', 'info')
      ficheMatierePillDiv.appendChild(descriptionPill)
    }

    const parcoursTitle = document.createElement('div')
    parcoursTitle.classList.add('min-w-0', 'space-y-3')

    const parcoursLibelle = document.createElement('a')
    parcoursLibelle.textContent = [
      fiche.type_diplome_libelle ? `${fiche.type_diplome_libelle} -` : null,
      `${fiche.mention_libelle} -`,
      isParcoursParDefaut(fiche.parcours_libelle) ? fiche.parcours_libelle : `Parcours ${fiche.parcours_libelle}`,
    ].filter(Boolean).join(' ')

    parcoursLibelle.target = '_blank'
    parcoursLibelle.rel = 'noreferrer'
    parcoursLibelle.classList.add('block', 'text-lg', 'font-semibold', 'text-primary-700', 'transition', 'hover:text-primary-800', 'hover:underline', 'dark:text-primary-300', 'dark:hover:text-primary-200')
    parcoursLibelle.setAttribute('href', parcoursViewUrl.replace('%C2%B5%25%24%C2%A3', fiche.parcours_id))

    const parcoursMeta = document.createElement('p')
    parcoursMeta.classList.add('text-sm', 'text-secondary-500', 'dark:text-secondary-400')
    parcoursMeta.textContent = fiche.parcours_sigle ? `Sigle : ${fiche.parcours_sigle}` : 'Sigle : -'

    parcoursTitle.appendChild(parcoursLibelle)
    parcoursTitle.appendChild(parcoursMeta)

    const wrapper = document.createElement('div')
    wrapper.classList.add('flex', 'flex-col', 'gap-4', 'lg:flex-row', 'lg:items-start', 'lg:justify-between')

    wrapper.appendChild(parcoursTitle)
    wrapper.appendChild(ficheMatiereTitle)

    row.appendChild(wrapper)

    rootNode.appendChild(row)
  })
}

function displayLoadingIcon() {
  hideLoadingIcon()
  const loadingIcon = document.createElement('div')
  loadingIcon.className = 'search-spinner inline-flex h-12 w-12 animate-spin rounded-full border-4 border-secondary-200 border-t-primary-600'
  const rootNode = document.querySelector('.loading-icon')
  rootNode.innerHTML = ''
  rootNode.appendChild(loadingIcon)
}

function hideLoadingIcon() {
  if (document.querySelector('.search-spinner')) {
    document.querySelector('.search-spinner').remove()
  }
}

function emptyResultList() {
  const rootNode = document.querySelector('.rootNodeForFicheMatiereList')
  while (rootNode.hasChildNodes()) {
    rootNode.removeChild(rootNode.firstChild)
  }
}

function displayEmptyState (message) {
  const rootNode = document.querySelector('.rootNodeForFicheMatiereList')
  const emptyState = document.createElement('div')
  emptyState.className = 'rounded-2xl border border-dashed border-secondary-300 bg-secondary-50 p-8 text-center text-secondary-600 dark:border-secondary-700 dark:bg-secondary-950/20 dark:text-secondary-400'

  const title = document.createElement('p')
  title.className = 'text-lg font-semibold text-text dark:text-secondary-100'
  title.textContent = 'Aucun résultat'

  const detail = document.createElement('p')
  detail.className = 'mt-2 text-sm'
  detail.textContent = message

  emptyState.appendChild(title)
  emptyState.appendChild(detail)

  rootNode.appendChild(emptyState)
}

function isStringContainingText(string, needle) {
  if (string) {
    return string.toUpperCase().includes(
      needle.toUpperCase(),
    )
  }

  return false
}

function displayBadge(text, color) {
  const pill = document.createElement('span')
  const colorClass = {
    warning: 'border border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-700 dark:bg-warning-900/30 dark:text-warning-300',
    info: 'border border-info-300 bg-info-50 text-info-700 dark:border-info-700 dark:bg-info-900/30 dark:text-info-300',
  }[color] || 'border border-secondary-300 bg-secondary-100 text-secondary-700 dark:border-secondary-600 dark:bg-secondary-800 dark:text-secondary-300'
  pill.className = `inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${colorClass}`
  pill.textContent = text

  return pill
}
