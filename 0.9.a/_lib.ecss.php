<?php
/**
 * eCss lib
 *
 * @author     Damien V.
 * @version    0.9.a
 * @copyright  (c) 2010 Damien V.
 */

function eCssAddBlocToStruc($aCssStructure, $sString, $iLevel) {
	static $iIdBloc = 1;
	$sString = trim($sString);
	
	if(!empty($sString)) {
		$aCssStructure[$iIdBloc] = str_repeat('%', $iLevel).$sString;
		$iIdBloc++;
		return $aCssStructure;
	}
	else {
		return false;
	}
}

// Analyse du sélecteur
function eCssCheckJoker ($sString) {				
	$aLong = array(
		'ac'  => 'active',
		'fo'  => 'focus',
		'ho'  => 'hover',
		'li'  => 'link',
		'vi'  => 'visited'
	);
	
	// $sString équivaut à une classe de bloc Css
	// Exemple: a:all li, a img, .head img
	if (mb_strpos($sString, ':') !== false) {
		$aString = explode(',', $sString);
			
		foreach($aString as $iKeyClass => $sClass) {
			// Matching de pseudo-class
			if (preg_match_all('#([-_.\#a-z]+)?:((?:-?(?:li|vi|ho|ac|fo|all)){1,5}[^a-z])#', $sClass.' ', $aMatchAll)) {
				foreach($aMatchAll[0] as $iKeyMatch => $aInutile) {
					$aLongTemp    = $aLong;
					$sSelector    = trim($aMatchAll[1][$iKeyMatch]);
					$sPseudoClass = trim($aMatchAll[2][$iKeyMatch]);
					$aNewClass = array();
					
					if(mb_strpos($sPseudoClass, 'all') !== false) {
						if($sSelector != 'a') {
							unset($aLongTemp['ac']);
							unset($aLongTemp['li']);
							unset($aLongTemp['vi']);
						}
						
						foreach($aLongTemp as $sTempClass) {
							$lastInsert  = str_replace($sSelector.':all', $sSelector.':'.$sTempClass, $sClass);
							$aNewClass[] = $lastInsert;
						}
					}
					else {
						$aShort   = explode('-', $sPseudoClass);
						foreach($aShort as $sShort) {
							$sShort = trim($sShort);
							$lastInsert = str_replace($sSelector.':'.$sPseudoClass, $sSelector.':'.$aLongTemp[$sShort], $sClass);
							$aNewClass[] = $lastInsert;
						}
					}
				}
				$aString[$iKeyClass] = implode(', ', $aNewClass);

				// Dans le cas où y a un matching multiple, on assure la récursivité
				if(count($aMatchAll[0]) > 1) {
					$aString[$iKeyClass] = eCssCheckJoker($aString[$iKeyClass]);
				}
			}
		}
		
		$sString = implode(',', $aString);
	}
	
	return $sString;
}
			
