// node --test tests/javascript/save-data.test.mjs
import {test} from 'node:test'
import assert from 'node:assert/strict'
import {readFile} from 'node:fs/promises'
const notices=[]
globalThis.recordNotice=(message,type)=>notices.push({message,type})
const source=(await readFile(new URL('../../assets/js/saveData.js',import.meta.url),'utf8')).replace(/^import .*$/gm,'')
const {saveData}=await import(`data:text/javascript;base64,${Buffer.from(`const callOut=globalThis.recordNotice;\n${source}`).toString('base64')}`)
function reply(status,data){return {status,ok:status>=200&&status<300,json:async()=>data}}
test('HTTP 500 produces one error and never a success',async()=>{
 notices.length=0;globalThis.fetch=async()=>reply(500,{message:'Refus'})
 assert.equal(await saveData('/save',{}),false)
 assert.deepEqual(notices.map(n=>n.type),['danger'])
})
test('HTTP 403 is a failure even with a success-shaped payload',async()=>{
 notices.length=0;globalThis.fetch=async()=>reply(403,{success:true})
 assert.equal(await saveData('/save',{}),false)
 assert.equal(notices[0].type,'danger')
})
test('HTTP 200 false and success:false are application failures',async()=>{
 for(const data of [false,{success:false}]){
  notices.length=0;globalThis.fetch=async()=>reply(200,data)
  assert.equal(await saveData('/save',{}),false)
  assert.equal(notices.some(n=>n.type==='success'),false)
 }
})
test('a validation report is preserved without a success message',async()=>{
 notices.length=0;const report={error:['PV manquant']};globalThis.fetch=async()=>reply(200,report)
 assert.equal(await saveData('/save',{}),report)
 assert.equal(notices[0].type,'danger')
})
test('network errors and invalid JSON do not escape as success',async()=>{
 for(const fetch of [async()=>{throw Error('offline')},async()=>({ok:true,status:200,json:async()=>{throw Error('html')}})]){
  notices.length=0;globalThis.fetch=fetch
  assert.equal(await saveData('/save',{}),false)
  assert.equal(notices[0].type,'danger')
 }
})
test('valid JSON and 204 keep the legacy success contract',async()=>{
 for(const [status,data] of [[200,true],[204,true]]){
  notices.length=0;globalThis.fetch=async()=>reply(status,data)
  assert.equal(await saveData('/save',{}),true)
  assert.equal(notices[0].type,'success')
 }
})
let updates=0
globalThis.stubSaveData=async()=>false
globalThis.stubUpdate=async()=>{updates+=1}
const consumer=(await readFile(new URL('../../assets/controllers/ec/structure_controller.js',import.meta.url),'utf8')).replace(/^import .*$/gm,'')
const Consumer=(await import(`data:text/javascript;base64,${Buffer.from(`class Controller {}\nconst saveData=(...args)=>globalThis.stubSaveData(...args);const updateEtatOnglet=globalThis.stubUpdate;\n${consumer}`).toString('base64')}`)).default
test('an EC indicator is not recalculated after a failed save',async()=>{
 updates=0;const c=new Consumer();c.urlSaveValue='/save'
 assert.equal(await c._save({}),false)
 assert.equal(updates,0)
 globalThis.stubSaveData=async()=>true
 assert.equal(await c._save({}),true)
 assert.equal(updates,1)
})
