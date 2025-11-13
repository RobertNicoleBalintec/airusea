CREATE DATABASE IF NOT EXISTS airerusea;
USE airerusea;

CREATE TABLE categories (
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(255)
);

CREATE TABLE wingtype (
    WingTypeID INT AUTO_INCREMENT PRIMARY KEY,
    WingTypeName VARCHAR(255)
);

CREATE TABLE motortype (
    MotorTypeID INT AUTO_INCREMENT PRIMARY KEY,
    MotorTypeName VARCHAR(255)
);

CREATE TABLE payloadcapacity (
    PayloadCapacityID INT AUTO_INCREMENT PRIMARY KEY,
    Capacity VARCHAR(255)
);

CREATE TABLE powersource (
    PowerSourceID INT AUTO_INCREMENT PRIMARY KEY,
    SourceType VARCHAR(255)
);

CREATE TABLE drones (
    DroneID INT AUTO_INCREMENT PRIMARY KEY,
    Brand VARCHAR(255),
    Model VARCHAR(255),
    CategoryID INT,
    WingTypeID INT,
    Size VARCHAR(100),
    PricePerDay DECIMAL(10,2),
    QuantityAvailable INT,
    PayloadCapacityID INT,
    PowerSourceID INT,
    MotorTypeID INT,
    UsageCase TEXT,
    ReleaseDate DATE,
    ImageURL VARCHAR(255),
    Description TEXT,
    FOREIGN KEY (CategoryID) REFERENCES categories(CategoryID),
    FOREIGN KEY (WingTypeID) REFERENCES wingtype(WingTypeID),
    FOREIGN KEY (PayloadCapacityID) REFERENCES payloadcapacity(PayloadCapacityID),
    FOREIGN KEY (PowerSourceID) REFERENCES powersource(PowerSourceID),
    FOREIGN KEY (MotorTypeID) REFERENCES motortype(MotorTypeID)
);

CREATE TABLE users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100),
    Email VARCHAR(255),
    Password VARCHAR(255),
    Phone VARCHAR(20),
    Address TEXT
);

CREATE TABLE rentals (
    RentalID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    DroneID INT,
    RentStart DATETIME,
    RentEnd DATETIME,
    TotalCost DECIMAL(10,2),
    FOREIGN KEY (UserID) REFERENCES users(UserID),
    FOREIGN KEY (DroneID) REFERENCES drones(DroneID)
);

CREATE TABLE paymentmethods (
    PaymentMethodID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255)
);

CREATE TABLE payments (
    PaymentID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    RentalID INT,
    PaymentMethodID INT,
    PaymentDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    AmountPaid DECIMAL(10,2),
    FOREIGN KEY (UserID) REFERENCES users(UserID),
    FOREIGN KEY (RentalID) REFERENCES rentals(RentalID),
    FOREIGN KEY (PaymentMethodID) REFERENCES paymentmethods(PaymentMethodID)
);
