-- ============================================================
-- SQL Seed Script: seed_data.sql
-- Project: COP4331 LAMP Group Project (Contacts Manager)
-- Description: Populates ContactsAppDB with initial Users & Contacts.
-- ============================================================

USE `ContactsAppDB`;

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
