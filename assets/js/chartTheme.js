/*
 * Thème des graphiques symfony/ux-chartjs : couleurs issues des tokens sémantiques (app.css).
 *
 * Opt-in : seuls les datasets qui déclarent `colorToken` (clé de MARK_CLASSES, ex. 'primary') sont colorés ici ; les axes et la grille
 * suivent alors le thème clair/sombre. Les couleurs sont recalculées quand le thème ou le thème de couleur change
 * (panneau accessibilité), sans recharger la page.
 */

const charts = new Set()
let probeContext = null

// Classes écrites en toutes lettres : Tailwind n'émet que les tokens qu'il trouve dans les sources (@source ../**/*.js).
// Clé = colorToken accepté dans un dataset. Les nuances 50–400 restent claires en sombre : pas différents par mode.
const MARK_CLASSES = {
  primary: { light: 'text-primary-500', lightHover: 'text-primary-700', dark: 'text-primary-400', darkHover: 'text-primary-200' },
}
const AXIS_CLASSES = {
  light: { grid: 'text-secondary-200', ticks: 'text-secondary-500' },
  dark: { grid: 'text-secondary-700', ticks: 'text-secondary-400' },
}

// Résout une classe de couleur (color-mix, oklab…) en rgb() lisible par Chart.js, via un pixel de canvas
const resolveColor = (className) => {
  const probe = document.createElement('span')
  probe.className = className
  probe.hidden = true
  document.body.appendChild(probe)
  const computed = window.getComputedStyle(probe).color
  probe.remove()

  probeContext ??= document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  probeContext.clearRect(0, 0, 1, 1)
  probeContext.fillStyle = '#000'
  probeContext.fillStyle = computed
  probeContext.fillRect(0, 0, 1, 1)
  const [r, g, b] = probeContext.getImageData(0, 0, 1, 1).data

  return `rgb(${r}, ${g}, ${b})`
}

const isThemed = (config) => (config.data?.datasets ?? []).some((dataset) => dataset.colorToken)

const applyTheme = (config) => {
  const mode = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
  const grid = resolveColor(AXIS_CLASSES[mode].grid)
  const ticks = resolveColor(AXIS_CLASSES[mode].ticks)

  for (const dataset of config.data.datasets) {
    const classes = MARK_CLASSES[dataset.colorToken]
    if (!classes) {
      continue
    }
    const color = resolveColor(classes[mode])
    const hover = resolveColor(classes[`${mode}Hover`])
    Object.assign(dataset, {
      backgroundColor: color,
      hoverBackgroundColor: hover,
      borderColor: color,
      hoverBorderColor: hover,
      pointBackgroundColor: color,
    })
  }

  for (const axis of Object.values(config.options?.scales ?? {})) {
    axis.grid = { ...(axis.grid ?? {}), color: grid }
    axis.border = { ...(axis.border ?? {}), color: grid }
    axis.ticks = { ...(axis.ticks ?? {}), color: ticks }
  }
}

document.addEventListener('chartjs:pre-connect', (event) => {
  const config = event.detail?.config
  if (config && isThemed(config)) {
    applyTheme(config)
  }
})

document.addEventListener('chartjs:connect', (event) => {
  const chart = event.detail?.chart
  if (chart && isThemed(chart.config)) {
    charts.add(chart)
  }
})

document.addEventListener('chartjs:disconnect', (event) => {
  charts.delete(event.detail?.chart)
})

new window.MutationObserver(() => {
  charts.forEach((chart) => {
    applyTheme(chart.config)
    chart.update()
  })
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-color-theme'] })
