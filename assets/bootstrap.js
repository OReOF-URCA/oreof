import { startStimulusApp } from '@symfony/reprise/stimulus'

// Reprise registers controllers from controllers.json and assets/controllers/.
export const app = startStimulusApp()

window.Stimulus = app
