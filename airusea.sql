CREATE TABLE ADMINS (
    adminID INT PRIMARY KEY AUTO_INCREMENT,
    adminName VARCHAR(100),
    adminEmail VARCHAR(100) UNIQUE
);

CREATE TABLE USERS (
    userID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    address VARCHAR(255),
    password VARCHAR(255)
);

CREATE TABLE MOTOR (
    motorID INT PRIMARY KEY AUTO_INCREMENT,
    motortype VARCHAR(50)
);

CREATE TABLE WING (
    wingID INT PRIMARY KEY AUTO_INCREMENT,
    wingtype VARCHAR(50)
);

CREATE TABLE BRAND (
    brandID INT PRIMARY KEY AUTO_INCREMENT,
    brandname VARCHAR(50)
);

CREATE TABLE POWERSOURCE (
    powersourceID INT PRIMARY KEY AUTO_INCREMENT,
    powersource VARCHAR(50)
);

CREATE TABLE DRONES (
    droneID INT PRIMARY KEY AUTO_INCREMENT,
    adminID INT,
    name VARCHAR(100),
    motorID INT,
    wingID INT,
    price DECIMAL(10,2),
    brandID INT,
    powersourceID INT,
    status ENUM('available','rented','maintenance') DEFAULT 'available',
    FOREIGN KEY (adminID) REFERENCES ADMINS(adminID),
    FOREIGN KEY (motorID) REFERENCES MOTOR(motorID),
    FOREIGN KEY (wingID) REFERENCES WING(wingID),
    FOREIGN KEY (brandID) REFERENCES BRAND(brandID),
    FOREIGN KEY (powersourceID) REFERENCES POWERSOURCE(powersourceID)
);

CREATE TABLE RENTALS (
    rentID INT PRIMARY KEY AUTO_INCREMENT,
    userID INT,
    droneID INT,
    rentstart DATE,
    rentdue DATE,
    actualReturn DATE,
    status ENUM('pending','approved','rejected','completed','overdue') DEFAULT 'pending',
    totalprice DECIMAL(10,2),
    penalty DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (userID) REFERENCES USERS(userID),
    FOREIGN KEY (droneID) REFERENCES DRONES(droneID)
);

