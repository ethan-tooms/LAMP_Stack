<?php
// ============================================================
//  api/index.php — Unified Colors Manager RESTful API
//
//  GET    /api/index.php?ping=1   — status ping health check
//  POST   /api/index.php (login)  — authenticate user
//  GET    /api/index.php          — list all colors for user
//  GET    /api/index.php?q=term   — partial search colors
//  GET    /api/index.php?id=1     — get single color by ID
//  POST   /api/index.php (color)  — create new color
//  PUT    /api/index.php?id=1     — update color by ID
//  DELETE /api/index.php?id=1     — delete color by ID
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

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
            $password = clean($body['password']);

            if (!$login || !$password)
                respond(400, ['error' => 'Login and password are required']);

            $stmt = $db->prepare('
                SELECT ID, FirstName, LastName, IsAdmin, IsEnabled
                FROM Users
                WHERE Login = :login
                AND Password = :password
                LIMIT 1');
            $stmt->execute([':login' => $login, ':password' => $password]);
            $user = $stmt->fetch();

            if (!$user) {
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
            $password = clean($body['password']);
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
            $stmt->execute([':firstName' => $firstName, ':lastName' => $lastName, ':login' => $login, ':password' => $password]);
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

    case "GET":

        switch($action){

            //?action=getContactByID
            case 'getContactByID':

                $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

                // Single contact by ID
                if($id){

                    $stmt = $db->prepare("SELECT ID as id,
                                          FirstName as firstName,
                                          LastName as lastName,
                                          Email as email,
                                          Nickname as nickname,
                                          Phone as phone,
                                          Address as address,
                                          ProfilePic as profilePic,
                                          UserID as user_id
                                          FROM Contacts
                                          WHERE ID = :id AND UserID = :uid LIMIT 1");

                    $stmt->execute([':id' => $id, ':uid' => $userId]);
                    $contact = $stmt->fetch(); //$contact["nane"] $contact["Name"]
                    if(!$contact){
                        respond(404, ['error' => 'Contact not found']);
                    }

                    respond(200, $contact);

                }

                if(!$id){

                    //when ?id=??? is not included, returns an error
                    respond(400, ['error' => 'ID is required']);

                }

                break;

            //?action=contactSearch
            case 'contactSearch':

                $search = isset($_GET['q'])  ? trim($_GET['q'])  : (isset($_GET['search']) ? trim($_GET['search']) : null);

                //partial search contacts by first name or last name or both. Otherwise, lists all contacts (TODO)
                if ($search !== null && $search !== '') {

                    $like = '%' . $search . '%';
                    $stmt = $db->prepare("SELECT ID as id,
                                          FirstName as firstName,
                                          LastName as lastName,
                                          Email as email,
                                          Nickname as nickname,
                                          Phone as phone,
                                          Address as address,
                                          ProfilePic as profilePic,
                                          DateCreated as dateCreated,
                                          DateUpdated as dateUpdated
                                          FROM Contacts
                                          WHERE (CONCAT(FirstName, ' ', LastName) LIKE :searchName OR Nickname LIKE :searchNickname) AND UserID = :uid
                                          ORDER BY FirstName, LastName");
                    $stmt->execute([':uid' => $userId, ':searchName' => $like, ':searchNickname' => $like]);
                    $rows = $stmt->fetchAll();
                    $results = [];
                    foreach ($rows as $row) {
                        $results[] = $row['firstName'] . ' ' . $row['lastName'];
                    }

                    if(empty($results)){
                        respond(200, ['results' => [], 'contacts' => [], 'error' => 'No Records Found']);
                    }

                    respond(200, ['results' => $results, 'contacts' => $rows, 'error' => '']);

                }
                else{
                    //if no search term is provided, return all contacts for the user
                    $stmt = $db->prepare("SELECT ID as id,
                                          FirstName as firstName,
                                          LastName as lastName,
                                          Email as email,
                                          Nickname as nickname,
                                          Phone as phone,
                                          Address as address,
                                          ProfilePic as profilePic,
                                          DateCreated as dateCreated,
                                          DateUpdated as dateUpdated
                                          FROM Contacts
                                          WHERE UserID = :uid
                                          ORDER BY FirstName, LastName");

                    $stmt->execute([':uid' => $userId]);
                    $rows = $stmt->fetchAll();
                    $results = [];
                    foreach ($rows as $row) {
                        $results[] = $row['firstName'] . ' ' . $row['lastName'];
                    }

                    if(empty($results)){
                        respond(200, ['results' => [], 'contacts' => [], 'error' => 'No Records Found']);
                    }

                    respond(200, ['results' => $results, 'contacts' => $rows, 'error' => '']);


                }

                break;

        }

}

/*
 *
 *
 *ANYTHING BELOW THIS LINE IS FROM THE ORIGINAL COLORS API, WHICH IS
 *FOR REFERENCE ONLY. IT IS NOT PART OF THE NEW AUTHENTICATED API.
 *
 *
 */

switch ($method) {

    // ── GET: search, list, or single color ──────────────────
    case 'GET':
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $search = isset($_GET['q'])  ? trim($_GET['q'])  : (isset($_GET['search']) ? trim($_GET['search']) : null);

        // Single color by ID
        if ($id) {
            $stmt = $db->prepare('SELECT ID as id, Name as name, UserID as user_id FROM Colors WHERE ID = :id AND UserID = :uid LIMIT 1');
            $stmt->execute([':id' => $id, ':uid' => $userId]);
            $color = $stmt->fetch();
            if (!$color) {
                respond(404, ['error' => 'Color not found']);
            }
            respond(200, $color);
        }
        /*SELECT ID as id, FirstName as firstName, LastName as lastName
        FROM Users
        WHERE CONCAT(FirstName, ' ', LastName) LIKE :search*/
        // Search colors (partial match)
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $stmt = $db->prepare('SELECT ID as id, Name as name FROM Colors WHERE UserID = :uid AND Name LIKE :q ORDER BY Name');
            $stmt->execute([':uid' => $userId, ':q' => $like]);
            $rows = $stmt->fetchAll();
            $results = array_column($rows, 'name');
            if (empty($results)) {
                respond(200, ['results' => [], 'colors' => [], 'error' => 'No Records Found']);
            }
            respond(200, ['results' => $results, 'colors' => $rows, 'error' => '']);
        }

        // List all colors
        $stmt = $db->prepare('SELECT ID as id, Name as name FROM Colors WHERE UserID = :uid ORDER BY Name');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        $results = array_column($rows, 'name');
        if (empty($results)) {
            respond(200, ['results' => [], 'colors' => [], 'error' => 'No Records Found']);
        }
        respond(200, ['results' => $results, 'colors' => $rows, 'error' => '']);
        break;

    // ── POST: create color ───────────────────────────────────
    case 'POST':
        $body  = getRequestBody();
        $color = clean($body['color'] ?? $body['name'] ?? '');
        if (!$color) {
            respond(400, ['error' => 'Color name is required']);
        }

        $stmt = $db->prepare('INSERT INTO Colors (UserID, Name) VALUES (:uid, :name)');
        $stmt->execute([':uid' => $userId, ':name' => $color]);

        respond(201, [
            'message' => 'Color created',
            'id'      => (int) $db->lastInsertId(),
            'error'   => ''
        ]);
        break;

    // ── PUT: update color ─────────────────────────────────────
    case 'PUT':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) {
            respond(400, ['error' => 'Color ID is required — use ?id=']);
        }

        $check = $db->prepare('SELECT ID FROM Colors WHERE ID = :id AND UserID = :uid LIMIT 1');
        $check->execute([':id' => $id, ':uid' => $userId]);
        if (!$check->fetch()) {
            respond(404, ['error' => 'Color not found']);
        }

        $body  = getRequestBody();
        $color = clean($body['color'] ?? $body['name'] ?? '');
        if (!$color) {
            respond(400, ['error' => 'Color name is required']);
        }

        $stmt = $db->prepare('UPDATE Colors SET Name = :name WHERE ID = :id AND UserID = :uid');
        $stmt->execute([':name' => $color, ':id' => $id, ':uid' => $userId]);

        respond(200, ['message' => 'Color updated', 'error' => '']);
        break;

    // ── DELETE: delete color ──────────────────────────────────
    case 'DELETE':
        $id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $name = isset($_GET['name']) ? clean($_GET['name']) : '';

        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM Colors WHERE ID = :id AND UserID = :uid');
            $stmt->execute([':id' => $id, ':uid' => $userId]);
        } elseif ($name !== '') {
            $stmt = $db->prepare('DELETE FROM Colors WHERE Name = :name AND UserID = :uid LIMIT 1');
            $stmt->execute([':name' => $name, ':uid' => $userId]);
        } else {
            respond(400, ['error' => 'Color ID or Name is required — use ?id= or ?name=']);
        }

        if ($stmt->rowCount() === 0) {
            respond(404, ['error' => 'Color not found']);
        }

        respond(200, ['message' => 'Color deleted', 'error' => '']);
        break;

    default:
        respond(405, ['error' => 'Method not allowed']);
}