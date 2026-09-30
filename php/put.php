<?php

function executePutCall($action, $db, $userId)
{
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
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
                    respond(404, ['error' => 'Invalid email format']);
                    exit;
                }
                // Check and normalize number format in DB
                $digitsOnly = preg_replace('/\D/', '', $phone);

                if (strlen($digitsOnly) !== 10) {
                    respond(422, ['error' => 'Invalid phone number, please use 10 digits']);
                    exit;
                }

                // Format as (305) 555 1234
                $formattedPhone = sprintf(
                    '%s-%s-%s',
                    substr($digitsOnly, 0, 3),
                    substr($digitsOnly, 3, 3),
                    substr($digitsOnly, 6, 4)
                );
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

            case "userEnable":

                $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                $adminCheck->execute([":uid"=> $userId]);
                $currentUser = $adminCheck->fetch();

                if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                    respond(403, ['error' => 'Admin access required for user enable']);

                }

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'User ID is required for user enable']);

                }

                if($id === $userId){

                    respond(400, ['error' => 'You cannot enable yourself']);

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



                $stmt->execute([':id' => $id, ':isEnabled' => 1, ':dateUpdated' => $dateUpdated]);

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
            
            case "disableAdmin":

                $adminCheck = $db->prepare("SELECT IsAdmin FROM Users WHERE ID = :uid LIMIT 1");
                $adminCheck->execute([":uid"=> $userId]);
                $currentUser = $adminCheck->fetch();

                if(!$currentUser || (int) $currentUser["IsAdmin"] !== 1){

                    respond(403, ['error' => 'Admin access required for admin disabling']);

                }

                $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

                if(!$id){

                    respond(400, ['error' => 'User ID is required for admin disabling']);

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
                                      IsAdmin = :isAdmin,
                                      DateUpdated = :dateUpdated
                                      WHERE ID = :id");



                $stmt->execute([':id' => $id, ':isAdmin' => 0, ':dateUpdated' => $dateUpdated]);

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
}