CREATE TABLE SYSTEM_LOGS (
    logID INT PRIMARY KEY AUTO_INCREMENT,
    actorRole ENUM('user','admin','superadmin'),
    actorID INT,
    action VARCHAR(255),
    tableAffected VARCHAR(50),
    recordID INT,
    actionTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


#Available drones for customer browsing
SELECT d.droneID, d.name, b.brandname, d.price
FROM DRONES d
JOIN BRAND b ON d.brandID = b.brandID
WHERE d.status = 'available';

#Rental History for Admins
SELECT u.name, d.name AS drone, r.status, r.totalprice
FROM RENTALS r
JOIN USERS u ON r.userID = u.userID
JOIN DRONES d ON r.droneID = d.droneID;

#Users with overdue rentals
SELECT name
FROM USERS
WHERE userID IN (
    SELECT userID
    FROM RENTALS
    WHERE status = 'overdue'
);

#Penalty Calculation
DELIMITER $$

CREATE FUNCTION fn_penalty(days_overdue INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    RETURN days_overdue * 100;
END$$

DELIMITER ;


#Admin dashboard
CREATE VIEW vw_active_rentals AS
SELECT r.rentID, u.name, d.name AS drone, r.rentdue, r.status
FROM RENTALS r
JOIN USERS u ON r.userID = u.userID
JOIN DRONES d ON r.droneID = d.droneID
WHERE r.status IN ('approved','overdue');

#Rental request
DELIMITER $$

CREATE PROCEDURE sp_request_rental (
    IN p_userID INT,
    IN p_droneID INT,
    IN p_start DATE,
    IN p_due DATE
)
BEGIN
    INSERT INTO RENTALS (userID, droneID, rentstart, rentdue)
    VALUES (p_userID, p_droneID, p_start, p_due);
END$$

DELIMITER ;


#Admin rental approval
DELIMITER $$

CREATE PROCEDURE sp_approve_rental (IN p_rentID INT)
BEGIN
    UPDATE RENTALS SET status='approved' WHERE rentID=p_rentID;
    UPDATE DRONES
    SET status='rented'
    WHERE droneID = (SELECT droneID FROM RENTALS WHERE rentID=p_rentID);
END$$

DELIMITER ;


#Indeces for searching
CREATE INDEX idx_drone_status ON DRONES(status);
CREATE INDEX idx_rental_status ON RENTALS(status);
CREATE INDEX idx_rental_due ON RENTALS(rentdue);


#Auto-set penalty for overdue rentals
DELIMITER $$

CREATE TRIGGER trg_overdue
BEFORE UPDATE ON RENTALS
FOR EACH ROW
BEGIN
    IF NEW.actualReturn IS NULL AND CURDATE() > NEW.rentdue THEN
        SET NEW.status = 'overdue';
        SET NEW.penalty = fn_penalty(DATEDIFF(CURDATE(), NEW.rentdue));
    END IF;
END$$

DELIMITER ;


#Set drone as available once retrieval is confirmed by admin
DELIMITER $$

CREATE TRIGGER trg_return_drone
AFTER UPDATE ON RENTALS
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        UPDATE DRONES SET status='available' WHERE droneID = NEW.droneID;
    END IF;
END$$

DELIMITER ;


#Automatic checking if rent is overdue
SET GLOBAL event_scheduler = ON;

CREATE EVENT ev_check_overdue
ON SCHEDULE EVERY 1 DAY
DO
UPDATE RENTALS
SET status='overdue',
    penalty = fn_penalty(DATEDIFF(CURDATE(), rentdue))
WHERE status='approved' AND CURDATE() > rentdue;


#Rental approval
START TRANSACTION;

UPDATE RENTALS SET status='approved' WHERE rentID=1;
UPDATE DRONES SET status='rented'
WHERE droneID = (SELECT droneID FROM RENTALS WHERE rentID=1);

COMMIT;

#Concurrency control
SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;

SELECT * FROM DRONES
WHERE droneID=1
FOR UPDATE;

#Roles and privileges
CREATE USER 'customer'@'%' IDENTIFIED BY 'cust123';
GRANT SELECT, INSERT ON RENTALS TO 'customer'@'%';

CREATE USER 'admin'@'%' IDENTIFIED BY 'admin123';
GRANT SELECT, UPDATE ON DRONES TO 'admin'@'%';

CREATE USER 'superadmin'@'%' IDENTIFIED BY 'root123';
GRANT ALL PRIVILEGES ON *.* TO 'superadmin'@'%';


#Log via stored procedure
DELIMITER $$

CREATE PROCEDURE sp_add_log (
    IN p_role ENUM('user','admin','superadmin'),
    IN p_actorID INT,
    IN p_action VARCHAR(255),
    IN p_table VARCHAR(50),
    IN p_recordID INT
)
BEGIN
    INSERT INTO SYSTEM_LOGS(actorRole, actorID, action, tableAffected, recordID)
    VALUES (p_role, p_actorID, p_action, p_table, p_recordID);
END$$

DELIMITER ;

#Rental request trigger
DELIMITER $$

CREATE TRIGGER trg_log_rental_request
AFTER INSERT ON RENTALS
FOR EACH ROW
BEGIN
    CALL sp_add_log(
        'user',
        NEW.userID,
        'Requested rental',
        'RENTALS',
        NEW.rentID
    );
END$$

DELIMITER ;


#Rental approval/rejection
DELIMITER $$

CREATE TRIGGER trg_log_rental_status
AFTER UPDATE ON RENTALS
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        CALL sp_add_log(
            'admin',
            (SELECT adminID FROM DRONES WHERE droneID = NEW.droneID),
            CONCAT('Changed rental status to ', NEW.status),
            'RENTALS',
            NEW.rentID
        );
    END IF;
END$$

DELIMITER ;


#drone deployement/return
DELIMITER $$

CREATE TRIGGER trg_log_drone_status
AFTER UPDATE ON DRONES
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        CALL sp_add_log(
            'admin',
            NEW.adminID,
            CONCAT('Drone status changed to ', NEW.status),
            'DRONES',
            NEW.droneID
        );
    END IF;
END$$

DELIMITER ;


#Logging overdues and penalties
DELIMITER $$

CREATE EVENT ev_log_overdue
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    INSERT INTO SYSTEM_LOGS(actorRole, actorID, action, tableAffected, recordID)
    SELECT
        'system',
        NULL,
        'Rental marked overdue with penalty',
        'RENTALS',
        rentID
    FROM RENTALS
    WHERE status='overdue';
END$$

DELIMITER ;


#View for super-admin
CREATE VIEW vw_system_logs AS
SELECT
    logID,
    actorRole,
    actorID,
    action,
    tableAffected,
    recordID,
    actionTime
FROM SYSTEM_LOGS
ORDER BY actionTime DESC;


#Only super-admin can see logs
GRANT SELECT ON vw_system_logs TO 'superadmin'@'%';
REVOKE SELECT ON SYSTEM_LOGS FROM 'admin'@'%', 'customer'@'%';