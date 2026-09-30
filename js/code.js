const urlBase = (typeof window !== 'undefined' && window.location && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.origin.includes('contacts-4331')))
  ? '/api/index.php'
  : 'https://lamp.contacts-4331.xyz/api/index.php';

const loginUrlBase = urlBase;

let userId = 0;
let firstName = "";
let lastName = "";
let isAdmin = 0;
let isEnabled = 0;
let currentContactPage = 1;
const contactsPerPage = 5;
let currentUserPage = 1;
const usersPerPage = 5;

// Formats a MySQL datetime/date string into a short display date (e.g. "Jan 5, 2026").
// Returns "" if the value is missing/unparseable so callers can skip rendering the badge.
function formatDate(value) {
  if (!value) return "";
  let d = new Date(String(value).replace(" ", "T"));
  if (isNaN(d.getTime())) return "";
  return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
}

// Shows an inline status message in the given element, styled the same way
// login/register/add-contact already do (icon + colored text), instead of
// alert()/confirm() popups, which are not allowed in this app.
// type: "error" | "warning" | "success"
function showStatus(elementId, message, type) {
  let el = typeof elementId === "string" ? document.getElementById(elementId) : elementId;
  if (!el) return;

  let cls, icon;
  if (type === "success") {
    cls = "text-success-wcag small fw-semibold";
    icon = "bi-check-circle-fill";
  } else if (type === "warning") {
    cls = "text-warning small fw-semibold";
    icon = "bi-exclamation-triangle-fill";
  } else {
    cls = "text-danger-wcag small fw-semibold";
    icon = "bi-exclamation-circle-fill";
  }

  el.className = cls;
  el.innerHTML = `<i class="bi ${icon} me-1"></i> ${message}`;
}

// Default avatar shown when a contact has no profilePic set. ProfilePic is
// just a filename (e.g. "contact-5-a1b2c3d4.jpg") pointing at a real file
// under the shared img/ folder on the server - default-pfp.jpg is the
// team's existing fallback image there.
const DEFAULT_PROFILE_PIC = "img/default-pfp.jpg";

