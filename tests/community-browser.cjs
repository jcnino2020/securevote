/* Run against the disposable fixture server; requires Playwright and test fixture accounts. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.SV_TEST_URL || 'http://127.0.0.1:8765';
if (!/^http:\/\/127\.0\.0\.1:8765$/.test(base)) throw new Error('Use the isolated localhost fixture server.');
(async () => {
 const browser = await chromium.launch({headless:true,channel:'chrome'});
 const guest = await browser.newContext({viewport:{width:1440,height:1000}});
 const page = await guest.newPage(); const errors = [];
 page.on('pageerror', e => errors.push(e.message));
 async function goto(path) { const r = await page.goto(base + path); assert.equal(r.status(), 200, path); }
 async function click(name) { await Promise.all([page.waitForURL(() => true, {waitUntil:'domcontentloaded'}),page.getByRole('button',{name,exact:true}).click()]); await page.waitForLoadState('domcontentloaded'); }
 await goto('/practice.php');
 await page.locator('[value="101"]').check(); await page.locator('[value="201"]').check();
 await click('Review practice choices');
 await page.getByRole('button',{name:'Finish practice ballot'}).waitFor();
 assert.match(await page.locator('main').innerText(), /Alex Rivera/);
 await click('Edit practice choices'); await page.locator('[value="102"]').check();
 await click('Review practice choices'); assert.match(await page.locator('main').innerText(), /only 1 selection is allowed/);
 await page.locator('[value="102"]').uncheck(); await click('Review practice choices'); await click('Finish practice ballot');
 assert.match(await page.locator('main').innerText(), /recorded no vote/);
 await goto('/verify.php'); await page.getByLabel('Receipt code').fill('SV-2026-ABC123'); await click('Check receipt');
 assert.match(await page.locator('main').innerText(), /Recorded ballot found/);
 assert.doesNotMatch(await page.locator('main').innerText(), /Browser Student|browser@example.test|VC-TEST-BROWSER/);
 await page.getByLabel('Receipt code').fill('SV-2026-FFFFFF'); await click('Check receipt');
 assert.match(await page.locator('main').innerText(), /No submitted ballot found/);
 await goto('/announcements.php'); assert.doesNotMatch(await page.locator('main').innerText(), /Secret draft update|Unpublished test update/);
 const csrf = await page.locator('#traffic-config').getAttribute('data-token');
 let response = await guest.request.post(base + '/help.php', {form:{subject:'CSRF should fail',email:'guest@example.test',category:'login',message:'This request should fail.'}});
 assert.equal(response.status(),403,'Missing CSRF rejected');
 await goto('/help.php'); await page.getByLabel('Contact email').fill('guest@example.test'); await page.getByLabel('Subject',{exact:true}).fill('Guest sign-in issue'); await page.getByLabel('What happened?').fill('Unable to sign in to the student portal.'); await click('Send issue report');
 assert.match(await page.locator('main').innerText(),/Report received/);
 const adminContext = await browser.newContext({viewport:{width:1440,height:1000}});
 const admin = await adminContext.newPage(); admin.on('pageerror', e => errors.push(e.message));
 async function login(p, name) { await p.goto(base+'/auth/login.php'); await p.getByLabel('Email, username, or voter code').fill(name); await p.locator('#password').fill('FeatureTest123'); await p.locator('form[action$="authenticate.php"] button[type="submit"]').click(); await p.waitForURL(/dashboard\.php/); }
 await login(admin,'admin');
 await admin.goto(base+'/admin/announcements.php');
 await admin.getByLabel('Title',{exact:true}).fill('Campus candidate forum'); await admin.getByLabel('Announcement',{exact:true}).fill('Join the candidate forum in the campus hall this Friday.'); await admin.getByLabel('Publication status').selectOption('published'); await admin.getByRole('button',{name:'Save announcement'}).click(); await admin.waitForLoadState('domcontentloaded');
 await goto('/announcements.php'); assert.match(await page.locator('main').innerText(),/Campus candidate forum/);
 await admin.goto(base+'/admin/readiness.php?election_id=1'); assert.equal(await admin.locator('.readiness-list li').count(),5);
 await admin.goto(base+'/admin/import-voters.php'); await admin.getByLabel('Assign to election').selectOption('1');
 await admin.getByLabel('Student roster CSV').setInputFiles({name:'roster.csv',mimeType:'text/csv',buffer:Buffer.from('username,email,first_name,last_name\ne2estudent,e2e@example.test,Import,Student\n')}); await admin.getByRole('button',{name:'Preview voters'}).click(); await admin.getByRole('button',{name:'Confirm voter import'}).waitFor(); assert.match(await admin.locator('main').innerText(), /Create account/);
 await admin.getByRole('button',{name:'Confirm voter import'}).click(); await admin.waitForURL(/voters\.php/); assert.match(await admin.locator('main').innerText(),/1 voters created/);
 const voterContext = await browser.newContext({viewport:{width:1440,height:1000}});
 const voter = await voterContext.newPage(); voter.on('pageerror', e => errors.push(e.message)); await login(voter,'browserstudent');
 await voter.goto(base+'/voter/compare.php?election_id=1'); assert.ok(await voter.locator('.comparison-table th[scope="col"]').count()>1);
 assert.match(await voter.locator('main').innerText(),/Digital student services/);
 response = await voterContext.request.get(base+'/admin/readiness.php'); assert.equal(response.status(),403,'Voter denied admin readiness');
 response = await voterContext.request.post(base+'/admin/announcements.php',{form:{csrf_token:await voter.locator('#traffic-config').getAttribute('data-token'),title:'Unauthorized post',body:'Voters must not publish announcements.',status:'published'}}); assert.equal(response.status(),403,'Voter cannot publish announcements');
 response = await voterContext.request.get(base+'/voter/compare.php?election_id=999999'); assert.equal(response.status(),404,'Unknown comparison election rejected');
 await voter.goto(base+'/help.php'); await voter.getByLabel('Subject',{exact:true}).fill('Student eligibility question'); await voter.getByLabel('What happened?').fill('Please check my eligibility for the student election.'); await voter.getByRole('button',{name:'Send issue report'}).click(); await voter.waitForLoadState('domcontentloaded');
 await admin.goto(base+'/admin/support.php'); const ticket = admin.locator('article').filter({has:admin.getByRole('heading',{name:'Student eligibility question'})}); await ticket.locator('select').selectOption('resolved'); await ticket.locator('textarea').fill('Your eligibility has been checked. Please try the election again.'); await ticket.getByRole('button',{name:'Save report update'}).click(); await admin.waitForLoadState('domcontentloaded');
 await voter.goto(base+'/help.php'); assert.match(await voter.locator('main').innerText(), /Your eligibility has been checked/); assert.doesNotMatch(await voter.locator('main').innerText(),/Guest sign-in issue/);
 // One batched layout inspection across all new routes, desktop and mobile.
 fs.mkdirSync('/tmp/securevote-feature-screens',{recursive:true});
 const captures = [[page,'/practice.php','practice'],[page,'/verify.php','verify'],[page,'/announcements.php','announcements'],[page,'/help.php','help'],[voter,'/voter/compare.php?election_id=1','compare'],[admin,'/admin/readiness.php?election_id=1','readiness'],[admin,'/admin/announcements.php','admin-announcements'],[admin,'/admin/import-voters.php','import'],[admin,'/admin/support.php?filter=all','support']];
 const overflows = [];
 for (const [p,path,name] of captures) {
   for (const [label,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
     await p.setViewportSize({width,height}); const r=await p.goto(base+path); assert.equal(r.status(),200,path); await p.evaluate(()=>document.fonts.ready);
     const overflow = await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
     if (overflow) overflows.push(name+' '+label);
     await p.screenshot({path:'/tmp/securevote-feature-screens/'+name+'-'+label+'.png',fullPage:true});
   }
 }
 assert.deepEqual(overflows,[],'No page-level horizontal overflow'); assert.deepEqual(errors,[],'No uncaught browser errors');
 await browser.close(); console.log('PASS all seven feature flows, role/CSRF/privacy checks, and 18 desktop/mobile captures.');
})().catch(e=>{console.error(e);process.exit(1)});
