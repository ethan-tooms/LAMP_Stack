const urlBase = (typeof window !== 'undefined' && window.location && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.origin.includes('contacts-4331')))
  ? '/api/index.php'
  : 'https://lamp.contacts-4331.xyz/api/index.php';

const loginUrlBase = urlBase;

let userId = 0;
let firstName = "";
let lastName = "";
let isAdmin = 0;
let isEnabled = 0;

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
            "<i class='bi bi-exclamation-circle-fill me-1'></i> Login failed";
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
        address: addressValue
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

function searchContacts() {
  let srchInput = document.getElementById("searchText");
  let srch = srchInput ? srchInput.value.trim() : "";
  let resultSpan = document.getElementById("contactSearchResult");
  resultSpan.innerHTML = "";

  let url = urlBase + "?action=contactSearch" + (srch ? ("&q=" + encodeURIComponent(srch)) : "");

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

        let contacts = jsonObject.contacts || [];
        if (contacts.length === 0 && Array.isArray(jsonObject.results) && jsonObject.results.length > 0) {
          contacts = jsonObject.results.map(name => ({ id: null, name: name }));
        }

        if (contacts.length === 0 || jsonObject.error === "No Records Found") {
          if (targetP) targetP.innerHTML = `<div class="text-secondary-contrast small italic py-2"><i class="bi bi-info-circle me-1"></i> No matching contacts found.</div>`;
          return;
        }

        let contactList = "";
        for (let i = 0; i < contacts.length; i++) {
          let c = contacts[i];

          contactList += `<div class="contact-item border rounded p-3 mb-2">
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
          </div>`
        }

        if (targetP) {
          targetP.innerHTML = contactList;
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
            alert(res.error || "Failed to delete contact");
        } catch (e) {
            alert("Error deleting contact");
        }
      }
    };
    xhr.send();
  } catch (err) {
    console.error(err);
  }
}

// Search for admin dashboard
function searchUsers() {
    // Check if admin
    if (isAdmin !== 1) {
        alert("Administrator access required");
        return;
    }

    let srchInput = document.getElementById("userSearchText");
    let srch = srchInput ? srchInput.value.trim() : "";
    let resultSpan = document.getElementById("userSearchResult");
    resultSpan.innerHTML = "";

    let url = urlBase + "?action=userSearch" + (srch ? "&q=" + encodeURIComponent(srch) : "");

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

                if (users.length === 0) {
                    if (targetP) {
                        targetP.innerHTML = `<div class="text-secondary-contrast small italic py-2"
                        <i class="bi bi-info-circle me-1"></i>
                        No users found.
                        </div>`;
                    }
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
                    <strong>${u.firstName} ${u.lastName}</strong>
                    <div>Login: ${u.login}</div>
                    <div>Account Staus: ${status}</div>
                    <div>Account Type: ${adminStatus}</div>
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
    // Check if admin
    if (isAdmin !== 1) {
        alert("Administrator access required");
        return;
    }

    let container = document.getElementById("userContacts-" + targetId);
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
        alert("Administrator access required");
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
                    alert(res.error || "Failed to disable user");
                } catch (e) {
                    alert("Error disabling user");
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
        alert("Administrator access required");
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
                    alert(res.error || "Failed to enable user");
                } catch (e) {
                    alert("Error enabling user");
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

    let firstNameValue = firstNameInput.value.trim();
    let lastNameValue = lastNameInput.value.trim();
    let emailValue = emailInput.value.trim();
    let phoneValue = phoneInput.value.trim();
    let nicknameValue = nicknameInput ? nicknameInput.value.trim() : "";
    let addressValue = addressInput ? addressInput.value.trim() : "";

    if (!firstNameValue || !lastNameValue) {
        alert("First and last name are required");
        return;
    }

    let jsonPayload = JSON.stringify({
        firstName: firstNameValue,
        lastName: lastNameValue,
        email: emailValue,
        phone: phoneValue,
        nickname: nicknameValue,
        address: addressValue
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
                    alert(res.error || "Failed to update contact");
                } catch (e) {
                    alert("Error updating contact");
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
        alert("Administrator access required");
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
                    alert(res.error || "Failed to make user an administrator");
                } catch (e) {
                    alert("Error making user an administrator");
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
        alert("Administrator access required");
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
                    alert(res.error || "Failed to remove administrator status");
                } catch (e) {
                    alert("Error removing administrator status");
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
        alert("Administrator access required");
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
    <button type="button" class="btn btn-secondary" onclick="cancelPassword(${targetId})">Cancel</button>`;
}

function savePassword(targetId) {
    //Check if admin
    if (isAdmin !== 1) {
        alert("Administrator access required");
        return;
    }

    let newPasswordInput = document.getElementById("newPassword-" + targetId);
    let confirmPasswordInput = document.getElementById("confirmPassword-" + targetId);

    let newPassword = newPasswordInput ? newPasswordInput.value.trim() : "";
    let confirmPassword = confirmPasswordInput ? confirmPasswordInput.value.trim() : "";

    if (!newPassword || ! confirmPassword) {
        alert("Please enter the password twice.");
        return;
    }

    if (newPassword !== confirmPassword) {
        alert("Passwords do not match.");
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
                alert("Password successfully changed.");
                searchUsers();
            } else {
                try {
                    let res = JSON.parse(xhr.responseText);
                    alert(res.error || "Failed to change password");
                } catch (e) {
                    alert("Error changing password");
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