// Builds the <img> src for a stored profilePic value. Normally this is
// just a filename that lives in img/, but a full URL or data URL is passed
// through as-is in case any legacy/test data still has one.
function profilePicSrc(profilePic) {
  let value = profilePic && String(profilePic).trim();
  if (!value) return DEFAULT_PROFILE_PIC;
  if (/^https?:\/\//i.test(value) || value.indexOf("data:") === 0) return value;
  return "img/" + value;
}

// Builds the small avatar <img> for a contact card, falling back to the
// default placeholder when the contact has no profilePic on file.
function avatarImg(profilePic) {
  let src = profilePicSrc(profilePic);
  return `<img class="contact-avatar" src="${src}" alt="" onerror="this.onerror=null;this.src='${DEFAULT_PROFILE_PIC}';">`;
}

// Uploads a picked photo to the server (POST ?action=contactPhotoUpload,
// multipart/form-data - contact photos are real files under img/, not
// base64 blobs in the DB). Pass contactId for an existing contact being
// edited, so the server saves it to that contact immediately; omit it for
// the Add Contact form, where the contact doesn't exist yet. Resolves to
// the filename the server assigned (to store as profilePic).
function uploadProfilePic(file, contactId) {
  return new Promise(function (resolve, reject) {
    if (!file) { reject(new Error("No file selected")); return; }

    let formData = new FormData();
    formData.append("photo", file);
    if (contactId) formData.append("contactId", contactId);

    let url = urlBase + "?action=contactPhotoUpload";
    let xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    // Note: no Content-Type header here - the browser sets the multipart
    // boundary itself when sending a FormData body.
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    xhr.onreadystatechange = function () {
      if (this.readyState !== 4) return;
      try {
        let res = JSON.parse(xhr.responseText);
        if (this.status === 200 && res.profilePic) {
          resolve(res.profilePic);
        } else {
          reject(new Error(res.error || "Failed to upload photo"));
        }
      } catch (e) {
        reject(new Error("Error uploading photo"));
      }
    };

    xhr.send(formData);
  });
}

// Existing-contact avatar click handler (contact list / edit mode). The
// avatar circle itself is the picture picker now - clicking only opens
// the file dialog while the contact is actually being edited (see
// editContact(), which adds the avatar-wrap-editable class).
function triggerProfilePicPicker(contactId) {
  let wrap = document.getElementById("avatarWrap-" + contactId);
  if (!wrap || !wrap.classList.contains("avatar-wrap-editable")) return;
  let fileInput = document.getElementById("profilePicFile-" + contactId);
  if (fileInput) fileInput.click();
}

// Uploading saves the photo to this contact right away - it isn't tied to
// that card's Save/Cancel buttons, the same way most apps treat an avatar
// change as its own action.
function handleProfilePicSelect(contactId, input) {
  let file = input.files && input.files[0];
  if (!file) return;
  uploadProfilePic(file, contactId).then(function (filename) {
    let wrap = document.getElementById("avatarWrap-" + contactId);
    if (!wrap) return;
    wrap.dataset.profilePic = filename;
    wrap.innerHTML = avatarImg(filename);
  }).catch(function (err) {
    showStatus("contactMsg-" + contactId, err.message || "Could not upload photo", "error");
  });
}

// New-contact avatar (Add Contact form). There's no contact id yet, so the
// photo is uploaded right away (unattached) and its filename is held here
// until addContact() sends it along with the rest of the new contact.
let newContactProfilePic = "";

function triggerNewContactProfilePic() {
  let fileInput = document.getElementById("newContactProfilePicFile");
  if (fileInput) fileInput.click();
}

function handleNewContactProfilePic(input) {
  let file = input.files && input.files[0];
  if (!file) return;
  uploadProfilePic(file, null).then(function (filename) {
    newContactProfilePic = filename;
    let wrap = document.getElementById("newContactAvatarWrap");
    if (wrap) wrap.innerHTML = avatarImg(filename);
  }).catch(function (err) {
    let resultEl = document.getElementById("contactAddResult");
    if (resultEl) {
      resultEl.className = "text-danger-wcag small fw-semibold";
      resultEl.innerHTML = err.message || "Could not upload photo";
    }
  });
}

// Builds the small date readout shown on a card. Rendered as a normal,
// single-line block (not absolutely positioned) so it never overlaps the
// card's other fields, however cramped the card is (e.g. the nested
// "View Contacts" list).
function dateBadge(dateCreated, dateUpdated) {
  let created = formatDate(dateCreated);
  let updated = formatDate(dateUpdated);
  if (!created && !updated) return "";
  let parts = [];
  if (created) parts.push(`Created ${created}`);
  if (updated) parts.push(`Updated ${updated}`);
  return `<div class="card-date-badge">${parts.join(" &middot; ")}</div>`;
}

function doLogin() {
  userId = 0;
  firstName = "";
  lastName = "";

  let loginInput = document.getElementById("loginName");
  let passwordInput = document.getElementById("loginPassword");
  let login = loginInput ? loginInput.value.trim() : "";
  let password = passwordInput ? passwordInput.value.trim() : "";

  document.getElementById("loginResult").innerHTML = "";

  let jsonPayload = JSON.stringify({ 
    login: login, 
    password: password
  });
  let url = loginUrlBase + "?action=login";

  let xhr = new XMLHttpRequest();
  xhr.open("POST", url, true);
  xhr.setRequestHeader("Content-type", "application/json; charset=UTF-8");
  try {
    xhr.onreadystatechange = function () {
      if (this.readyState === 4) {
        if (this.status === 200) {
          let jsonObject = JSON.parse(xhr.responseText);
          userId = jsonObject.id;

          if (userId < 1) {
            document.getElementById("loginResult").innerHTML =
              "<i class='bi bi-exclamation-circle-fill me-1'></i> User/Password combination incorrect";
            return;
          }

          firstName = jsonObject.firstName;
          lastName = jsonObject.lastName;

          // API should return these
          isAdmin = parseInt(jsonObject.isAdmin) || 0;
          isEnabled = parseInt(jsonObject.isEnabled) || 0;

          // Check if user is enabled
          if (isEnabled !== 1) {
            document.getElementById('loginResult').innerHTML =
            "<i class='bi bi-exclamation-circle-fill me-1'></i> This account is disabled";
            return;
          }

          saveCookie();
          // Send to right dashboard
          if (isAdmin === 1) {
            window.location.href = "admin.html";
          } else {
            window.location.href = "dashboard.html";
          }

        } else {
          document.getElementById("loginResult").innerHTML =
            "<i class='bi bi-exclamation-circle-fill me-1'></i> Username and/or password is incorrect";
        }
      }
    };
    xhr.send(jsonPayload);
  } catch (err) {
    document.getElementById("loginResult").innerHTML = err.message;
  }
}

function saveCookie() {
  let minutes = 20;
  let date = new Date();
  date.setTime(date.getTime() + minutes * 60 * 1000);
  document.cookie =
    "firstName=" +
    encodeURIComponent(firstName) +
    ",lastName=" +
    encodeURIComponent(lastName) +
    ",userId=" +
    userId +
    ",isAdmin=" +
    isAdmin +
    ",isEnabled=" +
    isEnabled +
    ";expires=" +
    date.toGMTString() +
    ";path=/";
}

function readCookie() {
  userId = -1;
  let data = document.cookie;
  let splits = data.split(";");
  for (var i = 0; i < splits.length; i++) {
    let pair = splits[i].trim();
    let tokens = pair.split(",");
    for (var j = 0; j < tokens.length; j++) {
      let keyVal = tokens[j].trim().split("=");
      if (keyVal[0] === "firstName") {
        firstName = decodeURIComponent(keyVal[1] || "");
      } else if (keyVal[0] === "lastName") {
        lastName = decodeURIComponent(keyVal[1] || "");
      } else if (keyVal[0] === "userId") {
        userId = parseInt(keyVal[1].trim());
      } else if (keyVal[0] === "isAdmin") {
        isAdmin = parseInt(keyVal[1].trim()) || 0;
      } else if (keyVal[0] === "isEnabled") {
        isEnabled = parseInt(keyVal[1].trim()) || 0;
      }
    }
  }

  if (userId < 0 || isNaN(userId)) {
    window.location.href = "index.html";
  } else {
    // Block user if disabled account
    if (isEnabled !== 1) {
        doLogout();
        return;
    }

    let userNameEl = document.getElementById("userName");
    if (userNameEl) {
      userNameEl.innerHTML = `<i class="bi bi-person-circle me-1 text-primary"></i> <span>Logged in as <strong class="text-white">${firstName} ${lastName}</strong></span>`;
    }
  }
}

function doLogout() {
  userId = 0;
  firstName = "";
  lastName = "";
  isAdmin = 0;
  isEnabled = 0;

  document.cookie = "firstName=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  document.cookie = "lastName=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  document.cookie = "userId=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  document.cookie = "isAdmin=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  document.cookie = "isEnabled=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  window.location.href = "index.html";
}

function registerUser() {
    let loginInput = document.getElementById("registerLogin");
    let passwordInput = document.getElementById("registerPassword");
    let firstNameInput = document.getElementById("registerFirstName");
    let lastNameInput = document.getElementById("registerLastName");

    let login = loginInput ? loginInput.value.trim() : "";
    let password = passwordInput ? passwordInput.value.trim() : "";
    let firstNameValue = firstNameInput ? firstNameInput.value.trim() : "";
    let lastNameValue = lastNameInput ? lastNameInput.value.trim() : "";
    let resultEl = document.getElementById("registerResult");

    resultEl.innerHTML = "";

    if (!login || !password || !firstNameValue || !lastNameValue) {
        resultEl.className = "text-warning small fw-semibold";
        resultEl.innerHTML = "<i class='bi bi-exclamation-triangle-fill me-1'></i> Please fill in all fields";
        return;
    }

    let jsonPayload = JSON.stringify({
        login: login,
        password: password,
        firstName: firstNameValue,
        lastName: lastNameValue
    });

    let url = urlBase + "?action=register";

    let xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-type", "application/json; charset=UTF-8");
    try {
        xhr.onreadystatechange = function() {
            if (this.readyState === 4) {
                if (this.status === 201 || this.status === 200)  {
                    resultEl.className = "text-success-wcag small fw-semibold";
                    resultEl.innerHTML = "<i class='bi bi-check-circle-fill me-1'></i> Registration successful!";

                    loginInput.value = "";
                    passwordInput.value = "";
                    firstNameInput.value = "";
                    lastNameInput.value = "";
                } else {
                    try {
                        let res = JSON.parse(xhr.responseText);
                        resultEl.className = "text-danger-wcag small fw-semibold";
                        resultEl.innerHTML = res.error || "Registration failed";
                    } catch (e) {
                        resultEl.className = "text-danger-wcag small fw-semibold";
                        resultEl.innerHTML = "Error registering user";
                    }
                }
            }
        };

        xhr.send(jsonPayload);
    } catch (err) {
        resultEl.className = "text-danger-wcag small fw-semibold";
        resultEl.innerHTML = err.message;
    }
}

function addContact() {
    let firstNameInput = document.getElementById("contactFirstName");
    let lastNameInput = document.getElementById("contactLastName")
    let emailInput = document.getElementById("contactEmail");
    let phoneInput = document.getElementById("contactPhone");
    let nicknameInput = document.getElementById("contactNickname");
    let addressInput = document.getElementById("contactAddress");

    let firstNameValue = firstNameInput ? firstNameInput.value.trim() : "";
    let lastNameValue = lastNameInput ? lastNameInput.value.trim() : "";
    let emailValue = emailInput ? emailInput.value.trim() : "";
    let phoneValue = phoneInput ? phoneInput.value.trim() : "";
    let nicknameValue = nicknameInput ? nicknameInput.value.trim() : "";
    let addressValue = addressInput ? addressInput.value.trim() : "";
    let profilePicValue = newContactProfilePic || "";

    let resultEl = document.getElementById("contactAddResult");
    resultEl.innerHTML = "";
    if (!firstNameValue || !lastNameValue) {
        resultEl.className = "text-warning small fw-semibold";
        resultEl.innerHTML = "<i class='bi bi-exclamation-triangle-fill me-1'></i> First and last name are required";
        return;
    }

    let jsonPayload = JSON.stringify({
        firstName: firstNameValue,
        lastName: lastNameValue,
        email: emailValue,
        phone: phoneValue,
        nickname: nicknameValue,
        address: addressValue,
        profilePic: profilePicValue
    });

    let url = urlBase + "?action=contactAdd";
    let xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-type", "application/json; charset=UTF-8");
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState === 4) {
                if (this.status === 201 || this.status === 200) {
                    resultEl.className = "text-success-wcag small fw-semibold";
                    resultEl.innerHTML = "<i class='bi bi-check-circle-fill me-1'></i> Contact successfully added!";

                    firstNameInput.value = "";
                    lastNameInput.value = "";
                    emailInput.value = "";
                    phoneInput.value = "";
                    if (nicknameInput) nicknameInput.value = "";
                    if (addressInput) addressInput.value = "";
                    newContactProfilePic = "";
                    let newAvatarWrap = document.getElementById("newContactAvatarWrap");
                    if (newAvatarWrap) newAvatarWrap.innerHTML = avatarImg("");
                    let newContactPicFile = document.getElementById("newContactProfilePicFile");
                    if (newContactPicFile) newContactPicFile.value = "";

                    searchContacts();
                } else {
                    try {
                        let res = JSON.parse(xhr.responseText);

                        resultEl.className = "text-danger-scag small fw-semibold";
                        resultEl.innerHTML = res.error || "Failed to add contact";
                    } catch (e) {
                        resultEl.className = "text-danger-wcag small fw-semibold";
                        resultEl.innerHTML = "Error adding contact";
                    }
                }
            }
        };
        xhr.send(jsonPayload);
    } catch (err) {
        resultEl.className = "text-danger-wcag small fw-semibold";
        resultEl.innerHTML = err.message;
    }
}

