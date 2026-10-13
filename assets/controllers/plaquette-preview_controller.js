import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['select', 'iframe', 'downloadBtn', 'loader', 'placeholder']
  static values = {
    urlPreview: String,  // /communication/plaquette/apercu/__ID__
    urlDownload: String, // /communication/plaquette/formation/__SLUG__
  }

  connect() {
    this.updatePreview()
  }

  change() {
    this.updatePreview()
  }

  updatePreview() {
    if (!this.hasSelectTarget) return

    const option = this.selectTarget.selectedOptions[0]
    if (!option || !option.value) {
      if (this.hasPlaceholderTarget) this.placeholderTarget.classList.remove('hidden')
      if (this.hasIframeTarget) this.iframeTarget.classList.add('hidden')
      if (this.hasDownloadBtnTarget) this.downloadBtnTarget.classList.add('hidden')
      return
    }

    const id = option.value
    const slug = option.dataset.slug

    if (this.hasPlaceholderTarget) this.placeholderTarget.classList.add('hidden')
    if (this.hasIframeTarget) this.iframeTarget.classList.remove('hidden')
    if (this.hasLoaderTarget) this.loaderTarget.classList.remove('hidden')

    if (this.hasIframeTarget && this.hasUrlPreviewValue) {
      const src = this.urlPreviewValue.replace('__ID__', id)
      this.iframeTarget.src = src
    }

    if (this.hasDownloadBtnTarget && this.hasUrlDownloadValue && slug) {
      this.downloadBtnTarget.href = this.urlDownloadValue.replace('__SLUG__', slug)
      this.downloadBtnTarget.classList.remove('hidden')
    }
  }

  iframeLoaded() {
    if (this.hasLoaderTarget) {
      this.loaderTarget.classList.add('hidden')
    }
  }
}
