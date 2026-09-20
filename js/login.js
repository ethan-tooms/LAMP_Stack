// ── Configuration ──────────────────────────────────────
const API_URL     = 'index.php';       // API lives next to index.html
const USER_PAGE   = 'dashboard.html';  // regular user dashboard
const ADMIN_PAGE  = 'admin.html';      // admin dashboard
const SESSION_KEY = 'contactsUser';

const form       = document.getElementById('loginForm');
const loginEl    = document.getElementById('login');
const passEl     = document.getElementById('password');
const rememberEl = document.getElementById('remember');
const submitBtn  = document.getElementById('submitBtn');
const errorBox   = document.getElementById('error');
const errorText  = document.getElementById('errorText');
const toggleBtn  = document.getElementById('togglePw');

toggleBtn.addEventListener('click', () => {
  const show = passEl.type === 'password';
  passEl.type = show ? 'text' : 'password';
  toggleBtn.setAttribute('aria-pressed', String(show));
  toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});

function showError(message) {
  errorText.textContent = message;
  errorBox.hidden = false;
  // restart the shake animation on repeat errors
  errorBox.style.animation = 'none';
  void errorBox.offsetWidth;
  errorBox.style.animation = '';
}

function setLoading(loading) {
  submitBtn.disabled = loading;
  submitBtn.classList.toggle('loading', loading);
  submitBtn.setAttribute('aria-busy', String(loading));
}

function saveSession(user, persist) {
  const data = JSON.stringify(user);
  const [keep, drop] = persist ? [localStorage, sessionStorage] : [sessionStorage, localStorage];
  try {
    drop.removeItem(SESSION_KEY);
    keep.setItem(SESSION_KEY, data);
  } catch (_) { /* storage blocked — nothing more we can do */ }
}

form.addEventListener('input', () => { errorBox.hidden = true; });

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.hidden = true;

  const login    = loginEl.value.trim();
  const password = passEl.value;

  if (!login || !password) {
    showError('Please enter both your username and password.');
    (login ? passEl : loginEl).focus();
    return;
  }

  setLoading(true);
  try {
    const res = await fetch(`${API_URL}?action=login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login, password })
    });

    let data = {};
    try { data = await res.json(); } catch (_) { /* non-JSON response */ }

    if (res.ok && data.id > 0) {
      const isAdmin = data.isAdmin === true || data.isAdmin === 1 || data.isAdmin === '1';
      saveSession({
        id: data.id,
        firstName: data.firstName,
        lastName: data.lastName,
        token: data.token,
        isAdmin
      }, rememberEl.checked);
      window.location.href = isAdmin ? ADMIN_PAGE : USER_PAGE;
      return;
    }

    if (res.status === 401 || data.error === 'No Records Found') {
      showError('Incorrect username or password. Please try again.');
      passEl.select();
    } else {
      showError(data.error || 'Something went wrong. Please try again.');
    }
  } catch (_) {
    showError('Unable to reach the server. Check your connection and try again.');
  } finally {
    setLoading(false);
  }
});