function eCssCompile ($aSpeConfig = array()) {
	global $eCssConfig;
	
	// Configuration spéciale pour cette instance
	if(is_array($aSpeConfig) && count($aSpeConfig) > 0) {
		foreach($eCssConfig as $sKeyConfig => $sValueConfig) {
			if(empty($aSpeConfig[$sKeyConfig])) {
				$aSpeConfig[$sKeyConfig] = $sValueConfig;
			}
		}
	}
	// Configuration générale
	else {
		$aSpeConfig = $eCssConfig;
	}
	
	if($aSpeConfig['disable']) {
		return false;
	}
	
	if(!is_int($aSpeConfig['output'])) {
		switch ($aSpeConfig['output']) {
			case 'nested':
				$iOut = 0;
				break;
			case 'expanded':
				$iOut = 1;
				break;
			case 'compact':
				$iOut = 2;
				break;
			case 'compressed':
				$iOut = 3;
				break;
			default:
				$iOut = 0;
		}
		$aSpeConfig['output'] = $iOut;
	}

	// Si c'est un tableau
	if (is_array($aSpeConfig['eCssFiles'])) {
		$sPathToVersion    = $aSpeConfig['path'].'cache.json';
		
		$aCacheTimeVersion = array();
		if(file_exists($sPathToVersion)) {
		    // Récupération des timestamp des modifications des fichiers eCss
		    $aCacheTimeVersion = json_decode(file_get_contents($sPathToVersion), true);
		}
		
		$bCompile = true;
		
		// Si il y a déjà un fichier en cache
		if($aSpeConfig['cache'] === true) {
			$bCompile = false;
			foreach($aSpeConfig['eCssFiles'] as $sFilename) {
				$sPathToECss = $aSpeConfig['path'].$sFilename.'.ecss';

				// Si le cache n'est pas à jour, on lance la compilation des fichiers
				if(file_exists($sPathToECss)) {
					// Récupération du timestamp de la dernière modification du fichier eCss
					$iTimeECss = filemtime($sPathToECss);
					
					// Si un fichier a été modifié depuis la dernière vérification, on recompile tout
					if(!isset($aCacheTimeVersion[$sFilename]) || $aCacheTimeVersion[$sFilename] != $iTimeECss) {
						$aCacheTimeVersion[$sFilename] = $iTimeECss;
						$bCompile = true;
					}
				}
			}
		}
		
		if($bCompile === true) {
		    $sMasterFile = null;
			// Compilation à la volée
			foreach($aSpeConfig['eCssFiles'] as $sFilename) {
				$sMasterFile .= eCssParse($aSpeConfig, $sFilename);
			}
			
			// Enregistrement des informations relatives aux fichiers eCss (cache)
			file_put_contents($sPathToVersion, json_encode($aCacheTimeVersion));

			// Si demande d'un fichier master
			if(!empty($aSpeConfig['master'])) {
				file_put_contents($aSpeConfig['path'].$aSpeConfig['master'].'.css', $sMasterFile);
			}
		}
	}
}

