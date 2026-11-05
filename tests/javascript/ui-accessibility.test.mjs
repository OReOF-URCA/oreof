// Run with: node --test tests/javascript/ui-accessibility.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

async function controller(name) {
  const source = (await readFile(new URL(`../../assets/controllers/${name}_controller.js`, import.meta.url), 'utf8'))
    .replace(/^import .*$/gm, '')
  return (await import(`data:text/javascript;base64,${Buffer.from(`class Controller {}\n${source}`).toString('base64')}`)).default
}
const Dropdown = await controller('dropdown')
const Tabs = await controller('tabs')
const Modal = await controller('modalturbo')

globalThis.document = { activeElement: null }
globalThis.getComputedStyle = () => ({ direction: 'ltr' })
function node(attributes = {}) {
  return {
    disabled: false, tabIndex: 0, isConnected: true, dataset: {},
    getAttribute: name => attributes[name] ?? null,
    setAttribute: (name, value) => { attributes[name] = value },
    getClientRects: () => [{}],
    contains(target) { return target === this },
    closest: () => null,
    focus() { document.activeElement = this },
    ...attributes
  }
}
function key(target, value, extra = {}) {
  return { target, key: value, prevented: false, stopped: false, preventDefault() { this.prevented = true }, stopPropagation() { this.stopped = true }, ...extra }
}
function menu() {
  const instance = new Dropdown()
  const button = node()
  const items = [node(), node({ disabled: true }), node()]
  instance._button = button
  instance._menu = node({ style: { display: 'block' }, classList: { contains: () => false }, querySelectorAll: () => items, contains: target => items.includes(target) })
  return { instance, button, items }
}

test('action menu arrows skip disabled entries, wrap, and support Home/End', () => {
  const { instance, items } = menu()
  items[0].focus()
  instance.onKeyDown(key(items[0], 'ArrowDown'))
  assert.equal(document.activeElement, items[2])
  instance.onKeyDown(key(items[2], 'ArrowDown'))
  assert.equal(document.activeElement, items[0])
  instance.onKeyDown(key(items[0], 'End'))
  assert.equal(document.activeElement, items[2])
  instance.onKeyDown(key(items[2], 'Home'))
  assert.equal(document.activeElement, items[0])
})

test('Escape is scoped to the open menu and requests focus restoration', () => {
  const { instance, items } = menu()
  let restore
  instance.close = value => { restore = value }
  const outside = key(node(), 'Escape')
  instance.onKeyDown(outside)
  assert.equal(restore, undefined)
  const event = key(items[0], 'Escape')
  instance.onKeyDown(event)
  assert.equal(restore, true)
  assert.equal(event.stopped, true)
})

test('ArrowUp on trigger requests the last action; Tab resumes at trigger', () => {
  const { instance, button, items } = menu()
  let requested
  instance.open = value => { requested = value }
  instance.onKeyDown(key(button, 'ArrowUp'))
  assert.equal(requested, 'last')
  instance.close = () => {}
  items[0].focus()
  const event = key(items[0], 'Tab')
  instance.onKeyDown(event)
  assert.equal(document.activeElement, button)
  assert.equal(event.prevented, false)
})

function tabs() {
  const instance = new Tabs()
  instance.element = node()
  instance.tabTargets = [node({ dataset: { tabId: 'first' } }), node({ disabled: true }), node({ dataset: { tabId: 'last' } })]
  instance.activate = id => { instance.selected = id }
  return instance
}
test('tabs can reach inactive tabs with arrows and skip disabled tabs', () => {
  const instance = tabs()
  instance.onKeydown(key(instance.tabTargets[0], 'ArrowRight'))
  assert.equal(instance.selected, 'last')
  assert.equal(document.activeElement, instance.tabTargets[2])
  instance.onKeydown(key(instance.tabTargets[2], 'ArrowRight'))
  assert.equal(instance.selected, 'first')
  instance.onKeydown(key(instance.tabTargets[0], 'End'))
  assert.equal(instance.selected, 'last')
})
test('tabs ignore keyboard input originating in a panel', () => {
  const instance = tabs()
  const event = key(node(), 'Home')
  instance.onKeydown(event)
  assert.equal(instance.selected, undefined)
  assert.equal(event.prevented, false)
})
test('vertical tabs leave horizontal arrows alone', () => {
  const instance = tabs()
  instance.tabTargets.forEach(tab => { tab.closest = () => node({ 'aria-orientation': 'vertical' }) })
  instance.onKeydown(key(instance.tabTargets[0], 'ArrowRight'))
  assert.equal(instance.selected, undefined)
  instance.onKeydown(key(instance.tabTargets[0], 'ArrowDown'))
  assert.equal(instance.selected, 'last')
})
test('modal Tab and Shift+Tab wrap focus in both directions', () => {
  const instance = new Modal()
  instance.isOpen = true
  instance.wrapperTarget = node()
  const first = node(), last = node()
  instance.focusableElements = () => [first, last]
  last.focus()
  instance.onKeydown(key(last, 'Tab'))
  assert.equal(document.activeElement, first)
  instance.onKeydown(key(first, 'Tab', { shiftKey: true }))
  assert.equal(document.activeElement, last)
})
test('Escape closes Tom Select first, then the modal, even from a field', () => {
  const instance = new Modal()
  instance.isOpen = true
  const select = { tomselect: { isOpen: true, close() { this.isOpen = false } } }
  instance.wrapperTarget = node({ querySelectorAll: () => [select], querySelector: () => null })
  let closed = false
  instance.close = () => { closed = true }
  instance.onKeydown(key(node(), 'Escape'))
  assert.equal(select.tomselect.isOpen, false)
  assert.equal(closed, false)
  instance.onKeydown(key(node(), 'Escape'))
  assert.equal(closed, true)
})
test('modal cleanup aborts loading, restores background state and trigger focus', () => {
  const instance = new Modal()
  instance.isOpen = true
  instance.hadScrollLock = true
  let removedScrollLock = false, aborted = false
  document.documentElement = { classList: { remove() { removedScrollLock = true } } }
  document.getElementById = () => null
  instance.wrapperTarget = node({ classList: { add() {} } })
  const background = { inert: true }
  instance.inertElements = new Map([[background, false]])
  instance.requestController = { abort() { aborted = true } }
  instance.returnFocus = node()
  instance.close()
  assert.equal(aborted, true)
  assert.equal(background.inert, false)
  assert.equal(document.activeElement, instance.returnFocus)
  assert.equal(removedScrollLock, false)
})
