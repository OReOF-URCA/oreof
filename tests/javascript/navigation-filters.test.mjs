// node --test tests/javascript/navigation-filters.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
async function load(name) {
  const source = (await readFile(new URL(`../../assets/controllers/${name}_controller.js`, import.meta.url), 'utf8')).replace(/^import .*$/gm, '')
  return (await import(`data:text/javascript;base64,${Buffer.from(`class Controller {}\n${source}`).toString('base64')}`)).default
}
const Navbar = await load('navbar_dropdown')
const Filter = await load('filter_panel')
function element() {
  const attrs = {}, classes = new Set(['hidden'])
  return { id: 'test-panel', setAttribute: (key, value) => { attrs[key] = value }, getAttribute: key => attrs[key], contains(target) { return target === this }, focus() { document.activeElement = this }, classList: { contains: name => classes.has(name), toggle(name, force) { const add = force ?? !classes.has(name); if (add) classes.add(name); else classes.delete(name) } } }
}
globalThis.document = { activeElement: null }
function nav() {
  const c = new Navbar()
  c.contentTarget = element(); c.triggerTarget = element()
  c.hasContentTarget = c.hasTriggerTarget = true
  c.element = { contains: target => [c.contentTarget, c.triggerTarget].includes(target) }
  c.connect()
  return c
}
test('disclosure button toggles visibility and accessible expanded state', () => {
  const c = nav()
  assert.equal(c.triggerTarget.getAttribute('aria-controls'), 'test-panel')
  c.toggle()
  assert.equal(c.triggerTarget.getAttribute('aria-expanded'), 'true')
  assert.equal(c.contentTarget.classList.contains('hidden'), false)
  c.toggle()
  assert.equal(c.triggerTarget.getAttribute('aria-expanded'), 'false')
})
test('focusing trigger does not invert its first activation; links open the disclosure', () => {
  const c = nav()
  c.focusIn({ target: c.triggerTarget })
  assert.equal(c.contentTarget.classList.contains('hidden'), true)
  c.focusIn({ target: c.contentTarget })
  assert.equal(c.contentTarget.classList.contains('hidden'), false)
  c.focusOut({ relatedTarget: c.triggerTarget })
  assert.equal(c.contentTarget.classList.contains('hidden'), false)
  c.focusOut({ relatedTarget: null })
  assert.equal(c.contentTarget.classList.contains('hidden'), true)
})
test('Escape closes disclosure and returns focus to its button', () => {
  const c = nav(); c.open()
  let prevented = false
  c.keydown({ key: 'Escape', preventDefault() { prevented = true }, stopPropagation() {} })
  assert.equal(prevented, true)
  assert.equal(document.activeElement, c.triggerTarget)
  assert.equal(c.triggerTarget.getAttribute('aria-expanded'), 'false')
})
test('touch pointer entry does not open before a tap', () => {
  const c = nav()
  c.open({ type: 'pointerenter', pointerType: 'touch' })
  assert.equal(c.contentTarget.classList.contains('hidden'), true)
  c.toggle()
  assert.equal(c.contentTarget.classList.contains('hidden'), false)
})
test('leaving with mouse does not hide links while keyboard focus stays inside', async () => {
  const c = nav(); c.open(); c.triggerTarget.focus()
  c.close({ type: 'pointerleave', pointerType: 'mouse' })
  await new Promise(resolve => setTimeout(resolve, 180))
  assert.equal(c.contentTarget.classList.contains('hidden'), false)
  c.disconnect()
})
test('filter state follows rendered visibility on connect and after each toggle', () => {
  const c = new Filter(), button = element()
  c.fieldsTarget = element(); c.hasFieldsTarget = true
  c.element = { querySelectorAll: () => [button] }
  c.connect()
  assert.equal(button.getAttribute('aria-expanded'), 'false')
  c.toggle()
  assert.equal(button.getAttribute('aria-expanded'), 'true')
  assert.equal(button.getAttribute('aria-controls'), c.fieldsTarget.id)
  c.toggle()
  assert.equal(button.getAttribute('aria-expanded'), 'false')
  c.fieldsTarget.classList.toggle('hidden', false)
  c.connect()
  assert.equal(button.getAttribute('aria-expanded'), 'true')
})
