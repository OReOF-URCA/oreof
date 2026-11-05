// node --test tests/javascript/autosave-capacites.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
const source = (await readFile(new URL('../../assets/controllers/autosave_capacites_controller.js', import.meta.url), 'utf8')).replace(/^import .*$/gm, '')
const Autosave = (await import(`data:text/javascript;base64,${Buffer.from(`class Controller {}\nconst callOut = () => {}\n${source}`).toString('base64')}`)).default
function setup() {
  const c = new Autosave(), hidden = new Set()
  const listeners = new Map()
  c.element = { addEventListener: (name, fn) => listeners.set(name, fn), removeEventListener: (name, fn) => { if (listeners.get(name) === fn) listeners.delete(name) } }
  c.hasStatusTarget = c.hasRetryTarget = true
  c.statusTarget = { dataset: {}, classList: { add() {} } }
  c.retryTarget = { classList: { toggle(name, force) { if (force) hidden.add(name); else hidden.delete(name) }, contains: name => hidden.has(name) } }
  c.connect()
  return { c, listeners }
}
globalThis.FormData = class {}
function response(data = { success: true }) { return { ok: true, headers: { get: () => 'application/json' }, json: async () => data } }
test('initial state makes no false claim that a save has occurred', () => {
  const { c } = setup()
  assert.equal(c.statusState, 'idle')
  assert.equal(c.statusTarget.textContent, 'Aucune modification en attente')
})
test('server messages remain plain text and retry stays visible after errors', () => {
  const { c } = setup()
  c.setStatus('error', '<img src=x onerror=alert(1)>')
  assert.equal(c.statusTarget.textContent, '<img src=x onerror=alert(1)>')
  assert.equal(c.retryTarget.classList.contains('hidden'), false)
  c.setStatus('saving', 'Enregistrement…')
  assert.equal(c.retryTarget.classList.contains('hidden'), true)
})
test('a response for an older revision cannot mark newer edits as saved', async () => {
  const { c } = setup()
  let resolve
  globalThis.fetch = () => new Promise(done => { resolve = done })
  const saving = c.save()
  c.revision += 1
  resolve(response())
  await saving
  assert.equal(c.statusState, 'typing')
  assert.match(c.statusTarget.textContent, /attente/)
})
test('successful save of the current revision is acknowledged', async () => {
  const { c } = setup()
  globalThis.fetch = async () => response()
  await c.save()
  assert.equal(c.statusState, 'saved')
})
test('application-level failures expose retry and manual retry saves again', async () => {
  const { c } = setup()
  globalThis.fetch = async () => response({ success: false, message: 'Impossible de sauvegarder' })
  await c.save()
  assert.equal(c.statusState, 'error')
  assert.equal(c.retryTarget.classList.contains('hidden'), false)
  globalThis.fetch = async () => response()
  await c.retry()
  assert.equal(c.statusState, 'saved')
})
test('disconnect removes listeners and ignores a late response', async () => {
  const { c, listeners } = setup()
  assert.equal(listeners.size, 4)
  let resolve
  globalThis.fetch = () => new Promise(done => { resolve = done })
  const saving = c.save()
  c.disconnect()
  assert.equal(listeners.size, 0)
  resolve(response())
  await saving
  assert.notEqual(c.statusState, 'saved')
})
