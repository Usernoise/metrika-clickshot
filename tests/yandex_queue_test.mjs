import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../public/counter.js', import.meta.url), 'utf8');
for (const order of ['before-snippet', 'queued-init', 'loaded-sdk']) {
  const events = [], calls = [];
  const window = {__clickShotBootstrap:{site:'queue_test',collection:{visits:false}}};
  const context = vm.createContext({window, URL, Blob,
    location:{href:'https://example.test/',pathname:'/'},
    document:{currentScript:{src:'https://counter.test/counter.js?id=queue_test',dataset:{}},readyState:'complete',referrer:''},
    navigator:{sendBeacon:(_,blob)=>{events.push(blob);return true;}},
    sessionStorage:{getItem:()=>null,setItem:()=>{}},
  });
  const snippet = () => vm.runInContext(`
    window.ym = window.ym || function(){(window.ym.a = window.ym.a || []).push(arguments)};
    window.ym.l = 123;
    window.ym(110148755, 'init', {webvisor:true});
  `, context);
  if (order === 'queued-init') snippet();
  if (order === 'loaded-sdk') window.ym = function(...args){calls.push(args);return 'forwarded';};
  const original = window.ym;
  const descriptor = Object.getOwnPropertyDescriptor(window, 'ym');
  vm.runInContext(source, context);
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(window.ym, original, 'ClickShot must not replace ym');
  assert.deepEqual(Object.getOwnPropertyDescriptor(window, 'ym'), descriptor,
    'ClickShot must not install a global assignment hook');
  if (order === 'before-snippet') snippet();
  if (order !== 'loaded-sdk') {
    assert.equal(window.ym.a?.[0]?.[1], 'init', order + ': SDK must see queued init');
    assert.equal(window.ym.l, 123, order + ': preserve snippet timestamp');
    const replacement = [];
    window.ym.a = replacement;
    if (original) assert.equal(original.a, replacement, 'Queue writes must reach original function');
    window.ym(110148755, 'reachGoal', 'queued_goal');
    assert.equal(replacement[0][1], 'reachGoal');
    window.ym = function(...args){calls.push(args);return 'forwarded';};
  }
  assert.equal(window.ym(110148755, 'reachGoal', 'form_sent'), 'forwarded');
  assert.equal(calls.filter(args=>args[2]==='form_sent').length, 1);
  await new Promise(resolve=>setImmediate(resolve));
  const payloads = await Promise.all(events.map(async blob=>JSON.parse(await blob.text())));
  assert.equal(payloads.filter(event=>event.event==='form_sent' || event.event==='queued_goal').length, 0,
    'Yandex goals must never be copied to ClickShot');
  await window.ClickShotMetrika.goal('own_goal');
  const ownPayloads = await Promise.all(events.map(async blob=>JSON.parse(await blob.text())));
  assert.equal(ownPayloads.filter(event=>event.event==='own_goal').length, 1);
  console.log('PASS Independent Yandex function, queue and goals: ' + order);
}
