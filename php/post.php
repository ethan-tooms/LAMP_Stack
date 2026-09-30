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
            if (!filter_var((clean($body['email'])), FILTER_VALIDATE_EMAIL)){
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


        case 'contactPhotoUpload':

            // Contact photos are real files on disk under /img (alongside
            // the shared default-pfp.jpg), not base64 blobs in the DB -
            // ProfilePic only ever stores the short filename. This is a
            // multipart/form-data POST, so the photo comes in via $_FILES,
            // not the JSON body getRequestBody() reads.

            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                respond(400, ['error' => 'No photo was uploaded, or the upload failed']);
            }

            $file = $_FILES['photo'];

            // Only real images, capped at 2MB, so img/ can't be filled with
            // arbitrary uploads.
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];

            $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : null;

            if (!$mime || !isset($allowedTypes[$mime])) {
                respond(400, ['error' => 'Only JPEG, PNG, GIF, or WEBP images are allowed']);
            }

            if ($file['size'] > 2 * 1024 * 1024) {
                respond(400, ['error' => 'Image must be 2MB or smaller']);
            }

            $imgDir = __DIR__ . '/../../img';

            if (!is_dir($imgDir) || !is_writable($imgDir)) {
                respond(500, ['error' => 'Image directory is not writable on the server']);
            }

            $ext = $allowedTypes[$mime];
            $filename = 'contact-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $imgDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                respond(500, ['error' => 'Failed to save uploaded image']);
            }

            // If this is for an existing contact (picked from edit mode,
            // not the Add Contact form), save it immediately - the photo
            // is independent of that contact's Save/Cancel buttons.
            $contactId = isset($_POST['contactId']) ? (int) $_POST['contactId'] : 0;

            if ($contactId) {

                $ownCheck = $db->prepare("
                    SELECT ID, ProfilePic
                    FROM Contacts
                    WHERE ID = :id AND UserID = :uid
                    LIMIT 1
                ");
                $ownCheck->execute([':id' => $contactId, ':uid' => $userId]);
                $existing = $ownCheck->fetch();

                if (!$existing) {
                    @unlink($destPath);
                    respond(404, ['error' => 'Contact not found']);
                }

                $stmt = $db->prepare("
                    UPDATE Contacts
                    SET ProfilePic = :profilePic
                    WHERE ID = :id AND UserID = :uid
                ");
                $stmt->execute([
                    ':profilePic' => $filename,
                    ':id' => $contactId,
                    ':uid' => $userId
                ]);

                // Clean up the previous photo file, but never delete the
                // shared default image.
                if (!empty($existing['ProfilePic']) && $existing['ProfilePic'] !== 'default-pfp.jpg') {
                    @unlink($imgDir . '/' . basename($existing['ProfilePic']));
                }
            }

            respond(200, [
                'message' => 'Photo uploaded',
                'profilePic' => $filename,
                'error' => ''
            ]);

            break;


        default:
            respond(404, [
                'error' => 'Unknown POST action'
            ]);
    }
}