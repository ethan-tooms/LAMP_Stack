<?php

function executePostCall($action, $db, $userId)
{
    switch ($action) {

        case 'contactAdd':

            $body = getRequestBody();

            $requiredFields = [
                'firstName',
                'lastName',
                'nickname',
                'phone',
                'email',
                'address'
            ];

            foreach ($requiredFields as $field) {

                if (
                    !isset($body[$field]) ||
                    clean($body[$field]) === ''
                ) {
                    respond(400, [
                        'error' => $field . ' is required'
                    ]);
                }
            }

            $stmt = $db->prepare("
                INSERT INTO Contacts
                (
                    FirstName,
                    LastName,
                    Nickname,
                    Phone,
                    Email,
                    Address,
                    ProfilePic,
                    UserID
                )
                VALUES
                (
                    :firstName,
                    :lastName,
                    :nickname,
                    :phone,
                    :email,
                    :address,
                    :profilePic,
                    :uid
                )
            ");

            $stmt->execute([
                ':firstName' => clean($body['firstName']),
                ':lastName' => clean($body['lastName']),
                ':nickname' => clean($body['nickname']),
                ':phone' => clean($body['phone']),
                ':email' => clean($body['email']),
                ':address' => clean($body['address']),
                ':profilePic' =>
                    isset($body['profilePic'])
                        ? clean($body['profilePic'])
                        : null,
                ':uid' => $userId
            ]);

            respond(201, [
                'message' => 'Contact successfully created',
                'id' => (int) $db->lastInsertId(),
                'error' => ''
            ]);

            break;


        default:
            respond(404, [
                'error' => 'Unknown POST action'
            ]);
    }
}