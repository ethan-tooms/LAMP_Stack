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
                INSERT INTO Users (FirstName, LastName, Login, Password, isAdmin, isEnabled)
                VALUES (:firstName, :lastName, :login, :password, :isAdmin, :isEnabled)
            ');
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([':firstName' => $firstName, ':lastName' => $lastName, ':login' => $login, ':password' => $hashedPassword, ':isAdmin' => 1, 'isEnabled' => 1]);
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

    case "POST":

        switch($action){
 
            case "contactAdd":

                $body = getRequestBody();

                $requiredFields = ['firstName', 'lastName', 'nickname', 'phone', 'email', 'address'];

                foreach ($requiredFields as $currentField){

                    if(!isset($body[$currentField]) || clean($body[$currentField]) === ''){

                        respond(400, ['error' => $currentField . ' is required']);

                    }

                }

                $firstName = clean($body['firstName']);
                $lastName = clean($body['lastName']);
                $nickname = isset($body['nickname']) ? clean($body['nickname']) : null;
                $phone = isset($body['phone']) ? clean($body['phone']) : null;
                $email = isset($body['email']) ? clean($body['email']) : null;
                $address = isset($body['address']) ? clean($body['address']) : null;
                $profilePic = isset($body['profilePic']) ? clean($body['profilePic']) : null;

                if(!$firstName || !$lastName){
                    
                    respond(400, ['error' => 'First and last name cannot be empty']);

                }

                $stmt = $db->prepare("INSERT INTO Contacts
                                      (FirstName,
                                      LastName,
                                      Nickname,
                                      Phone,
                                      Email,
                                      Address,
                                      ProfilePic,
                                      UserID)
                                      VALUES
                                      (:firstName,
                                      :lastName,
                                      :nickname,
                                      :phone,
                                      :email,
                                      :address,
                                      :profilePic,
                                      :uid)");

                $stmt->execute([':firstName' => $firstName, ':lastName' => $lastName,
                                 ':nickname' => $nickname, ':phone' => $phone,
                                 ':email' => $email, ':address' => $address,
                                 ':profilePic' => $profilePic, ':uid' => $userId]);

                respond(201, ['message' => 'Contact successfully created', 'id' => (int) $db->lastInsertId(), 'error' => '']);
                
                break;

        }
        
        break;

    case "PUT":

        switch($action){

            case "contactSave":

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'Contact ID is required - use ?id=']);

                }

                $contactCheck = $db->prepare("SELECT ID FROM Contacts WHERE ID = :id AND UserID = :uid LIMIT 1");

                $contactCheck->execute([':id' => $id, ':uid' => $userId]);
                $existingContact = $contactCheck->fetch();

                if(!$existingContact){

                    respond(404, ['error' => 'Contact not found']);

                }

                $body = getRequestBody();

                $requiredFields = ['firstName', 'lastName', 'nickname', 'phone', 'email', 'address'];

                foreach ($requiredFields as $currentField){

                    if(!isset($body[$currentField]) || clean($body[$currentField]) === ''){

                        respond(400, ['error' => $currentField . ' is required']);

                    }

                }

                $firstName = clean($body['firstName']);
                $lastName = clean($body['lastName']);
                $nickname = isset($body['nickname']) ? clean($body['nickname']) : null;
                $phone = isset($body['phone']) ? clean($body['phone']) : null;
                $email = isset($body['email']) ? clean($body['email']) : null;
                $address = isset($body['address']) ? clean($body['address']) : null;
                $profilePic = isset($body['profilePic']) ? clean($body['profilePic']) : null;
                $dateUpdated = date("Y-m-d");

                $stmt = $db->prepare("UPDATE Contacts SET
                                      FirstName = :firstName,
                                      LastName = :lastName,
                                      Nickname = :nickname,
                                      Phone = :phone,
                                      Email = :email,
                                      Address = :address,
                                      ProfilePic = :profilePic,
                                      DateUpdated = :dateUpdated
                                      WHERE ID = :id AND UserID = :uid");
                
                $stmt->execute([':firstName' => $firstName,
                                ':lastName' => $lastName,
                                ':nickname' => $nickname,
                                ':phone' => $phone,
                                ':email' => $email,
                                ':address' => $address,
                                ':profilePic' => $profilePic,
                                ':dateUpdated' => $dateUpdated,
                                ':id' => $id,
                                ':uid' => $userId]);

                respond(200, ['message' => 'Contact updated', 'error' => '']);

                break;
            
            case "userDisable":

                $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                $adminCheck->execute([":uid"=> $userId]);
                $currentUser = $adminCheck->fetch();

                if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                    respond(403, ['error' => 'Admin access required for user disable']);

                }

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'User ID is required for user disable']);

                }

                if($id === $userId){

                    respond(400, ['error' => 'You cannot disable yourself']);

                }

                $checkID = $db->prepare("SELECT ID FROM Users WHERE ID = :id LIMIT 1");
                $checkID->execute([':id' => $id]);
                $targetUser = $checkID->fetch();

                if (!$targetUser) {

                    respond(404, ['error' => 'User not found']);

                }

                $dateUpdated = date("Y-m-d");

                $stmt = $db->prepare("UPDATE Users SET
                                      IsEnabled = :isEnabled,
                                      DateUpdated = :dateUpdated
                                      WHERE ID = :id");

                
                
                $stmt->execute([':id' => $id, ':isEnabled' => 0, ':dateUpdated' => $dateUpdated]);

                respond(200, ['message' => 'User updated', 'error' => '']);
                
                break;

            case "makeAdmin":

                $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                $adminCheck->execute([":uid"=> $userId]);
                $currentUser = $adminCheck->fetch();

                if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                    respond(403, ['error' => 'Admin access required for admin enabling']);

                }

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'User ID is required for admin enabling']);

                }

                $checkID = $db->prepare("SELECT ID FROM Users WHERE ID = :id LIMIT 1");
                $checkID->execute([':id' => $id]);
                $targetUser = $checkID->fetch();

                if (!$targetUser) {

                    respond(404, ['error' => 'User not found']);

                }

                $dateUpdated = date("Y-m-d");

                $stmt = $db->prepare("UPDATE Users SET
                                      IsAdmin = :isAdmin,
                                      DateUpdated = :dateUpdated
                                      WHERE ID = :id");

                
                
                $stmt->execute([':id' => $id, ':isAdmin' => 1, ':dateUpdated' => $dateUpdated]);

                respond(200, ['message' => 'User updated', 'error' => '']);
                
                break;

            case "changePassword":

                $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                $adminCheck->execute([":uid"=> $userId]);
                $currentUser = $adminCheck->fetch();

                if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                    respond(403, ['error' => 'Admin access required for password changing']);

                }

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'User ID is required for password changing']);

                }

                $checkID = $db->prepare("SELECT ID FROM Users WHERE ID = :id LIMIT 1");
                $checkID->execute([':id' => $id]);
                $targetUser = $checkID->fetch();

                if (!$targetUser) {

                    respond(404, ['error' => 'User not found']);

                }

                $body = getRequestBody();

                if(!isset($body['password'])){

                    respond(400, ['error' => 'Password is required']);
 
                }

                $password = is_string($body['password']) ? $body['password'] : '';

                if(!$password){

                    respond(400, ['error' => 'Password cannot be empty']);

                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $dateUpdated = date("Y-m-d");

                $stmt = $db->prepare("UPDATE Users SET
                                      Password = :password,
                                      DateUpdated = :dateUpdated
                                      WHERE ID = :id");
                
                $stmt->execute([':password' => $hashedPassword, ':dateUpdated' => $dateUpdated, ':id' => $id]);

                respond(200, ['message' => 'Password updated', 'error' => '']);

                break;
        }

        break;
    /*DELETE
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
    
    case "DELETE":
        switch($action){
            case "contactDel":

            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if (!$id) {
                respond(400, ['error' => 'Contact ID is required']);
            }
            $contactCheck = $db->prepare("SELECT ID FROM Contacts WHERE ID = :id AND UserID = :uid LIMIT 1");

            $contactCheck->execute([':id' => $id, ':uid' => $userId]);
            $existingContact = $contactCheck->fetch();

            if(!$existingContact){

                respond(404, ['error' => 'Contact not found']);

            }
            
            $stmt = $db->prepare("DELETE FROM Contacts WHERE ID = :id AND UserID = :uid");
            $stmt->execute([':id' => $id, ':uid' => $userID]);
            respond(200, ['message' => 'Contact deleted', 'error' => '']);

            break;
        }
        
        break;
    */
}