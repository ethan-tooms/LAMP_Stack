<?php

/*
====================================================
25. QUICK HANDOFF FOR API TEAM
====================================================
Please implement these routes/actions to match the current frontend:
POST
- [no action] = login
- ?action=register
- ?action=contactAdd
GET
- ?action=contactSearch
- ?action=userSearch
PUT
- ?action=contactSave&id=ID
- ?action=userDisable&id=ID
- ?action=makeAdmin&id=ID
- ?action=changePassword&id=ID
DELETE
- ?action=contactDel&id=ID
Login must return:
- id
- firstName
- lastName
- isAdmin
- isEnabled
Registration should create:
- isAdmin = 0
- isEnabled = 1
Authenticated requests currently send:
- Authorization: Bearer USER_ID
- X-User-Id: USER_ID
Admin endpoints must verify administrator privileges server-side.
Contact endpoints must verify contact ownership server-side.
*/


require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

require_once __DIR__ . '/php/get.php';
require_once __DIR__ . '/php/post.php';
require_once __DIR__ . '/php/put.php';
require_once __DIR__ . '/php/delete.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db     = getDB();

// 1. Unauthenticated Health Check (Ping)
if ($method === 'GET' && (isset($_GET['ping']) || (isset($_GET['action']) && $_GET['action'] === 'ping'))) {
    respond(200, ['status' => 'OK', 'timestamp' => time()]);
}

// 2. Unauthenticated Login (POST with login & password in body) and Registration (POST with login, password, firstName, lastName in body)
if ($method === 'POST') {

    switch($action){

        case 'login':

            $body = getRequestBody();
            if (!isset($body['login']) || !isset($body['password']))
                respond(400, ['error' => 'Login and password are required']);
            $login    = clean($body['login']);
            $password = is_string($body['password']) ? $body['password'] : '';

            if (!$login || !$password)
                respond(400, ['error' => 'Login and password are required']);

            $stmt = $db->prepare('
                SELECT ID, FirstName, LastName, IsAdmin, IsEnabled, Password
                FROM Users
                WHERE Login = :login
                LIMIT 1');
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['Password'])) {
                respond(401, [
                    'id'        => 0,
                    'firstName' => '',
                    'lastName'  => '',
                    'error'     => 'No Records Found'
                ]);
            }
            respond(200, [
                'id'        => (int) $user['ID'],
                'firstName' => $user['FirstName'],
                'lastName'  => $user['LastName'],
                'isAdmin'   => (int) $user['IsAdmin'],
                'isEnabled' => (int) $user['IsEnabled'],
                'token'     => (string) $user['ID'],
                'error'     => ''
            ]);

            break;

        case 'register':

            $body = getRequestBody();

            if (!isset($body['login']) || !isset($body['password']))
                respond(400, ['error' => 'Login and password are required']);
            if (!isset($body['firstName']) || !isset($body['lastName']))
                respond(400, ['error' => 'First and last names are required']);

            $login    = clean($body['login']);
            $password = is_string($body['password']) ? $body['password'] : '';
            $firstName    = clean($body['firstName']);
            $lastName    = clean($body['lastName']);

            if (!$login || !$password || !$firstName || !$lastName)
                respond(400, ['error' => 'All fields must be filled']);
            $stmt = $db->prepare('
                SELECT ID
                FROM Users
                WHERE Login = :login
                LIMIT 1');
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();

            if ($user) {
                respond(409, ['error'     => 'User already registered. Please log in']);
            }
            $stmt = $db->prepare('
                INSERT INTO Users (FirstName, LastName, Login, Password)
                VALUES (:firstName, :lastName, :login, :password)
            ');
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([':firstName' => $firstName, ':lastName' => $lastName, ':login' => $login, ':password' => $hashedPassword]);
            respond(201, [
                'message' => 'User registered successfully',
                'id'      => (int)$db->lastInsertId(),
                'error'   => ''
            ]);

        break;

    }

}

// 3. All other routes require an authenticated user
$userId = requireAuth();

//switch method to determine which action to take (GET, POST, PUT, DELETE) THEN by "?action=???"
switch ($method) {

    case 'GET':
        executeGetCall($action, $db, $userId);
        break;

    case 'POST':
        executePostCall($action, $db, $userId);
        break;

    case 'PUT':
        executePutCall($action, $db, $userId);
        break;

    case 'DELETE':
        executeDeleteCall($action, $db, $userId);
        break;

    default:
        respond(405, [
            'error' => 'Method not allowed'
        ]);
}