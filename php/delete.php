<?php

function executeDeleteCall($action, $db, $userId)
{
    switch ($action) {

        case 'contactDel':

            $id = isset($_GET['id'])
                ? (int) $_GET['id']
                : 0;

            if (!$id) {
                respond(400, [
                    'error' => 'Contact ID is required'
                ]);
            }

            $contactCheck = $db->prepare("
                SELECT ID
                FROM Contacts
                WHERE ID = :id
                AND UserID = :uid
                LIMIT 1
            ");

            $contactCheck->execute([
                ':id' => $id,
                ':uid' => $userId
            ]);

            $existingContact = $contactCheck->fetch();

            if (!$existingContact) {
                respond(404, [
                    'error' => 'Contact not found'
                ]);
            }

            $stmt = $db->prepare("
                DELETE FROM Contacts
                WHERE ID = :id
                AND UserID = :uid
            ");

            $stmt->execute([
                ':id' => $id,
                ':uid' => $userId
            ]);

            respond(200, [
                'message' => 'Contact deleted',
                'error' => ''
            ]);

            break;


        default:
            respond(404, [
                'error' => 'Unknown DELETE action'
            ]);
    }
}