// Contacts are paginated (5 per page) rather than loaded all at once.
// Pass a page number to jump to it (e.g. from the Prev/Next controls);
// omit it to search fresh from page 1.
function searchContacts(page) {
  let srchInput = document.getElementById("searchText");
  let srch = srchInput ? srchInput.value.trim() : "";
  let resultSpan = document.getElementById("contactSearchResult");
  resultSpan.innerHTML = "";

  currentContactPage = (typeof page === "number" && page > 0) ? page : 1;

  let url = urlBase + "?action=contactSearch"
    + (srch ? ("&q=" + encodeURIComponent(srch)) : "")
    + "&page=" + currentContactPage
    + "&limit=" + contactsPerPage;

  let xhr = new XMLHttpRequest();
  xhr.open("GET", url, true);
  xhr.setRequestHeader("Authorization", "Bearer " + userId);
  xhr.setRequestHeader("X-User-Id", userId);

  try {
    xhr.onreadystatechange = function () {
      if (this.readyState !== 4) return;
      if (this.status === 200) {
        resultSpan.innerHTML = "<i class='bi bi-check-circle me-1'></i> Results updated";
        let jsonObject = JSON.parse(xhr.responseText);
        let targetP = document.getElementById("contactList") || document.getElementsByTagName("p")[0];
        let paginationEl = document.getElementById("contactPagination");

        let contacts = jsonObject.contacts || [];
        if (contacts.length === 0 && Array.isArray(jsonObject.results) && jsonObject.results.length > 0) {
          contacts = jsonObject.results.map(name => ({ id: null, name: name }));
        }

        if (typeof jsonObject.page === "number") currentContactPage = jsonObject.page;

        if (contacts.length === 0 || jsonObject.error === "No Records Found") {
          if (targetP) targetP.innerHTML = `<div class="text-secondary-contrast small italic py-2"><i class="bi bi-info-circle me-1"></i> No matching contacts found.</div>`;
          if (paginationEl) paginationEl.innerHTML = "";
          return;
        }

        let contactList = "";
        for (let i = 0; i < contacts.length; i++) {
          let c = contacts[i];

          contactList += `<div class="contact-item border rounded p-3 mb-2">
          ${dateBadge(c.dateCreated, c.dateUpdated)}
          <div class="avatar-picker">
            <div id="avatarWrap-${c.id}" class="avatar-wrap" data-profile-pic="${c.profilePic || ""}" onclick="triggerProfilePicPicker(${c.id})">${avatarImg(c.profilePic)}</div>
            <div id="avatarHint-${c.id}" class="avatar-hint" style="display:none;">Click photo<br>to change</div>
            <input type="file" id="profilePicFile-${c.id}" accept="image/*" style="display:none;" onchange="handleProfilePicSelect(${c.id}, this)">
          </div>
          <input id="firstName-${c.id}" value="${c.firstName || ""}" disabled class="form-control mb-2" placeholder="First name">
          <input id="lastName-${c.id}" value="${c.lastName || ""}" disabled class="form-control mb-2" placeholder="Last name">
          <input id="nickname-${c.id}" value="${c.nickname || ""}" disabled class="form-control mb-2" placeholder="Super Name">
          <input id="email-${c.id}" value="${c.email || ""}" disabled class="form-control mb-2" placeholder="Email">
          <input id="phone-${c.id}" value="${c.phone || ""}" disabled class="form-control mb-2" placeholder="Phone">
          <input id="address-${c.id}" value="${c.address || ""}" disabled class="form-control mb-2" placeholder="Address">
          <button id="editButton-${c.id}" type="button" class="btn btn-primary" onclick="editContact(${c.id})">Edit</button>
          <button id="deleteButton-${c.id}" type="button" class="btn btn-danger" onclick="deleteContact(${c.id})">Delete</button>
          <button id="saveButton-${c.id}" type="button" class="btn btn-success" style="display:none;" onclick="saveContact(${c.id})">Save</button>
          <button id="cancelButton-${c.id}" type="button" class="btn btn-secondary" style="display:none;" onclick="cancelEdit(${c.id})">Cancel</button>
          <div id="contactMsg-${c.id}" class="mt-2 w-100"></div>
          </div>`
        }

        if (targetP) {
          targetP.innerHTML = contactList;
        }

        if (paginationEl) {
          let totalPages = typeof jsonObject.totalPages === "number" ? jsonObject.totalPages : 1;
          if (totalPages <= 1) {
            paginationEl.innerHTML = "";
          } else {
            let prevDisabled = currentContactPage <= 1 ? "disabled" : "";
            let nextDisabled = currentContactPage >= totalPages ? "disabled" : "";
            paginationEl.innerHTML = `
              <button type="button" class="btn btn-sm btn-secondary" ${prevDisabled} onclick="searchContacts(${currentContactPage - 1})">
                <i class="bi bi-chevron-left"></i> Prev
              </button>
              <span class="page-indicator mx-2">Page ${currentContactPage} of ${totalPages}</span>
              <button type="button" class="btn btn-sm btn-secondary" ${nextDisabled} onclick="searchContacts(${currentContactPage + 1})">
                Next <i class="bi bi-chevron-right"></i>
              </button>`;
          }
        }
      } else {
        try {
            let res = JSON.parse(xhr.responseText);
            resultSpan.innerHTML = res.error || "Failed to search contacts";
        } catch (e) {
            resultSpan.innerHTML = "Error searching contacts";
        }
      }
    };

    xhr.send();
  } catch (err) {
    resultSpan.innerHTML = err.message;
  }
}

