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

            // Pagination: contacts are never loaded all at once - max 5 per page.
            $maxLimit = 5;
            $page  = isset($_GET['page'])  ? (int) $_GET['page']  : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : $maxLimit;
            if ($page < 1) { $page = 1; }
            if ($limit < 1 || $limit > $maxLimit) { $limit = $maxLimit; }
            $offset = ($page - 1) * $limit;

            if ($search !== null && $search !== '') {

                $like = '%' . $search . '%';

                $countStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM Contacts
                    WHERE (
                        CONCAT(FirstName, ' ', LastName) LIKE :searchName
                        OR Nickname LIKE :searchNickname
                        OR Email LIKE :searchEmail
                    )
                    AND UserID = :uid
                ");
                $countStmt->execute([
                    ':uid' => $userId,
                    ':searchName' => $like,
                    ':searchNickname' => $like,
                    ':searchEmail' => $like
                ]);
                $totalCount = (int) $countStmt->fetchColumn();

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
                        OR Email LIKE :searchEmail
                    )
                    AND UserID = :uid
                    ORDER BY FirstName, LastName
                    LIMIT $limit OFFSET $offset
                ");

                $stmt->execute([
                    ':uid' => $userId,
                    ':searchName' => $like,
                    ':searchNickname' => $like,
                    ':searchEmail' => $like
                ]);

            } else {

                $countStmt = $db->prepare("SELECT COUNT(*) FROM Contacts WHERE UserID = :uid");
                $countStmt->execute([':uid' => $userId]);
                $totalCount = (int) $countStmt->fetchColumn();

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
                    LIMIT $limit OFFSET $offset
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

            $totalPages = (int) ceil($totalCount / $limit);

            if (empty($rows)) {
                respond(200, [
                    'results' => [],
                    'contacts' => [],
                    'error' => 'No Records Found',
                    'page' => $page,
                    'limit' => $limit,
                    'totalCount' => $totalCount,
                    'totalPages' => $totalPages
                ]);
            }

            respond(200, [
                'results' => $results,
                'contacts' => $rows,
                'error' => '',
                'page' => $page,
                'limit' => $limit,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages
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

            // Pagination: users are never loaded all at once - max 5 per page.
            $maxLimit = 5;
            $page  = isset($_GET['page'])  ? (int) $_GET['page']  : 1;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : $maxLimit;
            if ($page < 1) { $page = 1; }
            if ($limit < 1 || $limit > $maxLimit) { $limit = $maxLimit; }
            $offset = ($page - 1) * $limit;

            if ($search !== null && $search !== '') {

                $like = '%' . $search . '%';

                $countStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM Users
                    WHERE
                        CONCAT(FirstName, ' ', LastName) LIKE :searchName
                        OR Login LIKE :searchLogin
                ");
                $countStmt->execute([
                    ':searchName' => $like,
                    ':searchLogin' => $like
                ]);
                $totalCount = (int) $countStmt->fetchColumn();

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
                        FirstName as firstName,
                        LastName as lastName,
                        Login as login,
                        IsAdmin as isAdmin,
                        IsEnabled as isEnabled,
                        DateCreated as dateCreated,
                        DateUpdated as dateUpdated
                    FROM Users
                    WHERE
                        CONCAT(FirstName, ' ', LastName) LIKE :searchName
                        OR Login LIKE :searchLogin
                    ORDER BY FirstName, LastName
                    LIMIT $limit OFFSET $offset
                ");

                $stmt->execute([
                    ':searchName' => $like,
                    ':searchLogin' => $like
                ]);

            } else {

                $countStmt = $db->prepare("SELECT COUNT(*) FROM Users");
                $countStmt->execute();
                $totalCount = (int) $countStmt->fetchColumn();

                $stmt = $db->prepare("
                    SELECT
                        ID as id,
                        FirstName as firstName,
                        LastName as lastName,
                        Login as login,
                        IsAdmin as isAdmin,
                        IsEnabled as isEnabled,
                        DateCreated as dateCreated,
                        DateUpdated as dateUpdated
                    FROM Users
                    ORDER BY FirstName, LastName
                    LIMIT $limit OFFSET $offset
                ");

                $stmt->execute();
            }

            $rows = $stmt->fetchAll();
            $totalPages = (int) ceil($totalCount / $limit);

            if (empty($rows)) {
                respond(200, [
                    'users' => [],
                    'error' => 'No Records Found',
                    'page' => $page,
                    'limit' => $limit,
                    'totalCount' => $totalCount,
                    'totalPages' => $totalPages
                ]);
            }

            respond(200, [
                'users' => $rows,
                'error' => '',
                'page' => $page,
                'limit' => $limit,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages
            ]);

            break;


        case 'userContacts':

            $adminCheck = $db->prepare("
                SELECT IsAdmin
                FROM Users
                WHERE ID = :uid
                LIMIT 1
            ");
            $adminCheck->execute([':uid' => $userId]);
            $currentUser = $adminCheck->fetch();

            if (!$currentUser || (int) $currentUser['IsAdmin'] !== 1) {
                respond(403, [
                    'error' => 'Administrator access required'
                ]);
            }

            $targetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if (!$targetId) {
                respond(400, ['error' => 'User ID is required']);
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
                    DateCreated as dateCreated,
                    DateUpdated as dateUpdated
                FROM Contacts
                WHERE UserID = :uid
                ORDER BY FirstName, LastName
            ");

            $stmt->execute([':uid' => $targetId]);
            $rows = $stmt->fetchAll();

            respond(200, [
                'contacts' => $rows,
                'error' => ''
            ]);

            break;


        default:
            respond(404, [
                'error' => 'Unknown GET action'
            ]);
    }
}