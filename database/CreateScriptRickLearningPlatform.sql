-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema rick learning platform
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema rick learning platform
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `rick learning platform` DEFAULT CHARACTER SET utf8mb4 ;
USE `rick learning platform` ;

-- -----------------------------------------------------
-- Table `rick learning platform`.`subjects`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`subjects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`courses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`courses` (
  `Id` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(45) NOT NULL,
  `Icon` VARCHAR(45) NOT NULL,
  `Color` VARCHAR(45) NOT NULL,
  `Subject_Id` INT(11) NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_Courses_Subjects_idx` (`Subject_Id` ASC),
  CONSTRAINT `fk_Courses_Subjects`
    FOREIGN KEY (`Subject_Id`)
    REFERENCES `rick learning platform`.`subjects` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`pagetypes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`pagetypes` (
  `Id` INT(11) NOT NULL,
  `Name` VARCHAR(45) NOT NULL,
  `Color` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`pages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`pages` (
  `Course_Id` INT(11) NOT NULL,
  `Id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(45) NOT NULL,
  `order` INT(11) NULL DEFAULT NULL,
  `published` TINYINT(1) NOT NULL,
  `PageType_Id` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_Pages_Courses1_idx` (`Course_Id` ASC),
  INDEX `fk_Pages_PageTypes1_idx` (`PageType_Id` ASC),
  CONSTRAINT `fk_Pages_Courses1`
    FOREIGN KEY (`Course_Id`)
    REFERENCES `rick learning platform`.`courses` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Pages_PageTypes1`
    FOREIGN KEY (`PageType_Id`)
    REFERENCES `rick learning platform`.`pagetypes` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`sections`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`sections` (
  `Id` INT(11) NOT NULL,
  `Pages_Id` INT(11) NOT NULL,
  `Title` VARCHAR(45) NOT NULL,
  `Order` INT(11) NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_Sections_Pages1_idx` (`Pages_Id` ASC),
  CONSTRAINT `fk_Sections_Pages1`
    FOREIGN KEY (`Pages_Id`)
    REFERENCES `rick learning platform`.`pages` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`ComponentType`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`ComponentType` (
  `ComponentTypeText` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`ComponentTypeText`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`components` (
  `Id` INT(11) NOT NULL,
  `section_Id` INT(11) NOT NULL,
  `Order` INT NOT NULL,
  `ComponentType_ComponentTypeText` VARCHAR(45) NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_components_sections1_idx` (`section_Id` ASC),
  INDEX `fk_components_ComponentType1_idx` (`ComponentType_ComponentTypeText` ASC),
  CONSTRAINT `fk_components_sections1`
    FOREIGN KEY (`section_Id`)
    REFERENCES `rick learning platform`.`sections` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_components_ComponentType1`
    FOREIGN KEY (`ComponentType_ComponentTypeText`)
    REFERENCES `rick learning platform`.`ComponentType` (`ComponentTypeText`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`PQQuestion`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`PQQuestion` (
  `Id` INT NOT NULL,
  `Question` VARCHAR(255) NOT NULL,
  `Image` VARCHAR(255) NULL DEFAULT NULL,
  `OpenQuestion` TINYINT NOT NULL,
  `component_Id` INT(11) NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_PQQuestion_components1_idx` (`component_Id` ASC),
  CONSTRAINT `fk_PQQuestion_components1`
    FOREIGN KEY (`component_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`PQAnswer`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`PQAnswer` (
  `PQQuestion_Id` INT NOT NULL,
  `AnswerOption` VARCHAR(255) NOT NULL,
  `IsCorrect` TINYINT NOT NULL,
  `Id` INT NOT NULL AUTO_INCREMENT,
  INDEX `fk_PQAnswer_PQQuestion_idx` (`PQQuestion_Id` ASC),
  PRIMARY KEY (`Id`),
  CONSTRAINT `fk_PQAnswer_PQQuestion`
    FOREIGN KEY (`PQQuestion_Id`)
    REFERENCES `rick learning platform`.`PQQuestion` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`InfoBoxes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`InfoBoxes` (
  `Id` INT NOT NULL,
  `components_Id` INT(11) NOT NULL,
  `Text` LONGTEXT NOT NULL,
  `IsWarning` TINYINT NOT NULL,
  INDEX `fk_InfoBoxes_components1_idx` (`components_Id` ASC),
  PRIMARY KEY (`Id`),
  CONSTRAINT `fk_InfoBoxes_components1`
    FOREIGN KEY (`components_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`MultiMedia`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`MultiMedia` (
  `Id` INT NOT NULL,
  `URL` VARCHAR(255) NOT NULL,
  `components_Id` INT(11) NOT NULL,
  `Uploaded` TINYINT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_MultiMedia_components1_idx` (`components_Id` ASC),
  CONSTRAINT `fk_MultiMedia_components1`
    FOREIGN KEY (`components_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`Assigments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`Assigments` (
  `component_Id` INT(11) NOT NULL,
  `Id` INT NOT NULL,
  `FileRequired` TINYINT NOT NULL,
  `Title` VARCHAR(90) NOT NULL,
  INDEX `fk_Assigments_components1_idx` (`component_Id` ASC),
  PRIMARY KEY (`Id`),
  CONSTRAINT `fk_Assigments_components1`
    FOREIGN KEY (`component_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`accounts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`accounts` (
  `username` VARCHAR(25) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`username`),
  UNIQUE INDEX `Email_UNIQUE` (`Email` ASC))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`accounts_opened_pages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`accounts_opened_pages` (
  `Accounts_username` VARCHAR(25) NOT NULL,
  `Pages_Id` INT(11) NOT NULL,
  `Completed` TINYINT(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Accounts_username`, `Pages_Id`),
  INDEX `fk_Accounts_has_Pages_Pages1_idx` (`Pages_Id` ASC),
  INDEX `fk_Accounts_has_Pages_Accounts1_idx` (`Accounts_username` ASC),
  CONSTRAINT `fk_Accounts_has_Pages_Accounts1`
    FOREIGN KEY (`Accounts_username`)
    REFERENCES `rick learning platform`.`accounts` (`username`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Accounts_has_Pages_Pages1`
    FOREIGN KEY (`Pages_Id`)
    REFERENCES `rick learning platform`.`pages` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`languages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`languages` (
  `Id` INT(11) NOT NULL,
  `LanguageName` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`Id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`codesnippets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`codesnippets` (
  `Id` INT(11) NOT NULL,
  `Components_Id` INT(11) NOT NULL,
  `Languages_Id` INT(11) NOT NULL,
  `Code` LONGTEXT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_CodeSnippets_Components1_idx` (`Components_Id` ASC),
  INDEX `fk_CodeSnippets_Languages1_idx` (`Languages_Id` ASC),
  CONSTRAINT `fk_CodeSnippets_Components1`
    FOREIGN KEY (`Components_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_CodeSnippets_Languages1`
    FOREIGN KEY (`Languages_Id`)
    REFERENCES `rick learning platform`.`languages` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`textblocks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`textblocks` (
  `Id` INT(11) NOT NULL AUTO_INCREMENT,
  `Component_Id` INT(11) NOT NULL,
  `Text` LONGTEXT NOT NULL,
  PRIMARY KEY (`Id`),
  INDEX `fk_TextBLocks_Components1_idx` (`Component_Id` ASC),
  CONSTRAINT `fk_TextBLocks_Components1`
    FOREIGN KEY (`Component_Id`)
    REFERENCES `rick learning platform`.`components` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`Accounts_have_assignments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`Accounts_have_assignments` (
  `account_username` VARCHAR(25) NOT NULL,
  `Assigment_Id` INT NOT NULL,
  `SubmittedTextAnswer` LONGTEXT NULL DEFAULT NULL,
  `FileName` VARCHAR(255) NULL DEFAULT NULL,
  `FilePath` VARCHAR(255) NULL DEFAULT NULL,
  `SubmittedOn` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`account_username`, `Assigment_Id`),
  INDEX `fk_accounts_has_Assigments_Assigments1_idx` (`Assigment_Id` ASC),
  INDEX `fk_accounts_has_Assigments_accounts1_idx` (`account_username` ASC),
  CONSTRAINT `fk_accounts_has_Assigments_accounts1`
    FOREIGN KEY (`account_username`)
    REFERENCES `rick learning platform`.`accounts` (`username`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_accounts_has_Assigments_Assigments1`
    FOREIGN KEY (`Assigment_Id`)
    REFERENCES `rick learning platform`.`Assigments` (`Id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`QuestionContext`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`QuestionContext` (
  `ContextType` VARCHAR(125) NOT NULL,
  PRIMARY KEY (`ContextType`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rick learning platform`.`AC_Did_Question`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`AC_Did_Question` (
  `accounts_username` VARCHAR(25) NOT NULL,
  `PQQuestion_Id` INT NOT NULL,
  `Id` INT NOT NULL AUTO_INCREMENT,
  `QuestionContext_ContextType` VARCHAR(125) NOT NULL,
  `AttemptDate` DATETIME NOT NULL,
  `ReviewedBy` VARCHAR(45) NULL,
  `ReviewedAt` DATETIME NULL,
  `OpenAnswer` VARCHAR(45) NULL,
  `ReviewFeedback` VARCHAR(45) NULL,
  INDEX `fk_accounts_has_PQQuestion_PQQuestion1_idx` (`PQQuestion_Id` ASC),
  INDEX `fk_accounts_has_PQQuestion_accounts1_idx` (`accounts_username` ASC),
  PRIMARY KEY (`Id`),
  INDEX `fk_accounts_has_PQQuestion_QuestionContext1_idx` (`QuestionContext_ContextType` ASC),
  CONSTRAINT `fk_accounts_has_PQQuestion_accounts1`
    FOREIGN KEY (`accounts_username`)
    REFERENCES `rick learning platform`.`accounts` (`username`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_accounts_has_PQQuestion_PQQuestion1`
    FOREIGN KEY (`PQQuestion_Id`)
    REFERENCES `rick learning platform`.`PQQuestion` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_accounts_has_PQQuestion_QuestionContext1`
    FOREIGN KEY (`QuestionContext_ContextType`)
    REFERENCES `rick learning platform`.`QuestionContext` (`ContextType`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `rick learning platform`.`AC_Picked_Answer`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rick learning platform`.`AC_Picked_Answer` (
  `AC_Did_Question_Id` INT NOT NULL,
  `PQAnswer_Id` INT NOT NULL,
  `Correct` INT NOT NULL,
  PRIMARY KEY (`AC_Did_Question_Id`, `PQAnswer_Id`),
  INDEX `fk_AC_Did_Question_has_PQAnswer_PQAnswer1_idx` (`PQAnswer_Id` ASC),
  INDEX `fk_AC_Did_Question_has_PQAnswer_AC_Did_Question1_idx` (`AC_Did_Question_Id` ASC),
  CONSTRAINT `fk_AC_Did_Question_has_PQAnswer_AC_Did_Question1`
    FOREIGN KEY (`AC_Did_Question_Id`)
    REFERENCES `rick learning platform`.`AC_Did_Question` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_AC_Did_Question_has_PQAnswer_PQAnswer1`
    FOREIGN KEY (`PQAnswer_Id`)
    REFERENCES `rick learning platform`.`PQAnswer` (`Id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
