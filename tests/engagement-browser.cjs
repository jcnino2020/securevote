const {chromium}=require('playwright');
const assert=require('node:assert/strict'); const fs=require('node:fs');
const base='http://127.0.0.1:8765';
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const errors=[]; const context=await browser.newContext({viewport:{width:1440,height:1000}}); const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 async function go(path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);}
 async function login(name){await go('/auth/login.php');await page.getByLabel('Email, username, or voter code').fill(name);await page.locator('#password').fill('FeatureTest123');await page.locator('form[action$="authenticate.php"] button[type="submit"]').click();await page.waitForURL(/dashboard\.php/);}
 await go('/timeline.php');assert.match(await page.locator('main').innerText(),/Not announced/);
 await page.getByRole('button',{name:'Filipino',exact:true}).click();await page.waitForLoadState();assert.equal(await page.locator('html').getAttribute('lang'),'fil');assert.match(await page.locator('main').innerText(),/Iskedyul ng eleksiyon/);await page.getByRole('button',{name:'English',exact:true}).click();await page.waitForLoadState();
 await login('admin');await go('/admin/engagement.php?election_id=1');
 await page.getByLabel('Registration opens',{exact:true}).fill('2026-10-01T08:00');await page.getByLabel('Registration closes',{exact:true}).fill('2026-10-02T17:00');await page.getByRole('button',{name:'Save timeline',exact:true}).click();await page.waitForLoadState();assert.match(await page.locator('main').innerText(),/Timeline saved/);
 const candidate=await page.locator('select[name="candidate_id"] option').nth(1).getAttribute('value');await page.getByLabel('Candidate',{exact:true}).selectOption(candidate);await page.getByLabel('Question',{exact:true}).fill('How will you support student services?');await page.getByLabel('Candidate answer',{exact:true}).fill('We will provide regular student service consultations.');await page.getByRole('button',{name:'Save answer',exact:true}).click();await page.waitForLoadState();
 const guest=await browser.newContext();const gp=await guest.newPage();await gp.goto(base+'/candidate-qa.php?election_id=1');assert.doesNotMatch(await gp.locator('main').innerText(),/How will you support/);
 await page.getByRole('link',{name:'Review or edit',exact:true}).click();await page.getByLabel('Publication status').selectOption('published');await page.getByRole('button',{name:'Save answer',exact:true}).click();await page.waitForLoadState();await gp.reload();assert.match(await gp.locator('main').innerText(),/How will you support/);
 let response=await guest.request.post(base+'/language.php',{form:{language:'fil'}});assert.equal(response.status(),403);
 await context.clearCookies();await login('browserstudent');
 await go('/voter/reminders.php');assert.match(await page.locator('main').innerText(),/Voting closes within 24 hours/);
 await page.getByRole('button',{name:'Dismiss reminder',exact:true}).first().click();await page.waitForLoadState();assert.doesNotMatch(await page.locator('main').innerText(),/Voting closes within 24 hours/);
 response=await context.request.get(base+'/admin/engagement.php');assert.equal(response.status(),403);
 await go('/voter/ballot.php?election_id=1');await page.getByRole('button',{name:'Filipino',exact:true}).click();await page.waitForLoadState();assert.match(await page.locator('main').innerText(),/Piliin ang iyong mga kandidato/);await page.getByRole('button',{name:'English',exact:true}).click();await page.waitForLoadState();
 await go('/voter/confirmation.php?election_id=2');assert.doesNotMatch(await page.locator('main').innerText(),/VC-TEST-BROWSER|<\?=/);await page.evaluate(()=>window.print=()=>{window.didPrint=true});await page.locator('[data-print-receipt]').click();assert.equal(await page.evaluate(()=>window.didPrint),true);
 response=await context.request.get(base+'/voter/confirmation.php?election_id=1');assert.equal(response.status(),404,'Cannot view a receipt for an unsubmitted election');
 await go('/voter/confirmation.php?election_id=2');await page.emulateMedia({media:'print'});assert.equal(await page.locator('.topbar').isVisible(),false);assert.equal(await page.locator('.reference-code').isVisible(),true);await page.pdf({path:'/tmp/securevote-engagement-receipt.pdf',format:'A4'});await page.emulateMedia({media:'screen'});
 fs.mkdirSync('/tmp/securevote-engagement-screens',{recursive:true});
 for(const [route,label] of [['/timeline.php?election_id=1','timeline'],['/candidate-qa.php?election_id=1','qa'],['/voter/reminders.php','reminders'],['/voter/confirmation.php?election_id=2','receipt']]){
  for(const [width,height] of [[1440,1000],[390,844]]){await page.setViewportSize({width,height});await go(route);await page.evaluate(()=>document.fonts.ready);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,route+' overflow '+width);await page.screenshot({path:'/tmp/securevote-engagement-screens/'+label+'-'+width+'.png',fullPage:true});}
 }
 assert.deepEqual(errors,[]);await browser.close();console.log('PASS browser flows: timeline editing, Q&A review/publication, CSRF, role checks, reminders/dismissal, bilingual ballot, receipt ownership/print/PDF, desktop/mobile layouts');
})().catch(e=>{console.error(e);process.exit(1)});
