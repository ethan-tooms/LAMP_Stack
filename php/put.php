<?php

function executePutCall($action, $db, $userId)
{
    switch ($action) {

        case 'contactSave':
            // move current contactSave code here
            break;

        case 'userDisable':
            // move current userDisable code here
            break;

        case 'makeAdmin':
            // move current makeAdmin code here
            break;

        case 'changePassword':
            // move current changePassword code here
            break;

        default:
            respond(404, [
                'error' => 'Unknown PUT action'
            ]);
    }
}