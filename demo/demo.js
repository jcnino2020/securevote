const screens = ['landing', 'register', 'login', 'dashboard'];
const notes = {
  landing: { title: '01 / Landing page', timing: '0:00–0:40 · Presenter 1', script: '“This is SecureVote’s public landing page. Students can see how voting works and find the path to registration or sign-in before entering their account.”', points: ['Clear sign-in and registration actions', 'Three voting steps: explore, choose, confirm', 'Public information is available before login'], link: '../', linkText: 'Open real landing page' },
  register: { title: '02 / Registration', timing: '0:40–1:50 · Presenter 1', script: '“A student can register with a name, username, email, and password. The real server checks the fields and creates a voter code. An administrator assigns election eligibility before the student can vote.”', points: ['Try a blank form to show validation', 'A new account has no election access automatically', 'The preview form does not save anything on the server'], link: '../auth/register.php', linkText: 'Open real registration' },
  login: { title: '03 / Sign in', timing: '1:50–2:50 · Presenter 2', script: '“Voters can identify themselves with an email, username, or voter code. Password accounts need the correct password. The real site checks account status, limits repeated failures, and starts a new session after login.”', points: ['Use the fictional sample credentials shown here', 'Never display a real password to the panel', 'Google sign-in is an alternate real-site route'], link: '../auth/login.php', linkText: 'Open real sign-in' },
  dashboard: { title: '04 / Dashboard', timing: '2:50–4:15 · Presenter 3', script: '“After sign-in, voters see their voter pass, assigned elections, and ballot summary. An account with no assigned election sees a clear waiting state. This is a sample view; the real dashboard reads the signed-in voter’s data.”', points: ['Show the voter pass and voting summary', 'Explain that eligibility is assigned by an administrator', 'Do not submit a real ballot during this presentation'], link: '../voter/dashboard.php', linkText: 'Open real dashboard' }
};

let current = 'landing';
let previewAccount = null;
const sample = { firstName: 'Demo', lastName: 'Voter', username: 'demo.voter', email: 'demo.voter@example.test', password: 'DemoPass2026!', code: 'VC-DEMO-2026', assigned: true };
const $ = (selector) => document.querySelector(selector);

function show(screen) {
  if (!screens.includes(screen)) return;
  current = screen;
  for (const name of screens) {
    const active = name === screen;
    $(`#screen-${name}`).hidden = !active;
    const button = document.querySelector(`[data-screen="${name}"]`);
    button.classList.toggle('is-active', active);
    if (active) button.setAttribute('aria-current', 'step');
    else button.removeAttribute('aria-current');
  }
  const note = notes[screen];
  $('#stage-title').textContent = note.title;
  $('#presenter-timing').textContent = note.timing;
  $('#presenter-script').textContent = note.script;
  $('#presenter-points').replaceChildren(...note.points.map((point) => { const item = document.createElement('li'); item.textContent = point; return item; }));
  $('#live-link').href = note.link;
  $('#live-link').firstChild.textContent = `${note.linkText} `;
  $('#prev-screen').disabled = screen === 'landing';
  $('#next-screen').disabled = screen === 'dashboard';
  if (screen === 'dashboard') renderDashboard(previewAccount || sample);
}

function setError(id, message) {
  const node = document.getElementById(id);
  node.textContent = message;
  node.hidden = !message;
}

function renderDashboard(account) {
  const assigned = account.assigned;
  $('#dashboard-greeting').textContent = `Hi, ${account.firstName}.`;
  $('#dashboard-message').textContent = assigned ? 'One election is ready for your vote. Take a look and choose when you are ready.' : 'Your account is ready. An election administrator will assign the elections you can vote in.';
  $('#dashboard-eligibility').textContent = assigned ? '1 assigned election' : 'No elections assigned yet';
  $('#dashboard-ready').textContent = assigned ? '01' : '00';
  $('#dashboard-code').textContent = account.code;
  $('#dashboard-election-title').textContent = assigned ? 'Ready when you are' : 'No elections assigned yet';
  $('#dashboard-election-copy').textContent = assigned ? 'Student Council Election · Voting open · Demo data only' : 'An administrator will assign your election access. This preview created no real account.';
}

document.querySelectorAll('[data-screen], [data-go]').forEach((button) => {
  button.addEventListener('click', () => show(button.dataset.screen || button.dataset.go));
});
$('#prev-screen').addEventListener('click', () => show(screens[screens.indexOf(current) - 1]));
$('#next-screen').addEventListener('click', () => show(screens[screens.indexOf(current) + 1]));
$('#sign-out').addEventListener('click', () => { previewAccount = null; show('login'); });

$('#register-form').addEventListener('submit', (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  setError('register-error', '');
  if (!form.reportValidity()) return;
  const data = new FormData(form);
  if (data.get('password') !== data.get('confirm')) {
    setError('register-error', 'The two passwords do not match.');
    form.elements.confirm.focus();
    return;
  }
  previewAccount = {
    firstName: String(data.get('firstName')).trim(),
    lastName: String(data.get('lastName')).trim(),
    username: String(data.get('username')).trim(),
    email: String(data.get('email')).trim(),
    password: String(data.get('password')),
    code: 'VC-PREVIEW-2026',
    assigned: false
  };
  show('dashboard');
});

$('#login-form').addEventListener('submit', (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  setError('login-error', '');
  if (!form.reportValidity()) return;
  const identifier = String(form.elements.identifier.value).trim().toLowerCase();
  const password = form.elements.password.value;
  const matches = (account) => account && [account.email, account.username, account.code].some((value) => value.toLowerCase() === identifier) && account.password === password;
  if (matches(previewAccount)) {
    show('dashboard');
  } else if (matches(sample)) {
    previewAccount = sample;
    show('dashboard');
  } else {
    setError('login-error', 'Those preview credentials do not match. Use the fictional sample shown above.');
  }
});

show('landing');