function deleteContact(contactId) {
  if (!contactId && contactId !== 0) return;

  let url = urlBase + "?action=contactDel&id=" + encodeURIComponent(contactId);

  let xhr = new XMLHttpRequest();
  xhr.open("DELETE", url, true);
  xhr.setRequestHeader("Authorization", "Bearer " + userId);
  xhr.setRequestHeader("X-User-Id", userId);

  try {
    xhr.onreadystatechange = function () {
      if (this.readyState !== 4) return;
      if (this.status === 200) {
        searchContacts();
      } else {
        try {
            let res = JSON.parse(xhr.responseText);
            showStatus("contactSearchResult", res.error || "Failed to delete contact", "error");
        } catch (e) {
            showStatus("contactSearchResult", "Error deleting contact", "error");
        }
      }
    };
    xhr.send();
  } catch (err) {
    console.error(err);
  }
}

// Search for admin dashboard. Users are paginated (5 per page) the same
// way contacts are. Pass a page number to jump to it; omit it to search
// fresh from page 1.
function searchUsers(page) {
    // Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let srchInput = document.getElementById("userSearchText");
    let srch = srchInput ? srchInput.value.trim() : "";
    let resultSpan = document.getElementById("userSearchResult");
    resultSpan.innerHTML = "";

    currentUserPage = (typeof page === "number" && page > 0) ? page : 1;

    let url = urlBase + "?action=userSearch"
        + (srch ? "&q=" + encodeURIComponent(srch) : "")
        + "&page=" + currentUserPage
        + "&limit=" + usersPerPage;

    let xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                let jsonObject = JSON.parse(xhr.responseText);
                let users = jsonObject.users || [];
                let targetP = document.getElementById("userList");
                let paginationEl = document.getElementById("userPagination");

                if (typeof jsonObject.page === "number") currentUserPage = jsonObject.page;

                if (users.length === 0) {
                    if (targetP) {
                        targetP.innerHTML = `<div class="text-secondary-contrast small italic py-2"
                        <i class="bi bi-info-circle me-1"></i>
                        No users found.
                        </div>`;
                    }
                    if (paginationEl) paginationEl.innerHTML = "";
                    return;
                }

                let userList = "";
                for (let i = 0; i < users.length; i++) {
                    let u = users[i];
                    let enabled = parseInt(u.isEnabled) === 1;
                    let admin = parseInt(u.isAdmin) === 1;
                    let status = enabled ? "Enabled" : "Disabled";
                    let adminStatus = admin ? "Administrator" : "Standard User";

                    // Disable/Enable and Make Admin/Remove Admin swap in place
                    // depending on the user's current state.
                    let statusButton = enabled
                        ? `<button type="button" class="btn btn-sm btn-danger" onclick="disableUser(${u.id})">Disable User</button>`
                        : `<button type="button" class="btn btn-sm btn-success" onclick="enableUser(${u.id})">Enable User</button>`;

                    let adminButton = admin
                        ? `<button type="button" class="btn btn-sm btn-secondary" onclick="removeAdmin(${u.id})">Remove Admin</button>`
                        : `<button type="button" class="btn btn-sm btn-warning" onclick="makeAdmin(${u.id})">Make Admin</button>`;

                    userList += `<div class="user-item border rounded p-3 mb-2">
                    ${dateBadge(u.dateCreated, u.dateUpdated)}
                    <strong>${u.firstName} ${u.lastName}</strong>
                    <div class="fact-chip">Login: ${u.login}</div>
                    <div class="fact-chip">Account Staus: ${status}</div>
                    <div class="fact-chip">Account Type: ${adminStatus}</div>
                    <div class="button-row">
                    ${statusButton}
                    ${adminButton}
                    <button type="button" class="btn btn-sm btn-warning" onclick="editPassword(${u.id})">Change Password</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="viewUserContacts(${u.id})">View Contacts</button>
                    </div>
                    <div id="password-${u.id}" class="mt-2"></div>
                    <div id="userContacts-${u.id}" class="mt-2"></div>
                    </div>`;
                }

                if (targetP) {
                    targetP.innerHTML = userList;
                }

                if (paginationEl) {
                    let totalPages = typeof jsonObject.totalPages === "number" ? jsonObject.totalPages : 1;
                    if (totalPages <= 1) {
                        paginationEl.innerHTML = "";
                    } else {
                        let prevDisabled = currentUserPage <= 1 ? "disabled" : "";
                        let nextDisabled = currentUserPage >= totalPages ? "disabled" : "";
                        paginationEl.innerHTML = `
                          <button type="button" class="btn btn-sm btn-secondary" ${prevDisabled} onclick="searchUsers(${currentUserPage - 1})">
                            <i class="bi bi-chevron-left"></i> Prev
                          </button>
                          <span class="page-indicator mx-2">Page ${currentUserPage} of ${totalPages}</span>
                          <button type="button" class="btn btn-sm btn-secondary" ${nextDisabled} onclick="searchUsers(${currentUserPage + 1})">
                            Next <i class="bi bi-chevron-right"></i>
                          </button>`;
                    }
                }
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    resultSpan.innerHTML = res.error || "Failed to search users";
                } catch (e) {
                    resultSpan.innerHTML = "Error searching users";
                }
            }
        };
        xhr.send();
    } catch (err) {
        resultSpan.innerHTML = err.message;
    }
}

