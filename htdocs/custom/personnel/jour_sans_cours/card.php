<?php
/**
 * Fiche : jour sans cours.
 * Fichier : custom/personnel/jour_sans_cours/card.php
 */

require '../init.php';
dol_include_once('/personnel/class/ecole_jour_sans_cours.class.php');

ecole_crud_card(new EcoleJourSansCours($db), personnel_crud_config('jour_sans_cours'));
