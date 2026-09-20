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
    `Password` VARCHAR(255) NOT NULL DEFAULT '',
    `IsAdmin` BOOLEAN NOT NULL DEFAULT 0, 
    `IsEnabled` BOOLEAN NOT NULL DEFAULT 1,
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

-- Seed Admin User
-- User 1: Admin User 
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`, `IsAdmin`, `IsEnabled`) 
VALUES ('root', 'root', 'AppAdmin', '$2y$12$L6hTE7YIh7L1nC/RO4SBL.KhuB.FaPXA181wx3WPqyZiPgw.EJt6O', 1, 1);

-- Seed Sample Users
-- User 2: Clark Kent (Plaintext password for demonstration / testing)
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Clark', 'Kent', 'Superman', '$2y$12$njH3NtonUzBDeoXO0UIolu.cjvIautidFEFEUWmQELDKh8oE4YfV2');

-- User 3: Peter Parker 
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Peter', 'Parker', 'Spiderman', '$2y$12$hTytZYMhpCq418CZQlr40Ov6TFbJZWosj78Hvh8.yf/pRJmE/2tx2');

-- User 4: Bruce Wayne
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Bruce', 'Wayne', 'Batman', '$2y$12$ldKrt6EjdQr9C6TPqHUI6.hT0CBOZTsvjkYF2C8Ld4m3KJ/CvEJ66');

-- Seed Initial Contacts
-- User ID 2 (Clark Kent)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`, `UserID`) VALUES 
('Peter', 'Parker', 'Spiderman', '111-111-1111', 'parker@nyc.com','New York City, NY', NULL, 2),
('Bruce', 'Wayne', 'Batman', '222-222-2222', 'wayne@gotham.com', 'Gotham City, NJ', NULL, 2);

-- User ID 3 (Peter Parker)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`,`UserID`) VALUES 
('Tony', 'Stark', 'Iron Man', '111-111-1111', 'stark@nyc.com', 'New York City, NY', NULL, 3),
('Bruce', 'Banner', 'Hulk', '111-111-1111', 'banner@nyc.com', 'New York City, NY', NULL, 3);


-- Create Application Database User & Grant Permissions
-- Note: Replace password if desired for custom deployments.