// Search contacts for admin when on a user
function viewUserContacts(targetId) {
    let container = document.getElementById("userContacts-" + targetId);

    // Check if admin
    if (isAdmin !== 1) {
        showStatus(container || "userSearchResult", "Administrator access required", "error");
        return;
    }

    if (!container) return;

    // Toggle closed if already open
    if (container.dataset.open === "true") {
        container.innerHTML = "";
        container.dataset.open = "false";
        return;
    }

    container.innerHTML = `<div class="text-secondary-contrast small py-1"><i class="bi bi-hourglass-split me-1"></i>Loading contacts…</div>`;

    let url = urlBase + "?action=userContacts&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                let jsonObject = JSON.parse(xhr.responseText);
                let contacts = jsonObject.contacts || [];

                if (contacts.length === 0) {
                    container.innerHTML = `<div class="text-secondary-contrast small fst-italic py-1"><i class="bi bi-info-circle me-1"></i> This user has no contacts.</div>`;
                    container.dataset.open = "true";
                    return;
                }

                let list = "";
                for (let i = 0; i < contacts.length; i++) {
                    let c = contacts[i];
                    list += `<div class="contact-item border rounded p-2 mb-2 small">
                        ${dateBadge(c.dateCreated, c.dateUpdated)}
                        <div class="avatar-wrap avatar-wrap-sm">${avatarImg(c.profilePic)}</div>
                        <div><strong>${c.firstName || ""} ${c.lastName || ""}</strong>${c.nickname ? " (" + c.nickname + ")" : ""}</div>
                        ${c.email ? `<div>${c.email}</div>` : ""}
                        ${c.phone ? `<div>${c.phone}</div>` : ""}
                        ${c.address ? `<div>${c.address}</div>` : ""}
                    </div>`;
                }
                container.innerHTML = list;
                container.dataset.open = "true";
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    container.innerHTML = `<div class="text-danger-wcag small py-1">${res.error || "Failed to load contacts"}</div>`;
                } catch (e) {
                    container.innerHTML = `<div class="text-danger-wcag small py-1">Error loading contacts</div>`;
                }
                container.dataset.open = "true";
            }
        };
        xhr.send();
    } catch (err) {
        console.error(err);
    }
}

