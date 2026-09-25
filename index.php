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

                case "userSearch":
                    
                    $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                    $adminCheck->execute([":uid"=> $userId]);
                    $currentUser = $adminCheck->fetch();

                    if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                        respond(403, ['error' => 'Admin access required for user search']);

                    }

                    $search = isset($_GET['q'])  ? trim($_GET['q'])  : (isset($_GET['search']) ? trim($_GET['search']) : null);

                    if($search !== null && $search !== ''){

                        $like = '%' . $search . '%';
                        $stmt = $db->prepare("SELECT ID as id,
                                              FirstName as firstName,
                                              LastName as lastName,
                                              Login as login,
                                              IsAdmin as isAdmin,
                                              IsEnabled as isEnabled
                                              FROM Users
                                              WHERE (CONCAT(FirstName, ' ', LastName) LIKE :searchName) OR (Login LIKE :searchLogin)
                                              ORDER BY FirstName, LastName");
                        $stmt->execute([':searchName' => $like, ':searchLogin' => $like]);

                        $rows = $stmt->fetchAll();

                        if(empty($rows)){

                            respond(200, ['users' => [], 'error' => 'No Records Found']);
 
                        }

                        respond(200, ['users' => $rows, 'error' => '']);
    
                    }
                    else{

                        $stmt = $db->prepare("SELECT ID as id,
                              FirstName as firstName,
                              LastName as lastName,
                              Login as login,
                              IsAdmin as isAdmin,
                              IsEnabled as isEnabled
                              FROM Users
                              ORDER BY FirstName, LastName");

                        $stmt->execute();

                        $rows = $stmt->fetchAll();

                        if(empty($rows)){

                            respond(200, ['users' => [], 'error' => 'No Records Found']);
 
                        }

                        respond(200, ['users' => $rows, 'error' => '']);

                    }

                    break;

        }

        break;

}