// node --test tests/javascript/form-feedback.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
const notices = []
globalThis.mockToast = Object.fromEntries(['error','warning','success','info'].map(type => [type, text => notices.push({type,text})]))
globalThis.mockRenderStream = () => {}
const source = (await readFile(new URL('../../assets/js/callOut.js', import.meta.url), 'utf8')).replace(/^import .*$/gm, '')
const {handleToastResponse} = await import(`data:text/javascript;base64,${Buffer.from(`const Toast = globalThis.mockToast; const renderStreamMessage = globalThis.mockRenderStream;\n${source}`).toString('base64')}`)
function json(payload, ok = true) { return {ok, headers:{get:()=> 'application/json'}, text:async()=>JSON.stringify(payload)} }
function stream(text, ok = true) { return {ok, headers:{get:()=> 'text/vnd.turbo-stream.html'}, text:async()=>text} }
test('HTTP 200 application failure is not a success notification', async () => {
  notices.length = 0
  const result = await handleToastResponse(json({success:false,message:'Corrigez le formulaire',type:'success'}))
  assert.equal(result.success, false)
  assert.equal(notices[0].type, 'error')
})
test('HTTP failure cannot be overridden by payload success', async () => {
  const result = await handleToastResponse(json({success:true,message:'Erreur serveur'}, false))
  assert.equal(result.success, false)
})
test('explicit JSON success remains successful', async () => {
  const result = await handleToastResponse(json({success:true,message:'Enregistré'}))
  assert.equal(result.success, true)
})
test('invalid 422 stream is rendered but not reported successful', async () => {
  const result = await handleToastResponse(stream('<turbo-stream></turbo-stream>',false))
  assert.equal(result.format, 'turbo-stream')
  assert.equal(result.success, false)
})
test('legacy 200 streams with error summaries or error toast markers stay failures', async () => {
  assert.equal((await handleToastResponse(stream('<div data-form-error-summary></div>'))).success, false)
  assert.equal((await handleToastResponse(stream('<div data-toast-type="danger"></div>'))).success, false)
})
test('confirmation streams allow legacy modal closure', async () => {
  assert.equal((await handleToastResponse(stream('<div data-toast-type="success"></div>'))).success, true)
})
const controllerSource = (await readFile(new URL('../../assets/controllers/form_feedback_controller.js',import.meta.url),'utf8')).replace(/^import .*$/gm,'')
const Feedback = (await import(`data:text/javascript;base64,${Buffer.from(`class Controller {}\n${controllerSource}`).toString('base64')}`)).default
function form() {
  let busy = null
  const feedback = []
  return {feedback, isConnected:true, getAttribute:()=>busy, setAttribute:(name,value)=>{busy=value},removeAttribute:()=>{busy=null},querySelector:()=>null,closest:()=>null,prepend:node=>feedback.push(node)}
}
globalThis.requestAnimationFrame = fn => fn()
globalThis.document = {createElement:()=>({dataset:{},setAttribute(){}})}
test('network failure keeps the form and adds a persistent local message', () => {
  const c = new Feedback(), f = form()
  c.pending = new Map([[f,null]])
  c.onEnd({target:f,detail:{success:false}})
  assert.equal(f.isConnected, true)
  assert.equal(f.feedback.length, 1)
  assert.match(f.feedback[0].textContent, /Impossible de confirmer/)
  assert.equal(f.getAttribute('aria-busy'), null)
})
test('successful response does not invent a generic saved confirmation', () => {
  const c = new Feedback(), f = form()
  c.pending = new Map([[f,null]])
  c.onEnd({target:f,detail:{success:true}})
  assert.equal(f.feedback.length,0)
})
