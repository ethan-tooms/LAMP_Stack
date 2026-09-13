-- ============================================================
-- SQL Seed Script: seed_data.sql
-- Project: COP4331 LAMP Group Project (Contacts Manager)
-- Description: Populates ContactsAppDB with initial Users & Contacts.
-- ============================================================

USE `ContactsAppDB`;

-- 1. Seed Sample Users
-- User 1: Valerie Lopez (Plaintext password for demonstration / testing)
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Valerie', 'Lopez', 'VLopez', 'COP4331');

-- User 2: Peter Parker 
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Peter', 'Parker', 'Spiderman', 'COP4331');

-- User 3: Bruce Wayne
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) 
VALUES ('Bruce', 'Wayne', 'Batman', 'COP4331');


-- 2. Seed Initial Contacts
-- User ID 1 (Valerie Lopez)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`, `UserID`) VALUES 
('Peter', 'Parker', 'Spiderman', '111-111-1111', 'parker@nyc.com','New York City, NY', NULL, 1),
('Bruce', 'Wayne', 'Batman', '222-222-2222', 'wayne@gotham.com', 'Gotham City, NJ', NULL, 1);

-- User ID 2 (Peter Parker)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Nickname`, `Phone`, `Email`, `Address`, `ProfilePic`,`UserID`) VALUES 
('Tony', 'Stark', 'Iron Man', '111-111-1111', 'stark@nyc.com', 'New York City, NY', NULL, 2),
('Bruce', 'Banner', 'Hulk', '111-111-1111', 'banner@nyc.com', 'New York City, NY', NULL, 2);