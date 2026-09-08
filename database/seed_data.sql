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


-- 2. Seed Initial Palette Contacts
-- User ID 1 (Valerie Lopez)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Phone`, `Email`, `UserID`) VALUES 
('Peter', 'Parker', '111-111-1111', 'spidey@nyc.com', 1),
('Bruce', 'Wayne', '222-222-2222', 'bat@gotham.com', 1);

-- User ID 2 (Peter Parker)
INSERT INTO `Contacts` (`FirstName`, `LastName`, `Phone`, `Email`, `UserID`) VALUES 
('Gwen', 'Stacy', '111-111-1111', 'gwen@nyc.com', 2),
('Harry', 'Osborne', '222-222-2222', 'harry@nyc.com', 2);