function eCssParse($aSpeConfig, $sFilename) {
	// Si le fichier n'existe pas, on bloque le tout
	if (empty($sFilename) || !file_exists($aSpeConfig['path'].$sFilename.'.ecss')) {
		return false;
	}
	else {
		$sPathToECss    = $aSpeConfig['path'].$sFilename.'.ecss';
		$sPathToCss     = $aSpeConfig['path'].$sFilename.'.css';
		
		// Ouverture du fichier eCss
		$sCssFile = file_get_contents($sPathToECss);
		// Suppression des commentaires multilignes
		$sCssFile = preg_replace("/\/\*(.*)?\*\//Usiu", null, $sCssFile);
		// Suppression des commentaires monolignes
		$sCssFile = preg_replace("/[^:]\/\/.+/u", null, $sCssFile);
		// Découpage du fichier eCss en segment ligne par ligne
		$aCssFile = explode("\n", $sCssFile);
		
		// Structure du CSS par bloc 
		// Tableau [id du sélecteur     => sélecteur]
		$aCssStructure = array();
		// Tableau [id du sélecteur     => propriétés]
		$aProperties   = array();
		// Tableau [nom de la constante => valeur de la constante]
		$aConstant     = array();
		// Tableau [=> lignes isolées]
		$aHead         = array();
		// Tableau [nom de l'injection  => tableau des propriétés]
		$aMixin        = array();
		$iLevel        = 0;
		
		// Cette première analyse ligne par ligne permet d'arriver à une structure du CSS
		// divisée par bloc d'instruction, le tout sous forme de tableau.
		// N'hésitez pas à faire un var_dump($aCssStructure) plus loin pour voir ce que ça donne.
		foreach ($aCssFile as $iLine => $sLine) {
			// On nettoie la ligne pour qu'il ne reste que l'instruction ou la valeur
			$sLine = str_replace(array("\r", "\n", "\t"), array('', '', ''), trim($sLine));

			// Ouverture de bloc
			if (mb_strpos($sLine, '{') !== false) {
				$bConstant = false;
				$bMixin    = false;
				// Bloc ouvert = constantes
				if (mb_substr($sLine, 0, 10) == '@constants') {
					$bConstant = true;
				}
				// Bloc ouvert = mixins
				elseif (mb_substr($sLine, 0, 1) == '=') {
					$bMixin    = true;
					$sMixin    = trim(str_replace('{', '', mb_substr($sLine, 1)));
				}
				// Bloc ouvert = class normal
				else {
					$sLine = trim(str_replace('{', '', $sLine));

					$aCssStructure = eCssAddBlocToStruc($aCssStructure, $sLine, $iLevel);
					$aKeys         = array_keys($aCssStructure);
					// Récupération de l'ID de la classe
					$iIdBloc      = $aKeys[count($aKeys)-1];
					// On initialise les propriétés du bloc
					$aProperties[$iIdBloc] = array();
				}
				$iLevel++;
			}
			// Fermeture de bloc
			elseif (mb_strpos($sLine, '}') !== false) {
				$iLevel--;
			}
			elseif (!empty($sLine)) {
				// Si on est à l'intérieur du bloc constantes
				if ($bConstant) {
					list($sConstantName, $sConstantValue) = explode(':', $sLine);
					$aConstant['$'.trim($sConstantName)] = mb_substr(trim($sConstantValue), 0, -1);
				}
				// Si on est à l'intérieur d'un bloc mixin
				elseif ($bMixin) {
					$aMixin[$sMixin][] = $sLine;
				}
				// Si il s'agit d'un bloc classique
				elseif ($iLevel > 0 && !empty($iIdBloc)) {
					// Propriétés = mixin
					if(mb_substr($sLine, 0, 1) == '+' && is_array($aMixin[mb_substr($sLine, 1)])) {
						foreach($aMixin[mb_substr($sLine, 1)] as $sProperty) {
							list($sAttribut, $sValue) = explode(':', $sProperty);
							$aProperties[$iIdBloc][]  = trim($sAttribut).'-: '.trim($sValue);
						}
					}
					// Propriété = propriété normale
					else {
						list($sAttribut, $sValue) = explode(':', $sLine);
						$aProperties[$iIdBloc][]    = trim($sAttribut).'-: '.trim($sValue);
					}
				}
				// Si on est en dehors de tout bloc
				else {
					$aHead[] = $sLine;
				}
			}
		}
		
		// Fichier Css final
		$sCssFile = null;
		// Insertion des données en header
		foreach($aHead as $sLine) {
			$sCssFile .= $sLine.$sBackCharriot;
		}
		
		// Insertion des blocs successifs
		foreach($aCssStructure as $iIdBloc => $sClass) {
			$iLevelClass = mb_substr_count($sClass, '%');
			$sClass      = str_replace('%', '', $sClass);
			if($iLevelClass == 0) {
				$sCurrentClass = $sClass;
				
				// Malus nécessaire au rendu nested
				// lorsqu'un bloc ne contient aucune propriété
				$iMalus        = 0;
				
				// Nested
				if($aSpeConfig['output'] == 0) {
				    $sCssFile .= "\n";
				}
			}
			else {
				$sParentClass      = $aParent[$iLevelClass-1];
				$aTempCurrentClass = array();
				$aTempClass        = array();
				
				// Récupération de la ou des sélecteurs parents
				if (mb_strpos($sParentClass, ',') === false) {
					$aTempCurrentClass[] = $sParentClass;
				}
				else {
					$aTempCurrentClass = explode(',', $sParentClass);
				}
				
				// Récupération de la ou des sélecteurs courants
				if(mb_strpos($sClass, ',') === false) {
					$aTempClass[] = $sClass;
				}
				else {
					$aTempClass = explode(',', $sClass);
				}
				
				$aOutput = array();
				// Croisement des sélecteurs de deux niveaux différents (factorisation)
				foreach($aTempCurrentClass as $sTempCurrentClass) {
					foreach($aTempClass as $sTempClass) {
						$sTempClass        = trim($sTempClass);
						$sTempCurrentClass = trim($sTempCurrentClass);
						
						if(mb_strpos($sTempClass, '&:') !== false || mb_strpos($sTempClass, '&#') !== false || mb_strpos($sTempClass, '&.') !== false || mb_strpos($sTempClass, '&[') !== false) {
							$sTempClass = str_replace('&', '', $sTempClass);
							if ($iCurrentLevel < $iLevelClass) {
								$sOutputClass = $sTempCurrentClass.$sTempClass;
							}
							elseif ($iCurrentLevel >= $iLevelClass) {
								$aParentClass = explode('>', $sTempCurrentClass);
								array_splice($aParentClass, $iLevelClass);
								$sOutputClass = implode('>', $aParentClass).$sTempClass;
							}
						}
						else {
							if ($iCurrentLevel < $iLevelClass) {
								$sOutputClass = $sTempCurrentClass.'>'.$sTempClass;
							}
							elseif ($iCurrentLevel >= $iLevelClass) {
								$aParentClass = explode('>', $sTempCurrentClass);
								$aParentClass[$iLevelClass] = $sTempClass;
								array_splice($aParentClass, $iLevelClass+1);
								$sOutputClass = implode('>', $aParentClass);
							}
						}
						
						$aOutput[] = $sOutputClass;
					}
				}
				$sCurrentClass = implode(', ', $aOutput);
			}
			$sCurrentClass         = eCssCheckJoker($sCurrentClass);
			$aParent[$iLevelClass] = $sCurrentClass;

			// Propriété du bloc courant
			$aClassProperties = $aProperties[$iIdBloc];
			
			if (count($aClassProperties) == 0) {
			    $iMalus++;
			}
			
			// On ne rajoute le bloc que si il contient des propriétés
			if (is_array($aClassProperties) && count($aClassProperties) > 0) {
				// On trie les propriétés par ordre alphabétique
				sort($aClassProperties);
				
				$sTempSelector = str_replace(array('>', '%'), array(' ', ''), $sCurrentClass);
				
				// Nested
				if ($aSpeConfig['output'] == 0) {
				    // On ajoute les propriétés au fichier CSS
				    $sCssFile .= str_repeat('  ', $iLevelClass-$iMalus).$sTempSelector.' {'."\n";
				    $sCssFile .= str_repeat('  ', $iLevelClass-$iMalus+1).implode("\n".str_repeat('  ', $iLevelClass-$iMalus+1), $aClassProperties).' }'."\n";
				}
				elseif ($aSpeConfig['output'] == 1) {
				    $sCssFile .= $sTempSelector.' {'."\n".'  '.implode("\n".'  ', $aClassProperties)."\n".'}'."\n";
				}
				elseif ($aSpeConfig['output'] == 2) {
				    $sCssFile .= $sTempSelector.' {  '.implode('  ', $aClassProperties).' }'."\n";
				}
				else {
				    // On ajoute les propriétés au fichier CSS
				    $sCssFile .= $sTempSelector.'{'.implode('', $aClassProperties).'}';
				}
			}
			
			$iCurrentLevel = $iLevelClass;
		}
		
		// Correction d'une rustine pour un bug de tri :
		// le sort() plaçait les sous-propriétés (border-top, margin-left, etc.)
		// avant la propriétés englobante, ce qui annulait leur effet.
		$sCssFile = str_replace('-:', ':', $sCssFile);
		
		// Si la compression est au plus haut degré, on supprime tous les caractères blancs
		if($aSpeConfig['output'] == 3) {
			$sCssFile = str_replace(array("\n ", "\n", "\t", '  ', '   '), null, $sCssFile);
			$sCssFile = str_replace(array(' {', ';}', ': ', ', '), array('{', '}', ':', ','), $sCssFile);
		}
		
		// On trie le tableau constantes en sens inverse
		krsort($aConstant);
		// On remplace les constantes par leurs valeurs
		$sCssFile = str_replace(array_keys($aConstant), array_values($aConstant), $sCssFile);
	
		// Sauvegarde du fichier Css compilé
		file_put_contents($sPathToCss, $sCssFile);
		
		return $sCssFile;
	}
}