function disableUser(targetId) {
    // Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let url = urlBase + "?action=userDisable&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                searchUsers();
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("userSearchResult", res.error || "Failed to disable user", "error");
                } catch (e) {
                    showStatus("userSearchResult", "Error disabling user", "error");
                }
            }
        };
        xhr.send();
    } catch (err) {
        console.error(err);
    }
}

function enableUser(targetId) {
    // Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let url = urlBase + "?action=userEnable&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                searchUsers();
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("userSearchResult", res.error || "Failed to enable user", "error");
                } catch (e) {
                    showStatus("userSearchResult", "Error enabling user", "error");
                }
            }
        };
        xhr.send();
    } catch (err) {
        console.error(err);
    }
}

function editContact(contactId) {
    let firstNameInput = document.getElementById("firstName-" + contactId);
    let lastNameInput = document.getElementById("lastName-" + contactId);
    let emailInput = document.getElementById("email-" + contactId);
    let phoneInput = document.getElementById("phone-" + contactId);
    let nicknameInput = document.getElementById("nickname-" + contactId);
    let addressInput = document.getElementById("address-" + contactId);
    let avatarWrap = document.getElementById("avatarWrap-" + contactId);
    let avatarHint = document.getElementById("avatarHint-" + contactId);

    let editButton = document.getElementById("editButton-" + contactId);
    let deleteButton = document.getElementById("deleteButton-" + contactId);
    let saveButton = document.getElementById("saveButton-" + contactId);
    let cancelButton = document.getElementById("cancelButton-" + contactId);

    if (!firstNameInput || !lastNameInput || !emailInput || !phoneInput) {
        return;
    }

    // Allows user to edit contact fields
    firstNameInput.disabled = false;
    lastNameInput.disabled = false;
    emailInput.disabled = false;
    phoneInput.disabled = false;
    if (nicknameInput) nicknameInput.disabled = false;
    if (addressInput) addressInput.disabled = false;
    if (avatarWrap) avatarWrap.classList.add("avatar-wrap-editable");
    if (avatarHint) avatarHint.style.display = "block";

    // Hide edit and delete buttons
    editButton.style.display = "none";
    deleteButton.style.display = "none";

    // Show save and cancel buttons
    saveButton.style.display = "inline-block";
    cancelButton.style.display = "inline-block";
}

