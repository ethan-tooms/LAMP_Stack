-- ============================================================
-- SQL Schema Script: create_tables.sql
-- Project: COP4331 LAMP Group Project (Contacts Manager)
-- Description: Creates the ContactsAppDB database, Users table,
--              Contacts table, and grants user permissions.
-- ============================================================

-- 1. Create and select the database
CREATE DATABASE IF NOT EXISTS `ContactsAppDB`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `ContactsAppDB`;

-- 2. Create Users Table
CREATE TABLE IF NOT EXISTS `Users` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL DEFAULT '',
    `LastName` VARCHAR(50) NOT NULL DEFAULT '',
    `Login` VARCHAR(50) NOT NULL DEFAULT '',
    `Password` VARCHAR(50) NOT NULL DEFAULT '',
    `IsAdmin` BOOLEAN NOT NULL DEFAULT 0, 
    `IsAuthorized` BOOLEAN NOT NULL DEFAULT 1, 
    `DateCreated` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `DateUpdated` DATE NOT NULL DEFAULT (CURRENT_DATE), -- API must handle date updates

    PRIMARY KEY (`ID`),
    UNIQUE INDEX `idx_users_login` (`Login`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Contacts Table
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

-- 4. Create Application Database User & Grant Permissions
-- Note: Replace password if desired for custom deployments.

