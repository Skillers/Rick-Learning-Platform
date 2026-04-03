-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema Rick Learning Platform
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema Rick Learning Platform
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `Rick Learning Platform` DEFAULT CHARACTER SET utf8mb4 ;
USE `Rick Learning Platform` ;

-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Subjects`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Subjects` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Courses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Courses` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(45) NOT NULL,
  `Icon` VARCHAR(45) NOT NULL,
  `Color` VARCHAR(45) NOT NULL,
  `Subject_Id` INT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_Courses_Subjects_idx` (`Subject_Id` ASC),
  CONSTRAINT `fk_Courses_Subjects`
    FOREIGN KEY (`Subject_Id`)
    REFERENCES `Rick Learning Platform`.`Subjects` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`PageTypes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`PageTypes` (
  `Id` INT NOT NULL,
  `Name` VARCHAR(45) NOT NULL,
  `Color` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Pages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Pages` (
  `Course_Id` INT NOT NULL,
  `Id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(45) NOT NULL,
  `order` INT NULL,
  `published` TINYINT(1) NOT NULL,
  `PageType_Id` INT NULL,
  INDEX `fk_Pages_Courses1_idx` (`Course_Id` ASC),
  PRIMARY KEY (`Id`),
  INDEX `fk_Pages_PageTypes1_idx` (`PageType_Id` ASC),
  CONSTRAINT `fk_Pages_Courses1`
    FOREIGN KEY (`Course_Id`)
    REFERENCES `Rick Learning Platform`.`Courses` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Pages_PageTypes1`
    FOREIGN KEY (`PageType_Id`)
    REFERENCES `Rick Learning Platform`.`PageTypes` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Sections`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Sections` (
  `Id` INT NOT NULL,
  `Pages_Id` INT NOT NULL,
  `Title` VARCHAR(45) NOT NULL,
  `Order` INT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_Sections_Pages1_idx` (`Pages_Id` ASC),
  CONSTRAINT `fk_Sections_Pages1`
    FOREIGN KEY (`Pages_Id`)
    REFERENCES `Rick Learning Platform`.`Pages` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Components` (
  `Id` INT NOT NULL,
  `TypeName` VARCHAR(45) NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Components` (
  `Id` INT NOT NULL,
  `TypeName` VARCHAR(45) NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Languages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Languages` (
  `Id` INT NOT NULL,
  `LanguageName` VARCHAR(45) NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`CodeSnippets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`CodeSnippets` (
  `Id` INT NOT NULL,
  `Components_Id` INT NOT NULL,
  `Languages_Id` INT NOT NULL,
  `Code` LONGTEXT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_CodeSnippets_Components1_idx` (`Components_Id` ASC),
  INDEX `fk_CodeSnippets_Languages1_idx` (`Languages_Id` ASC),
  CONSTRAINT `fk_CodeSnippets_Components1`
    FOREIGN KEY (`Components_Id`)
    REFERENCES `Rick Learning Platform`.`Components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_CodeSnippets_Languages1`
    FOREIGN KEY (`Languages_Id`)
    REFERENCES `Rick Learning Platform`.`Languages` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`TextBLocks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`TextBLocks` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `Component_Id` INT NOT NULL,
  `Text` LONGTEXT NOT NULL,
  INDEX `fk_TextBLocks_Components1_idx` (`Component_Id` ASC),
  PRIMARY KEY (`Id`),
  CONSTRAINT `fk_TextBLocks_Components1`
    FOREIGN KEY (`Component_Id`)
    REFERENCES `Rick Learning Platform`.`Components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `Rick Learning Platform`.`Accounts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rick Learning Platform`.`Accounts` (
  `username` INT NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`username`),
  UNIQUE INDEX `Email_UNIQUE` (`Email` ASC))
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