function saveContact(contactId) {
    let firstNameInput = document.getElementById("firstName-" + contactId);
    let lastNameInput = document.getElementById("lastName-" + contactId);
    let emailInput = document.getElementById("email-" + contactId);
    let phoneInput = document.getElementById("phone-" + contactId);
    let nicknameInput = document.getElementById("nickname-" + contactId);
    let addressInput = document.getElementById("address-" + contactId);
    let avatarWrap = document.getElementById("avatarWrap-" + contactId);

    let firstNameValue = firstNameInput.value.trim();
    let lastNameValue = lastNameInput.value.trim();
    let emailValue = emailInput.value.trim();
    let phoneValue = phoneInput.value.trim();
    let nicknameValue = nicknameInput ? nicknameInput.value.trim() : "";
    let addressValue = addressInput ? addressInput.value.trim() : "";
    let profilePicValue = avatarWrap ? (avatarWrap.dataset.profilePic || "") : "";

    if (!firstNameValue || !lastNameValue) {
        showStatus("contactMsg-" + contactId, "First and last name are required", "warning");
        return;
    }

    let jsonPayload = JSON.stringify({
        firstName: firstNameValue,
        lastName: lastNameValue,
        email: emailValue,
        phone: phoneValue,
        nickname: nicknameValue,
        address: addressValue,
        profilePic: profilePicValue
    });

    let url = urlBase + "?action=contactSave&id=" + encodeURIComponent(contactId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Content-type", "application/json; charset=UTF-8");
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                // Reloads contacts after save
                searchContacts()
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("contactMsg-" + contactId, res.error || "Failed to update contact", "error");
                } catch (e) {
                    showStatus("contactMsg-" + contactId, "Error updating contact", "error");
                }
            }
        };
        xhr.send(jsonPayload);
    } catch (err) {
        console.error(err);
    }
}

