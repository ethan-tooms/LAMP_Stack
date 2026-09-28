<?php

function executeGetCall($action, $db, $userId)
{
    switch ($action) {

        case 'getContactByID':

            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if (!$id) {
                respond(400, ['error' => 'ID is required']);
            }

            $stmt = $db->prepare("
                SELECT
                    ID as id,
                    FirstName as firstName,
                    LastName as lastName,
                    Email as email,
                    Nickname as nickname,
                    Phone as phone,
                    Address as address,
                    ProfilePic as profilePic,
                    UserID as user_id
                FROM Contacts
                WHERE ID = :id
                AND UserID = :uid
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $id,
                ':uid' => $userId
            ]);

            $contact = $stmt->fetch();

            if (!$contact) {
                respond(404, ['error' => 'Contact not found']);
            }

            respond(200, $contact);

            break;


        case 'contactSearch':

            $search = isset($_GET['q'])
                ? trim($_GET['q'])
                : (isset($_GET['search']) ? trim($_GET['search']) : null);

            if ($search !== null && $search !== '') {

                $like = '%' . $search . '%';

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
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
                    WHERE (
                        CONCAT(FirstName, ' ', LastName) LIKE :searchName
                        OR Nickname LIKE :searchNickname
                        OR Email LIKE : searchEmail
                    )
                    AND UserID = :uid
                    ORDER BY FirstName, LastName
                ");

                $stmt->execute([
                    ':uid' => $userId,
                    ':searchName' => $like,
                    ':searchNickname' => $like,
                    ':searchEmail' => $like
                ]);

            } else {

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
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
                    ORDER BY FirstName, LastName
                ");

                $stmt->execute([
                    ':uid' => $userId
                ]);
            }

            $rows = $stmt->fetchAll();

            $results = [];

            foreach ($rows as $row) {
                $results[] = $row['firstName'] . ' ' . $row['lastName'];
            }

            if (empty($rows)) {
                respond(200, [
                    'results' => [],
                    'contacts' => [],
                    'error' => 'No Records Found'
                ]);
            }

            respond(200, [
                'results' => $results,
                'contacts' => $rows,
                'error' => ''
            ]);

            break;


        case 'userSearch':

            $adminCheck = $db->prepare("
                SELECT IsAdmin
                FROM Users
                WHERE ID = :uid
                LIMIT 1
            ");

            $adminCheck->execute([
                ':uid' => $userId
            ]);

            $currentUser = $adminCheck->fetch();

            if (
                !$currentUser ||
                (int) $currentUser['IsAdmin'] !== 1
            ) {
                respond(403, [
                    'error' => 'Admin access required for user search'
                ]);
            }

            $search = isset($_GET['q'])
                ? trim($_GET['q'])
                : (isset($_GET['search']) ? trim($_GET['search']) : null);

            if ($search !== null && $search !== '') {

                $like = '%' . $search . '%';

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
                        FirstName as firstName,
                        LastName as lastName,
                        Login as login,
                        IsAdmin as isAdmin,
                        IsEnabled as isEnabled
                    FROM Users
                    WHERE
                        CONCAT(FirstName, ' ', LastName) LIKE :searchName
                        OR Login LIKE :searchLogin
                    ORDER BY FirstName, LastName
                ");

                $stmt->execute([
                    ':searchName' => $like,
                    ':searchLogin' => $like
                ]);

            } else {

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
                        FirstName as firstName,
                        LastName as lastName,
                        Login as login,
                        IsAdmin as isAdmin,
                        IsEnabled as isEnabled
                    FROM Users
                    ORDER BY FirstName, LastName
                ");

                $stmt->execute();
            }

            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                respond(200, [
                    'users' => [],
                    'error' => 'No Records Found'
                ]);
            }

            respond(200, [
                'users' => $rows,
                'error' => ''
            ]);

            break;


        default:
            respond(404, [
                'error' => 'Unknown GET action'
            ]);
    }
}