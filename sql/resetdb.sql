-- ============================================================
-- SQL Full Reset Script: resetdb.sql
-- Project: COP4331 LAMP LAMP Group Project (Contacts Manager)
-- Description: Drops existing tables if present, recreates schema,
--              seeds users and contacts, and sets up user permissions.
-- ============================================================

-- Create and select database
CREATE DATABASE IF NOT EXISTS `ContactsAppDB`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `ContactsAppDB`;

-- Drop existing tables to ensure a clean state
DROP TABLE IF EXISTS `Contacts`;
DROP TABLE IF EXISTS `Users`;

-- Create Users Table
CREATE TABLE IF NOT EXISTS `Users` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL DEFAULT '',
    `LastName` VARCHAR(50) NOT NULL DEFAULT '',
    `Login` VARCHAR(50) NOT NULL DEFAULT '',
    `Password` VARCHAR(50) NOT NULL DEFAULT '',
    `IsAdmin` BOOLEAN NOT NULL DEFAULT 0, 
    `IsAuthorized` BOOLEAN NOT NULL DEFAULT 1,
    `DateCreated` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `DateUpdated` DATE NOT NULL DEFAULT (CURRENT_DATE), -- API must update this

    PRIMARY KEY (`ID`),
    UNIQUE INDEX `idx_users_login` (`Login`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Contacts Table
CREATE TABLE IF NOT EXISTS `Contacts` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL DEFAULT '',
    `LastName` VARCHAR(50) NOT NULL DEFAULT '',
    `Nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `Phone` VARCHAR(50) NOT NULL DEFAULT '',
    `Email` VARCHAR(50) NOT NULL DEFAULT '',
    `Address` VARCHAR(255) NOT NULL DEFAULT '',
    `ProfilePic` VARCHAR(255) DEFAULT NULL,
    `DateCreated` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `DateUpdated` DATE NOT NULL DEFAULT (CURRENT_DATE), -- API must update this
    `UserID` INT NOT NULL,

    PRIMARY KEY (`ID`),
    INDEX `idx_contacts_userid` (`UserID`),

    CONSTRAINT `fk_contacts_user` FOREIGN KEY (`UserID`) REFERENCES `Users` (`ID`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Seed Sample Users
-- User 1: Nick Fury (Plaintext password for demonstration / testing)
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`, `IsAdmin`, `IsAuthorized`) 
VALUES ('Nick', 'Fury', 'UCF', 'COP4331', 1, 1);

-- User 2: Peter Parker 
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Peter', 'Parker', 'Spiderman', 'COP4331');

-- User 3: Bruce Wayne
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Bruce', 'Wayne', 'Batman', 'COP4331');

-- Seed Initial Contacts
-- User ID 1 (Nick Fury)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`, `UserID`) VALUES 
('Peter', 'Parker', 'Spiderman', '111-111-1111', 'parker@nyc.com','New York City, NY', NULL, 1),
('Bruce', 'Wayne', 'Batman', '222-222-2222', 'wayne@gotham.com', 'Gotham City, NJ', NULL, 1);

-- User ID 2 (Peter Parker)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`,`UserID`) VALUES 
('Tony', 'Stark', 'Iron Man', '111-111-1111', 'stark@nyc.com', 'New York City, NY', NULL, 2),
('Bruce', 'Banner', 'Hulk', '111-111-1111', 'banner@nyc.com', 'New York City, NY', NULL, 2);


-- Create Application Database User & Grant Permissions
-- Note: Replace password if desired for custom deployments.
