<?php
/**
 * _clone.php — deep-copy a version's section/component tree into fresh,
 * independent rows.
 *
 * Used at publish time: the OUTGOING live version is cloned so the version
 * that is becoming archived owns a private copy of its content. That frees the
 * original rows to stay with the new live version (preserving section ids, so
 * student progress carries forward) while the archived snapshot can never be
 * mutated by a later in-place live edit.
 */

/**
 * Replace every section a version links with a private deep copy, then delete
 * any original rows left unreferenced. Idempotent per call; safe inside a tx.
 */
function clone_version_into_independent(PDO $pdo, int $versionId): void {
    // Running id counters for the manual-id tables.
    $next = [];
    foreach (['Sections', 'Components', 'CodeSnippets', 'InfoBoxes', 'PQQuestion', 'MultiMedia'] as $t) {
        $next[$t] = (int)$pdo->query("SELECT COALESCE(MAX(`Id`),0) FROM `$t`")->fetchColumn();
    }
    $newId = function (string $t) use (&$next) { return ++$next[$t]; };

    $insSection = $pdo->prepare("INSERT INTO `Sections` (`Id`, `Title`) VALUES (?, ?)");
    $insSHC     = $pdo->prepare("INSERT INTO `sections_has_components` (`sections_Id`, `components_Id`, `Order`) VALUES (?,?,?)");
    $insComp    = $pdo->prepare("INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`) VALUES (?,?)");
    $repoint    = $pdo->prepare("UPDATE `PageVersion_has_sections` SET `sections_Id` = ? WHERE `PageVersion_Id` = ? AND `sections_Id` = ?");

    // Detail copiers (one stored row per component).
    $copyText  = $pdo->prepare("INSERT INTO `TextBLocks` (`Component_Id`, `Text`) SELECT ?, `Text` FROM `TextBLocks` WHERE `Component_Id` = ?");
    $copyEmpty = $pdo->prepare("INSERT INTO `EmptySpace` (`BeforeLineSpace`,`AfterLineSpace`,`table1_LineType`,`components_Id`)
                                SELECT `BeforeLineSpace`,`AfterLineSpace`,`table1_LineType`, ? FROM `EmptySpace` WHERE `components_Id` = ?");

    $fetchCode  = $pdo->prepare("SELECT `Languages_Id`, `Code` FROM `CodeSnippets` WHERE `Components_Id` = ?");
    $insCode    = $pdo->prepare("INSERT INTO `CodeSnippets` (`Id`,`Components_Id`,`Languages_Id`,`Code`) VALUES (?,?,?,?)");
    $fetchInfo  = $pdo->prepare("SELECT `Text`, `IsWarning` FROM `InfoBoxes` WHERE `components_Id` = ?");
    $insInfo    = $pdo->prepare("INSERT INTO `InfoBoxes` (`Id`,`components_Id`,`Text`,`IsWarning`) VALUES (?,?,?,?)");
    $fetchMedia = $pdo->prepare("SELECT `URL`,`Uploaded`,`MultiMediaType_MultiMediaType` FROM `MultiMedia` WHERE `components_Id` = ?");
    $insMedia   = $pdo->prepare("INSERT INTO `MultiMedia` (`Id`,`URL`,`components_Id`,`Uploaded`,`MultiMediaType_MultiMediaType`) VALUES (?,?,?,?,?)");
    $fetchQ     = $pdo->prepare("SELECT `Id`,`Question`,`Image`,`OpenQuestion`,`ExpectedResult`,`AllowDocument`,`AllowImage` FROM `PQQuestion` WHERE `component_Id` = ?");
    $insQ       = $pdo->prepare("INSERT INTO `PQQuestion` (`Id`,`Question`,`Image`,`OpenQuestion`,`component_Id`,`ExpectedResult`,`AllowDocument`,`AllowImage`) VALUES (?,?,?,?,?,?,?,?)");
    $copyA      = $pdo->prepare("INSERT INTO `PQAnswer` (`PQQuestion_Id`,`AnswerOption`,`IsCorrect`)
                                 SELECT ?, `AnswerOption`, `IsCorrect` FROM `PQAnswer` WHERE `PQQuestion_Id` = ?");

    // Original sections of this version.
    $secRows = $pdo->prepare("SELECT pvs.`sections_Id`, s.`Title`
                              FROM `PageVersion_has_sections` pvs JOIN `sections` s ON s.`Id` = pvs.`sections_Id`
                              WHERE pvs.`PageVersion_Id` = ?");
    $secRows->execute([$versionId]);
    $sections = $secRows->fetchAll(PDO::FETCH_ASSOC);
    $originalSectionIds = [];

    foreach ($sections as $sec) {
        $origSec = (int)$sec['sections_Id'];
        $originalSectionIds[] = $origSec;

        $newSec = $newId('Sections');
        $insSection->execute([$newSec, $sec['Title']]);

        // Copy each component (in order) into the new section.
        $comps = $pdo->prepare("SELECT shc.`components_Id`, shc.`Order`, c.`ComponentType_ComponentTypeText` AS type
                                FROM `sections_has_components` shc JOIN `components` c ON c.`Id` = shc.`components_Id`
                                WHERE shc.`sections_Id` = ? ORDER BY shc.`Order`");
        $comps->execute([$origSec]);
        foreach ($comps->fetchAll(PDO::FETCH_ASSOC) as $cp) {
            $origComp = (int)$cp['components_Id'];
            $newComp  = $newId('Components');
            $insComp->execute([$newComp, $cp['type']]);
            $insSHC->execute([$newSec, $newComp, (int)$cp['Order']]);

            // Copy whatever detail rows this component has.
            $copyText->execute([$newComp, $origComp]);
            $copyEmpty->execute([$newComp, $origComp]);

            $fetchCode->execute([$origComp]);
            if ($r = $fetchCode->fetch(PDO::FETCH_ASSOC)) $insCode->execute([$newId('CodeSnippets'), $newComp, $r['Languages_Id'], $r['Code']]);

            $fetchInfo->execute([$origComp]);
            if ($r = $fetchInfo->fetch(PDO::FETCH_ASSOC)) $insInfo->execute([$newId('InfoBoxes'), $newComp, $r['Text'], $r['IsWarning']]);

            $fetchMedia->execute([$origComp]);
            if ($r = $fetchMedia->fetch(PDO::FETCH_ASSOC)) $insMedia->execute([$newId('MultiMedia'), $r['URL'], $newComp, $r['Uploaded'], $r['MultiMediaType_MultiMediaType']]);

            $fetchQ->execute([$origComp]);
            if ($r = $fetchQ->fetch(PDO::FETCH_ASSOC)) {
                $newQ = $newId('PQQuestion');
                $insQ->execute([$newQ, $r['Question'], $r['Image'], $r['OpenQuestion'], $newComp,
                                $r['ExpectedResult'], $r['AllowDocument'], $r['AllowImage']]);
                $copyA->execute([$newQ, (int)$r['Id']]);
            }
        }

        // Point this version at the copy instead of the original.
        $repoint->execute([$newSec, $versionId, $origSec]);
    }

    // GC originals that no version references any more.
    $linkCount = $pdo->prepare("SELECT COUNT(*) FROM `PageVersion_has_sections` WHERE `sections_Id` = ?");
    $compLinks = $pdo->prepare("SELECT COUNT(*) FROM `sections_has_components` WHERE `components_Id` = ?");
    foreach ($originalSectionIds as $sid) {
        $linkCount->execute([$sid]);
        if ((int)$linkCount->fetchColumn() > 0) continue;
        $cids = $pdo->prepare("SELECT `components_Id` FROM `sections_has_components` WHERE `sections_Id` = ?");
        $cids->execute([$sid]);
        $cids = array_map('intval', $cids->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM `Sections` WHERE `Id` = ?")->execute([$sid]); // cascades sections_has_components
        foreach ($cids as $cid) {
            $compLinks->execute([$cid]);
            if ((int)$compLinks->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM `PQAnswer` WHERE `PQQuestion_Id` IN (SELECT `Id` FROM `PQQuestion` WHERE `component_Id` = ?)")->execute([$cid]);
                $pdo->prepare("DELETE FROM `EmptySpace` WHERE `components_Id` = ?")->execute([$cid]);
                $pdo->prepare("DELETE FROM `Components` WHERE `Id` = ?")->execute([$cid]); // cascades remaining detail
            }
        }
    }
}