function cancelEdit(contactId) {
    //No changes were made, just reload contacts
    searchContacts();
}

function makeAdmin(targetId) {
    // Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let url = urlBase + "?action=makeAdmin&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                searchUsers();
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("userSearchResult", res.error || "Failed to make user an administrator", "error");
                } catch (e) {
                    showStatus("userSearchResult", "Error making user an administrator", "error");
                }
            }
        };
        xhr.send();
    } catch (err) {
        console.error(err);
    }
}

function removeAdmin(targetId) {
    // Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let url = urlBase + "?action=disableAdmin&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                searchUsers();
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("userSearchResult", res.error || "Failed to remove administrator status", "error");
                } catch (e) {
                    showStatus("userSearchResult", "Error removing administrator status", "error");
                }
            }
        };
        xhr.send();
    } catch (err) {
        console.error(err);
    }
}

function editPassword(targetId) {
    //Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let passwordArea = document.getElementById("password-" + targetId);
    if (!passwordArea) {
        return;
    }

    passwordArea.innerHTML =
    `<input type="password" id="newPassword-${targetId}" class="form-control mb-2" placeholder="New password">
    <input type="password" id="confirmPassword-${targetId}" class="form-control mb-2" placeholder="Confirm new password">
    <button type="button" class="btn btn-success" onclick="savePassword(${targetId})">Confirm</button>
    <button type="button" class="btn btn-secondary" onclick="cancelPassword(${targetId})">Cancel</button>
    <div id="passwordMsg-${targetId}" class="mt-2"></div>`;
}

function savePassword(targetId) {
    //Check if admin
    if (isAdmin !== 1) {
        showStatus("userSearchResult", "Administrator access required", "error");
        return;
    }

    let newPasswordInput = document.getElementById("newPassword-" + targetId);
    let confirmPasswordInput = document.getElementById("confirmPassword-" + targetId);

    let newPassword = newPasswordInput ? newPasswordInput.value.trim() : "";
    let confirmPassword = confirmPasswordInput ? confirmPasswordInput.value.trim() : "";

    if (!newPassword || !confirmPassword) {
        showStatus("passwordMsg-" + targetId, "Please enter the password twice.", "warning");
        return;
    }

    if (newPassword !== confirmPassword) {
        showStatus("passwordMsg-" + targetId, "Passwords do not match.", "warning");
        return;
    }

    let jsonPayload =JSON.stringify({
        password: newPassword
    });

    let url = urlBase + "?action=changePassword&id=" + encodeURIComponent(targetId);
    let xhr = new XMLHttpRequest();
    xhr.open("PUT", url, true);
    xhr.setRequestHeader("Content-type", "application/json; charset=UTF-8");
    xhr.setRequestHeader("Authorization", "Bearer " + userId);
    xhr.setRequestHeader("X-User-Id", userId);

    try {
        xhr.onreadystatechange = function () {
            if (this.readyState !== 4) return;
            if (this.status === 200) {
                let passwordArea = document.getElementById("password-" + targetId);
                if (passwordArea) {
                    passwordArea.innerHTML = "";
                    showStatus(passwordArea, "Password successfully changed.", "success");
                }
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    showStatus("passwordMsg-" + targetId, res.error || "Failed to change password", "error");
                } catch (e) {
                    showStatus("passwordMsg-" + targetId, "Error changing password", "error");
                }
            }
        };
        xhr.send(jsonPayload);
    } catch (err) {
        console.error(err);
    }
}

function cancelPassword(targetId) {
    //No changes made, just reload users
    searchUsers();
}